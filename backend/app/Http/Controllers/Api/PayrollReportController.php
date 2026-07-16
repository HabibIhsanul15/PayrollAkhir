<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\PerfLog;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\CryptoService;

class PayrollReportController extends Controller
{
    private function roleOf($user): string
    {
        return strtolower((string) ($user->role ?? ''));
    }

    private function forbid(string $msg = 'Forbidden')
    {
        return response()->json(['message' => $msg], 403);
    }

    /**
     * GET /api/reports/payroll?month=YYYY-MM&status=paid&employee_id=1
     * Akses: FAT + Director
     *
     * NOTE:
     * - Default: status=paid
     * - Support HYBRID: decrypt via decryptHybridPayrollRow (butuh dek_enc + enc_meta)
     *
     * PERF LOG:
     * - Akan insert 1 row ke perf_logs untuk setiap hit report:
     *   scenario=REPORT, alg=... (AES/RSA/HYBRID/MIXED), db_ms, decrypt_ms, total_ms, meta
     */
    public function index(Request $request)
    {
        $t0 = microtime(true);

        $user = $request->user();
        $role = $this->roleOf($user);

        if (!in_array($role, ['fat', 'director'], true)) {
            return $this->forbid();
        }

        // ---- filter tanggal
        $month = $request->query('month'); // YYYY-MM (utama)
        $from  = $request->query('from');  // YYYY-MM-DD (opsional)
        $to    = $request->query('to');    // YYYY-MM-DD (opsional)

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $payrollPeriod = PayrollPeriod::forMonth($month);
            $start = Carbon::parse($payrollPeriod->start_date)->startOfDay();
            $end   = Carbon::parse($payrollPeriod->end_date)->endOfDay();
        } else {
            $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
            $end   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfMonth();
            $month = $start->format('Y-m');
        }

        // ---- status filter (default paid)
        $status = strtolower((string) $request->query('status', 'paid'));
        $allowedStatus = ['paid', 'approved', 'requested', 'draft', 'rejected', 'all', 'semua'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'paid';
        }

        // optional filter employee
        $employeeId = $request->query('employee_id');

        $q = Payroll::query()
            ->with([
                'employee:id,name,employee_code,position_id',
                'employee.salaryProfiles',
            ])
            ->whereBetween('periode', [$start->toDateString(), $end->toDateString()]);

        if (!in_array($status, ['all', 'semua'], true)) {
            $q->where('status', $status);
        }

        if ($employeeId) {
            $q->where('employee_id', $employeeId);
        }

        // ========== DB TIME ==========
        $tDb0 = microtime(true);

        // WAJIB ambil dek_enc + enc_meta supaya HYBRID bisa decrypt
        $payrollRows = (clone $q)
            ->with(['allowances.allowanceType', 'deductions'])
            ->orderBy('periode')
            ->orderBy('employee_id')
            ->get([
                'id','employee_id','periode','status',

                // ciphertext
                'gaji_pokok_enc','tunjangan_enc','potongan_enc','total_enc','catatan_enc',
                'total_allowances_enc','total_deductions_enc',

                // HYBRID meta
                'dek_enc','enc_meta',

                // plain optional
                'gaji_pokok','tunjangan','potongan','total',
                'total_allowances','total_deductions',
                'period_from','period_to',

                // meta
                'calculation_mode','calculated_at',
                'salary_alg',
                'created_at',
                'paid_at',
            ]);

        $dbMs = (microtime(true) - $tDb0) * 1000;

        // ========== DECRYPT TIME ==========
        $decryptTotalMs = 0.0;

        $rows = $payrollRows->map(function (Payroll $p) use (&$decryptTotalMs) {
            $alg = strtoupper((string) ($p->salary_alg ?? 'AES'));

            $periodEnd = $p->period_to ?: $p->periode;
            $profile = $p->employee?->salaryProfiles
                ?->filter(fn ($profile) => $profile->effective_from && $profile->effective_from->lte($periodEnd))
                ->sortByDesc('effective_from')
                ->first();

            $positionName = $profile?->getAttribute('position')
                ?: Position::find($profile?->position_id)?->name;
            $positionNames = $positionName ? [$positionName] : [];

            if ($positionNames === []) {
                $fallbackPosition = $p->employee?->getAttribute('position')
                    ?: Position::find($p->employee?->position_id)?->name;

                if ($fallbackPosition) {
                    $positionNames = [$fallbackPosition];
                }
            }

            $tDec0 = microtime(true);

            $gaji = $tunj = $pot = $total = null;
            $tot_all = $tot_ded = null;

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
                        'total_allowances_enc' => $p->total_allowances_enc,
                        'total_deductions_enc' => $p->total_deductions_enc,
                    ]);

