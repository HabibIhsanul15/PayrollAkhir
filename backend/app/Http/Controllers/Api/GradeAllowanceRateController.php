<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeAllowanceRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeAllowanceRateController extends Controller
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

        return response()->json(
            GradeAllowanceRate::with(['grade', 'allowanceType'])
                ->orderBy('grade_id')
                ->get()
        );
    }

    public function show(Request $request, GradeAllowanceRate $gradeAllowanceRate)
    {
        if (!$this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        return response()->json($gradeAllowanceRate->load(['grade', 'allowanceType']));
    }

    public function store(Request $request)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'grade_id' => ['required', 'exists:grades,id'],
            'allowance_type_id' => ['required', 'exists:allowance_types,id'],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_multiplier' => ['nullable', 'numeric', 'min:0'],
            'rate_formula' => ['nullable', 'string', 'max:200'],
            'requires_condition' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Unique constraint check
        $exists = GradeAllowanceRate::where('grade_id', $data['grade_id'])
            ->where('allowance_type_id', $data['allowance_type_id'])
            ->where('effective_from', $data['effective_from'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A rate with the same grade, allowance type, and effective from date already exists.'
            ], 422);
        }

        $rate = GradeAllowanceRate::create($data);

        return response()->json($rate->load(['grade', 'allowanceType']), 201);
    }

    public function update(Request $request, GradeAllowanceRate $gradeAllowanceRate)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'grade_id' => ['sometimes', 'required', 'exists:grades,id'],
            'allowance_type_id' => ['sometimes', 'required', 'exists:allowance_types,id'],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_multiplier' => ['nullable', 'numeric', 'min:0'],
            'rate_formula' => ['nullable', 'string', 'max:200'],
            'requires_condition' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        // Unique constraint check if changing unique keys
        $gradeId = $data['grade_id'] ?? $gradeAllowanceRate->grade_id;
        $allowanceTypeId = $data['allowance_type_id'] ?? $gradeAllowanceRate->allowance_type_id;
        $effectiveFrom = $data['effective_from'] ?? ($gradeAllowanceRate->effective_from ? $gradeAllowanceRate->effective_from->toDateString() : null);

        $exists = GradeAllowanceRate::where('grade_id', $gradeId)
            ->where('allowance_type_id', $allowanceTypeId)
            ->where('effective_from', $effectiveFrom)
            ->where('id', '!=', $gradeAllowanceRate->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A rate with the same grade, allowance type, and effective from date already exists.'
            ], 422);
        }

        $gradeAllowanceRate->update($data);

        return response()->json($gradeAllowanceRate->load(['grade', 'allowanceType']));
    }

    public function destroy(Request $request, GradeAllowanceRate $gradeAllowanceRate)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $gradeAllowanceRate->delete();

        return response()->json(['message' => 'Grade Allowance Rate deleted successfully']);
    }
}
