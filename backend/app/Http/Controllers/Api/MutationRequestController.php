<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\JobHistory;
use App\Models\MutationRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\MonthlyRecap;
use App\Services\AllowanceRateResolver;
use App\Services\CryptoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MutationRequestController extends Controller
{
    public function __construct(private AllowanceRateResolver $rateResolver) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['hcga', 'director'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = MutationRequest::with(['employee', 'targetPosition', 'requester', 'approver'])
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->latest();

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'hcga') {
            return response()->json(['message' => 'Hanya HCGA yang dapat mengajukan promosi/demosi.'], 403);
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'mutation_type' => ['required', 'in:promotion,demotion'],
            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],
            'period_month' => ['required', 'date_format:Y-m'],
            'reason' => ['nullable', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $currentProfile = $employee->currentSalaryProfile();
        $currentPosition = Position::find($currentProfile?->position_id ?? $employee->position_id);
        $targetPosition = Position::findOrFail($data['position_id']);

        if (!$currentPosition || !$currentPosition->level) {
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

        $path = null;
        if ($request->hasFile('document')) {
            $path = $request->file('document')->store('mutations', 'public');
        }

        $period = PayrollPeriod::forMonth($data['period_month']);
        $effectiveDate = Carbon::parse($period->start_date)->startOfDay();

        $mutationRequest = DB::transaction(function () use ($employee, $targetPosition, $data, $path, $effectiveDate, $user) {
            $lockedEmployee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $activeMutation = $this->activeMutationQuery($lockedEmployee->id)->first();
            if ($activeMutation) {
                abort(409, $this->activeMutationMessage($activeMutation));
            }

            $payrollLockMessage = $this->payrollLockMessage($lockedEmployee, $effectiveDate);
            if ($payrollLockMessage) {
                abort(409, $payrollLockMessage);
            }

            return MutationRequest::create([
                'employee_id' => $lockedEmployee->id,
                'target_position_id' => $targetPosition->id,
                'mutation_type' => $data['mutation_type'],
                'effective_date' => $effectiveDate->toDateString(),
                'reason' => $data['reason'] ?? null,
                'document_path' => $path,
                'status' => 'pending',
                'requested_by' => $user->id,
            ]);
        });

        return response()->json([
            'message' => 'Pengajuan promosi/demosi berhasil dikirim dan menunggu persetujuan Direktur.',
            'data' => $mutationRequest,
        ]);
    }

    public function approve(Request $request, int|string $id)
    {
        $user = $request->user();
        if ($user->role !== 'director') {
            return response()->json(['message' => 'Hanya Direktur yang dapat menyetujui.'], 403);
        }

        DB::transaction(function () use ($id, $user) {
            $mutationRequest = MutationRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($mutationRequest->status !== 'pending') {
                abort(409, 'Pengajuan sudah diproses.');
            }

            $employee = $mutationRequest->employee;
            $targetPosition = $mutationRequest->targetPosition;
            $employee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $activeMutation = $this->activeMutationQuery($employee->id, $mutationRequest->id)->first();
            if ($activeMutation) {
                abort(409, $this->activeMutationMessage($activeMutation));
            }

            $effectiveDate = Carbon::parse($mutationRequest->effective_date)->startOfDay();
            $payrollLockMessage = $this->payrollLockMessage($employee, $effectiveDate);
            if ($payrollLockMessage) {
                abort(409, $payrollLockMessage);
            }

            $currentProfile = $employee->currentSalaryProfile($effectiveDate->copy()->subDay()->toDateString());

            $amount = (float) ($targetPosition->default_base_salary_amount ?? $targetPosition->default_mandays_rate ?? 0);

            if ($amount <= 0) {
                abort(422, 'Gaji pokok jabatan tujuan belum diatur. Lengkapi master gaji jabatan sebelum menyetujui pengajuan.');
            }
            
            $positionRate = $this->rateResolver->resolveByCode($targetPosition->id, 'position');
            $baseAllowance = (float) ($positionRate?->rate_amount ?? 0);

            $currentAlg = strtoupper((string) ($currentProfile?->salary_alg ?? 'AES'));
            $alg = $currentAlg === 'RSA' ? 'RSA' : 'AES';
            $encrypt = fn (string $value) => $alg === 'RSA'
                ? CryptoService::encryptRSA($value)
                : CryptoService::encryptAESGCM($value);
            $readCurrent = function (?string $cipher, mixed $plain = 0) use ($alg): float {
                return (float) (CryptoService::readEncryptedOrPlainSafe($cipher, $plain, $alg) ?? 0);
            };
            
            $allowanceFixed = $currentProfile ? $readCurrent($currentProfile->allowance_fixed_enc, $currentProfile->allowance_fixed) : 0;
            $deductionFixed = $currentProfile ? $readCurrent($currentProfile->deduction_fixed_enc, $currentProfile->deduction_fixed) : 0;

            $employee->salaryProfiles()->updateOrCreate(
                ['effective_from' => $effectiveDate->toDateString()],
                [
                    'position_id' => $targetPosition->id,
                    'position' => $targetPosition->name,
                    'base_salary_amount' => 0,
                    'position_allowance' => 0,
                    'mandays_rate' => null,
                    'allowance_fixed' => 0,
                    'deduction_fixed' => 0,
                    'base_salary_amount_enc' => $encrypt((string) $amount),
                    'position_allowance_enc' => $encrypt((string) $baseAllowance),
                    'mandays_rate_enc' => $encrypt((string) $amount),
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
                    'notes' => ucfirst($mutationRequest->mutation_type).': '.($mutationRequest->reason ?? '-').' (Approved by Dir)',
                ]
            );

            if (! $effectiveDate->isFuture()) {
                $employee->update(['position_id' => $targetPosition->id, 'position' => $targetPosition->name]);
            }

            $mutationRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
            ]);
        });

        return response()->json(['message' => 'Perubahan jabatan berhasil disetujui dan diterapkan.']);
    }

    public function show(Request $request, int|string $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['hcga', 'director'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $mutationRequest = MutationRequest::with(['employee.position', 'targetPosition', 'requester', 'approver'])->findOrFail($id);
        $period = PayrollPeriod::forDate($mutationRequest->effective_date);

        return response()->json(array_merge($mutationRequest->toArray(), [
            'payroll_period' => $period?->only(['period_month', 'start_date', 'end_date', 'status']),
        ]));
    }

    public function reject(Request $request, int|string $id)
    {
        $user = $request->user();
        if ($user->role !== 'director') {
            return response()->json(['message' => 'Hanya Direktur yang dapat menolak.'], 403);
        }

        $mutationRequest = MutationRequest::findOrFail($id);
        if ($mutationRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan pending yang dapat diproses.'], 400);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        $mutationRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => $user->id,
        ]);

        return response()->json(['message' => 'Pengajuan berhasil ditolak.']);
    }

    public function cancel(Request $request, int|string $id)
    {
        $user = $request->user();
        if ($user->role !== 'hcga') {
            return response()->json(['message' => 'Hanya HCGA yang dapat membatalkan.'], 403);
        }

        $mutationRequest = MutationRequest::findOrFail($id);
        if ($mutationRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan pending yang dapat dibatalkan.'], 400);
        }

        $mutationRequest->update([
            'status' => 'cancelled',
        ]);

        return response()->json(['message' => 'Pengajuan berhasil dibatalkan.']);
    }

    public function update(Request $request, int|string $id)
    {
        $user = $request->user();
        if ($user->role !== 'hcga') {
            return response()->json(['message' => 'Hanya HCGA yang dapat mengubah pengajuan.'], 403);
        }

        $mutationRequest = MutationRequest::findOrFail($id);
        if ($mutationRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan pending yang dapat diubah.'], 400);
        }

        $data = $request->validate([
            'mutation_type' => ['required', 'in:promotion,demotion'],
            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],
            'period_month' => ['required', 'date_format:Y-m'],
            'reason' => ['nullable', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $employee = $mutationRequest->employee;
        $currentProfile = $employee->currentSalaryProfile();
        $currentPosition = Position::find($currentProfile?->position_id ?? $employee->position_id);
        $targetPosition = Position::findOrFail($data['position_id']);

        if (!$currentPosition || !$currentPosition->level) {
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

        $path = $mutationRequest->document_path;
        if ($request->hasFile('document')) {
            $path = $request->file('document')->store('mutations', 'public');
        }

        $period = PayrollPeriod::forMonth($data['period_month']);
        $effectiveDate = Carbon::parse($period->start_date)->startOfDay();

        $activeMutation = $this->activeMutationQuery($employee->id, $mutationRequest->id)->first();
        if ($activeMutation) {
            return response()->json(['message' => $this->activeMutationMessage($activeMutation)], 409);
        }

        $payrollLockMessage = $this->payrollLockMessage($employee, $effectiveDate);
        if ($payrollLockMessage) {
            return response()->json(['message' => $payrollLockMessage], 409);
        }

        $mutationRequest->update([
            'target_position_id' => $targetPosition->id,
            'mutation_type' => $data['mutation_type'],
            'effective_date' => $effectiveDate->toDateString(),
            'reason' => $data['reason'] ?? null,
            'document_path' => $path,
        ]);

        return response()->json([
            'message' => 'Pengajuan promosi/demosi berhasil diubah.',
            'data' => $mutationRequest,
        ]);
    }

    private function activeMutationQuery(int $employeeId, ?int $excludeId = null)
    {
        return MutationRequest::query()
            ->where('employee_id', $employeeId)
            ->when($excludeId, fn (mixed $query) => $query->where('id', '<>', $excludeId))
            ->where(function (mixed $query) {
                $query->where('status', 'pending')
                    ->orWhere(function (mixed $approved) {
                        $approved->where('status', 'approved')
                            ->whereDate('effective_date', '>', Carbon::today()->toDateString());
                    });
            })
            ->orderByDesc('created_at');
    }

    private function activeMutationMessage(MutationRequest $mutation): string
    {
        if ($mutation->status === 'pending') {
            return 'Karyawan masih memiliki pengajuan promosi/demosi aktif.';
        }

        return 'Pengajuan baru dapat dibuat setelah tanggal efektif '
            .Carbon::parse($mutation->effective_date)->translatedFormat('d F Y')
            .'.';
    }

    private function payrollLockMessage(Employee $employee, Carbon $effectiveDate): ?string
    {
        $period = PayrollPeriod::forDate($effectiveDate);

        if ($period && MonthlyRecap::query()
            ->where('employee_id', $employee->id)
            ->where('period_month', $period->period_month)
            ->where('is_finalized', true)
            ->exists()) {
            return "Perubahan tidak dapat dijadwalkan karena rekap kehadiran periode {$period->period_month} sudah difinalisasi.";
        }

        $payroll = Payroll::query()
            ->where('employee_id', $employee->id)
            ->when($period, fn (mixed $query) => $query->whereDate('periode', $period->start_date))
            ->when(! $period, fn (mixed $query) => $query
                ->whereYear('periode', $effectiveDate->year)
                ->whereMonth('periode', $effectiveDate->month))
            ->whereIn('status', ['requested', 'submitted', 'approved', 'paid'])
            ->first();

        if (! $payroll) {
            return null;
        }

        $labels = [
            'requested' => 'Menunggu diproses',
            'submitted' => 'Menunggu persetujuan Direktur',
            'approved' => 'Disetujui',
            'paid' => 'Sudah dibayar',
        ];

        $status = $labels[$payroll->status] ?? $payroll->status;

        $periodMonth = $period?->period_month ?? $effectiveDate->format('Y-m');
        return "Payroll periode {$periodMonth} sudah diproses ({$status}).";
    }
}
