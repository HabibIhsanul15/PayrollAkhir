<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\JobHistory;
use App\Models\MutationRequest;
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
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $existingPending = MutationRequest::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return response()->json(['message' => 'Karyawan ini masih memiliki pengajuan promosi/demosi yang berstatus Menunggu Persetujuan. Harap tunggu keputusan Direktur terlebih dahulu.'], 400);
        }
        $currentPosition = Position::find($employee->position_id);
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

        $effectiveDateInput = \Carbon\Carbon::parse($data['effective_from'])->startOfDay();
        $payrollPeriod = \App\Models\PayrollPeriod::where('start_date', '<=', $effectiveDateInput)
            ->where('end_date', '>=', $effectiveDateInput)
            ->first();
            
        if ($payrollPeriod) {
            $effectiveDate = \Carbon\Carbon::parse($payrollPeriod->start_date)->startOfDay();
        } else {
            $effectiveDate = $effectiveDateInput->copy()->startOfMonth();
        }

        // --- NEW VALIDATION: Block if Payroll is already processed for this period ---
        $existingPayroll = \App\Models\Payroll::where('employee_id', $employee->id)
            ->whereYear('periode', $effectiveDate->year)
            ->whereMonth('periode', $effectiveDate->month)
            ->first();

        if ($existingPayroll) {
            $statusLabels = [
                'draft' => 'Draft',
                'submitted' => 'Menunggu Direktur',
                'approved' => 'Disetujui',
                'paid' => 'Sudah Dibayar'
            ];
            $statusStr = $statusLabels[$existingPayroll->status] ?? $existingPayroll->status;
            
            return response()->json([
                'message' => "Pengajuan promosi/demosi tidak dapat dilakukan karena data penggajian karyawan untuk periode {$effectiveDate->format('M Y')} sudah diproses (Status: {$statusStr}). Silakan minta Finance untuk membatalkan/menghapus payroll periode tersebut jika ingin melanjutkan pengajuan."
            ], 400);
        }
        // ----------------------------------------------------------------------------

        $mutationRequest = MutationRequest::create([
            'employee_id' => $employee->id,
            'target_position_id' => $targetPosition->id,
            'mutation_type' => $data['mutation_type'],
            'effective_date' => $effectiveDate->toDateString(),
            'reason' => $data['reason'] ?? null,
            'document_path' => $path,
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Pengajuan promosi/demosi berhasil dikirim dan menunggu persetujuan Direktur.',
            'data' => $mutationRequest,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'director') {
            return response()->json(['message' => 'Hanya Direktur yang dapat menyetujui.'], 403);
        }

        $mutationRequest = MutationRequest::findOrFail($id);
        if ($mutationRequest->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan pending yang dapat diproses.'], 400);
        }

        DB::transaction(function () use ($mutationRequest, $user) {
            $employee = $mutationRequest->employee;
            $targetPosition = $mutationRequest->targetPosition;
            $effectiveDateInput = Carbon::parse($mutationRequest->effective_date)->startOfDay();

            $payrollPeriod = \App\Models\PayrollPeriod::where('start_date', '<=', $effectiveDateInput)
                ->where('end_date', '>=', $effectiveDateInput)
                ->first();

            if ($payrollPeriod) {
                $effectiveDate = Carbon::parse($payrollPeriod->start_date)->startOfDay();
            } else {
                $effectiveDate = $effectiveDateInput->copy()->startOfMonth();
            }

            // --- NEW VALIDATION: Block if Payroll is already processed for this period ---
            $existingPayroll = \App\Models\Payroll::where('employee_id', $employee->id)
                ->whereYear('periode', $effectiveDate->year)
                ->whereMonth('periode', $effectiveDate->month)
                ->first();

            if ($existingPayroll) {
                $statusLabels = [
                    'draft' => 'Draft',
                    'submitted' => 'Menunggu Direktur',
                    'approved' => 'Disetujui',
                    'paid' => 'Sudah Dibayar'
                ];
                $statusStr = $statusLabels[$existingPayroll->status] ?? $existingPayroll->status;
                
                abort(400, "Persetujuan gagal: Data penggajian untuk periode {$effectiveDate->format('M Y')} sudah diproses (Status: {$statusStr}). Minta Finance membatalkan payroll tersebut sebelum menyetujui.");
            }
            // ----------------------------------------------------------------------------

            $currentProfile = $employee->currentSalaryProfile($effectiveDate->copy()->subDay()->toDateString());

            $basis = $targetPosition->base_salary_basis ?? 'daily';
            $amount = (float) ($targetPosition->default_base_salary_amount ?? $targetPosition->default_mandays_rate ?? 0);
            
            $positionRate = $this->rateResolver->resolveByCode($targetPosition->id, 'position', $effectiveDate);
            $baseAllowance = (float) ($positionRate?->rate_amount ?? 0);

            $currentAlg = strtoupper((string) ($currentProfile?->salary_alg ?? 'AES'));
            $alg = $currentAlg === 'RSA' ? 'RSA' : 'AES';
            $encrypt = fn (string $value) => $alg === 'RSA'
                ? CryptoService::encryptRSA($value)
                : CryptoService::encryptAESGCM($value);
            $readCurrent = function (?string $cipher, $plain = 0) use ($alg): float {
                return (float) (CryptoService::readEncryptedOrPlainSafe($cipher, $plain, $alg) ?? 0);
            };
            
            $allowanceFixed = $currentProfile ? $readCurrent($currentProfile->allowance_fixed_enc, $currentProfile->allowance_fixed) : 0;
            $deductionFixed = $currentProfile ? $readCurrent($currentProfile->deduction_fixed_enc, $currentProfile->deduction_fixed) : 0;

            $employee->salaryProfiles()->updateOrCreate(
                ['effective_from' => $effectiveDate->toDateString()],
                [
                    'position_id' => $targetPosition->id,
                    'position' => $targetPosition->name,
                    'base_salary_basis' => $basis,
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

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['hcga', 'director'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $mutationRequest = MutationRequest::with(['employee.position', 'targetPosition', 'requester', 'approver'])->findOrFail($id);
        return response()->json($mutationRequest);
    }

    public function reject(Request $request, $id)
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

    public function cancel(Request $request, $id)
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

    public function update(Request $request, $id)
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
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $employee = $mutationRequest->employee;
        $currentPosition = Position::find($employee->position_id);
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

        $effectiveDateInput = \Carbon\Carbon::parse($data['effective_from'])->startOfDay();
        $payrollPeriod = \App\Models\PayrollPeriod::where('start_date', '<=', $effectiveDateInput)
            ->where('end_date', '>=', $effectiveDateInput)
            ->first();
            
        if ($payrollPeriod) {
            $effectiveDate = \Carbon\Carbon::parse($payrollPeriod->start_date)->startOfDay();
        } else {
            $effectiveDate = $effectiveDateInput->copy()->startOfMonth();
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
}
