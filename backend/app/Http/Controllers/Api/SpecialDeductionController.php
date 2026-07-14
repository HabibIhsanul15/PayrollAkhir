<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $query = SpecialDeduction::with(['employee', 'creator']);
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

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string|max:50',
            'period_month' => 'required|date_format:Y-m',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $employee = Employee::find($request->employee_id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $deduction = SpecialDeduction::create([
            'employee_id' => $request->employee_id,
            'type' => $request->type,
            'period_month' => $request->period_month,
            'amount' => null,
            'amount_enc' => CryptoService::encryptAESGCM((string) round($request->amount)),
            'salary_alg' => 'AES',
            'salary_key_id' => CryptoService::keyId(),
            'description' => $request->description,
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
