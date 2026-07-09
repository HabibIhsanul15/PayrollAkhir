<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
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
        if (!$this->inRoles($request->user(), ['hcga', 'fat', 'director'])) {
            return $this->forbid();
        }

        $accounts = ChartOfAccount::orderBy('code')->get();
        return response()->json($accounts);
    }

    public function show(Request $request, ChartOfAccount $chartOfAccount)
    {
        if (!$this->inRoles($request->user(), ['hcga', 'fat', 'director'])) {
            return $this->forbid();
        }

        return response()->json($chartOfAccount);
    }

    public function store(Request $request)
    {
        if (!$this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:100'],
            'group' => ['required', 'string', Rule::in(['Aset', 'Liabilitas', 'Ekuitas', 'Pendapatan', 'Beban'])],
            'normal_balance' => ['required', 'string', Rule::in(['debit', 'credit'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account = ChartOfAccount::create($data);

        return response()->json($account, 201);
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        if (!$this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('chart_of_accounts', 'code')->ignore($chartOfAccount->id)],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'group' => ['sometimes', 'required', 'string', Rule::in(['Aset', 'Liabilitas', 'Ekuitas', 'Pendapatan', 'Beban'])],
            'normal_balance' => ['sometimes', 'required', 'string', Rule::in(['debit', 'credit'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        $chartOfAccount->update($data);

        return response()->json($chartOfAccount);
    }

    public function destroy(Request $request, ChartOfAccount $chartOfAccount)
    {
        if (!$this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        if ($chartOfAccount->journalItems()->exists()) {
            return response()->json([
                'message' => 'Akun tidak bisa dihapus karena sudah memiliki histori transaksi jurnal.'
            ], 422);
        }

        // Prevent deleting core payroll accounts
        if (in_array($chartOfAccount->code, ['10100', '20100', '20200', '50100', '50200'], true)) {
            return response()->json([
                'message' => 'Akun sistem inti tidak dapat dihapus.'
            ], 422);
        }

        $chartOfAccount->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
