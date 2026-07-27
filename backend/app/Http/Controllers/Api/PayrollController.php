<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\Payroll;
use App\Models\PerfLog;
use App\Models\SalaryProfile;
use App\Services\CryptoService;
use App\Support\PayrollPeriodRange;
use App\Support\PayrollPeriodResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PayrollController extends Controller
{
    /**
     * GET /api/payrolls
     * List payroll (nominal di-mask kalau user tidak berhak)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $user = $request->user();

        $query = Payroll::query()
            ->with([
                'user:id,name',
                'employee:id,user_id,employee_code,name,status,bank_name,bank_account_number_enc,pii_alg,pii_key_id,dek_enc,enc_meta',
            ])
            ->orderByDesc('id');

        // optional filter status
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // ✅ Staff hanya boleh lihat payroll miliknya (tidak boleh override via query param)
        if (($user->role ?? '') === 'staff') {
            if (! empty($user->employee_id)) {
                $query->where('employee_id', $user->employee_id);
            } else {
                $query->whereHas('employee', fn (mixed $q) => $q->where('user_id', $user->id));
            }
        } else {
            // selain staff, baru boleh filter employee_id
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
        }

        if ($request->filled('period_month')) {
            $payrollPeriod = PayrollPeriodResolver::forMonth($request->period_month);
            $query->whereDate('periode', \Carbon\Carbon::parse($payrollPeriod->start_date)->toDateString());
        } elseif ($request->filled('periode')) {
            $query->whereDate('periode', $request->periode);
        }

        $rows = $query->get()->map(function (Payroll $p) use ($user) {
            $canSeeNominal = $this->canSeeNominal($user, $p);
            $canSeeBank = $this->canSeeBank($user, $p);
            $alg = strtoupper((string) ($p->salary_alg ?? 'AES'));

            $gaji = $tunj = $pot = $total = null;
            $dataUnavailable = false;

            if ($canSeeNominal) {
                try {
                    if ($alg === 'HYBRID') {
                        // ✅ HYBRID: wajib decrypt via row (dek_enc + enc_meta + *_enc)
                        $dec = CryptoService::decryptHybridPayrollRow([
                            'dek_enc' => $p->dek_enc,
                            'enc_meta' => $p->enc_meta,

                            'gaji_pokok_enc' => $p->gaji_pokok_enc,
                            'tunjangan_enc' => $p->tunjangan_enc,
                            'potongan_enc' => $p->potongan_enc,
                            'total_enc' => $p->total_enc,
                        ]);

                        $gaji = $dec['gaji_pokok'] ?? null;
                        $tunj = $dec['tunjangan'] ?? null;
                        $pot = $dec['potongan'] ?? null;
                        $total = $dec['total'] ?? null;
                    } else {
                        // ✅ AES / RSA
                        $gaji = CryptoService::readEncryptedOrPlainSafe($p->gaji_pokok_enc, $p->gaji_pokok, $alg);
                        $tunj = CryptoService::readEncryptedOrPlainSafe($p->tunjangan_enc, $p->tunjangan, $alg);
                        $pot = CryptoService::readEncryptedOrPlainSafe($p->potongan_enc, $p->potongan, $alg);
                        $total = CryptoService::readEncryptedOrPlainSafe($p->total_enc, $p->total, $alg);
                    }

                    // nominal -> float
                    $gaji = $gaji !== null ? (float) $gaji : null;
                    $tunj = $tunj !== null ? (float) $tunj : null;
                    $pot = $pot !== null ? (float) $pot : null;
                    $total = $total !== null ? (float) $total : null;
                } catch (\Throwable $e) {
                    // Gagal tertutup untuk payroll ini saja; daftar payroll
                    // lain tetap dapat dibaca dan error teknis tidak dikirim.
                    $gaji = $tunj = $pot = $total = null;
                    $dataUnavailable = true;
                    Log::warning('Payroll tidak dapat diverifikasi saat daftar payroll.', [
                        'payroll_id' => $p->id,
                        'exception' => $e::class,
                    ]);
                }
            }

            $periodMonth = PayrollPeriodResolver::forDate($p->periode)->period_month;

            $total_mandays = \App\Models\MonthlyRecap::where('employee_id', $p->employee_id)
                ->where('period_month', $periodMonth)->sum('total_mandays');

            return [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'employee_id' => $p->employee_id,
                'employee_code' => $p->employee?->employee_code,
                'employee_name' => $p->employee?->name,
                'employee_status' => $p->employee?->status,
                ...($canSeeBank ? [
                    'bank_name' => $p->employee?->bank_name,
                    'bank_account_number' => $p->employee?->bank_account_number,
                ] : []),

                'created_by' => $p->user?->name,
                'periode' => optional($p->periode)->toDateString(),
                'period_month' => $periodMonth,

                'status' => $p->status ?? null,
                'salary_alg' => $p->salary_alg ?? null,

                'created_at' => optional($p->created_at)->toISOString(),
                'updated_at' => optional($p->updated_at)->toISOString(),

                'gaji_pokok' => $gaji,
                'tunjangan' => $tunj,
                'potongan' => $pot,
                'total' => $total,
                'total_mandays' => (float) $total_mandays,

                'masked' => ! $canSeeNominal,
                'data_unavailable' => $dataUnavailable,
                'message' => $dataUnavailable
                    ? 'Slip gaji sementara tidak dapat ditampilkan. Silakan hubungi administrator.'
                    : null,
            ];
        });

        return response()->json($rows);
    }

    /**
     * GET /api/payrolls/{payroll}
     */
    public function show(Request $request, Payroll $payroll)
    {
        $this->authorize('view', $payroll);

        // Reset relation + load (bagian DB)
        $payroll->unsetRelation('employee');
        $payroll->unsetRelation('user');

        $payroll->load([
            'user:id,name',
            'employee',
            'employee.Position',
            'allowances.allowanceType',
            'deductions',
        ]);

        $user = $request->user();

        // Kunci slip gaji untuk staff jika belum ditransfer (paid)
        if (($user->role ?? 'staff') === 'staff' && $payroll->status !== 'paid') {
            return response()->json([
                'locked' => true,
                'message' => 'Slip Gaji Terkunci: Gaji Anda sedang dalam proses persetujuan dan belum ditransfer.',
            ], 403);
        }

        $canSeeNominal = $this->canSeeNominal($user, $payroll);
        $canSeeBank = $this->canSeeBank($user, $payroll);
        $alg = strtoupper((string) ($payroll->salary_alg ?? 'AES'));

        if ($payroll->employee) {
            if ($canSeeBank) {
                $payroll->employee->bank_account_number_decrypted = $payroll->employee->bank_account_number;
            }
        }

        $gaji = $tunj = $pot = $total = null;

        $dec_ms = null;

        if ($canSeeNominal) {
            $t0_dec = hrtime(true);

            try {
                if ($alg === 'HYBRID') {
                    // ✅ HYBRID: decrypt 1 payroll row (butuh dek_enc + enc_meta)
                    $plain = CryptoService::decryptHybridPayrollRow([
                        'dek_enc' => $payroll->dek_enc,
                        'enc_meta' => $payroll->enc_meta,

                        'gaji_pokok_enc' => $payroll->gaji_pokok_enc,
                        'tunjangan_enc' => $payroll->tunjangan_enc,
                        'potongan_enc' => $payroll->potongan_enc,
                        'total_enc' => $payroll->total_enc,
                    ]);

                    $gaji = $plain['gaji_pokok'] ?? null;
                    $tunj = $plain['tunjangan'] ?? null;
                    $pot = $plain['potongan'] ?? null;
                    $total = $plain['total'] ?? null;
                } else {
                    // ✅ AES / RSA
                    $gaji = CryptoService::readEncryptedOrPlain($payroll->gaji_pokok_enc, $payroll->gaji_pokok, $alg);
                    $tunj = CryptoService::readEncryptedOrPlain($payroll->tunjangan_enc, $payroll->tunjangan, $alg);
                    $pot = CryptoService::readEncryptedOrPlain($payroll->potongan_enc, $payroll->potongan, $alg);
                    $total = CryptoService::readEncryptedOrPlain($payroll->total_enc, $payroll->total, $alg);
                }

                // nominal jadi float
                $gaji = $gaji !== null ? (float) $gaji : null;
                $tunj = $tunj !== null ? (float) $tunj : null;
                $pot = $pot !== null ? (float) $pot : null;
                $total = $total !== null ? (float) $total : null;

                foreach ($payroll->allowances as $al) {
                    $al->amount = (float) ($al->amount ?? 0);
                }
                foreach ($payroll->deductions as $dd) {
                    $dd->amount = (float) ($dd->amount ?? 0);
                }

                $dec_ms = (hrtime(true) - $t0_dec) / 1e6;
            } catch (\Throwable $e) {
                $decrypt_ms_fail = (hrtime(true) - $t0_dec) / 1e6;
                // log failure (optional)
                try {
                    PerfLog::create([
                        'scenario' => 'READ_DETAIL',
                        'alg' => $alg,
                        'payroll_id' => $payroll->id,
                        'encrypt_ms' => null,
                        'decrypt_ms' => round($decrypt_ms_fail, 3),
                    ]);
                } catch (\Throwable $e2) {
                    // ignore
                }

                return response()->json([
                    'message' => 'Slip gaji sementara tidak dapat ditampilkan. Silakan hubungi administrator.',
                ], 422);
            }
        }

        $activeProfile = null;
        if ($payroll->employee && $canSeeNominal) {
            $prof = $payroll->employee->currentSalaryProfile(optional($payroll->periode)->toDateString());
            if ($prof) {
                $decBase = $this->resolvePositionAllowanceFromProfile($prof, $payroll->employee);
                $baseSalary = $this->resolveBaseSalaryFromProfile($prof, $payroll->employee);

                $activeProfile = [
                    'position_allowance' => $decBase,
                    'base_salary_amount' => $baseSalary['amount'],
                ];
            }
        }

        // Simpan benchmark kriptografi. decrypt_ms null bila tidak ada dekripsi payroll.
        try {
            PerfLog::create([
                'scenario' => 'READ_DETAIL',
                'alg' => $alg,
                'payroll_id' => $payroll->id,
                'encrypt_ms' => null,
                'decrypt_ms' => $dec_ms,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        $periodMonth = PayrollPeriodResolver::forDate($payroll->periode)->period_month;

        $employeePayload = $payroll->employee ? [
            'join_date' => optional($payroll->employee->join_date)->toDateString(),
            'position' => $payroll->employee->Position?->name,
            'position_name' => $payroll->employee->Position?->name,
        ] : null;

        if ($employeePayload && $canSeeBank) {
            $employeePayload += [
                'bank_name' => $payroll->employee->bank_name,
                'bank_account_name' => $payroll->employee->bank_account_name,
                'bank_account_number_decrypted' => $payroll->employee->bank_account_number_decrypted,
            ];
        }

        return response()->json([
            'id' => $payroll->id,

            'employee_id' => $payroll->employee_id,
            'employee_code' => $payroll->employee?->employee_code,
            'employee_name' => $payroll->employee?->name,
            'employee_status' => $payroll->employee?->status,
            'employee' => $employeePayload,

            'created_by' => $payroll->user?->name,
            'periode' => optional($payroll->periode)->toDateString(),
            'period_month' => $periodMonth,
            'period_from' => optional($payroll->period_from)->toDateString(),
            'period_to' => optional($payroll->period_to)->toDateString(),

            'status' => $payroll->status ?? null,
            'rejection_reason' => $payroll->status === 'rejected' ? $payroll->approval_note : null,
            'salary_alg' => $payroll->salary_alg ?? null,
            'paid_ref' => $payroll->paid_ref,
            'paid_at' => optional($payroll->paid_at)->toDateTimeString(),
            'paid_note' => $payroll->paid_note,

            'gaji_pokok' => $gaji,
            'tunjangan' => $tunj,
            'potongan' => $pot,
            'total' => $total,

            'calculated_at' => $payroll->calculated_at,

            'allowances' => $canSeeNominal ? collect($payroll->allowances)->map(function (mixed $al) {
                // Ensure relation is loaded if possible, though React uses al.allowance_type as fallback
                return $al;
            })->values()->all() : [],
            'deductions' => $canSeeNominal ? collect($payroll->deductions)->filter(function (mixed $dd) {
                return $dd->amount > 0;
            })->values()->all() : [],

            'mandays_summary' => [
                'mandays_ho_wfo' => \App\Models\MonthlyRecap::where('employee_id', $payroll->employee_id)
                    ->where('period_month', $periodMonth)->sum('wfo_days'),
                'mandays_ho_wfh' => \App\Models\MonthlyRecap::where('employee_id', $payroll->employee_id)
                    ->where('period_month', $periodMonth)->sum('wfh_days'),
                'mandays_outside_city' => \App\Models\MonthlyRecap::where('employee_id', $payroll->employee_id)
                    ->where('period_month', $periodMonth)->sum('out_of_town_days'),
                'mandays_project' => 0, // deprecated
                'mandays_training' => \App\Models\MonthlyRecap::where('employee_id', $payroll->employee_id)
                    ->where('period_month', $periodMonth)->sum('training_days'),
                'total_mandays' => \App\Models\MonthlyRecap::where('employee_id', $payroll->employee_id)
                    ->where('period_month', $periodMonth)->sum('total_mandays'),
            ],

            'monthly_recaps' => \App\Models\MonthlyRecap::where('employee_id', $payroll->employee_id)
                ->where('period_month', $periodMonth)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function (mixed $r) use ($payroll) {
                    $prof = \App\Models\SalaryProfile::find($r->salary_profile_id);
                    $decBase = $prof ? $this->resolvePositionAllowanceFromProfile($prof, $payroll->employee) : 0;
                    $baseSalary = $prof ? $this->resolveBaseSalaryFromProfile($prof, $payroll->employee) : ['amount' => 0];

                    return [
                        'id' => $r->id,
                        'wfo_days' => (float) $r->wfo_days,
                        'wfh_days' => (float) $r->wfh_days,
                        'total_mandays' => (float) $r->total_mandays,
                        'base_salary_amount' => (float) $baseSalary['amount'],
                        'position_allowance' => (float) $decBase,
                        'position_name' => $prof && $prof->Position ? $prof->Position->name : '-',
                        'effective_from' => $prof ? $prof->effective_from->toDateString() : '-',
                    ];
                }),

            'active_salary_profile' => $activeProfile,

            'masked' => ! $canSeeNominal,

            'created_at' => optional($payroll->created_at)->toDateTimeString(),
            'updated_at' => optional($payroll->updated_at)->toDateTimeString(),
        ]);
    }

    public function pdf(Request $request, Payroll $payroll)
    {
        $this->authorize('view', $payroll);

        $payroll->unsetRelation('employee');
        $payroll->unsetRelation('user');

        $payroll->load([
            'user:id,name',
            'employee',
            'employee.Position',
            'allowances.allowanceType',
            'deductions',
        ]);

        $user = $request->user();

        if (! $this->canSeeNominal($user, $payroll)) {
            return response()->json([
                'message' => 'Tidak memiliki akses untuk membuka PDF slip gaji.',
            ], 403);
        }

        $alg = strtoupper((string) ($payroll->salary_alg ?? 'AES'));

        if ($payroll->employee) {
            if ($this->canSeeBank($user, $payroll)) {
                $payroll->employee->bank_account_number_decrypted = $payroll->employee->bank_account_number;
            }
        }

        try {
            if ($alg === 'HYBRID') {
                $plain = CryptoService::decryptHybridPayrollRow([
                    'dek_enc' => $payroll->dek_enc,
                    'enc_meta' => $payroll->enc_meta,

                    'gaji_pokok_enc' => $payroll->gaji_pokok_enc,
                    'tunjangan_enc' => $payroll->tunjangan_enc,
                    'potongan_enc' => $payroll->potongan_enc,
                    'total_enc' => $payroll->total_enc,
                ]);

                $payroll->gaji_pokok = $plain['gaji_pokok'] ?? null;
                $payroll->tunjangan = $plain['tunjangan'] ?? null;
                $payroll->potongan = $plain['potongan'] ?? null;
                $payroll->total = $plain['total'] ?? null;
            } else {
                $payroll->gaji_pokok = CryptoService::readEncryptedOrPlain($payroll->gaji_pokok_enc, $payroll->gaji_pokok, $alg);
                $payroll->tunjangan = CryptoService::readEncryptedOrPlain($payroll->tunjangan_enc, $payroll->tunjangan, $alg);
                $payroll->potongan = CryptoService::readEncryptedOrPlain($payroll->potongan_enc, $payroll->potongan, $alg);
                $payroll->total = CryptoService::readEncryptedOrPlain($payroll->total_enc, $payroll->total, $alg);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Slip gaji tidak dapat diproses. Hubungi admin.',
            ], 422);
        }

        // Cast nominal jadi float supaya rupiah() di blade aman
        $payroll->gaji_pokok = $payroll->gaji_pokok !== null ? (float) $payroll->gaji_pokok : 0;
        $payroll->tunjangan = $payroll->tunjangan !== null ? (float) $payroll->tunjangan : 0;
        $payroll->potongan = $payroll->potongan !== null ? (float) $payroll->potongan : 0;

        foreach ($payroll->allowances as $al) {
            $al->amount = (float) ($al->amount ?? 0);
        }
        foreach ($payroll->deductions as $dd) {
            $dd->amount = (float) ($dd->amount ?? 0);
        }

        $payroll->total = $payroll->total !== null
            ? (float) $payroll->total
            : ($payroll->gaji_pokok + $payroll->tunjangan - $payroll->potongan);

        $payrollPeriod = PayrollPeriodResolver::forDate($payroll->periode);
        $baseSalaryRows = $this->baseSalaryRows($payroll, $payrollPeriod);
        $payrollPosition = $baseSalaryRows !== []
            ? $baseSalaryRows[array_key_last($baseSalaryRows)]['position']
            : $payroll->employee?->position;

        $pdf = Pdf::loadView('pdf.payroll-slip', [
            'payroll' => $payroll,
            'payrollPeriod' => $payrollPeriod,
            'baseSalaryRows' => $baseSalaryRows,
            'payrollPosition' => $payrollPosition,
            'canSeeBank' => $this->canSeeBank($user, $payroll),
        ])->setPaper('A4', 'portrait');

        $filename = 'slip-gaji-'.
            ($payroll->employee?->employee_code ?? $payroll->employee_id).
            '-'.$payrollPeriod->period_month.'.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * Build tampilan gaji pokok dari rekap dan profile yang dipakai pada periode
     * payroll, sehingga slip lama tidak berubah saat jabatan pegawai berubah.
     *
     * @return array<int, array{position: ?\App\Models\Position, mandays: float, rate: float, amount: float}>
     */
    private function baseSalaryRows(Payroll $payroll, PayrollPeriodRange $period): array
    {
        if (! $payroll->employee_id) {
            return [];
        }

        $recaps = MonthlyRecap::query()
            ->where('employee_id', $payroll->employee_id)
            ->where('period_month', $period->period_month)
            ->orderBy('id')
            ->get(['total_mandays', 'salary_profile_id']);

        $profileIds = $recaps->pluck('salary_profile_id')->filter()->unique()->values();
        $profiles = SalaryProfile::query()
            ->with('position')
            ->whereIn('id', $profileIds)
            ->get()
            ->keyBy('id');

        $fallbackProfile = SalaryProfile::query()
            ->with('position')
            ->where('employee_id', $payroll->employee_id)
            ->whereDate('effective_from', '<=', $payroll->period_from ?? $payroll->periode)
            ->orderByDesc('effective_from')
            ->first();

        return $recaps->map(function (MonthlyRecap $recap) use ($profiles, $fallbackProfile): ?array {
            $profile = $recap->salary_profile_id
                ? $profiles->get($recap->salary_profile_id)
                : $fallbackProfile;
            $mandays = (float) $recap->total_mandays;
            $rate = (float) ($profile?->base_salary_amount ?? 0);

            if (! $profile || $mandays <= 0 || $rate <= 0) {
                return null;
            }

            return [
                'position' => $profile->position,
                'mandays' => $mandays,
                'rate' => $rate,
                'amount' => round($rate * $mandays),
            ];
        })->filter()->values()->all();
    }

    /**
     * DELETE /api/payrolls/{payroll}
     */
    public function destroy(Payroll $payroll)
    {
        $this->authorize('delete', $payroll);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'message' => 'Payroll hanya dapat dihapus saat masih berstatus draft.',
            ], 422);
        }

        $payroll->delete();

        return response()->json([
            'message' => 'Payroll deleted',
        ]);
    }

    private function resolvePositionAllowanceFromProfile(mixed $profile, ?Employee $employee): float
    {
        $decrypted = $profile->position_allowance;

        // Nilai 0 pada profil lama berarti belum ada override per pegawai.
        // Gunakan tarif tunjangan jabatan yang aktif pada master jabatan.
        if ($decrypted === null || $decrypted === '' || (float) $decrypted <= 0) {
            if ($profile->position_allowance > 0) {
                return (float) $profile->position_allowance;
            }

            $Position = $profile->Position ?? $employee?->Position;
            $posRate = $Position
                ? \App\Models\PositionAllowanceRate::where('position_id', $Position->id)
                    ->whereHas('allowanceType', fn (mixed $q) => $q->where('code', 'position'))
                    ->first()
                : null;

            return (float) ($posRate?->rate_amount ?? 0);
        }

        return (float) $decrypted;
    }

    private function resolveBaseSalaryFromProfile(mixed $profile, ?Employee $employee): array
    {
        $amount = $profile->base_salary_amount;

        if ($amount === null || $amount === '') {
            $Position = $profile->Position ?? $employee?->Position;
            $amount = $Position?->default_base_salary_amount ?? 0;
        }

        return [
            'amount' => (float) $amount,
        ];
    }

    /**
     * Nominal gaji boleh dilihat oleh:
     * - role fat / director
     * - ATAU pegawai pemilik slip (jika user punya employee_id)
     *
     * NOTE: jangan pakai "creator payroll" sebagai owner slip, itu beda konsep.
     */
    private function canSeeNominal(mixed $user, Payroll $payroll): bool
    {
        if (! $user) {
            return false;
        }

        // FAT / Director selalu boleh lihat nominal
        if (in_array($user->role, ['fat', 'director'], true)) {
            return true;
        }

        // staff selain pemilik slip -> tidak boleh
        if (($user->role ?? '') !== 'staff') {
            return false;
        }

        // staff pemilik slip
        $payroll->loadMissing('employee:id,user_id');

        $isOwner =
            (! empty($user->employee_id) && (int) $user->employee_id === (int) $payroll->employee_id)
            || ((int) ($payroll->employee?->user_id) === (int) $user->id);

        if (! $isOwner) {
            return false;
        }

        // Setelah transfer dicatat, slip dianggap terkirim dan nominal boleh dibuka staff.
        return $payroll->status === 'paid';
    }

    private function canSeeBank(mixed $user, Payroll $payroll): bool
    {
        if (! $user) {
            return false;
        }

        if (in_array(strtolower($user->role ?? ''), ['fat', 'director'])) {
            return true;
        }

        if (strtolower($user->role ?? '') !== 'staff') {
            return false;
        }

        $payroll->loadMissing('employee:id,user_id');

        return (
            ! empty($user->employee_id) && (int) $user->employee_id === (int) $payroll->employee_id
        ) || ((int) ($payroll->employee?->user_id) === (int) $user->id);
    }

    private function ensureRole(mixed $user, array $roles)
    {
        $r = $user->role ?? '';
        if (! in_array($r, $roles, true)) {
            response()->json(['message' => 'Tidak punya akses.'], 403)->send();
            exit;
        }
    }

    private function makePaidReference(Payroll $payroll): string
    {
        $periodKey = PayrollPeriodResolver::forDate($payroll->periode)->period_month;
        $periodKey = str_replace('-', '', $periodKey);
        $payrollId = str_pad((string) $payroll->id, 5, '0', STR_PAD_LEFT);

        return "TRF-{$periodKey}-{$payrollId}";
    }

    public function markPaid(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        $this->ensureRole($user, ['fat']); // FAT saja

        if ($payroll->status !== 'approved') {
            return response()->json(['message' => 'Tidak bisa mark paid (status bukan approved).'], 422);
        }

        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'paid_ref' => ['nullable', 'string', 'max:120'],
            'paid_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $request->hasFile('proof')) {
            return response()->json(['message' => 'File proof tidak terbaca.'], 422);
        }

        // simpan file
        $path = $request->file('proof')->store('payroll_proofs', 'public');
        $paidRef = $this->makePaidReference($payroll);

        $payroll->update([
            'status' => 'paid',
            'paid_by' => $user->id,
            'paid_at' => Carbon::now(),

            'paid_proof_path' => $path,
            'paid_proof_uploaded_by' => $user->id,
            'paid_proof_uploaded_at' => Carbon::now(),

            'paid_ref' => $paidRef,
            'paid_note' => $data['paid_note'] ?? null,
        ]);

        $payroll->refresh(); // ✅ ambil data terbaru dari DB

        return response()->json([
            'message' => 'Payroll ditandai PAID + bukti transfer tersimpan.',
            'payroll' => $payroll,
        ]);
    }

    public function proof(Request $request, Payroll $payroll)
    {
        $this->authorize('view', $payroll);

        $user = $request->user();

        if (($user->role ?? '') === 'staff') {
            $payroll->loadMissing('employee:id,user_id');

            $isOwner =
                (! empty($user->employee_id) && (int) $user->employee_id === (int) $payroll->employee_id)
                || ((int) ($payroll->employee?->user_id) === (int) $user->id);

            if (! $isOwner || $payroll->status !== 'paid') {
                return response()->json(['message' => 'Tidak punya akses bukti transfer.'], 403);
            }
        } else {
            if (! in_array($user->role, ['fat', 'director'], true)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        if (! $payroll->paid_proof_path) {
            return response()->json(['message' => 'Bukti transfer belum tersedia.'], 404);
        }

        if (! Storage::disk('public')->exists($payroll->paid_proof_path)) {
            return response()->json(['message' => 'File bukti tidak ditemukan di storage.'], 404);
        }

        $fullPath = Storage::disk('public')->path($payroll->paid_proof_path);

        return response()->file($fullPath);
    }
}
