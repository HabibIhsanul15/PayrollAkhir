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
        'business_trips',
        'training_days',
        'out_of_town_days',
        'wfo_days',
        'wfh_days',
        'overtime_hours',
    ];

    private const DAILY_SOURCES = [
        'total_mandays',
        'training_days',
        'out_of_town_days',
        'wfo_days',
        'wfh_days',
        'overtime_hours',
    ];

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
            'name' => ['required', 'string', 'max:150'],
            'calculation_type' => ['required', 'in:per_mandays,per_trip,flat,formula'],
            'input_source' => ['nullable', Rule::in(self::INPUT_SOURCES)],
            'applies_to' => ['nullable', 'in:all'],
            'display_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'condition_field' => ['nullable', Rule::in(['num_toddlers', 'is_trainer', 'is_on_probation'])],
            'condition_operator' => ['nullable', 'required_with:condition_field', Rule::in(['=', '!=', '>', '>=', '<', '<='])],
            'condition_value' => ['nullable', 'required_with:condition_field', 'numeric'],
        ]);

        $data = $this->normalizeCalculationPayload($data);
        $allowanceType = AllowanceType::create($data);

        return response()->json($allowanceType, 201);
    }

    public function update(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'calculation_type' => ['sometimes', 'required', 'in:per_mandays,per_trip,flat,formula'],
            'input_source' => ['nullable', Rule::in(self::INPUT_SOURCES)],
            'applies_to' => ['nullable', 'in:all'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'condition_field' => ['nullable', Rule::in(['num_toddlers', 'is_trainer', 'is_on_probation'])],
            'condition_operator' => ['nullable', 'required_with:condition_field', Rule::in(['=', '!=', '>', '>=', '<', '<='])],
            'condition_value' => ['nullable', 'required_with:condition_field', 'numeric'],
        ]);

        $data = $this->normalizeCalculationPayload($data, $allowanceType);
        $allowanceType->update($data);

        return response()->json($allowanceType);
    }

    public function destroy(Request $request, AllowanceType $allowanceType)
    {
        if (! $this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        if ($allowanceType->gradeRates()->exists() || $allowanceType->payrollAllowances()->exists()) {
            return response()->json([
                'message' => 'Jenis tunjangan sudah dipakai tarif atau payroll. Nonaktifkan tanpa menghapusnya.',
            ], 422);
        }

        $allowanceType->delete();

        return response()->json(['message' => 'Allowance Type deleted successfully']);
    }

    private function normalizeCalculationPayload(array $data, ?AllowanceType $existing = null): array
    {
        $calculationType = $data['calculation_type'] ?? $existing?->calculation_type;
        $inputSource = array_key_exists('input_source', $data)
            ? $data['input_source']
            : $existing?->input_source;

        if ($calculationType === 'flat') {
            $data['input_source'] = null;
        }

        if ($calculationType === 'per_trip') {
            if ($inputSource && $inputSource !== 'business_trips') {
                throw ValidationException::withMessages([
                    'input_source' => ['Per trip hanya boleh memakai indikator jumlah perjalanan dinas.'],
                ]);
            }

            $data['input_source'] = 'business_trips';
        }

        if ($calculationType === 'per_mandays') {
            if (! $inputSource || ! in_array($inputSource, self::DAILY_SOURCES, true)) {
                throw ValidationException::withMessages([
                    'input_source' => ['Tunjangan berdasarkan rekap wajib memilih indikator kehadiran/aktivitas.'],
                ]);
            }

            $data['input_source'] = $inputSource;
        }

        if ($calculationType === 'formula') {
            $data['input_source'] = $inputSource ?: null;
        }

        $data['applies_to'] = 'all';

        return $data;
    }
}
