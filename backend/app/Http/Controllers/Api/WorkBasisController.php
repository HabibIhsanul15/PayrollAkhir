<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkBasis;
use Illuminate\Http\Request;

class WorkBasisController extends Controller
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

        return response()->json(WorkBasis::all());
    }
}
