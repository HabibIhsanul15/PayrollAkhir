<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllowanceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AllowanceTypeController extends Controller
{
    private const INPUT_SOURCES = [
        'total_mandays',
        'training_days',
        'out_of_town_days',
        'wfo_days',
        'wfh_days',
        'business_trips',
    ];

    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    private function inRoles(mixed $user, array $roles): bool
    {
        $r = strtolower((string) ($user->role ?? ''));
        $roles = array_map(fn (mixed $x) => strtolower((string) $x), $roles);

        return in_array($r, $roles, true);
    }

    public function index(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
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
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        return response()->json($allowanceType);
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:allowance_types,code'],
            'name' => ['required', 'string', 'max:150', 'unique:allowance_types,name'],
            'calculation_type' => ['required', 'in:per_mandays,per_trip,flat,per_toddler'],
            'input_source' => ['nullable', Rule::in(self::INPUT_SOURCES)],
            'applies_to' => ['nullable', 'in:all'],
            'display_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['applies_to'] = 'all';
        $data['input_source'] = $this->normalizeInputSource($data['calculation_type'], $data['input_source'] ?? null);
        $allowanceType = AllowanceType::create($data);

        return response()->json($allowanceType, 201);
    }

    public function update(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
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
            'calculation_type' => ['sometimes', 'required', 'in:per_mandays,per_trip,flat,per_toddler'],
            'input_source' => ['nullable', Rule::in(self::INPUT_SOURCES)],
            'applies_to' => ['nullable', 'in:all'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $data['applies_to'] = 'all';
        if (array_key_exists('calculation_type', $data) || array_key_exists('input_source', $data)) {
            $data['input_source'] = $this->normalizeInputSource(
                $data['calculation_type'] ?? $allowanceType->calculation_type,
                $data['input_source'] ?? $allowanceType->input_source
            );
        }
        $allowanceType->update($data);

        return response()->json($allowanceType);
    }

    public function destroy(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
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

    private function normalizeInputSource(string $calculationType, ?string $inputSource): ?string
    {
        return match ($calculationType) {
            'flat' => null,
            'per_trip' => $inputSource ?: 'business_trips',
            default => $inputSource ?: 'total_mandays',
        };
    }
}
