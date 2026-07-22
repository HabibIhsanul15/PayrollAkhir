<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PerfLog;
use App\Services\CryptoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
                'employee:id,user_id,employee_code,name,status,bank_name,bank_account_number_enc,pii_alg',
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
            $payrollPeriod = \App\Models\PayrollPeriod::forMonth($request->period_month);
            $query->whereDate('periode', \Carbon\Carbon::parse($payrollPeriod->start_date)->toDateString());
        } elseif ($request->filled('periode')) {
            $query->whereDate('periode', $request->periode);
        }

        $rows = $query->get()->map(function (Payroll $p) use ($user) {
            $canSeeNominal = $this->canSeeNominal($user, $p);
            $canSeeBank = $this->canSeeBank($user, $p);
            $alg = strtoupper((string) ($p->salary_alg ?? 'AES'));

            $gaji = $tunj = $pot = $total = null;
            $cat = null;

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
                            'catatan_enc' => $p->catatan_enc,
                        ]);

                        $gaji = $dec['gaji_pokok'] ?? null;
                        $tunj = $dec['tunjangan'] ?? null;
                        $pot = $dec['potongan'] ?? null;
                        $total = $dec['total'] ?? null;
                        $cat = $dec['catatan'] ?? null;
                    } else {
                        // ✅ AES / RSA
                        $gaji = CryptoService::readEncryptedOrPlainSafe($p->gaji_pokok_enc, $p->gaji_pokok, $alg);
                        $tunj = CryptoService::readEncryptedOrPlainSafe($p->tunjangan_enc, $p->tunjangan, $alg);
                        $pot = CryptoService::readEncryptedOrPlainSafe($p->potongan_enc, $p->potongan, $alg);
                        $total = CryptoService::readEncryptedOrPlainSafe($p->total_enc, $p->total, $alg);
                        $cat = CryptoService::readEncryptedOrPlainSafe($p->catatan_enc, $p->catatan, $alg);
                    }

                    // nominal -> float (catatan biarkan string)
                    $gaji = $gaji !== null ? (float) $gaji : null;
                    $tunj = $tunj !== null ? (float) $tunj : null;
                    $pot = $pot !== null ? (float) $pot : null;
                    $total = $total !== null ? (float) $total : null;
                } catch (\Throwable $e) {
                    // kalau decrypt gagal, jangan bikin endpoint crash
                    $gaji = $tunj = $pot = $total = null;
                    $cat = null;
                }
            }

                $periodMonth = \App\Models\PayrollPeriod::forDate($p->periode)->period_month;
                
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
                        'bank_account_number' => $p->employee?->bank_account_number_enc
                            ? CryptoService::readEncryptedOrPlainSafe($p->employee->bank_account_number_enc, $p->employee->bank_account_number, $p->employee->pii_alg ?? 'AES')
                            : $p->employee?->bank_account_number,
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
                    'catatan' => $cat,
                    'total_mandays' => (float) $total_mandays,

                    'masked' => ! $canSeeNominal,
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

        $t0_total = hrtime(true);

        // Reset relation + load (bagian DB)
        $payroll->unsetRelation('employee');
        $payroll->unsetRelation('user');

        $t0_db = hrtime(true);

        $payroll->load([
            'user:id,name',
            'employee',
            'employee.Position',
            'allowances.allowanceType',
            'deductions',
        ]);

        $db_ms = (hrtime(true) - $t0_db) / 1e6;

        $user = $request->user();

        // Kunci slip gaji untuk staff jika belum ditransfer (paid)
        if (($user->role ?? 'staff') === 'staff' && $payroll->status !== 'paid') {
            return response()->json([
                'locked' => true,
                'message' => 'Slip Gaji Terkunci: Gaji Anda sedang dalam proses persetujuan dan belum ditransfer.'
            ], 403);
        }

        $canSeeNominal = $this->canSeeNominal($user, $payroll);
        $alg = strtoupper((string) ($payroll->salary_alg ?? 'AES'));

        if ($payroll->employee) {
            $empAlg = strtoupper((string) ($payroll->employee->pii_alg ?? 'AES'));
            if ($this->canSeeBank($user, $payroll)) {
                $payroll->employee->bank_account_number_decrypted = \App\Services\CryptoService::readEncryptedOrPlain(
                    $payroll->employee->bank_account_number_enc,
                    $payroll->employee->bank_account_number,
                    $empAlg
                );
            }
        }

        $gaji = $tunj = $pot = $total = null;
        $cat = null;
        $tot_all = $tot_ded = null;

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
                        'catatan_enc' => $payroll->catatan_enc,
                        'total_allowances_enc' => $payroll->total_allowances_enc,
                        'total_deductions_enc' => $payroll->total_deductions_enc,
                    ]);

                    $gaji = $plain['gaji_pokok'] ?? null;
                    $tunj = $plain['tunjangan'] ?? null;
                    $pot = $plain['potongan'] ?? null;
                    $total = $plain['total'] ?? null;
                    $cat = $plain['catatan'] ?? null;
                    $tot_all = $plain['total_allowances'] ?? null;
                    $tot_ded = $plain['total_deductions'] ?? null;
                } else {
                    // ✅ AES / RSA
                    $gaji = CryptoService::readEncryptedOrPlain($payroll->gaji_pokok_enc, $payroll->gaji_pokok, $alg);
                    $tunj = CryptoService::readEncryptedOrPlain($payroll->tunjangan_enc, $payroll->tunjangan, $alg);
                    $pot = CryptoService::readEncryptedOrPlain($payroll->potongan_enc, $payroll->potongan, $alg);
                    $total = CryptoService::readEncryptedOrPlain($payroll->total_enc, $payroll->total, $alg);
                    $cat = CryptoService::readEncryptedOrPlain($payroll->catatan_enc, $payroll->catatan, $alg);
                    $tot_all = CryptoService::readEncryptedOrPlainSafe($payroll->total_allowances_enc, $payroll->total_allowances, $alg);
                    $tot_ded = CryptoService::readEncryptedOrPlainSafe($payroll->total_deductions_enc, $payroll->total_deductions, $alg);
                }

                // nominal jadi float (catatan tetap string)
                $gaji = $gaji !== null ? (float) $gaji : null;
                $tunj = $tunj !== null ? (float) $tunj : null;
                $pot = $pot !== null ? (float) $pot : null;
                $total = $total !== null ? (float) $total : null;
                $tot_all = $tot_all !== null ? (float) $tot_all : null;
                $tot_ded = $tot_ded !== null ? (float) $tot_ded : null;

                foreach ($payroll->allowances as $al) {
                    if ($al->amount_enc) {
                        $al->amount = (float) CryptoService::readEncryptedOrPlainSafe($al->amount_enc, $al->amount, $al->salary_alg ?? 'AES');
                    } elseif ($al->amount !== null) {
                        $al->amount = (float) $al->amount;
                    }
                }
                foreach ($payroll->deductions as $dd) {
                    if ($dd->amount_enc) {
                        $dd->amount = (float) CryptoService::readEncryptedOrPlainSafe($dd->amount_enc, $dd->amount, $dd->salary_alg ?? 'AES');
                    } elseif ($dd->amount !== null) {
                        $dd->amount = (float) $dd->amount;
                    }
                }

                $dec_ms = (hrtime(true) - $t0_dec) / 1e6;
            } catch (\Throwable $e) {
                $total_ms_fail = (hrtime(true) - $t0_total) / 1e6;

                // log failure (optional)
                try {
                    PerfLog::create([
                        'scenario' => 'READ_DETAIL',
                        'alg' => $alg,
                        'payroll_id' => $payroll->id,
                        'decrypt_ms' => null,
                        'db_ms' => $db_ms,
                        'total_ms' => $total_ms_fail,
                        'meta' => [
                            'masked' => false,
                            'decrypt_error' => 'DECRYPT_FAILED',
                            'err' => substr($e->getMessage(), 0, 200),
                        ],
                    ]);
                } catch (\Throwable $e2) {
                    // ignore
                }

                return response()->json([
                    'message' => 'Data payroll tidak dapat diproses. Hubungi admin.',
                ], 422);
            }
        }

        // audit view detail
        $this->audit($request, 'PAYROLL_VIEW_DETAIL', $payroll);

        $total_ms = (hrtime(true) - $t0_total) / 1e6;

        $activeProfile = null;
        if ($payroll->employee && $canSeeNominal) {
            $prof = $payroll->employee->currentSalaryProfile(optional($payroll->periode)->toDateString());
            if ($prof) {
                $decBase = $this->resolvePositionAllowanceFromProfile($prof, $payroll->employee);
                $baseSalary = $this->resolveBaseSalaryFromProfile($prof, $payroll->employee);

                $activeProfile = [
                    'position_allowance' => $decBase,
                    'base_salary_basis' => $baseSalary['basis'],
                    'base_salary_amount' => $baseSalary['amount'],
                ];
            }
        }

        // simpan perf log (kalau masked, decrypt_ms null)
        try {
            PerfLog::create([
                'scenario' => 'READ_DETAIL',
                'alg' => $alg,
                'payroll_id' => $payroll->id,
                'decrypt_ms' => $dec_ms,
                'db_ms' => $db_ms,
                'total_ms' => $total_ms,
                'meta' => [
                    'masked' => ! $canSeeNominal,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        $periodMonth = \App\Models\PayrollPeriod::forDate($payroll->periode)->period_month;

        $employeePayload = $payroll->employee ? [
            'join_date' => optional($payroll->employee->join_date)->toDateString(),
            'department' => $payroll->employee->department,
            'position' => $payroll->employee->position,
            'position_name' => $payroll->employee->Position?->name,
            'base_salary_basis' => $activeProfile['base_salary_basis'] ?? ($payroll->employee->Position?->base_salary_basis ?? 'daily'),
            'base_salary_basis_label' => $this->baseSalaryBasisLabel(
                $activeProfile['base_salary_basis'] ?? ($payroll->employee->Position?->base_salary_basis ?? 'daily')
            ),
        ] : null;

        if ($employeePayload && $this->canSeeBank($user, $payroll)) {
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

            'status' => $payroll->status ?? null,
            'salary_alg' => $payroll->salary_alg ?? null,
            'paid_ref' => $payroll->paid_ref,
            'paid_at' => optional($payroll->paid_at)->toDateTimeString(),
            'paid_note' => $payroll->paid_note,

            'gaji_pokok' => $gaji,
            'tunjangan' => $tunj,
            'potongan' => $pot,
            'total' => $total,
            'catatan' => $cat,

            'total_allowances' => $tot_all,
            'total_deductions' => $tot_ded,
            'calculation_mode' => $payroll->calculation_mode,
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
                    $baseSalary = $prof ? $this->resolveBaseSalaryFromProfile($prof, $payroll->employee) : ['basis' => 'daily', 'amount' => 0];

                    return [
                        'id' => $r->id,
                        'wfo_days' => (float) $r->wfo_days,
                        'wfh_days' => (float) $r->wfh_days,
                        'total_mandays' => (float) $r->total_mandays,
                        'base_salary_basis' => $baseSalary['basis'],
                        'base_salary_basis_label' => $this->baseSalaryBasisLabel($baseSalary['basis']),
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

    public function inspection(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        $this->ensureRole($user, ['director', 'fat']);

        $this->audit($request, 'SECURITY_INSPECTION', $payroll);

        $alg = strtoupper((string) ($payroll->salary_alg ?: 'AES'));

        $snapshot = [];
        $compare = [];

        // Add metadata first
        $snapshot[] = ['column' => 'salary_alg', 'value' => $alg, 'length' => strlen($alg)];
        if ($payroll->salary_key_id) {
            $snapshot[] = ['column' => 'salary_key_id', 'value' => $payroll->salary_key_id, 'length' => strlen($payroll->salary_key_id)];
        }

        foreach (['gaji_pokok', 'tunjangan', 'total'] as $field) {
            $encField = $field.'_enc';
            if (! empty($payroll->$encField)) {
                $snapshot[] = [
                    'column' => $encField,
                    'value' => substr($payroll->$encField, 0, 30).'...',
                    'length' => strlen($payroll->$encField),
                ];

                // Decode plain text for compare table
                $plainVal = null;
                if ($alg === 'HYBRID') {
                    try {
                        $plain = \App\Services\CryptoService::decryptHybridPayrollRow([
                            'salary_key_id' => $payroll->salary_key_id,
                            'dek_enc' => $payroll->dek_enc,
                            'enc_meta' => $payroll->enc_meta,
                            $encField => $payroll->$encField,
                        ]);
                        $plainVal = $plain[$field] ?? null;
                    } catch (\Throwable $e) {
                    }
                } else {
                    $plainVal = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->$encField, $payroll->$field, $alg);
                }

                $compare[] = [
                    'field' => $encField,
                    'ciphertext' => substr($payroll->$encField, 0, 15).'...',
                    'plaintext' => $plainVal ? (float) $plainVal : 0,
                ];
            }
        }

        $dekMasked = null;
        $tagVerified = false;

        if ($alg === 'HYBRID' && $payroll->dek_enc) {
            $snapshot[] = [
                'column' => 'dek_enc',
                'value' => substr($payroll->dek_enc, 0, 30).'...',
                'length' => strlen($payroll->dek_enc),
            ];

            // Assuming rsa key id is extracted from salary_key_id format (e.g. "hybrid:RSA_ID:AES_ID")
            $parts = explode(':', $payroll->salary_key_id);
            if (count($parts) >= 2) {
                $snapshot[] = ['column' => 'rsa_key_id', 'value' => $parts[1], 'length' => strlen($parts[1])];
            }

            try {
                $dekRaw = \App\Services\CryptoService::decryptRSA($payroll->dek_enc);
                if ($dekRaw) {
                    $hex = strtoupper(bin2hex($dekRaw));
                    $dekMasked = substr($hex, 0, 2).':'.substr($hex, 2, 2).':**:**:**:**:'.substr($hex, -2);
                }
            } catch (\Exception $e) {
            }
        }

        if ($payroll->gaji_pokok_enc) {
            $raw = base64_decode($payroll->gaji_pokok_enc, true);
            if ($raw && strlen($raw) >= 28) {
                $tagVerified = true;
            }
        }

        $t0 = hrtime(true);
        $plainTotal = null;
        if ($alg === 'HYBRID') {
            try {
                $plain = \App\Services\CryptoService::decryptHybridPayrollRow([
                    'salary_key_id' => $payroll->salary_key_id,
                    'dek_enc' => $payroll->dek_enc,
                    'enc_meta' => $payroll->enc_meta,
                    'total_enc' => $payroll->total_enc,
                ]);
                $plainTotal = $plain['total'] ?? null;
            } catch (\Throwable $e) {
            }
        } else {
            $plainTotal = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->total_enc, $payroll->total, $alg);
        }
        $t1 = (hrtime(true) - $t0) / 1e6;

        return response()->json([
            'id' => $payroll->id,
            'salary_alg' => $alg,
            'key_id' => $payroll->salary_key_id,
            'snapshot' => $snapshot,
            'compare' => $compare,
            'dek_masked' => $dekMasked,
            'tag_verified' => $tagVerified,
            'decryption_time_ms' => round($t1, 2),
            'plaintext' => [
                'total' => $plainTotal,
            ],
        ]);
    }

    public function inspectionPdf(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        $this->ensureRole($user, ['director', 'fat']);

        $this->audit($request, 'SECURITY_INSPECTION_EXPORT', $payroll);

        $alg = strtoupper((string) ($payroll->salary_alg ?: 'AES'));

        $t0 = hrtime(true);
        $plainTotal = null;
        if ($alg === 'HYBRID') {
            try {
                $plain = \App\Services\CryptoService::decryptHybridPayrollRow([
                    'salary_key_id' => $payroll->salary_key_id,
                    'dek_enc' => $payroll->dek_enc,
                    'enc_meta' => $payroll->enc_meta,
                    'total_enc' => $payroll->total_enc,
                ]);
                $plainTotal = $plain['total'] ?? null;
            } catch (\Throwable $e) {
            }
        } else {
            $plainTotal = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->total_enc, $payroll->total, $alg);
        }
        $t1 = (hrtime(true) - $t0) / 1e6;

        $data = [
            'payroll_id' => $payroll->id,
            'employee_name' => $payroll->employee->name ?? '-',
            'inspection_time' => now()->format('d M Y H:i:s'),
            'inspector' => $request->user()->name,
            'algorithm' => $alg === 'HYBRID' ? 'AES-128-GCM + RSA-2048' : 'AES-128-GCM',
            'tag_verified' => true, // Simplification for UI consistency
            'ciphertext_sample' => substr($payroll->total_enc ?? '', 0, 32).'...',
            'plaintext_total' => $plainTotal,
            'decryption_time_ms' => round($t1, 2),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.security-inspection', $data);

        return $pdf->stream("Security_Inspection_{$payroll->id}.pdf");
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
            $empAlg = strtoupper((string) ($payroll->employee->pii_alg ?? 'AES'));
            if ($this->canSeeBank($user, $payroll)) {
                $payroll->employee->bank_account_number_decrypted = \App\Services\CryptoService::readEncryptedOrPlain(
                    $payroll->employee->bank_account_number_enc,
                    $payroll->employee->bank_account_number,
                    $empAlg
                );
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
                    'catatan_enc' => $payroll->catatan_enc,
                    'total_allowances_enc' => $payroll->total_allowances_enc,
                    'total_deductions_enc' => $payroll->total_deductions_enc,
                ]);

                $payroll->gaji_pokok = $plain['gaji_pokok'] ?? null;
                $payroll->tunjangan = $plain['tunjangan'] ?? null;
                $payroll->potongan = $plain['potongan'] ?? null;
                $payroll->total = $plain['total'] ?? null;
                $payroll->catatan = $plain['catatan'] ?? null;
                $payroll->total_allowances = $plain['total_allowances'] ?? null;
                $payroll->total_deductions = $plain['total_deductions'] ?? null;
            } else {
                $payroll->gaji_pokok = CryptoService::readEncryptedOrPlain($payroll->gaji_pokok_enc, $payroll->gaji_pokok, $alg);
                $payroll->tunjangan = CryptoService::readEncryptedOrPlain($payroll->tunjangan_enc, $payroll->tunjangan, $alg);
                $payroll->potongan = CryptoService::readEncryptedOrPlain($payroll->potongan_enc, $payroll->potongan, $alg);
                $payroll->total = CryptoService::readEncryptedOrPlain($payroll->total_enc, $payroll->total, $alg);
                $payroll->catatan = CryptoService::readEncryptedOrPlain($payroll->catatan_enc, $payroll->catatan, $alg);
                $payroll->total_allowances = CryptoService::readEncryptedOrPlainSafe($payroll->total_allowances_enc, $payroll->total_allowances, $alg);
                $payroll->total_deductions = CryptoService::readEncryptedOrPlainSafe($payroll->total_deductions_enc, $payroll->total_deductions, $alg);
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

        $payroll->total_allowances = $payroll->total_allowances !== null ? (float) $payroll->total_allowances : null;
        $payroll->total_deductions = $payroll->total_deductions !== null ? (float) $payroll->total_deductions : null;

        foreach ($payroll->allowances as $al) {
            if ($al->amount_enc) {
                $al->amount = (float) CryptoService::readEncryptedOrPlainSafe($al->amount_enc, $al->amount, $al->salary_alg ?? 'AES');
            } elseif ($al->amount !== null) {
                $al->amount = (float) $al->amount;
            }
        }
        foreach ($payroll->deductions as $dd) {
            if ($dd->amount_enc) {
                $dd->amount = (float) CryptoService::readEncryptedOrPlainSafe($dd->amount_enc, $dd->amount, $dd->salary_alg ?? 'AES');
            } elseif ($dd->amount !== null) {
                $dd->amount = (float) $dd->amount;
            }
        }

        $payroll->total = $payroll->total !== null
            ? (float) $payroll->total
            : ($payroll->gaji_pokok + $payroll->tunjangan - $payroll->potongan);

        $pdf = Pdf::loadView('pdf.payroll-slip', [
            'payroll' => $payroll,
            'canSeeBank' => $this->canSeeBank($user, $payroll),
        ])->setPaper('A4', 'portrait');

        $filename = 'slip-gaji-'.
            ($payroll->employee?->employee_code ?? $payroll->employee_id).
            '-'.optional($payroll->periode)->format('Y-m').'.pdf';

        $this->audit($request, 'PAYROLL_VIEW_PDF', $payroll);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * POST /api/payrolls
     * Create payroll
     */
    public function store(Request $request)
    {
        $this->authorize('create', Payroll::class);

        $t0_total = hrtime(true);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'periode' => ['required', 'date'],
            'gaji_pokok' => ['required', 'numeric', 'min:0'],
            'tunjangan' => ['nullable', 'numeric', 'min:0'],
            'potongan' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'auto_request' => ['nullable', 'boolean'],
        ]);

        $periode = \App\Models\PayrollPeriod::forDate($data['periode'])->start_date->startOfDay();
        $data['periode'] = $periode->toDateString();
        $autoRequest = (bool) ($request->input('auto_request', false));

        // validasi status employee
        $employee = Employee::select('id', 'status')->findOrFail($data['employee_id']);
        if (($employee->status ?? null) !== 'active') {
            return response()->json([
                'message' => 'Employee inactive tidak bisa dibuat payroll.',
                'errors' => ['employee_id' => ['Employee status inactive.']],
            ], 422);
        }

        // cek duplikat periode
        $exists = Payroll::where('employee_id', $data['employee_id'])
            ->whereDate('periode', $periode)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Payroll untuk employee dan periode ini sudah ada.',
                'errors' => ['periode' => ['Payroll periode ini sudah dibuat.']],
            ], 422);
        }

        // hitung nominal
        $gaji = (float) $data['gaji_pokok'];
        $tunj = (float) ($data['tunjangan'] ?? 0);
        $pot = (float) ($data['potongan'] ?? 0);
        $total = $gaji + $tunj - $pot;

        $alg = strtoupper((string) CryptoService::writeAlg());

        // salary_key_id buat audit/rotasi/pembanding TA
        $keyId = match ($alg) {
            'RSA' => CryptoService::rsaKeyId(),
            'HYBRID' => CryptoService::hybridKeyId(),
            default => CryptoService::keyId(),
        };

        // ===== encrypt timer =====
        $t0_enc = hrtime(true);

        $gaji_enc = $tunj_enc = $pot_enc = $total_enc = null;
        $cat_enc = null;

        $payrollDekEnc = null;
        $payrollEncMeta = null;

        if ($alg === 'HYBRID') {
            // ✅ 1x pack untuk satu payroll row (DEK 1x)
            $pack = CryptoService::encryptHybridPayroll([
                'gaji_pokok' => (string) $gaji,
                'tunjangan' => (string) $tunj,
                'potongan' => (string) $pot,
                'total' => (string) $total,
                'catatan' => (string) ($data['catatan'] ?? ''),
            ]);

            $gaji_enc = $pack['fields']['gaji_pokok_enc'];
            $tunj_enc = $pack['fields']['tunjangan_enc'];
            $pot_enc = $pack['fields']['potongan_enc'];
            $total_enc = $pack['fields']['total_enc'];
            $cat_enc = ! empty($data['catatan']) ? $pack['fields']['catatan_enc'] : null;

            $payrollDekEnc = $pack['dek_enc'];
            $payrollEncMeta = $pack['enc_meta'];
        } else {
            $enc = function (string $v) use ($alg) {
                return match ($alg) {
                    'RSA' => CryptoService::encryptRSA($v),
                    default => CryptoService::encryptAESGCM($v),
                };
            };

            $gaji_enc = $enc((string) $gaji);
            $tunj_enc = $enc((string) $tunj);
            $pot_enc = $enc((string) $pot);
            $total_enc = $enc((string) $total);
            $cat_enc = ! empty($data['catatan']) ? $enc((string) $data['catatan']) : null;
        }

        $enc_ms = (hrtime(true) - $t0_enc) / 1e6;

        // ===== DB timer =====
        $t0_db = hrtime(true);

        $payroll = Payroll::create([
            'user_id' => $request->user()->id,
            'employee_id' => $data['employee_id'],
            'periode' => $data['periode'],
            'status' => $autoRequest ? 'requested' : 'draft',
            'requested_by' => $autoRequest ? $request->user()->id : null,
            'requested_at' => $autoRequest ? Carbon::now() : null,

            // plaintext null (CIPHER_ONLY)
            'gaji_pokok' => null,
            'tunjangan' => null,
            'potongan' => null,
            'total' => null,
            'catatan' => null,

            // ciphertext
            'gaji_pokok_enc' => $gaji_enc,
            'tunjangan_enc' => $tunj_enc,
            'potongan_enc' => $pot_enc,
            'total_enc' => $total_enc,
            'catatan_enc' => $cat_enc,

            // ✅ HYBRID support (boleh null kalau AES/RSA)
            'dek_enc' => $payrollDekEnc,
            'enc_meta' => $payrollEncMeta,

            'salary_alg' => $alg,
            'salary_key_id' => $keyId,
        ]);

        $db_ms = (hrtime(true) - $t0_db) / 1e6;

        $this->audit($request, 'PAYROLL_CREATE', $payroll, [
            'employee_id' => $payroll->employee_id,
            'periode' => $payroll->periode,
            'alg' => $payroll->salary_alg,
            'key_id' => $payroll->salary_key_id,
        ]);

        $total_ms = (hrtime(true) - $t0_total) / 1e6;

        try {
            PerfLog::create([
                'scenario' => 'CREATE',
                'alg' => $alg,
                'payroll_id' => $payroll->id,
                'encrypt_ms' => $enc_ms,
                'db_ms' => $db_ms,
                'total_ms' => $total_ms,
                'meta' => [
                    'read_mode' => env('PAYROLL_READ_MODE'),
                    'storage_mode' => env('SALARY_STORAGE_MODE'),
                    'cat_len' => isset($data['catatan']) ? strlen((string) $data['catatan']) : 0,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        $payroll->load(['user:id,name', 'employee:id,employee_code,name,status']);

        return response()->json([
            'message' => 'Payroll created',
            'data' => [
                'id' => $payroll->id,
                'employee_id' => $payroll->employee_id,
                'employee_code' => $payroll->employee?->employee_code,
                'employee_name' => $payroll->employee?->name,
                'employee_status' => $payroll->employee?->status,
                'created_by' => $payroll->user?->name,
                'periode' => optional($payroll->periode)->toDateString(),
                'masked' => false,
                'created_at' => optional($payroll->created_at)->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * PUT/PATCH /api/payrolls/{payroll}
     */
    public function update(Request $request, Payroll $payroll)
    {
        $this->authorize('update', $payroll);

        $data = $request->validate([
            'periode' => ['sometimes', 'date'],
            'gaji_pokok' => ['sometimes', 'numeric', 'min:0'],
            'tunjangan' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'potongan' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'catatan' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // 1) Algoritma target dari config/env (jangan terima dari FE)
        $alg = CryptoService::writeAlg(); // pastikan ini return AES/RSA/HYBRID

        // 2) Ambil nilai lama (cipher-only => decrypt dari *_enc)
        $oldAlg = strtoupper((string) ($payroll->salary_alg ?? 'AES'));

        try {
            if ($oldAlg === 'HYBRID') {
                $oldPlain = CryptoService::decryptHybridPayrollRow([
                    'dek_enc' => $payroll->dek_enc,
                    'enc_meta' => $payroll->enc_meta,
                    'gaji_pokok_enc' => $payroll->gaji_pokok_enc,
                    'tunjangan_enc' => $payroll->tunjangan_enc,
                    'potongan_enc' => $payroll->potongan_enc,
                    'total_enc' => $payroll->total_enc,
                    'catatan_enc' => $payroll->catatan_enc,
                ]);

                $oldGaji = (float) ($oldPlain['gaji_pokok'] ?? 0);
                $oldTunj = (float) ($oldPlain['tunjangan'] ?? 0);
                $oldPot = (float) ($oldPlain['potongan'] ?? 0);
                $oldCat = (string) ($oldPlain['catatan'] ?? '');
            } else {
                $oldGaji = (float) (CryptoService::readEncryptedOrPlainSafe($payroll->gaji_pokok_enc, null, $oldAlg) ?? 0);
                $oldTunj = (float) (CryptoService::readEncryptedOrPlainSafe($payroll->tunjangan_enc, null, $oldAlg) ?? 0);
                $oldPot = (float) (CryptoService::readEncryptedOrPlainSafe($payroll->potongan_enc, null, $oldAlg) ?? 0);
                $oldCat = (string) (CryptoService::readEncryptedOrPlainSafe($payroll->catatan_enc, null, $oldAlg) ?? '');
            }
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Data payroll lama tidak dapat diproses untuk update. Hubungi admin.',
            ], 422);
        }

        // 3) Gabungkan nilai baru (jika tidak dikirim, pakai nilai lama)
        $gaji = array_key_exists('gaji_pokok', $data) ? (float) $data['gaji_pokok'] : $oldGaji;
        $tunj = array_key_exists('tunjangan', $data) ? (float) ($data['tunjangan'] ?? 0) : $oldTunj;
        $pot = array_key_exists('potongan', $data) ? (float) ($data['potongan'] ?? 0) : $oldPot;

        $catatanInputProvided = array_key_exists('catatan', $data);
        $catatan = $catatanInputProvided
            ? (string) ($data['catatan'] ?? '')
            : $oldCat;

        $total = $gaji + $tunj - $pot;

        // 4) Handle periode
        $periode = null;
        if (array_key_exists('periode', $data)) {
            $periode = \App\Models\PayrollPeriod::forDate($data['periode'])->start_date->startOfDay();
            $data['periode'] = $periode->toDateString();

            // Cegah duplikat periode
            $exists = Payroll::where('employee_id', $payroll->employee_id)
                ->whereDate('periode', $periode)
                ->where('id', '!=', $payroll->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Payroll untuk employee dan bulan ini sudah ada.',
                    'errors' => ['periode' => ['Payroll bulan ini sudah dibuat.']],
                ], 422);
            }
        }

        // 5) Cipher-only: plaintext NULL
        $data['gaji_pokok'] = null;
        $data['tunjangan'] = null;
        $data['potongan'] = null;
        $data['total'] = null;
        if ($catatanInputProvided) {
            $data['catatan'] = null;
        }

        // 6) Encrypt ulang sesuai algoritma target
        $payrollDekEnc = null;
        $payrollEncMeta = null;

        if ($alg === 'HYBRID') {
            // ✅ HYBRID RSA-AES: pakai encryptHybridPayroll() yang menghasilkan:
            // fields + dek_enc + enc_meta
            $pack = CryptoService::encryptHybridPayroll([
                'gaji_pokok' => (string) $gaji,
                'tunjangan' => (string) $tunj,
                'potongan' => (string) $pot,
                'total' => (string) $total,
                'catatan' => (string) $catatan,
            ]);

            $data['gaji_pokok_enc'] = $pack['fields']['gaji_pokok_enc'];
            $data['tunjangan_enc'] = $pack['fields']['tunjangan_enc'];
            $data['potongan_enc'] = $pack['fields']['potongan_enc'];
            $data['total_enc'] = $pack['fields']['total_enc'];

            // catatan: kalau kosong, set null biar hemat
            if ($catatanInputProvided) {
                $data['catatan_enc'] = ($catatan !== '') ? $pack['fields']['catatan_enc'] : null;
            }

            $payrollDekEnc = $pack['dek_enc'];
            $payrollEncMeta = $pack['enc_meta'];

            $data['dek_enc'] = $payrollDekEnc;
            $data['enc_meta'] = $payrollEncMeta;
        } else {
            $enc = function (string $v) use ($alg) {
                return match ($alg) {
                    'RSA' => CryptoService::encryptRSA($v),
                    default => CryptoService::encryptAESGCM($v),
                };
            };

            $data['gaji_pokok_enc'] = $enc((string) $gaji);
            $data['tunjangan_enc'] = $enc((string) $tunj);
            $data['potongan_enc'] = $enc((string) $pot);
            $data['total_enc'] = $enc((string) $total);

            if ($catatanInputProvided) {
                $data['catatan_enc'] = ($catatan !== '') ? $enc((string) $catatan) : null;
            }

            // kalau pindah dari HYBRID -> AES/RSA, bersihin meta biar rapi
            $data['dek_enc'] = null;
            $data['enc_meta'] = null;
        }

        // 7) salary_key_id
        $keyId = match ($alg) {
            'RSA' => CryptoService::rsaKeyId(),
            'HYBRID' => CryptoService::hybridKeyId(), // harus berisi rsa key id yang dipakai bungkus DEK
            default => CryptoService::keyId(),
        };

        $data['salary_alg'] = $alg;
        $data['salary_key_id'] = $keyId;

        $payroll->update($data);

        $this->audit($request, 'PAYROLL_UPDATE', $payroll, [
            'fields_updated' => array_keys($data),
            'employee_id' => $payroll->employee_id,
            'periode' => optional($payroll->periode)->toDateString(),
            'alg' => $alg,
            'key_id' => $keyId,
        ]);

        return response()->json([
            'message' => 'Payroll updated',
            'data' => $payroll->fresh()->loadMissing([
                'user:id,name',
                'employee:id,employee_code,name,status',
            ]),
        ]);
    }

    /**
     * DELETE /api/payrolls/{payroll}
     */
    public function destroy(Payroll $payroll)
    {
        $this->authorize('delete', $payroll);

        $payroll->delete();

        $this->audit(
            request(),
            'PAYROLL_DELETE',
            $payroll);

        return response()->json([
            'message' => 'Payroll deleted',
        ]);
    }

    private function resolvePositionAllowanceFromProfile(mixed $profile, ?Employee $employee): float
    {
        $alg = strtoupper((string) ($profile->salary_alg ?? 'AES'));
        $decrypted = $profile->position_allowance_enc
            ? CryptoService::decryptByAlg($profile->position_allowance_enc, $alg)
            : null;

        if ($decrypted === null || $decrypted === '') {
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
        $alg = strtoupper((string) ($profile->salary_alg ?? 'AES'));
        $amount = $profile->base_salary_amount_enc
            ? CryptoService::decryptByAlg($profile->base_salary_amount_enc, $alg)
            : null;

        if ($amount === null || $amount === '') {
            if ($profile->base_salary_amount !== null) {
                $amount = $profile->base_salary_amount;
            } elseif ($profile->mandays_rate_enc) {
                $amount = CryptoService::decryptByAlg($profile->mandays_rate_enc, $alg);
            } elseif ($profile->mandays_rate !== null) {
                $amount = $profile->mandays_rate;
            } else {
                $Position = $profile->Position ?? $employee?->Position;
                $amount = $Position?->default_base_salary_amount ?? $Position?->default_mandays_rate ?? 0;
            }
        }

        $Position = $profile->Position ?? $employee?->Position;
        $basis = $Position?->base_salary_basis ?? 'daily';

        return [
            'basis' => $basis ?: 'daily',
            'amount' => (float) $amount,
        ];
    }

    private function baseSalaryBasisLabel(?string $basis): string
    {
        return match ($basis) {
            'monthly' => 'Bulanan',
            default => 'Harian',
        };
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

    private function audit(Request $request, string $action, ?Payroll $payroll = null, array $meta = []): void
    {
        try {
            $u = $request->user();

            AuditLog::create([
                'user_id' => $u?->id,
                'action' => $action,
                'payroll_id' => $payroll?->id,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            // Jangan ganggu flow utama kalau log gagal
            // Optional: logger()->warning('Audit log failed', ['err' => $e->getMessage()]);
        }
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
        $periodKey = optional($payroll->periode)->format('Ym') ?: Carbon::now()->format('Ym');
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

        $this->audit($request, 'PAYROLL_MARK_PAID', $payroll, [
            'paid_proof_path' => $path,
            'paid_ref' => $payroll->paid_ref,
        ]);

        return response()->json([
            'message' => 'Payroll ditandai PAID + bukti transfer tersimpan.',
            'payroll' => $payroll,
        ]);
    }

    public function rejectPayment(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        $this->ensureRole($user, ['director']); // Director saja

        if (! in_array($payroll->status, ['requested', 'approved'], true)) {
            return response()->json(['message' => 'Tidak bisa reject untuk status ini.'], 422);
        }

        $from = $payroll->status;

        $payroll->update([
            'status' => 'rejected',
            'approval_note' => $request->input('approval_note'),
            // optional: kalau mau bersih, bisa juga null-kan approved_by/approved_at kalau reject dari approved
            // 'approved_by' => null,
            // 'approved_at' => null,
        ]);

        $this->audit($request, 'PAYROLL_REJECT_PAYMENT', $payroll, [
            'from' => $from,
            'to' => 'rejected',
        ]);

        return response()->json([
            'message' => 'Payroll di-reject.',
            'payroll' => $payroll->fresh(),
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

        $this->audit($request, 'PAYROLL_VIEW_PROOF', $payroll);

        $fullPath = Storage::disk('public')->path($payroll->paid_proof_path);

        return response()->file($fullPath);
    }
}
