<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GeneralLedgerController extends Controller
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

    public function index(Request $request, AccountingService $accountingService)
    {
        if (!$this->inRoles($request->user(), ['fat', 'director'])) {
            return $this->forbid();
        }

        $request->validate([
            'account_id' => ['required', 'exists:chart_of_accounts,id'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date'],
        ]);

        $accountId = (int)$request->account_id;
        $startDate = $request->query('start_date') ?: Carbon::now()->startOfMonth()->toDateString();
        $endDate   = $request->query('end_date') ?: Carbon::now()->endOfMonth()->toDateString();

        $ledger = $accountingService->getGeneralLedger($accountId, $startDate, $endDate);

        return response()->json($ledger);
    }
}
