<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    private function normalizeBaseSalaryPayload(array $data): array
    {
        $data['base_salary_basis'] = 'daily';

        if (array_key_exists('default_base_salary_amount', $data)) {
            $data['default_mandays_rate'] = $data['default_base_salary_amount'];
        }

        return $data;
    }

    private function makeCodeFromName(string $name): string
    {
        $words = preg_split('/\s+/', strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $name)))) ?: [];
        $words = array_values(array_filter($words));

        if (count($words) === 0) {
            return 'jabatan';
        }

        if (count($words) > 1) {
            return substr(implode('', array_map(fn ($word) => $word[0], $words)), 0, 20);
        }

        return substr($words[0], 0, 30);
    }

    private function sanitizeCode(?string $code): string
    {
        $clean = strtolower(trim((string) $code));
        $clean = preg_replace('/[^a-z0-9_-]/', '', $clean) ?: '';

        return $clean !== '' ? substr($clean, 0, 50) : 'jabatan';
    }

    private function uniqueCode(string $base, ?int $ignoreId = null): string
    {
        $base = $this->sanitizeCode($base);
        $exists = fn (string $code) => Grade::where('code', $code)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if (! $exists($base)) {
            return $base;
        }

        $counter = 2;
        do {
            $suffix = '-'.$counter;
            $candidate = substr($base, 0, 50 - strlen($suffix)).$suffix;
            $counter++;
        } while ($exists($candidate));

        return $candidate;
    }

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

    private function gradePayloadFor($user, Grade $grade): array
    {
        $data = $grade->toArray();

        if ($this->inRoles($user, ['hcga'])) {
            unset(
                $data['allowance_rates'],
                $data['default_base_salary_amount'],
                $data['default_mandays_rate']
            );
        }

        return $data;
    }

    public function index(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        $date = $request->query('date', now()->toDateString());
        $query = Grade::query();

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($this->inRoles($request->user(), ['fat'])) {
            $query->with(['allowanceRates' => function ($rates) use ($date) {
                $rates->activeOn($date)
                    ->whereHas('allowanceType', fn ($type) => $type->where('is_active', true))
                    ->with('allowanceType');
            }]);
        }

        $grades = $query
            ->orderBy('level')
            ->get()
            ->map(fn (Grade $grade) => $this->gradePayloadFor($request->user(), $grade))
            ->values();

        return response()->json($grades);
    }

    public function show(Request $request, Grade $grade)
    {
        if (! $this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        return response()->json($this->gradePayloadFor($request->user(), $grade));
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100', Rule::unique('grades', 'name')],
            'level' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = $this->uniqueCode($data['code'] ?? $this->makeCodeFromName($data['name']));
        $data['base_salary_basis'] = 'daily';
        $data['default_base_salary_amount'] = 0;
        $data['default_mandays_rate'] = 0;
        $grade = Grade::create($data);

        return response()->json($this->gradePayloadFor($request->user(), $grade), 201);
    }

    public function update(Request $request, Grade $grade)
    {
        if (! $this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        if ($this->inRoles($request->user(), ['hcga'])) {
            $data = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('grades', 'name')->ignore($grade->id)],
                'code' => ['nullable', 'string', 'max:50'],
                'level' => ['sometimes', 'required', 'integer', 'min:1'],
                'description' => ['nullable', 'string'],
                'is_active' => ['sometimes', 'required', 'boolean'],
            ]);

            if (array_key_exists('name', $data) || array_key_exists('code', $data)) {
                $data['code'] = $this->uniqueCode(
                    $data['code'] ?? $this->makeCodeFromName($data['name'] ?? $grade->name),
                    $grade->id
                );
            }

            $data['base_salary_basis'] = 'daily';
            $grade->update($data);

            return response()->json($this->gradePayloadFor($request->user(), $grade->fresh()));
        }

        $structureFields = ['name', 'code', 'level', 'description', 'is_active', 'base_salary_basis'];
        foreach ($structureFields as $field) {
            if ($request->exists($field)) {
                return $this->forbid('Finance hanya boleh mengubah nominal gaji pokok harian.');
            }
        }

        $data = $request->validate([
            'default_base_salary_amount' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $data = $this->normalizeBaseSalaryPayload($data);
        $grade->update($data);

        return response()->json($grade->fresh());
    }

    public function destroy(Request $request, Grade $grade)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        if ($grade->employees()->exists() || $grade->salaryProfiles()->exists() || $grade->jobHistories()->exists()) {
            return response()->json([
                'message' => 'Jabatan sudah dipakai karyawan atau riwayat. Nonaktifkan jabatan tanpa menghapusnya.',
            ], 422);
        }

        $grade->delete();

        return response()->json(['message' => 'Grade deleted successfully']);
    }
}
