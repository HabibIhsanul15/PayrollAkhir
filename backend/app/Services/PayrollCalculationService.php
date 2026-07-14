<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    const ENGINE_VERSION = 'v2.0';

    public function __construct(
        private AllowanceCalculationService $allowanceCalculator,
        private AllowanceRateResolver $rateResolver,
        private PayrollCipherService $cipherService
    ) {}

    public function validatePrerequisites($employee, $periodMonth, $ignorePayrollId = null)
    {
        if ($employee->status !== 'active') {
            return ['status' => false, 'error' => 'Employee tidak aktif.'];
        }
        if (! $employee->position_id) {
            return ['status' => false, 'error' => 'Position ID kosong.'];
        }

        $recaps = MonthlyRecap::where('employee_id', $employee->id)->where('period_month', $periodMonth)->get();
        if ($recaps->isEmpty()) {
            return ['status' => false, 'error' => 'Rekap Bulanan (Monthly Recap) belum diinput.'];
        }

        foreach ($recaps as $recap) {
            if (! $recap->is_finalized) {
                return ['status' => false, 'error' => 'Ada Rekap Bulanan yang belum difinalisasi oleh HCGA.'];
            }
        }

        $payrollPeriod = \App\Models\PayrollPeriod::where('period_month', $periodMonth)->first();
        if ($payrollPeriod) {
            $start = Carbon::parse($payrollPeriod->start_date);
            $end = Carbon::parse($payrollPeriod->end_date);
        } else {
            // Fallback to calendar month
            $start = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        // Resolve profiles for each recap
        $profilesData = [];
        $fallbackProfile = $employee->currentSalaryProfile($start->toDateString());

        if (! $fallbackProfile && $recaps->contains(fn ($r) => ! $r->salary_profile_id)) {
            return ['status' => false, 'error' => 'Salary profile aktif tidak ditemukan untuk sebagian rekap.'];
        }

        $totalRecapsMandays = 0;

        foreach ($recaps as $recap) {
            $totalRecapsMandays += $recap->total_mandays;
            $profile = $recap->salary_profile_id ? \App\Models\SalaryProfile::find($recap->salary_profile_id) : $fallbackProfile;

            if (! $profile) {
                return ['status' => false, 'error' => 'Salary profile tidak ditemukan untuk rekap tertentu.'];
            }

            $activePositionId = $profile->position_id ?? $employee->position_id;
            $Position = $activePositionId ? \App\Models\Position::find($activePositionId) : null;

            // Profile decrypt and fallback
            $positionAllowanceDecrypted = $this->resolvePositionAllowance($profile, $Position, $start->toDateString());
            $baseSalary = $this->resolveBaseSalary($profile, $Position, $employee);

            if ($baseSalary['amount'] === null || $baseSalary['amount'] === '') {
                return ['status' => false, 'error' => 'Gaji pokok default kosong pada salah satu profile.'];
            }

            $profilesData[] = [
                'recap' => $recap,
                'profile' => [
                    'position_allowance' => $positionAllowanceDecrypted,
                    'base_salary_amount' => $baseSalary['amount'],
                    'base_salary_basis' => $baseSalary['basis'],
                    'position_id' => $activePositionId,
                    'effective_from' => $profile->effective_from->toDateString(),
                ],
            ];
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
            'profilesData' => $profilesData,
            'recaps' => $recaps,
            'total_mandays' => $totalRecapsMandays, // combined total
            'periodFrom' => $start->toDateString(),
            'periodTo' => $end->toDateString(),
            'periode' => $periodeDate,
        ];
    }

    public function runEngine($employee, $periodMonth, $ignorePayrollId = null)
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

        $profilesData = $prereq['profilesData'];
        // Use the last profile as the "primary" profile for single-value references
        // (like base position allowance rate for display, though we use prorata for math)
        $primaryProfileData = end($profilesData);
        $profile = $primaryProfileData['profile'];

        $blocking_warnings = [];
        $non_blocking_warnings = [];

        $gaji_pokok = 0;
        $totalPositionAllowance = 0;

        $gaji_pokok = 0;
        $base_salary_segments = [];
        $accumulatedAllowances = [];

        $addAllowance = function ($typeCode, $typeId, $typeName, $amount, $mandays, $rate, $detail, $positionName = null) use (&$accumulatedAllowances, $profilesData) {
            if (! isset($accumulatedAllowances[$typeCode])) {
                $accumulatedAllowances[$typeCode] = [
                    'allowance_type_id' => $typeId,
                    'allowance_type' => $typeCode,
                    'allowance_label' => $typeName,
                    'amount' => 0,
                    'rate_amount' => count($profilesData) > 1 ? null : $rate, // if prorated, rate is blended
                    'mandays' => 0,
                    'calculation_detail' => $detail,
                ];
                if (count($profilesData) > 1) {
                    $accumulatedAllowances[$typeCode]['calculation_detail']['is_prorated'] = true;
                    $accumulatedAllowances[$typeCode]['calculation_detail']['segments'] = [];
                }
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
            if (count($profilesData) > 1 && $amount > 0) {
                $accumulatedAllowances[$typeCode]['calculation_detail']['segments'][] = [
                    'Position' => $positionName,
                    'amount' => $amount,
                    'rate' => $rate,
                    'mandays' => $mandays,
                ];
            }
        };

        foreach ($profilesData as $pd) {
            $r = $pd['recap'];
            $p = $pd['profile'];
            $segPositionId = $p['position_id'];

            $segPositionName = 'Jabatan';
            if (isset($p['position_id'])) {
                $gr = \App\Models\Position::find($p['position_id']);
                if ($gr) {
                    $segPositionName = $gr->name;
                }
            }

            // 1. Basic Salary
            $ratio = $prereq['total_mandays'] > 0 ? ((float) $r->total_mandays / (float) $prereq['total_mandays']) : 0;
            $segBaseSalary = $p['base_salary_basis'] === 'monthly'
                ? (float) $p['base_salary_amount'] * $ratio
                : (float) $p['base_salary_amount'] * (float) $r->total_mandays;
            $gaji_pokok += $segBaseSalary;

            if (count($profilesData) > 1 && $segBaseSalary > 0) {
                $base_salary_segments[] = [
                    'Position' => $segPositionName,
                    'amount' => $segBaseSalary,
                    'mandays' => $r->total_mandays,
                ];
            }

            // 2. Position Allowance
            $segPosAllow = (float) $p['position_allowance'] * $ratio;
            $trPos = AllowanceType::where('code', 'position')->first();
            if ($trPos && $segPosAllow > 0) {
                $amt = $segPosAllow;
                if ($employee->is_on_probation) {
                    $amt = $amt * 0.5;
                }
                $addAllowance($trPos->code, $trPos->id, $trPos->name, $amt, null, $p['position_allowance'], ['is_on_probation' => $employee->is_on_probation], $segPositionName);
            }

            $rateDate = max($prereq['periodFrom'], $p['effective_from']);
            $calculatedAllowances = $this->allowanceCalculator->calculate(
                $employee,
                $r,
                $segPositionId,
                $rateDate,
                (float) $p['base_salary_amount'],
                $ratio
            );

            foreach ($calculatedAllowances as $calculated) {
                $type = $calculated['type'];
                $rate = $calculated['rate'];
                $mandays = in_array($type->input_source, ['total_mandays', 'training_days', 'out_of_town_days', 'wfo_days', 'wfh_days', 'overtime_hours'], true)
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
                    $segPositionName
                );
            }
        }

        $allowances = array_values($accumulatedAllowances);
        $total_allowances = 0;

        foreach ($allowances as $al) {
            $total_allowances += $al['amount'];
        }

        $total_deductions = 0;
        $deductions_list = [];

        // Fetch Special Deductions
        $specialDeductions = \App\Models\SpecialDeduction::where('employee_id', $employee->id)
            ->where('period_month', $periodMonth)
            ->get();

        foreach ($specialDeductions as $sd) {
            $sdAmount = (float) (CryptoService::readEncryptedOrPlainSafe($sd->amount_enc, $sd->amount, $sd->salary_alg ?? 'AES') ?? 0);
            $total_deductions += $sdAmount;
            $deductions_list[] = [
                'deduction_type' => $sd->type,
                'deduction_label' => $sd->description ?: ucfirst($sd->type),
                'amount' => $sdAmount,
                'calculation_detail' => ['special_deduction_id' => $sd->id],
            ];
        }

        $total_nett = $gaji_pokok + $total_allowances - $total_deductions;

        return [
            'is_calculable' => count($blocking_warnings) === 0,
            'prerequisite_status' => true,
            'blocking_warnings' => $blocking_warnings,
            'non_blocking_warnings' => $non_blocking_warnings,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'period_month' => $periodMonth,
            'period_from' => $prereq['periodFrom'],
            'period_to' => $prereq['periodTo'],
            'periode' => $prereq['periode'],
            'gaji_pokok' => $gaji_pokok,
            'base_salary_segments' => $base_salary_segments,
            'allowances' => $allowances,
            'deductions' => $deductions_list,
            'total_allowances' => $total_allowances,
            'total_deductions' => $total_deductions,
            'total_nett' => $total_nett,
            'calculation_mode' => 'auto',
            'engine_version' => self::ENGINE_VERSION,
            'message' => 'PPh 21 dan BPJS belum dihitung.',
        ];
    }

    public function calculatePreview($employeeId, $periodMonth, $ignorePayrollId = null)
    {
        $employee = Employee::with(['employmentType', 'workBasis'])->find($employeeId);
        if (! $employee) {
            return ['is_calculable' => false, 'prerequisite_status' => false, 'blocking_warnings' => ['Employee not found']];
        }

        return $this->runEngine($employee, $periodMonth, $ignorePayrollId);
    }

    public function calculateAndSave($employeeId, $periodMonth, $recordedBy)
    {
        $employee = Employee::with(['employmentType', 'workBasis'])->find($employeeId);
        if (! $employee) {
            throw new \Exception('Employee not found');
        }

        $res = $this->runEngine($employee, $periodMonth);
        if (! $res['is_calculable']) {
            throw new \Exception('Cannot calculate: '.implode(', ', $res['blocking_warnings']));
        }

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
                'engine_version' => $res['engine_version'],
                'calculated_at' => now(),
                ...$this->encryptedPayrollAttributes($res),
            ]);

            $this->createAllowanceRows($payroll, $res['allowances']);
            $this->createDeductionRows($payroll, $res['deductions'] ?? []);

            AuditLog::create([
                'user_id' => $recordedBy,
                'action' => 'PAYROLL_AUTO_CALCULATE',
                'payroll_id' => $payroll->id,
                'meta' => ['employee_id' => $employee->id, 'period_month' => $periodMonth, 'warnings' => $res['non_blocking_warnings']],
                'ip_address' => request()->ip(),
            ]);

            DB::commit();

            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function batchGenerate($periodMonth, $recordedBy)
    {
        $employees = Employee::where('status', 'active')->get();
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

                $payroll = Payroll::create([
                    'user_id' => $recordedBy,
                    'employee_id' => $employee->id,
                    'periode' => $res['periode'],
                    'period_from' => $res['period_from'],
                    'period_to' => $res['period_to'],
                    'status' => 'draft',
                    'calculation_mode' => 'auto',
                    'engine_version' => $res['engine_version'],
                    'calculated_at' => now(),
                    ...$this->encryptedPayrollAttributes($res),
                ]);

                $this->createAllowanceRows($payroll, $res['allowances']);
                $this->createDeductionRows($payroll, $res['deductions'] ?? []);

                DB::commit();
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

        AuditLog::create([
            'user_id' => $recordedBy,
            'action' => 'PAYROLL_BATCH_GENERATE',
            'payroll_id' => null,
            'meta' => ['period_month' => $periodMonth, 'total_employees' => count($employees), 'success' => $success, 'failed' => $failed],
            'ip_address' => request()->ip(),
        ]);

        return [
            'period_month' => $periodMonth,
            'total_employees' => count($employees),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    public function batchPreview($periodMonth)
    {
        $employees = Employee::where('status', 'active')->get();
        $payrolls = Payroll::whereDate('periode', $periodMonth . '-01')->get()->keyBy('employee_id');

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
                        'catatan_enc' => $existing->catatan_enc,
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
                
                $recap = \Illuminate\Support\Facades\DB::table('monthly_recaps')
                    ->where('employee_id', $employee->id)
                    ->where('period_month', $periodMonth)
                    ->first();
                
                $results[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number_enc ? CryptoService::readEncryptedOrPlainSafe($employee->bank_account_number_enc, $employee->bank_account_number, $employee->pii_alg ?? 'AES') : $employee->bank_account_number,
                    'status' => 'generated',
                    'payroll_id' => $existing->id,
                    'payroll_status' => $existing->status,
                    'total_mandays' => $recap->total_mandays ?? 0,
                    'gaji_pokok' => $gaji_pokok,
                    'total_allowances' => $tunjangan,
                    'total_deductions' => $potongan,
                    'total_nett' => $nett,
                ];
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

                $gaji_pokok = (float)($res['gaji_pokok'] ?? 0);
                $tunjangan = (float)($res['total_allowances'] ?? 0);
                $potongan = (float)($res['total_deductions'] ?? 0);
                $nett = (float)($res['total_nett'] ?? 0);

                $total_gaji_pokok += $gaji_pokok;
                $total_allowances += $tunjangan;
                $total_deductions += $potongan;
                $total_nett += $nett;

                $success++;
                $results[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number_enc ? CryptoService::readEncryptedOrPlainSafe($employee->bank_account_number_enc, $employee->bank_account_number, $employee->pii_alg ?? 'AES') : $employee->bank_account_number,
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

    public function recalculate(Payroll $payroll, $force, $recordedBy)
    {
        if ($payroll->status !== 'draft') {
            throw new \Exception('Hanya payroll draft yang bisa direcalculate.');
        }
        if ($payroll->calculation_mode !== 'auto') {
            throw new \Exception('Hanya auto payroll yang bisa direcalculate.');
        }

        $hasOverride = $payroll->allowances()->where('is_manual_override', true)->exists();
        if ($hasOverride && ! $force) {
            throw new \Exception('Terdapat manual override allowance. Recalculate ditolak tanpa force.');
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

            AuditLog::create([
                'user_id' => $recordedBy,
                'action' => 'PAYROLL_RECALCULATE',
                'payroll_id' => $payroll->id,
                'meta' => ['force' => $force, 'has_override_overwritten' => $hasOverride],
                'ip_address' => request()->ip(),
            ]);

            DB::commit();

            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function overrideAllowance(Payroll $payroll, PayrollAllowance $allowance, $amount, $reason, $recordedBy)
    {
        if ($payroll->status !== 'draft') {
            throw new \Exception('Hanya draft payroll yang dapat di override.');
        }
        if ($payroll->calculation_mode !== 'auto') {
            throw new \Exception('Hanya auto payroll yang dapat di override.');
        }
        if ($allowance->payroll_id !== $payroll->id) {
            throw new \Exception('Allowance tidak valid untuk payroll ini.');
        }

        DB::beginTransaction();
        try {
            $allowance->update([
                'amount' => null,
                'amount_enc' => CryptoService::encryptAESGCM((string) round($amount)),
                'salary_alg' => 'AES',
                'salary_key_id' => CryptoService::keyId(),
                'is_manual_override' => true,
                'condition_notes' => $reason,
            ]);

            $totalAllowances = $payroll->allowances()->get()->sum(function (PayrollAllowance $row) {
                return (float) (CryptoService::readEncryptedOrPlainSafe(
                    $row->amount_enc,
                    $row->amount,
                    $row->salary_alg ?? 'AES'
                ) ?? 0);
            });
            $plain = $this->cipherService->decrypt($payroll);
            $gaji = (float) ($plain['gaji_pokok'] ?? 0);
            $deductions = (float) ($plain['total_deductions'] ?? $plain['potongan'] ?? 0);
            $total = $gaji + $totalAllowances - $deductions;
            $cipher = $this->cipherService->encrypt([
                'gaji_pokok' => $gaji,
                'tunjangan' => $totalAllowances,
                'potongan' => $deductions,
                'total' => $total,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $deductions,
                'catatan' => (string) ($plain['catatan'] ?? ''),
            ]);

            $payroll->update($this->cipherAttributes($cipher));

            AuditLog::create([
                'user_id' => $recordedBy,
                'action' => 'PAYROLL_ALLOWANCE_OVERRIDE',
                'payroll_id' => $payroll->id,
                'meta' => ['allowance_id' => $allowance->id, 'new_amount' => $amount, 'reason' => $reason],
                'ip_address' => request()->ip(),
            ]);

            DB::commit();

            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function resolvePositionAllowance($profile, $Position, string $periodStart): string
    {
        $profileAlg = strtoupper((string) ($profile->salary_alg ?? 'AES'));
        $positionAllowanceDecrypted = $profile->position_allowance_enc
            ? CryptoService::decryptByAlg($profile->position_allowance_enc, $profileAlg)
            : null;

        if ($positionAllowanceDecrypted === null || $positionAllowanceDecrypted === '') {
            if ($profile->position_allowance > 0) {
                return (string) $profile->position_allowance;
            }

            $rateDate = max($periodStart, $profile->effective_from->toDateString());
            $posRate = $Position
                ? $this->rateResolver->resolveByCode($Position->id, 'position', $rateDate)
                : null;

            return $posRate ? (string) $posRate->rate_amount : '0';
        }

        return (string) $positionAllowanceDecrypted;
    }

    private function resolveBaseSalary($profile, $Position, Employee $employee): array
    {
        $profileAlg = strtoupper((string) ($profile->salary_alg ?? 'AES'));
        $amount = $profile->base_salary_amount_enc
            ? CryptoService::decryptByAlg($profile->base_salary_amount_enc, $profileAlg)
            : null;

        if ($amount === null || $amount === '') {
            if ($profile->base_salary_amount !== null && $profile->base_salary_amount !== '') {
                $amount = (string) $profile->base_salary_amount;
            } elseif ($profile->mandays_rate_enc) {
                $amount = CryptoService::decryptByAlg($profile->mandays_rate_enc, $profileAlg);
            } elseif ($profile->mandays_rate !== null && $profile->mandays_rate !== '') {
                $amount = (string) $profile->mandays_rate;
            } elseif ($Position?->default_base_salary_amount !== null) {
                $amount = (string) $Position->default_base_salary_amount;
            } elseif ($Position?->default_mandays_rate !== null) {
                $amount = (string) $Position->default_mandays_rate;
            }
        }

        $basis = $profile->base_salary_basis
            ?: $Position?->base_salary_basis
            ?: match ($employee->workBasis?->code) {
                'monthly' => 'monthly',
                default => 'daily',
            };

        return [
            'basis' => $basis ?: 'daily',
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
            'total_allowances' => $result['total_allowances'],
            'total_deductions' => $result['total_deductions'],
            'catatan' => '',
        ]);

        return $this->cipherAttributes($cipher);
    }

    private function cipherAttributes(array $cipher): array
    {
        return [
            'gaji_pokok' => null,
            'tunjangan' => null,
            'potongan' => null,
            'total' => null,
            'total_allowances' => null,
            'total_deductions' => null,
            'catatan' => null,
            ...$cipher['fields'],
            'dek_enc' => $cipher['dek_enc'],
            'enc_meta' => $cipher['enc_meta'],
            'salary_alg' => $cipher['alg'],
            'salary_key_id' => $cipher['key_id'],
        ];
    }

    private function createAllowanceRows(Payroll $payroll, array $allowances): void
    {
        foreach ($allowances as $allowance) {
            PayrollAllowance::create([
                'payroll_id' => $payroll->id,
                'allowance_type_id' => $allowance['allowance_type_id'],
                'rate_amount' => $allowance['rate_amount'],
                'mandays' => $allowance['mandays'],
                'amount' => null,
                'amount_enc' => CryptoService::encryptAESGCM((string) round($allowance['amount'])),
                'calculation_detail' => $allowance['calculation_detail'],
                'salary_alg' => 'AES',
                'salary_key_id' => CryptoService::keyId(),
            ]);
        }
    }

    private function createDeductionRows(Payroll $payroll, array $deductions): void
    {
        foreach ($deductions as $deduction) {
            \App\Models\PayrollDeduction::create([
                'payroll_id' => $payroll->id,
                'deduction_type' => $deduction['deduction_type'],
                'deduction_label' => $deduction['deduction_label'],
                'amount' => null,
                'amount_enc' => CryptoService::encryptAESGCM((string) round($deduction['amount'])),
                'calculation_detail' => $deduction['calculation_detail'],
                'salary_alg' => 'AES',
                'salary_key_id' => CryptoService::keyId(),
            ]);
        }
    }
}
