<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Decrypt payroll attributes (gaji_pokok, tunjangan, potongan, total)
     */
    public function decryptPayrollValues(Payroll $p): array
    {
        $alg = strtoupper((string) ($p->salary_alg ?? 'AES'));
        $gaji = $tunj = $pot = $total = 0.0;

        try {
            if ($alg === 'HYBRID') {
                $dec = CryptoService::decryptHybridPayrollRow([
                    'dek_enc'        => $p->dek_enc,
                    'enc_meta'       => $p->enc_meta,
                    'gaji_pokok_enc' => $p->gaji_pokok_enc,
                    'tunjangan_enc'  => $p->tunjangan_enc,
                    'potongan_enc'   => $p->potongan_enc,
                    'total_enc'      => $p->total_enc,
                    'catatan_enc'    => $p->catatan_enc,
                ]);

                $gaji  = isset($dec['gaji_pokok']) ? (float) $dec['gaji_pokok'] : 0.0;
                $tunj  = isset($dec['tunjangan'])  ? (float) $dec['tunjangan']  : 0.0;
                $pot   = isset($dec['potongan'])   ? (float) $dec['potongan']   : 0.0;
                $total = isset($dec['total'])      ? (float) $dec['total']      : 0.0;
            } else {
                $gaji  = (float) CryptoService::readEncryptedOrPlainSafe($p->gaji_pokok_enc, $p->gaji_pokok, $alg);
                $tunj  = (float) CryptoService::readEncryptedOrPlainSafe($p->tunjangan_enc,  $p->tunjangan,  $alg);
                $pot   = (float) CryptoService::readEncryptedOrPlainSafe($p->potongan_enc,   $p->potongan,   $alg);
                $total = (float) CryptoService::readEncryptedOrPlainSafe($p->total_enc,      $p->total,      $alg);
            }
        } catch (\Throwable $e) {
            $gaji  = (float) $p->gaji_pokok;
            $tunj  = (float) $p->tunjangan;
            $pot   = (float) $p->potongan;
            $total = (float) $p->total;
        }

        if ($total <= 0.0) {
            $total = $gaji + $tunj - $pot;
        }

        return [
            'gaji_pokok' => $gaji,
            'tunjangan'  => $tunj,
            'potongan'   => $pot,
            'total'      => $total,
        ];
    }

    /**
     * Generate unique journal number: JU-YYYYMM-XXXX
     */
    public function generateJournalNumber(string $dateString): string
    {
        $carbon = Carbon::parse($dateString);
        $yearMonth = $carbon->format('Ym'); // e.g. "202607"
        
        $prefix = "JU-" . $yearMonth . "-";

        // Query the database to find the last sequence number for the same prefix
        $lastEntry = JournalEntry::where('journal_number', 'like', $prefix . '%')
            ->orderBy('journal_number', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastEntry) {
            $lastNumStr = substr($lastEntry->journal_number, strlen($prefix));
            $nextNumber = ((int) $lastNumStr) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create Accrual Journal for a period
     */
    public function createAccrualJournalByPeriod(string $periode): ?JournalEntry
    {
        $payrolls = Payroll::whereDate('periode', $periode)->get();

        if ($payrolls->isEmpty()) {
            return null;
        }

        $sumGajiPokok = 0.0;
        $sumTunjangan = 0.0;
        $sumPotongan = 0.0;
        $sumTotalGajiBersih = 0.0;

        foreach ($payrolls as $p) {
            $vals = $this->decryptPayrollValues($p);
            $sumGajiPokok       += $vals['gaji_pokok'];
            $sumTunjangan       += $vals['tunjangan'];
            $sumPotongan        += $vals['potongan'];
            $sumTotalGajiBersih += $vals['total'];
        }

        // Fetch Accounts
        $coaKasBank         = ChartOfAccount::where('code', '10100')->firstOrFail();
        $coaUtangGaji       = ChartOfAccount::where('code', '20100')->firstOrFail();
        $coaUtangPotongan   = ChartOfAccount::where('code', '20200')->firstOrFail();
        $coaBebanGajiPokok  = ChartOfAccount::where('code', '50100')->firstOrFail();
        $coaBebanTunjangan  = ChartOfAccount::where('code', '50200')->firstOrFail();

        return DB::transaction(function () use ($periode, $sumGajiPokok, $sumTunjangan, $sumPotongan, $sumTotalGajiBersih, $coaUtangGaji, $coaUtangPotongan, $coaBebanGajiPokok, $coaBebanTunjangan) {
            // Find or create journal entry
            $entry = JournalEntry::where('reference_type', 'payroll_period')
                ->where('reference_id', $periode)
                ->where('journal_type', 'ACCRUAL')
                ->first();

            if (!$entry) {
                $entry = JournalEntry::create([
                    'journal_number' => $this->generateJournalNumber($periode),
                    'journal_type' => 'ACCRUAL',
                    'transaction_date' => Carbon::parse($periode)->endOfMonth()->toDateString(),
                    'reference_type' => 'payroll_period',
                    'reference_id' => $periode,
                    'description' => "Jurnal Pengakuan Beban Gaji Karyawan Periode " . Carbon::parse($periode)->format('F Y'),
                    'status' => 'posted',
                ]);
            } else {
                $entry->update([
                    'transaction_date' => Carbon::parse($periode)->endOfMonth()->toDateString(),
                    'description' => "Jurnal Pengakuan Beban Gaji Karyawan Periode " . Carbon::parse($periode)->format('F Y'),
                    'status' => 'posted',
                ]);
            }

            // Remove old journal items
            $entry->items()->delete();

            // 1. Debit: Beban Gaji Pokok
            $entry->items()->create([
                'account_id' => $coaBebanGajiPokok->id,
                'debit' => $sumGajiPokok,
                'credit' => 0.00,
                'description' => "Pengakuan beban gaji pokok periode " . Carbon::parse($periode)->format('F Y'),
            ]);

            // 2. Debit: Beban Tunjangan (jika ada)
            if ($sumTunjangan > 0) {
                $entry->items()->create([
                    'account_id' => $coaBebanTunjangan->id,
                    'debit' => $sumTunjangan,
                    'credit' => 0.00,
                    'description' => "Pengakuan beban tunjangan periode " . Carbon::parse($periode)->format('F Y'),
                ]);
            }

            // 3. Kredit: Utang Potongan Gaji (jika ada)
            if ($sumPotongan > 0) {
                $entry->items()->create([
                    'account_id' => $coaUtangPotongan->id,
                    'debit' => 0.00,
                    'credit' => $sumPotongan,
                    'description' => "Pengakuan potongan gaji karyawan periode " . Carbon::parse($periode)->format('F Y'),
                ]);
            }

            // 4. Kredit: Utang Gaji Karyawan
            $entry->items()->create([
                'account_id' => $coaUtangGaji->id,
                'debit' => 0.00,
                'credit' => $sumTotalGajiBersih,
                'description' => "Pengakuan utang gaji bersih karyawan periode " . Carbon::parse($periode)->format('F Y'),
            ]);

            return $entry;
        });
    }

    /**
     * Create Payment Journal for a period
     */
    public function createPaymentJournalByPeriod(string $periode): ?JournalEntry
    {
        $payrolls = Payroll::whereDate('periode', $periode)->get();

        if ($payrolls->isEmpty()) {
            return null;
        }

        $sumTotalGajiBersih = 0.0;

        foreach ($payrolls as $p) {
            $vals = $this->decryptPayrollValues($p);
            $sumTotalGajiBersih += $vals['total'];
        }

        // Fetch Accounts
        $coaKasBank   = ChartOfAccount::where('code', '10100')->firstOrFail();
        $coaUtangGaji = ChartOfAccount::where('code', '20100')->firstOrFail();

        return DB::transaction(function () use ($periode, $sumTotalGajiBersih, $coaKasBank, $coaUtangGaji) {
            // Find or create journal entry
            $entry = JournalEntry::where('reference_type', 'payroll_period')
                ->where('reference_id', $periode)
                ->where('journal_type', 'PAYMENT')
                ->first();

            // Set transaction date to today (payment date)
            $paymentDate = Carbon::now()->toDateString();

            if (!$entry) {
                $entry = JournalEntry::create([
                    'journal_number' => $this->generateJournalNumber($periode),
                    'journal_type' => 'PAYMENT',
                    'transaction_date' => $paymentDate,
                    'reference_type' => 'payroll_period',
                    'reference_id' => $periode,
                    'description' => "Jurnal Pembayaran Gaji Karyawan Periode " . Carbon::parse($periode)->format('F Y'),
                    'status' => 'posted',
                ]);
            } else {
                $entry->update([
                    'transaction_date' => $paymentDate,
                    'description' => "Jurnal Pembayaran Gaji Karyawan Periode " . Carbon::parse($periode)->format('F Y'),
                    'status' => 'posted',
                ]);
            }

            // Remove old journal items
            $entry->items()->delete();

            // 1. Debit: Utang Gaji Karyawan
            $entry->items()->create([
                'account_id' => $coaUtangGaji->id,
                'debit' => $sumTotalGajiBersih,
                'credit' => 0.00,
                'description' => "Pelunasan utang gaji bersih karyawan periode " . Carbon::parse($periode)->format('F Y'),
            ]);

            // 2. Kredit: Kas & Bank
            $entry->items()->create([
                'account_id' => $coaKasBank->id,
                'debit' => 0.00,
                'credit' => $sumTotalGajiBersih,
                'description' => "Pembayaran kas/bank gaji karyawan periode " . Carbon::parse($periode)->format('F Y'),
            ]);

            return $entry;
        });
    }

    /**
     * Remove Accrual Journal if payroll is rejected
     */
    public function removeAccrualJournalByPeriod(string $periode): void
    {
        JournalEntry::where('reference_type', 'payroll_period')
            ->where('reference_id', $periode)
            ->where('journal_type', 'ACCRUAL')
            ->delete();
    }

    /**
     * Get General Ledger data dynamically
     */
    public function getGeneralLedger(int $accountId, string $startDate, string $endDate): array
    {
        $coa = ChartOfAccount::findOrFail($accountId);
        $normalBalance = strtolower((string)$coa->normal_balance); // debit or credit

        // 1. Saldo Awal (Opening Balance) - cumulative before startDate
        $openingDebit = JournalItem::where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($startDate) {
                $q->where('status', 'posted')
                  ->where('transaction_date', '<', $startDate);
            })
            ->sum('debit');

        $openingCredit = JournalItem::where('account_id', $accountId)
            ->whereHas('entry', function ($q) use ($startDate) {
                $q->where('status', 'posted')
                  ->where('transaction_date', '<', $startDate);
            })
            ->sum('credit');

        $openingBalance = 0.0;
        if ($normalBalance === 'debit') {
            $openingBalance = $openingDebit - $openingCredit;
        } else {
            $openingBalance = $openingCredit - $openingDebit;
        }

        // 2. Mutations (during period)
        $items = JournalItem::join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_items.account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.transaction_date', [$startDate, $endDate])
            ->select(
                'journal_items.id',
                'journal_items.journal_entry_id',
                'journal_items.debit',
                'journal_items.credit',
                'journal_items.description as item_desc',
                'journal_entries.journal_number',
                'journal_entries.transaction_date',
                'journal_entries.description as entry_desc'
            )
            ->orderBy('journal_entries.transaction_date')
            ->orderBy('journal_entries.journal_number')
            ->orderBy('journal_items.id')
            ->get();

        $mutations = [];
        $runningBalance = $openingBalance;

        foreach ($items as $item) {
            $debit = (float)$item->debit;
            $credit = (float)$item->credit;

            if ($normalBalance === 'debit') {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $mutations[] = [
                'id' => $item->id,
                'journal_entry_id' => $item->journal_entry_id,
                'journal_number' => $item->journal_number,
                'transaction_date' => Carbon::parse($item->transaction_date)->toDateString(),
                'description' => $item->item_desc ?: $item->entry_desc,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'account' => [
                'id' => $coa->id,
                'code' => $coa->code,
                'name' => $coa->name,
                'group' => $coa->group,
                'normal_balance' => $coa->normal_balance,
            ],
            'opening_balance' => $openingBalance,
            'mutations' => $mutations,
            'closing_balance' => $runningBalance,
        ];
    }
}
