<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Grade;
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

    private function resolveBaseSalaryPayload(Grade $grade, array $data): array
    {
        $basis = $data['base_salary_basis']
            ?? $grade->base_salary_basis
            ?? 'daily';

        $amount = array_key_exists('base_salary_amount', $data) && $data['base_salary_amount'] !== null
            ? (float) $data['base_salary_amount']
            : (array_key_exists('mandays_rate', $data) && $data['mandays_rate'] !== null
                ? (float) $data['mandays_rate']
                : (float) ($grade->default_base_salary_amount ?? $grade->default_mandays_rate ?? 0));

        return [
            'basis' => $basis,
            'amount' => $amount,
        ];
    }

    /**
     * POST /api/employees/{id}/mutate
     */
    public function store(Request $request, $id)
    {
        $user = $request->user();

        // Hanya HCGA dan Director yang boleh memutasi
        if (! in_array($user->role, ['hcga', 'director'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'mutation_type' => ['required', 'in:promotion,demotion'],
            'grade_id' => ['required', Rule::exists('grades', 'id')->where('is_active', true)],
            'position_allowance' => ['nullable', 'numeric', 'min:0'],
            'base_salary_basis' => ['nullable', Rule::in(['daily', 'monthly'])],
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],
            'mandays_rate' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $effectiveDate = Carbon::parse($data['effective_from'])->startOfDay();
        $currentProfile = $employee->currentSalaryProfile($effectiveDate->copy()->subDay()->toDateString());
        $currentGrade = Grade::find($currentProfile?->grade_id ?? $employee->grade_id);
        $targetGrade = Grade::findOrFail($data['grade_id']);

        if (! $currentGrade || ! $currentGrade->level) {
            throw ValidationException::withMessages([
                'grade_id' => 'Jabatan saat ini belum memiliki level yang valid.',
            ]);
        }

        if ((int) $targetGrade->id === (int) $currentGrade->id) {
            throw ValidationException::withMessages([
                'grade_id' => 'Jabatan tujuan harus berbeda dari jabatan saat ini.',
            ]);
        }

        if ($data['mutation_type'] === 'promotion' && (int) $targetGrade->level >= (int) $currentGrade->level) {
            throw ValidationException::withMessages([
                'grade_id' => 'Promosi hanya bisa ke jabatan dengan level lebih tinggi dari jabatan saat ini.',
            ]);
        }

        if ($data['mutation_type'] === 'demotion' && (int) $targetGrade->level <= (int) $currentGrade->level) {
            throw ValidationException::withMessages([
                'grade_id' => 'Demosi hanya bisa ke jabatan dengan level lebih rendah dari jabatan saat ini.',
            ]);
        }

        DB::transaction(function () use ($data, $employee, $effectiveDate, $currentProfile, $targetGrade) {
            $salaryConfig = $this->resolveBaseSalaryPayload($targetGrade, $data);
            $positionRate = $this->rateResolver->resolveByCode($targetGrade->id, 'position', $effectiveDate);
            $base = array_key_exists('position_allowance', $data) && $data['position_allowance'] !== null
                ? (float) $data['position_allowance']
                : (float) ($positionRate?->rate_amount ?? 0);
            $baseSalaryAmount = $salaryConfig['amount'];

            $currentAlg = strtoupper((string) ($currentProfile?->salary_alg ?? 'AES'));
            $alg = $currentAlg === 'RSA' ? 'RSA' : 'AES';
            $encrypt = fn (string $value) => $alg === 'RSA'
                ? CryptoService::encryptRSA($value)
                : CryptoService::encryptAESGCM($value);
            $readCurrent = function (?string $cipher, $plain = 0) use ($alg): float {
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
                    'grade_id' => $targetGrade->id,
                    'position' => $targetGrade->name,
                    'base_salary_basis' => $salaryConfig['basis'],
                    'base_salary_amount' => 0,
                    'position_allowance' => 0,
                    'mandays_rate' => null,
                    'allowance_fixed' => 0,
                    'deduction_fixed' => 0,
                    'base_salary_amount_enc' => $encrypt((string) $baseSalaryAmount),
                    'position_allowance_enc' => $encrypt((string) $base),
                    'mandays_rate_enc' => $encrypt((string) $baseSalaryAmount),
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
                    'grade_id' => $targetGrade->id,
                    'position' => $targetGrade->name,
                    'end_date' => null,
                    'status' => $effectiveDate->isFuture() ? 'inactive' : 'active',
                    'notes' => ucfirst($data['mutation_type']).': '.($data['notes'] ?? '-'),
                ]
            );

            if (! $effectiveDate->isFuture()) {
                $employee->update(['grade_id' => $targetGrade->id, 'position' => $targetGrade->name]);
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
