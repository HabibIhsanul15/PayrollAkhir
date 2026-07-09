<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllowanceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        if (!$this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        return response()->json(AllowanceType::orderBy('display_order')->get());
    }

    public function show(Request $request, AllowanceType $allowanceType)
    {
        if (!$this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        return response()->json($allowanceType);
    }

    public function store(Request $request)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:allowance_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'calculation_type' => ['required', 'in:per_mandays,per_trip,flat,formula'],
            'applies_to' => ['required', 'in:all,project_only,fix_rate_only'],
            'display_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $allowanceType = AllowanceType::create($data);

        return response()->json($allowanceType, 201);
    }

    public function update(Request $request, AllowanceType $allowanceType)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('allowance_types', 'code')->ignore($allowanceType->id)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'calculation_type' => ['sometimes', 'required', 'in:per_mandays,per_trip,flat,formula'],
            'applies_to' => ['sometimes', 'required', 'in:all,project_only,fix_rate_only'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $allowanceType->update($data);

        return response()->json($allowanceType);
    }

    public function destroy(Request $request, AllowanceType $allowanceType)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        if ($allowanceType->gradeRates()->exists()) {
            return response()->json(['message' => 'Allowance Type cannot be deleted because it is mapped to grade rates.'], 422);
        }

        $allowanceType->delete();

        return response()->json(['message' => 'Allowance Type deleted successfully']);
    }
}
