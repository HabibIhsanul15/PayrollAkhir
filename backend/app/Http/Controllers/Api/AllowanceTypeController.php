<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllowanceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AllowanceTypeController extends Controller
{
    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    private function inRoles($user, array $roles): bool
    {
        $r = strtolower((string) ($user->role ?? ''));
        $roles = array_map(fn ($x) => strtolower((string) $x), $roles);

        return in_array($r, $roles, true);
    }

    public function index(Request $request)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $query = AllowanceType::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('display_order')->get());
    }

    public function show(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        return response()->json($allowanceType);
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:allowance_types,code'],
            'name' => ['required', 'string', 'max:150', 'unique:allowance_types,name'],
            'calculation_type' => ['required', 'in:per_mandays,per_trip,flat'],
            'applies_to' => ['nullable', 'in:all'],
            'display_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['applies_to'] = 'all';
        $allowanceType = AllowanceType::create($data);

        return response()->json($allowanceType, 201);
    }

    public function update(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('allowance_types', 'name')->ignore($allowanceType->id),
            ],
            'calculation_type' => ['sometimes', 'required', 'in:per_mandays,per_trip,flat'],
            'applies_to' => ['nullable', 'in:all'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $data['applies_to'] = 'all';
        $allowanceType->update($data);

        return response()->json($allowanceType);
    }

    public function destroy(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        if ($allowanceType->positionRates()->exists() || $allowanceType->payrollAllowances()->exists()) {
            return response()->json([
                'message' => 'Jenis tunjangan sudah dipakai tarif atau payroll. Nonaktifkan tanpa menghapusnya.',
            ], 422);
        }

        $allowanceType->delete();

        return response()->json(['message' => 'Allowance Type deleted successfully']);
    }
}
