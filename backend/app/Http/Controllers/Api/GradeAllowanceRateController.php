<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeAllowanceRate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $query = GradeAllowanceRate::query();
        if ($request->boolean('active_on')) {
            $query->activeOn($request->query('active_on'));
        }

        return response()->json(
            $query->with(['grade', 'allowanceType'])
                ->orderBy('grade_id')
                ->orderByDesc('effective_from')
                ->get()
        );
    }

    public function show(Request $request, GradeAllowanceRate $gradeAllowanceRate)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        return response()->json($gradeAllowanceRate->load(['grade', 'allowanceType']));
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'grade_id' => ['required', Rule::exists('grades', 'id')->where('is_active', true)],
            'allowance_type_id' => ['required', Rule::exists('allowance_types', 'id')->where('is_active', true)],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_multiplier' => ['nullable', 'numeric', 'min:0'],
            'rate_formula' => ['nullable', 'string', 'max:200'],
            'requires_condition' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rate = DB::transaction(function () use ($data) {
            $previous = GradeAllowanceRate::query()
                ->where('grade_id', $data['grade_id'])
                ->where('allowance_type_id', $data['allowance_type_id'])
                ->whereDate('effective_from', '<', $data['effective_from'])
                ->orderByDesc('effective_from')
                ->first();

            if ($previous && ($previous->effective_to === null || $previous->effective_to->gte($data['effective_from']))) {
                $previous->update([
                    'effective_to' => Carbon::parse($data['effective_from'])->subDay()->toDateString(),
                ]);
            }

            if ($this->overlaps($data)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'effective_from' => ['Periode tarif bertumpang tindih dengan tarif yang sudah ada.'],
                ]);
            }

            return GradeAllowanceRate::create($data);
        });

        return response()->json($rate->load(['grade', 'allowanceType']), 201);
    }

    public function update(Request $request, GradeAllowanceRate $gradeAllowanceRate)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'grade_id' => ['sometimes', 'required', Rule::exists('grades', 'id')->where('is_active', true)],
            'allowance_type_id' => ['sometimes', 'required', Rule::exists('allowance_types', 'id')->where('is_active', true)],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_multiplier' => ['nullable', 'numeric', 'min:0'],
            'rate_formula' => ['nullable', 'string', 'max:200'],
            'requires_condition' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $gradeId = $data['grade_id'] ?? $gradeAllowanceRate->grade_id;
        $allowanceTypeId = $data['allowance_type_id'] ?? $gradeAllowanceRate->allowance_type_id;
        $effectiveFrom = $data['effective_from'] ?? ($gradeAllowanceRate->effective_from ? $gradeAllowanceRate->effective_from->toDateString() : null);

        $candidate = array_merge($gradeAllowanceRate->toArray(), $data, [
            'grade_id' => $gradeId,
            'allowance_type_id' => $allowanceTypeId,
            'effective_from' => $effectiveFrom,
        ]);

        if ($this->overlaps($candidate, $gradeAllowanceRate->id)) {
            return response()->json(['message' => 'Periode tarif bertumpang tindih dengan tarif yang sudah ada.'], 422);
        }

        $gradeAllowanceRate->update($data);

        return response()->json($gradeAllowanceRate->load(['grade', 'allowanceType']));
    }

    public function destroy(Request $request, GradeAllowanceRate $gradeAllowanceRate)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $gradeAllowanceRate->delete();

        return response()->json(['message' => 'Grade Allowance Rate deleted successfully']);
    }

    private function overlaps(array $data, ?int $ignoreId = null): bool
    {
        $start = $data['effective_from'];
        $end = $data['effective_to'] ?? null;

        return GradeAllowanceRate::query()
            ->where('grade_id', $data['grade_id'])
            ->where('allowance_type_id', $data['allowance_type_id'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $start);
            })
            ->when($end, fn ($query) => $query->whereDate('effective_from', '<=', $end))
            ->exists();
    }
}
