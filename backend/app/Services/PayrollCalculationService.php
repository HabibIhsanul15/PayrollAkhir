<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Models\PayrollDeduction;
use App\Models\PerfLog;
use App\Models\Position;
use App\Models\SalaryProfile;
use App\Support\PayrollPeriodResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollCalculationService
{
    public function __construct(
        private AllowanceCalculationService $allowanceCalculator,
        private AllowanceRateResolver $rateResolver,
        private PayrollCipherService $cipherService,
        private SensitiveFieldCipherService $sensitiveCipher,
    ) {}

    public function validatePrerequisites(Employee $employee, string $periodMonth, ?int $ignorePayrollId = null): array
    {
        if ($employee->status !== 'active') {
            return ['status' => false, 'error' => 'Employee tidak aktif.'];
        }
        $recaps = MonthlyRecap::where('employee_id', $employee->id)->where('period_month', $periodMonth)->get();
        if ($recaps->isEmpty()) {
            return ['status' => false, 'error' => 'Rekap Bulanan (Monthly Recap) belum diinput.'];
        }

        // Aturan bisnis: satu pegawai hanya boleh memiliki satu recap dan satu
        // salary profile untuk satu periode payroll. Jangan pernah membagi
        // perhitungan berdasarkan jabatan lama dan baru di tengah periode.
        if ($recaps->count() !== 1) {
            return ['status' => false, 'error' => 'Payroll tidak dapat dihitung karena terdapat lebih dari satu Rekap Bulanan pada periode ini.'];
        }

        $recap = $recaps->first();
        if (! $recap->is_finalized) {
            return ['status' => false, 'error' => 'Rekap Bulanan belum difinalisasi oleh HCGA.'];
        }

        $payrollPeriod = PayrollPeriodResolver::forMonth($periodMonth);
        $start = Carbon::parse($payrollPeriod->start_date);
        $end = Carbon::parse($payrollPeriod->end_date);

        // Periode payroll berjalan dari tanggal 28 bulan sebelumnya sampai
        // tanggal 27 bulan berjalan. Profile ini adalah satu-satunya sumber
        // jabatan dan tarif payroll untuk seluruh periode tersebut.
        $periodProfile = $employee->currentSalaryProfile($start->toDateString());

        if (! $periodProfile) {
            return ['status' => false, 'error' => 'Salary profile yang berlaku pada awal periode payroll tidak ditemukan.'];
        }

        if (! $periodProfile->position_id) {
            return ['status' => false, 'error' => 'Jabatan pada salary profile periode payroll tidak ditemukan.'];
        }

        $periodPosition = Position::find($periodProfile->position_id);
        if (! $periodPosition) {
            return ['status' => false, 'error' => 'Data jabatan pada salary profile periode payroll tidak ditemukan.'];
        }

        $recapProfileId = $recap->salary_profile_id;
        if ($recapProfileId && (int) $recapProfileId !== (int) $periodProfile->id) {
            return ['status' => false, 'error' => 'Salary profile pada Rekap Bulanan tidak sesuai dengan profile yang berlaku pada awal periode payroll.'];
        }

        // Profile dan jabatan pada awal periode berlaku untuk seluruh payroll.
        $positionAllowance = $this->resolvePositionAllowance($periodProfile, $periodPosition);
        $baseSalary = $this->resolveBaseSalary($periodProfile, $periodPosition);

        if ($baseSalary['amount'] === null || $baseSalary['amount'] === '') {
            return ['status' => false, 'error' => 'Gaji pokok default kosong pada salary profile periode payroll.'];
        }

        $periodeDate = $start->toDateString();
        $existingQ = Payroll::where('employee_id', $employee->id)->where('periode', $periodeDate);
        if ($ignorePayrollId) {
            $existingQ->where('id', '!=', $ignorePayrollId);
        }
        $existing = $existingQ->first();
        if ($existing) {
            return ['status' => false, 'error' => 'Payroll sudah ada di periode ini.'];
        }

        return [
            'status' => true,
            'recap' => $recap,
            'profile' => [
                'position_allowance' => $positionAllowance,
                'base_salary_amount' => $baseSalary['amount'],
                'position_id' => $periodProfile->position_id,
                'position_name' => $periodPosition->name,
            ],
            'total_mandays' => $recap->total_mandays,
            'periodFrom' => $start->toDateString(),
            'periodTo' => $end->toDateString(),
            'periode' => $periodeDate,
        ];
    }

    public function runEngine(Employee $employee, string $periodMonth, ?int $ignorePayrollId = null): array
    {
        $prereq = $this->validatePrerequisites($employee, $periodMonth, $ignorePayrollId);
        if (! $prereq['status']) {
            return [
                'is_calculable' => false,
                'prerequisite_status' => false,
                'blocking_warnings' => [$prereq['error']],
                'non_blocking_warnings' => [],
            ];
        }

        $recap = $prereq['recap'];
        $profile = $prereq['profile'];
        $positionName = $profile['position_name'];

        $blocking_warnings = [];
        $non_blocking_warnings = [];

        $gaji_pokok = (float) $profile['base_salary_amount'] * (float) $recap->total_mandays;
        $accumulatedAllowances = [];

        $addAllowance = function (
            string $typeCode,
            int $typeId,
            string $typeName,
            float $amount,
            ?float $mandays,
            ?float $rate,
            array $detail,
        ) use (&$accumulatedAllowances) {
            if (! isset($accumulatedAllowances[$typeCode])) {
                $accumulatedAllowances[$typeCode] = [
                    'allowance_type_id' => $typeId,
                    'allowance_type' => $typeCode,
                    'allowance_label' => $typeName,
                    'amount' => 0,
                    'rate_amount' => $rate,
                    'mandays' => 0,
                    'calculation_detail' => [
                        ...$detail,
                        ...($rate !== null ? ['rate_amount' => $rate] : []),
                    ],
                ];
            } else {
                // accumulate numeric details
                foreach ($detail as $k => $v) {
                    if (is_numeric($v)) {
                        if (! isset($accumulatedAllowances[$typeCode]['calculation_detail'][$k])) {
                            $accumulatedAllowances[$typeCode]['calculation_detail'][$k] = 0;
                        }
                        $accumulatedAllowances[$typeCode]['calculation_detail'][$k] += $v;
                    }
                }
            }
            $accumulatedAllowances[$typeCode]['amount'] += $amount;
            if ($mandays !== null) {
                $accumulatedAllowances[$typeCode]['mandays'] += $mandays;
            }
        };

        // Tunjangan jabatan adalah nominal bulanan pada salary profile periode ini.
        $positionAllowance = (float) $profile['position_allowance'];
        $positionAllowanceType = AllowanceType::where('code', 'position')->first();
        if ($positionAllowanceType && $positionAllowance > 0) {
            $addAllowance(
                $positionAllowanceType->code,
                $positionAllowanceType->id,
                $positionAllowanceType->name,
                $positionAllowance,
                null,
                $positionAllowance,
                [],
            );
        }

        $calculatedAllowances = $this->allowanceCalculator->calculate(
            $employee,
            $recap,
            $profile['position_id'],
        );

        foreach ($calculatedAllowances as $calculated) {
            $type = $calculated['type'];
            $rate = $calculated['rate'];
            $mandays = $type->calculation_type !== 'per_toddler'
                && in_array($type->input_source, ['total_mandays', 'training_days', 'out_of_town_days', 'wfo_days', 'wfh_days'], true)
                ? $calculated['units']
                : null;

            $addAllowance(
                $type->code,
                $type->id,
                $type->name,
                $calculated['amount'],
                $mandays,
                $rate->rate_amount !== null ? (float) $rate->rate_amount : null,
                $calculated['detail'],
            );
        }

        $allowances = array_values($accumulatedAllowances);
        $total_allowances = 0;

        foreach ($allowances as $al) {
            $total_allowances += $al['amount'];
        }

        $total_deductions = 0;
        $deductions_list = [];

        // Fetch Special Deductions
        $specialDeductions = \App\Models\SpecialDeduction::with('deductionType')
            ->where('employee_id', $employee->id)
            ->where('period_month', $periodMonth)
            ->get();

        foreach ($specialDeductions as $sd) {
            $sdAmount = (float) ($sd->amount ?? 0);
            $total_deductions += $sdAmount;
            $deductions_list[] = [
                'deduction_type' => $sd->type,
                'deduction_type_id' => $sd->deduction_type_id,
                'deduction_label' => $sd->deductionType?->name ?: ($sd->description ?: ucfirst($sd->type)),
                'amount' => $sdAmount,
                'calculation_detail' => ['special_deduction_id' => $sd->id],
            ];
        }

        // Auto Late Penalty Deduction memakai jabatan yang aktif pada awal periode.
        $totalLateCount = (int) ($recap->late_count ?? 0);
        $position = Position::find($profile['position_id']);
        $penaltyPerCount = (float) ($position?->default_late_penalty_amount ?? 0);
        $totalLatePenalty = $totalLateCount * $penaltyPerCount;

        if ($totalLatePenalty > 0) {
            $total_deductions += $totalLatePenalty;
            $deductions_list[] = [
                'deduction_type' => 'late_penalty',
                'deduction_label' => "Potongan Keterlambatan ({$totalLateCount} kali)",
                'amount' => $totalLatePenalty,
                'calculation_detail' => [
                    'late_count' => $totalLateCount,
                ],
            ];
        }

        $total_nett = $gaji_pokok + $total_allowances - $total_deductions;

        $recapsSummary = [[
            'wfo_days' => $recap->wfo_days ?? 0,
            'wfh_days' => $recap->wfh_days ?? 0,
            'out_of_town_days' => $recap->out_of_town_days ?? 0,
            'training_days' => $recap->training_days ?? 0,
            'total_mandays' => $recap->total_mandays ?? 0,
            'late_count' => $recap->late_count ?? 0,
            'business_trip_count' => $recap->business_trip_count ?? 0,
        ]];

        return [
            'is_calculable' => count($blocking_warnings) === 0,
            'prerequisite_status' => true,
            'blocking_warnings' => $blocking_warnings,
            'non_blocking_warnings' => $non_blocking_warnings,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'position_name' => $positionName ?: 'Belum ditentukan',
            'period_month' => $periodMonth,
            'period_from' => $prereq['periodFrom'],
            'period_to' => $prereq['periodTo'],
            'periode' => $prereq['periode'],
            'gaji_pokok' => $gaji_pokok,
            'allowances' => $allowances,
            'deductions' => $deductions_list,
            'total_allowances' => $total_allowances,
            'total_deductions' => $total_deductions,
            'total_nett' => $total_nett,
            'total_mandays' => $prereq['total_mandays'],
            'recaps' => $recapsSummary,
            'calculation_mode' => 'auto',
            'message' => 'PPh 21 dan BPJS belum dihitung.',
        ];
    }

    public function calculatePreview(int $employeeId, string $periodMonth, ?int $ignorePayrollId = null): array
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            return ['is_calculable' => false, 'prerequisite_status' => false, 'blocking_warnings' => ['Employee not found']];
        }

        return $this->runEngine($employee, $periodMonth, $ignorePayrollId);
    }

    public function calculateAndSave(int $employeeId, string $periodMonth, int $recordedBy): Payroll
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            throw new \Exception('Employee not found');
        }

        $res = $this->runEngine($employee, $periodMonth);
        if (! $res['is_calculable']) {
            throw new \Exception('Cannot calculate: '.implode(', ', $res['blocking_warnings']));
        }

        $t0Encrypt = hrtime(true);
        $encryptedPayroll = $this->encryptedPayrollAttributes($res);
        $encryptMs = (hrtime(true) - $t0Encrypt) / 1e6;
        DB::beginTransaction();
        try {
            $payroll = Payroll::create([
                'user_id' => $recordedBy,
                'employee_id' => $employee->id,
                'periode' => $res['periode'],
                'period_from' => $res['period_from'],
                'period_to' => $res['period_to'],
                'status' => 'draft',
                'calculation_mode' => 'auto',
                'calculated_at' => now(),
                ...$encryptedPayroll,
            ]);

            $this->createAllowanceRows($payroll, $res['allowances']);
            $this->createDeductionRows($payroll, $res['deductions'] ?? []);

            DB::commit();

            $this->logCreatePerformance($payroll, $encryptMs);

            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function batchGenerate(string $periodMonth, int $recordedBy): array
    {
        $employees = Employee::where('status', 'active')
            ->whereHas('monthlyRecaps', function (Builder $q) use ($periodMonth) {
                $q->where('period_month', $periodMonth)
                    ->where('is_finalized', true);
            })
            ->get();
        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($employees as $employee) {
            DB::beginTransaction();
            try {
                $prereq = $this->validatePrerequisites($employee, $periodMonth);
                if (! $prereq['status']) {
                    throw new \Exception($prereq['error']);
                }

                $res = $this->runEngine($employee, $periodMonth);
                if (! $res['is_calculable']) {
                    throw new \Exception(implode(', ', $res['blocking_warnings']));
                }

                $t0Encrypt = hrtime(true);
                $encryptedPayroll = $this->encryptedPayrollAttributes($res);
                $encryptMs = (hrtime(true) - $t0Encrypt) / 1e6;
                $payroll = Payroll::create([
                    'user_id' => $recordedBy,
                    'employee_id' => $employee->id,
                    'periode' => $res['periode'],
                    'period_from' => $res['period_from'],
                    'period_to' => $res['period_to'],
                    'status' => 'draft',
                    'calculation_mode' => 'auto',
                    'calculated_at' => now(),
                    ...$encryptedPayroll,
                ]);

                $this->createAllowanceRows($payroll, $res['allowances']);
                $this->createDeductionRows($payroll, $res['deductions'] ?? []);

                DB::commit();
                $this->logCreatePerformance($payroll, $encryptMs);
                $success++;
                $results[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'status' => 'success',
                    'payroll_id' => $payroll->id,
                    'total_mandays' => $prereq['total_mandays'] ?? 0,
                    'gaji_pokok' => $res['gaji_pokok'] ?? 0,
                    'total_allowances' => $res['total_allowances'] ?? 0,
                    'total_deductions' => $res['total_deductions'] ?? 0,
                    'total_nett' => $res['total_nett'] ?? 0,
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                $results[] = ['employee_id' => $employee->id, 'employee_name' => $employee->name, 'status' => 'failed', 'errors' => [$e->getMessage()]];
            }
        }

        return [
            'period_month' => $periodMonth,
            'total_employees' => count($employees),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    public function batchPreview(string $periodMonth): array
    {
        $employees = Employee::where('status', 'active')
            ->whereHas('monthlyRecaps', function (Builder $q) use ($periodMonth) {
                $q->where('period_month', $periodMonth)
                    ->where('is_finalized', true);
            })
            ->get();
        $payrollPeriod = PayrollPeriodResolver::forMonth($periodMonth);
        $periodeDate = Carbon::parse($payrollPeriod->start_date)->toDateString();
        $payrolls = Payroll::whereDate('periode', $periodeDate)->get()->keyBy('employee_id');

        $results = [];
        $success = 0;
        $failed = 0;
        $generated = 0;

        $total_employees = count($employees);
        $total_gaji_pokok = 0;
        $total_allowances = 0;
        $total_deductions = 0;
        $total_nett = 0;

        foreach ($employees as $employee) {
            $existing = $payrolls->get($employee->id);

            if ($existing) {
                try {
                    // Already generated
                    $alg = strtoupper($existing->salary_alg ?? 'AES');
                    if ($alg === 'HYBRID') {
                        $dec = CryptoService::decryptHybridPayrollRow([
                            'dek_enc' => $existing->dek_enc,
                            'enc_meta' => $existing->enc_meta,
                            'gaji_pokok_enc' => $existing->gaji_pokok_enc,
                            'tunjangan_enc' => $existing->tunjangan_enc,
                            'potongan_enc' => $existing->potongan_enc,
                            'total_enc' => $existing->total_enc,
                        ]);
                        $gaji_pokok = $dec['gaji_pokok'] ?? 0;
                        $tunjangan = $dec['tunjangan'] ?? 0;
                        $potongan = $dec['potongan'] ?? 0;
                        $nett = $dec['total'] ?? 0;
                    } else {
                        $gaji_pokok = CryptoService::readEncryptedOrPlainSafe($existing->gaji_pokok_enc, $existing->gaji_pokok, $alg);
                        $tunjangan = CryptoService::readEncryptedOrPlainSafe($existing->tunjangan_enc, $existing->tunjangan, $alg);
                        $potongan = CryptoService::readEncryptedOrPlainSafe($existing->potongan_enc, $existing->potongan, $alg);
                        $nett = CryptoService::readEncryptedOrPlainSafe($existing->total_enc, $existing->total, $alg);
                    }

                    $gaji_pokok = (float) $gaji_pokok;
                    $tunjangan = (float) $tunjangan;
                    $potongan = (float) $potongan;
                    $nett = (float) $nett;

                    $total_gaji_pokok += $gaji_pokok;
                    $total_allowances += $tunjangan;
                    $total_deductions += $potongan;
                    $total_nett += $nett;

                    $generated++;

                    $recap = DB::table('monthly_recaps')
                        ->where('employee_id', $employee->id)
                        ->where('period_month', $periodMonth)
                        ->first();

                    $results[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'bank_name' => $employee->bank_name,
                        'bank_account_number' => $employee->bank_account_number,
                        'status' => 'generated',
                        'payroll_id' => $existing->id,
                        'payroll_status' => $existing->status,
                        'rejection_reason' => $existing->status === 'rejected' ? $existing->approval_note : null,
                        'total_mandays' => $recap->total_mandays ?? 0,
                        'gaji_pokok' => $gaji_pokok,
                        'total_allowances' => $tunjangan,
                        'total_deductions' => $potongan,
                        'total_nett' => $nett,
                    ];
                } catch (\Throwable $e) {
                    // Gagal tertutup untuk record ini saja. Pesan teknis tidak
                    // boleh dikirim ke pengguna atau menghentikan payroll lain.
                    Log::warning('Payroll tidak dapat diverifikasi saat batch preview.', [
                        'payroll_id' => $existing->id,
                        'employee_id' => $employee->id,
                        'exception' => $e::class,
                    ]);

                    $failed++;
                    $results[] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                        'status' => 'unavailable',
                        'payroll_id' => $existing->id,
                        'payroll_status' => $existing->status,
                        'message' => 'Slip gaji sementara tidak dapat ditampilkan. Silakan hubungi administrator.',
                    ];
                }

                continue;
            }

            try {
                $prereq = $this->validatePrerequisites($employee, $periodMonth);
                if (! $prereq['status']) {
                    throw new \Exception($prereq['error']);
                }

                $res = $this->runEngine($employee, $periodMonth);
                if (! $res['is_calculable']) {
                    throw new \Exception(implode(', ', $res['blocking_warnings']));
                }

                $gaji_pokok = (float) ($res['gaji_pokok'] ?? 0);
                $tunjangan = (float) ($res['total_allowances'] ?? 0);
                $potongan = (float) ($res['total_deductions'] ?? 0);
                $nett = (float) ($res['total_nett'] ?? 0);

                $total_gaji_pokok += $gaji_pokok;
                $total_allowances += $tunjangan;
                $total_deductions += $potongan;
                $total_nett += $nett;

                $success++;
                $results[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number,
                    'status' => 'draft', // Simulated, not generated
                    'total_mandays' => $prereq['total_mandays'] ?? 0,
                    'gaji_pokok' => $gaji_pokok,
                    'total_allowances' => $tunjangan,
                    'total_deductions' => $potongan,
                    'total_nett' => $nett,
                ];
            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'status' => 'failed',
                    'errors' => [$e->getMessage()],
                    'gaji_pokok' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'total_nett' => 0,
                ];
            }
        }

        return [
            'period_month' => $periodMonth,
            'total_employees' => $total_employees,
            'success_count' => $success,
            'failed_count' => $failed,
            'generated_count' => $generated,
            'results' => $results,
            'summary' => [
                'total_gaji_pokok' => $total_gaji_pokok,
                'total_allowances' => $total_allowances,
                'total_deductions' => $total_deductions,
                'total_nett' => $total_nett,
            ],
        ];
    }

    public function recalculate(Payroll $payroll): Payroll
    {
        if (! in_array($payroll->status, ['draft', 'rejected'], true)) {
            throw new \Exception('Hanya payroll draft atau yang ditolak yang bisa direcalculate.');
        }
        if ($payroll->calculation_mode !== 'auto') {
            throw new \Exception('Hanya auto payroll yang bisa direcalculate.');
        }

        $employee = $payroll->employee;
        $pm = Carbon::parse($payroll->period_from)->format('Y-m');

        $res = $this->runEngine($employee, $pm, $payroll->id);
        if (! $res['is_calculable']) {
            throw new \Exception('Cannot recalculate: '.implode(', ', $res['blocking_warnings']));
        }

        DB::beginTransaction();
        try {
            $payroll->allowances()->delete();
            $payroll->deductions()->delete();
            $payroll->update([
                'calculated_at' => now(),
                ...$this->encryptedPayrollAttributes($res),
            ]);

            $this->createAllowanceRows($payroll, $res['allowances']);
            $this->createDeductionRows($payroll, $res['deductions'] ?? []);

            DB::commit();

            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function resolvePositionAllowance(SalaryProfile $profile, ?Position $Position): string
    {
        $positionAllowanceDecrypted = $profile->position_allowance;

        // Nilai 0 pada profil lama berarti belum ada nominal khusus. Dalam kondisi
        // tersebut, gunakan tarif tunjangan jabatan dari master jabatan.
        if ($positionAllowanceDecrypted === null || $positionAllowanceDecrypted === '' || (float) $positionAllowanceDecrypted <= 0) {
            $posRate = $Position
                ? $this->rateResolver->resolveByCode($Position->id, 'position')
                : null;

            return $posRate ? (string) $posRate->rate_amount : '0';
        }

        return (string) $positionAllowanceDecrypted;
    }

    private function resolveBaseSalary(SalaryProfile $profile, ?Position $Position): array
    {
        $amount = $profile->base_salary_amount;

        if ($amount === null || $amount === '') {
            if ($Position?->default_base_salary_amount !== null) {
                $amount = (string) $Position->default_base_salary_amount;
            }
        }

        return [
            'amount' => $amount,
        ];
    }

    private function encryptedPayrollAttributes(array $result): array
    {
        $cipher = $this->cipherService->encrypt([
            'gaji_pokok' => $result['gaji_pokok'],
            'tunjangan' => $result['total_allowances'],
            'potongan' => $result['total_deductions'],
            'total' => $result['total_nett'],
        ]);

        return $this->cipherAttributes($cipher);
    }

    private function cipherAttributes(array $cipher): array
    {
        return [
            ...$cipher['fields'],
            'dek_enc' => $cipher['dek_enc'],
            'enc_meta' => $cipher['enc_meta'],
            'salary_alg' => $cipher['alg'],
            'salary_key_id' => $cipher['key_id'],
        ];
    }

    private function logCreatePerformance(
        Payroll $payroll,
        float $encryptMs
    ): void {
        try {
            $ciphertexts = [
                $payroll->gaji_pokok_enc,
                $payroll->tunjangan_enc,
                $payroll->potongan_enc,
                $payroll->total_enc,
                $payroll->dek_enc,
                ...$payroll->allowances()->pluck('amount_enc')->all(),
                ...$payroll->deductions()->pluck('amount_enc')->all(),
            ];

            $cipherBytes = array_sum(array_map(
                static fn (?string $value): int => strlen((string) $value),
                $ciphertexts
            ));

            PerfLog::create([
                'scenario' => 'CREATE',
                'alg' => $payroll->salary_alg ?? 'AES',
                'payroll_id' => $payroll->id,
                'encrypt_ms' => round($encryptMs, 3),
                'decrypt_ms' => null,
                'cipher_bytes' => $cipherBytes,
            ]);
        } catch (\Throwable) {
            // Kegagalan pencatatan metrik tidak boleh menggagalkan payroll.
        }
    }

    private function createAllowanceRows(Payroll $payroll, array $allowances): void
    {
        foreach ($allowances as $allowance) {
            PayrollAllowance::create([
                'payroll_id' => $payroll->id,
                'allowance_type_id' => $allowance['allowance_type_id'],
                'mandays' => $allowance['mandays'],
                'calculation_detail' => $allowance['calculation_detail'],
                ...$this->sensitiveCipher->encryptAttributes([
                    'amount' => round($allowance['amount']),
                ]),
            ]);
        }
    }

    private function createDeductionRows(Payroll $payroll, array $deductions): void
    {
        foreach ($deductions as $deduction) {
            PayrollDeduction::create([
                'payroll_id' => $payroll->id,
                'deduction_type' => $deduction['deduction_type'],
                'deduction_label' => $deduction['deduction_label'],
                'calculation_detail' => $deduction['calculation_detail'],
                ...$this->sensitiveCipher->encryptAttributes([
                    'amount' => round($deduction['amount']),
                ]),
            ]);
        }
    }
}
