<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JournalEntryController extends Controller
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
        if (!$this->inRoles($request->user(), ['fat', 'director'])) {
            return $this->forbid();
        }

        $query = JournalEntry::with(['items.account'])->orderBy('transaction_date', 'desc')->orderBy('journal_number', 'desc');

        if ($request->filled('journal_type')) {
            $query->where('journal_type', $request->journal_type);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        return response()->json($query->paginate(15));
    }

    public function show(Request $request, JournalEntry $journalEntry)
    {
        if (!$this->inRoles($request->user(), ['fat', 'director'])) {
            return $this->forbid();
        }

        $journalEntry->load(['items.account']);
        return response()->json($journalEntry);
    }

    public function store(Request $request, AccountingService $accountingService)
    {
        // Hanya FAT yang boleh membuat jurnal penyesuaian manual
        if (!$this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'items.*.debit' => ['required', 'numeric', 'min:0'],
            'items.*.credit' => ['required', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        // Validasi: Total Debit harus sama dengan Total Kredit
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($data['items'] as $item) {
            $totalDebit += (float)$item['debit'];
            $totalCredit += (float)$item['credit'];
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            return response()->json([
                'message' => 'Validasi gagal: Total Debit harus sama dengan Total Kredit (Balance).',
                'errors' => [
                    'items' => ['Jurnal tidak seimbang (debit = ' . number_format($totalDebit, 2) . ', kredit = ' . number_format($totalCredit, 2) . ').']
                ]
            ], 422);
        }

        if ($totalDebit <= 0.0) {
            return response()->json([
                'message' => 'Validasi gagal: Nominal transaksi harus lebih besar dari 0.',
                'errors' => [
                    'items' => ['Nominal jurnal harus lebih besar dari Rp 0.']
                ]
            ], 422);
        }

        $entry = DB::transaction(function () use ($data, $accountingService) {
            $journalNumber = $accountingService->generateJournalNumber($data['transaction_date']);
            
            $entry = JournalEntry::create([
                'journal_number' => $journalNumber,
                'journal_type' => 'ADJUSTMENT',
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'],
                'status' => 'posted',
            ]);

            foreach ($data['items'] as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            return $entry;
        });

        return response()->json($entry->load('items.account'), 201);
    }

    public function destroy(Request $request, JournalEntry $journalEntry)
    {
        if (!$this->inRoles($request->user(), ['fat'])) {
            return $this->forbid();
        }

        // Hanya boleh menghapus jurnal penyesuaian manual (ADJUSTMENT)
        // Jurnal ACCRUAL & PAYMENT dari Payroll tidak boleh dihapus manual
        if ($journalEntry->journal_type !== 'ADJUSTMENT') {
            return response()->json([
                'message' => 'Hanya jurnal penyesuaian manual (ADJUSTMENT) yang dapat dihapus.'
            ], 422);
        }

        $journalEntry->delete();

        return response()->json(['message' => 'Jurnal berhasil dihapus.']);
    }
}
