<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\SpecialDeduction;
use App\Services\CryptoService;
use Illuminate\Http\Request;

class SpecialDeductionController extends Controller
{
    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    private function inRoles($user, array $roles): bool
    {
        $role = strtolower((string) ($user->role ?? ''));
        $roles = array_map(fn ($item) => strtolower((string) $item), $roles);

        return in_array($role, $roles, true);
    }

    public function index(Request $request)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $query = SpecialDeduction::with(['employee', 'creator', 'deductionType']);
        if ($request->period_month) {
            $query->where('period_month', $request->period_month);
        }
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        $deductions = $query->latest()->get()->map(function ($item) {
            $item->amount = (float) (CryptoService::readEncryptedOrPlainSafe($item->amount_enc, $item->amount, $item->salary_alg ?? 'AES') ?? 0);

            return $item;
        });

        return response()->json($deductions);
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'deduction_type_id' => 'nullable|integer|exists:deduction_types,id',
            'type' => 'nullable|string|max:50',
            'period_month' => 'required|date_format:Y-m',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        if (empty($data['deduction_type_id']) && empty($data['type'])) {
            return response()->json(['message' => 'Jenis potongan wajib dipilih.'], 422);
        }

        $employee = Employee::find($request->employee_id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $deductionType = ! empty($data['deduction_type_id'])
            ? DeductionType::whereKey($data['deduction_type_id'])->where('is_active', true)->first()
            : null;

        if (! empty($data['deduction_type_id']) && ! $deductionType) {
            return response()->json(['message' => 'Jenis potongan tidak aktif.'], 422);
        }

        $duplicateQuery = SpecialDeduction::query()
            ->where('employee_id', $data['employee_id'])
            ->where('period_month', $data['period_month']);

        if ($deductionType) {
            $duplicateQuery->where(function ($query) use ($deductionType) {
                $query->where('deduction_type_id', $deductionType->id)
                    ->orWhere(function ($legacyQuery) use ($deductionType) {
                        $legacyQuery->whereNull('deduction_type_id')
                            ->where('type', $deductionType->code);
                    });
            });
        } else {
            $duplicateQuery->whereNull('deduction_type_id')->where('type', $data['type']);
        }

        if ($duplicateQuery->exists()) {
            return response()->json([
                'message' => 'Jenis potongan tersebut sudah ditambahkan untuk karyawan dan periode ini.',
            ], 409);
        }

        $deduction = SpecialDeduction::create([
            'employee_id' => $data['employee_id'],
            'deduction_type_id' => $deductionType?->id,
            'type' => $deductionType?->code ?? $data['type'],
            'period_month' => $data['period_month'],
            'amount' => null,
            'amount_enc' => CryptoService::encryptAESGCM((string) round($data['amount'])),
            'salary_alg' => 'AES',
            'salary_key_id' => CryptoService::keyId(),
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($deduction, 201);
    }

    public function destroy(SpecialDeduction $special_deduction)
    {
        if (! $this->inRoles(request()->user(), ['fat'])) {
            return $this->forbid();
        }

        $special_deduction->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
