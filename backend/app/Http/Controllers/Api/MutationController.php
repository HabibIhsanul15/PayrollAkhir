<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\JobHistory;
use App\Services\AllowanceRateResolver;
use App\Services\CryptoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MutationController extends Controller
{
    public function __construct(private AllowanceRateResolver $rateResolver) {}

    private function resolveBaseSalaryPayload(Position $Position, array $data): array
    {
        $amount = array_key_exists('base_salary_amount', $data) && $data['base_salary_amount'] !== null
            ? (float) $data['base_salary_amount']
            : (array_key_exists('mandays_rate', $data) && $data['mandays_rate'] !== null
                ? (float) $data['mandays_rate']
                : (float) ($Position->default_base_salary_amount ?? $Position->default_mandays_rate ?? 0));

        return [
            'amount' => $amount,
        ];
    }

    /**
     * POST /api/employees/{id}/mutate
     */
    public function store(Request $request, int|string $id)
    {
        $user = $request->user();

        // Hanya HCGA dan Director yang boleh memutasi
        if (! in_array($user->role, ['hcga', 'director'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'mutation_type' => ['required', 'in:promotion,demotion'],
            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],
            'position_allowance' => ['nullable', 'numeric', 'min:0'],
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],
            'mandays_rate' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $effectiveDateInput = Carbon::parse($data['effective_from'])->startOfDay();
        
        $effectiveDate = \App\Models\PayrollPeriod::forDate($effectiveDateInput)->start_date->startOfDay();

        $currentProfile = $employee->currentSalaryProfile($effectiveDate->copy()->subDay()->toDateString());
        $currentPosition = Position::find($currentProfile?->position_id ?? $employee->position_id);
        $targetPosition = Position::findOrFail($data['position_id']);

        if (! $currentPosition || ! $currentPosition->level) {
            throw ValidationException::withMessages([
                'position_id' => 'Jabatan saat ini belum memiliki level yang valid.',
            ]);
        }

        if ((int) $targetPosition->id === (int) $currentPosition->id) {
            throw ValidationException::withMessages([
                'position_id' => 'Jabatan tujuan harus berbeda dari jabatan saat ini.',
            ]);
        }

        if ($data['mutation_type'] === 'promotion' && (int) $targetPosition->level >= (int) $currentPosition->level) {
            throw ValidationException::withMessages([
                'position_id' => 'Promosi hanya bisa ke jabatan dengan level lebih tinggi dari jabatan saat ini.',
            ]);
        }

        if ($data['mutation_type'] === 'demotion' && (int) $targetPosition->level <= (int) $currentPosition->level) {
            throw ValidationException::withMessages([
                'position_id' => 'Demosi hanya bisa ke jabatan dengan level lebih rendah dari jabatan saat ini.',
            ]);
        }

        DB::transaction(function () use ($data, $employee, $effectiveDate, $currentProfile, $targetPosition) {
            $salaryConfig = $this->resolveBaseSalaryPayload($targetPosition, $data);
            if ($salaryConfig['amount'] <= 0) {
                throw ValidationException::withMessages([
                    'position_id' => 'Gaji pokok jabatan tujuan belum diatur pada master jabatan.',
                ]);
            }
            $positionRate = $this->rateResolver->resolveByCode($targetPosition->id, 'position');
            $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null
                ? (float) $data['position_allowance']
                : (float) ($positionRate?->rate_amount ?? 0);
            $baseSalaryAmount = $salaryConfig['amount'];

            $currentAlg = strtoupper((string) ($currentProfile?->salary_alg ?? 'AES'));
            $alg = $currentAlg === 'RSA' ? 'RSA' : 'AES';
            $encrypt = fn (string $value) => $alg === 'RSA'
                ? CryptoService::encryptRSA($value)
                : CryptoService::encryptAESGCM($value);
            $readCurrent = function (?string $cipher, mixed $plain = 0) use ($alg): float {
                return (float) (CryptoService::readEncryptedOrPlainSafe($cipher, $plain, $alg) ?? 0);
            };
            $allowanceFixed = $currentProfile
                ? $readCurrent($currentProfile->allowance_fixed_enc, $currentProfile->allowance_fixed)
                : 0;
            $deductionFixed = $currentProfile
                ? $readCurrent($currentProfile->deduction_fixed_enc, $currentProfile->deduction_fixed)
                : 0;

            $employee->salaryProfiles()->updateOrCreate(
                ['effective_from' => $effectiveDate->toDateString()],
                [
                    'position_id' => $targetPosition->id,
                    'position' => $targetPosition->name,
                    'base_salary_amount' => 0,
                    'position_allowance' => 0,
                    'allowance_fixed' => 0,
                    'deduction_fixed' => 0,
                    'base_salary_amount_enc' => $encrypt((string) $baseSalaryAmount),
                    'position_allowance_enc' => $encrypt((string) $base),
                    'allowance_fixed_enc' => $encrypt((string) $allowanceFixed),
                    'deduction_fixed_enc' => $encrypt((string) $deductionFixed),
                    'salary_alg' => $alg,
                    'salary_key_id' => $alg === 'RSA' ? CryptoService::rsaKeyId() : CryptoService::keyId(),
                ]
            );

            $previous = JobHistory::query()
                ->where('employee_id', $employee->id)
                ->whereDate('start_date', '<', $effectiveDate->toDateString())
                ->orderByDesc('start_date')
                ->first();
            if ($previous) {
                $previous->update([
                    'end_date' => $effectiveDate->copy()->subDay()->toDateString(),
                    'status' => $effectiveDate->isFuture() ? 'active' : 'inactive',
                ]);
            }

            JobHistory::updateOrCreate(
                ['employee_id' => $employee->id, 'start_date' => $effectiveDate->toDateString()],
                [
                    'position_id' => $targetPosition->id,
                    'position' => $targetPosition->name,
                    'end_date' => null,
                    'status' => $effectiveDate->isFuture() ? 'inactive' : 'active',
                    'notes' => ucfirst($data['mutation_type']).': '.($data['notes'] ?? '-'),
                ]
            );

            if (! $effectiveDate->isFuture()) {
                $employee->update(['position_id' => $targetPosition->id, 'position' => $targetPosition->name]);
            }
        });

        return response()->json([
            'message' => $effectiveDate->isFuture()
                ? 'Perubahan jabatan berhasil dijadwalkan.'
                : 'Perubahan jabatan berhasil diterapkan.',
            'status' => $effectiveDate->isFuture() ? 'scheduled' : 'applied',
        ]);
    }
}
