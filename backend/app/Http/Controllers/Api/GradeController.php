<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends Controller
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

        return response()->json(Grade::with(['allowanceRates.allowanceType'])->orderBy('level')->get());
    }

    public function show(Request $request, Grade $grade)
    {
        if (!$this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        return response()->json($grade);
    }

    public function store(Request $request)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:grades,code'],
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'default_mandays_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $grade = Grade::create($data);

        return response()->json($grade, 201);
    }

    public function update(Request $request, Grade $grade)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('grades', 'code')->ignore($grade->id)],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'level' => ['sometimes', 'required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'default_mandays_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $grade->update($data);

        return response()->json($grade);
    }

    public function destroy(Request $request, Grade $grade)
    {
        if (!$this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        if ($grade->employees()->exists()) {
            return response()->json(['message' => 'Grade cannot be deleted because it is assigned to employees.'], 422);
        }

        $grade->delete();

        return response()->json(['message' => 'Grade deleted successfully']);
    }
}
