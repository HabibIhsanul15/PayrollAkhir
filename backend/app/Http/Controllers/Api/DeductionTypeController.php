<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeductionType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeductionTypeController extends Controller
{
    private function forbid(string $message = 'Forbidden')
    {
        return response()->json(['message' => $message], 403);
    }

    private function inRoles($user, array $roles): bool
    {
        $role = strtolower((string) ($user->role ?? ''));
        $roles = array_map(fn ($item) => strtolower((string) $item), $roles);

        return in_array($role, $roles, true);
    }

    public function index(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga', 'fat'])) {
            return $this->forbid();
        }

        $query = DeductionType::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('display_order')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:deduction_types,code'],
            'name' => ['required', 'string', 'max:150', 'unique:deduction_types,name'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['display_order'] = $data['display_order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        return response()->json(DeductionType::create($data), 201);
    }

    public function update(Request $request, DeductionType $deductionType)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('deduction_types', 'code')->ignore($deductionType->id),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('deduction_types', 'name')->ignore($deductionType->id),
            ],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $deductionType->update($data);

        return response()->json($deductionType->fresh());
    }

    public function destroy(Request $request, DeductionType $deductionType)
    {
        if (! $this->inRoles($request->user(), ['hcga'])) {
            return $this->forbid();
        }

        if ($deductionType->specialDeductions()->exists()) {
            return response()->json([
                'message' => 'Jenis potongan sudah digunakan. Nonaktifkan tanpa menghapusnya.',
            ], 422);
        }

        $deductionType->delete();

        return response()->json(['message' => 'Jenis potongan berhasil dihapus.']);
    }
}
