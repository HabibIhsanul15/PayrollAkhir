<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    private function makeCodeFromName(string $name): string
    {
        $words = preg_split('/\s+/', strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $name)))) ?: [];
        $words = array_values(array_filter($words));

        if (count($words) === 0) {
            return 'jabatan';
        }

        if (count($words) > 1) {
            return substr(implode('', array_map(fn (mixed $word) => $word[0], $words)), 0, 20);
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
        $exists = fn (string $code) => Position::where('code', $code)
            ->when($ignoreId, fn (mixed $query) => $query->whereKeyNot($ignoreId))
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

    private function inRoles(mixed $user, array $roles): bool
    {
        $r = strtolower((string) ($user->role ?? ''));
        $roles = array_map(fn (mixed $x) => strtolower((string) $x), $roles);

        return in_array($r, $roles, true);
    }

    private function positionPayloadFor(mixed $user, Position $Position): array
    {
        $data = $Position->toArray();

        if ($this->inRoles($user, ['fat'])) {
            unset(
                $data['default_base_salary_amount'],
                $data['default_late_penalty_amount']
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
        $query = Position::query();

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($this->inRoles($request->user(), ['fat'])) {
            $query->with(['allowanceRates' => function (mixed $rates) {
                $rates->whereHas('allowanceType', fn (mixed $type) => $type->where('is_active', true))
                    ->with('allowanceType');
            }]);
        }

        $positions = $query
            ->orderBy('level')
            ->get()
            ->map(fn (Position $Position) => $this->positionPayloadFor($request->user(), $Position))
            ->values();

        return response()->json($positions);
    }

    public function show(Request $request, Position $Position)
    {
        if (! $this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        return response()->json($this->positionPayloadFor($request->user(), $Position));
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100', Rule::unique('positions', 'name')],
            'level' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'default_base_salary_amount' => ['sometimes', 'required', 'integer', 'min:0'],
            'default_late_penalty_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $data['code'] = $this->uniqueCode($data['code'] ?? $this->makeCodeFromName($data['name']));
        
        $Position = Position::create($data);

        return response()->json($this->positionPayloadFor($request->user(), $Position), 201);
    }

    public function update(Request $request, Position $Position)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('positions', 'name')->ignore($Position->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'level' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'default_base_salary_amount' => ['sometimes', 'required', 'integer', 'min:0'],
            'default_late_penalty_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('name', $data) || array_key_exists('code', $data)) {
            $data['code'] = $this->uniqueCode(
                $data['code'] ?? $this->makeCodeFromName($data['name'] ?? $Position->name),
                $Position->id
            );
        }

        $Position->update($data);

        return response()->json($this->positionPayloadFor($request->user(), $Position->fresh()));
    }

    public function destroy(Request $request, Position $Position)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        if ($Position->employees()->exists() || $Position->salaryProfiles()->exists() || $Position->jobHistories()->exists()) {
            return response()->json([
                'message' => 'Jabatan sudah dipakai karyawan atau riwayat. Nonaktifkan jabatan tanpa menghapusnya.',
            ], 422);
        }

        $Position->delete();

        return response()->json(['message' => 'Position deleted successfully']);
    }
}
