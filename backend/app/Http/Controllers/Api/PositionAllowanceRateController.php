<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PositionAllowanceRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionAllowanceRateController extends Controller
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
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $query = PositionAllowanceRate::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json(
            $query->with(['position', 'allowanceType'])
                ->orderBy('position_id')
                ->get()
        );
    }

    public function show(Request $request, PositionAllowanceRate $PositionAllowanceRate)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        return response()->json($PositionAllowanceRate->load(['position', 'allowanceType']));
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'position_id' => ['required', Rule::exists('positions', 'id')->where('is_active', true)],
            'allowance_type_id' => ['required', Rule::exists('allowance_types', 'id')->where('is_active', true)],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rate = PositionAllowanceRate::updateOrCreate(
            [
                'position_id' => $data['position_id'],
                'allowance_type_id' => $data['allowance_type_id'],
            ],
            [
                'rate_amount' => $data['rate_amount'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        return response()->json($rate->load(['position', 'allowanceType']), 201);
    }

    public function update(Request $request, PositionAllowanceRate $PositionAllowanceRate)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $PositionAllowanceRate->update([
            'rate_amount' => $data['rate_amount'] ?? null,
            'is_active' => $data['is_active'] ?? $PositionAllowanceRate->is_active,
        ]);

        return response()->json($PositionAllowanceRate->load(['position', 'allowanceType']));
    }

    public function destroy(Request $request, PositionAllowanceRate $PositionAllowanceRate)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $PositionAllowanceRate->delete();

        return response()->json(['message' => 'Rate deleted successfully']);
    }
}