                    $gaji  = $dec['gaji_pokok'] ?? null;
                    $tunj  = $dec['tunjangan']  ?? null;
                    $pot   = $dec['potongan']   ?? null;
                    $total = $dec['total']      ?? null;
                    $tot_all = $dec['total_allowances'] ?? null;
                    $tot_ded = $dec['total_deductions'] ?? null;
                } else {
                    // AES / RSA
                    $gaji  = CryptoService::readEncryptedOrPlainSafe($p->gaji_pokok_enc, $p->gaji_pokok, $alg);
                    $tunj  = CryptoService::readEncryptedOrPlainSafe($p->tunjangan_enc,  $p->tunjangan,  $alg);
                    $pot   = CryptoService::readEncryptedOrPlainSafe($p->potongan_enc,   $p->potongan,   $alg);
                    $total = CryptoService::readEncryptedOrPlainSafe($p->total_enc,      $p->total,      $alg);
                    $tot_all = CryptoService::readEncryptedOrPlainSafe($p->total_allowances_enc, $p->total_allowances, $alg);
                    $tot_ded = CryptoService::readEncryptedOrPlainSafe($p->total_deductions_enc, $p->total_deductions, $alg);
                }
            } catch (\Throwable $e) {
                // biarkan null -> jadi 0
            }

            $decryptTotalMs += (microtime(true) - $tDec0) * 1000;

            $gaji = $gaji !== null ? (float) $gaji : 0.0;
            $tunj = $tunj !== null ? (float) $tunj : 0.0;
            $pot  = $pot  !== null ? (float) $pot  : 0.0;
            $total = $total !== null ? (float) $total : ($gaji + $tunj - $pot);
            $tot_all = $tot_all !== null ? (float) $tot_all : null;
            $tot_ded = $tot_ded !== null ? (float) $tot_ded : null;

            foreach ($p->allowances as $al) {
                if ($al->amount_enc) {
                    $al->amount = (float) CryptoService::readEncryptedOrPlainSafe($al->amount_enc, $al->amount, $al->salary_alg ?? 'AES');
                } else if ($al->amount !== null) {
                    $al->amount = (float) $al->amount;
                }
            }
            foreach ($p->deductions as $dd) {
                if ($dd->amount_enc) {
                    $dd->amount = (float) CryptoService::readEncryptedOrPlainSafe($dd->amount_enc, $dd->amount, $dd->salary_alg ?? 'AES');
                } else if ($dd->amount !== null) {
                    $dd->amount = (float) $dd->amount;
                }
            }

            return [
                'id' => $p->id,
                'employee_id' => $p->employee_id,
                'employee_code' => $p->employee?->employee_code,
                'employee_name' => $p->employee?->name,
                'position_name' => implode(' / ', $positionNames) ?: 'Belum ditentukan',

                'periode' => optional($p->periode)->toDateString(),
                'period_from' => optional($p->period_from)->toDateString(),
                'period_to' => optional($p->period_to)->toDateString(),
                'status' => $p->status,

                'gaji_pokok' => $gaji,
                'tunjangan'  => $tunj,
                'potongan'   => $pot,
                'total'      => $total,

                'total_allowances' => $tot_all,
                'total_deductions' => $tot_ded,
                'calculation_mode' => $p->calculation_mode,
                'calculated_at'    => $p->calculated_at,

                'allowances' => $p->allowances,
                'deductions' => $p->deductions,

                'salary_alg' => $p->salary_alg,
                'paid_at' => optional($p->paid_at)->toISOString(),
                'created_at' => optional($p->created_at)->toISOString(),

                'masked' => false,
            ];
        })->values();

        $summary = [
            'count' => (int) $rows->count(),
            'sum_gaji_pokok' => (float) $rows->sum('gaji_pokok'),
            'sum_tunjangan'  => (float) $rows->sum('tunjangan'),
            'sum_potongan'   => (float) $rows->sum('potongan'),
            'sum_total'      => (float) $rows->sum('total'),
        ];

        $totalMs = (microtime(true) - $t0) * 1000;

        // ========== TENTUKAN ALG UNTUK REPORT ==========
        // kalau isinya campur (AES/RSA/HYBRID), tandai MIXED
        $algList = $payrollRows->pluck('salary_alg')->filter()->map(fn($a) => strtoupper((string)$a))->unique()->values();
        $reportAlg = $algList->count() === 1 ? ($algList->first() ?? 'AES') : 'MIXED';

        // ========== INSERT PERF LOG ==========
        try {
            PerfLog::create([
                'scenario'   => 'REPORT',
                'alg'        => $reportAlg,

                // report tidak spesifik payroll_id
                'payroll_id' => null,

                // report tidak melakukan encrypt, jadi 0
                'encrypt_ms' => 0,

                // total decrypt dari semua row (akumulasi)
                'decrypt_ms' => round($decryptTotalMs, 3),

                'db_ms'      => round($dbMs, 3),
                'total_ms'   => round($totalMs, 3),

                'meta'       => json_encode([
                    'month' => $month,
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'status' => $status,
                    'employee_id' => $employeeId,
                    'row_count' => (int) $rows->count(),
                    'read_mode' => CryptoService::readMode(),
                    'storage_mode' => CryptoService::salaryStorageMode(),
                ], JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            // jangan bikin report gagal hanya karena logging perf gagal
        }

        return response()->json([
            'filters' => [
                'month' => $month,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'status' => $status,
                'employee_id' => $employeeId,
            ],
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }
}
