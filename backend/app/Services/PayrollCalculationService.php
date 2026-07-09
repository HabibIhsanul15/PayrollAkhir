<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Models\AllowanceType;
use App\Models\GradeAllowanceRate;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollCalculationService
{
    const ENGINE_VERSION = 'v2.0';

    public function validatePrerequisites($employee, $periodMonth, $ignorePayrollId = null)
    {
        if ($employee->status !== 'active') return ['status' => false, 'error' => 'Employee tidak aktif.'];
        if (!$employee->grade_id) return ['status' => false, 'error' => 'Grade ID kosong.'];
        if (!$employee->employment_type_id) return ['status' => false, 'error' => 'Employment Type kosong.'];

        $recaps = MonthlyRecap::where('employee_id', $employee->id)->where('period_month', $periodMonth)->get();
        if ($recaps->isEmpty()) return ['status' => false, 'error' => 'Rekap Bulanan (Monthly Recap) belum diinput.'];
        
        foreach ($recaps as $recap) {
            if (!$recap->is_finalized) return ['status' => false, 'error' => 'Ada Rekap Bulanan yang belum difinalisasi oleh HCGA.'];
        }

        // Period logic simplified: just first and last day of the month
        $start = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Resolve profiles for each recap
        $profilesData = [];
        $fallbackProfile = $employee->currentSalaryProfile($start->toDateString());
        
        if (!$fallbackProfile && $recaps->contains(fn($r) => !$r->salary_profile_id)) {
            return ['status' => false, 'error' => 'Salary profile aktif tidak ditemukan untuk sebagian rekap.'];
        }

        $totalRecapsMandays = 0;

        foreach ($recaps as $recap) {
            $totalRecapsMandays += $recap->total_mandays;
            $profile = $recap->salary_profile_id ? \App\Models\SalaryProfile::find($recap->salary_profile_id) : $fallbackProfile;
            
            if (!$profile) return ['status' => false, 'error' => 'Salary profile tidak ditemukan untuk rekap tertentu.'];

            $activeGradeId = $profile->grade_id ?? $employee->grade_id;
            $grade = $activeGradeId ? \App\Models\Grade::find($activeGradeId) : null;

            // Profile decrypt and fallback
            $positionAllowanceDecrypted = $profile->position_allowance_enc ? CryptoService::decryptAESGCM($profile->position_allowance_enc) : null;
            if ($positionAllowanceDecrypted === null || $positionAllowanceDecrypted === '') {
                if ($profile->position_allowance > 0) {
                    $positionAllowanceDecrypted = (string)$profile->position_allowance;
                } else {
                    $posRate = $grade ? \App\Models\GradeAllowanceRate::where('grade_id', $grade->id)
                        ->whereHas('allowanceType', function($q) { $q->where('code', 'position'); })
                        ->first() : null;
                    $positionAllowanceDecrypted = $posRate ? (string)$posRate->rate_amount : '0';
                }
            }

            $mandaysDecrypted = $profile->mandays_rate_enc ? CryptoService::decryptAESGCM($profile->mandays_rate_enc) : null;
            if ($mandaysDecrypted === null || $mandaysDecrypted === '') {
                if ($profile->mandays_rate > 0) {
                    $mandaysDecrypted = (string)$profile->mandays_rate;
                } else {
                    $mandaysDecrypted = $grade ? (string)$grade->default_mandays_rate : '0';
                }
            }
            
            if ($mandaysDecrypted === null || $mandaysDecrypted === '') {
                return ['status' => false, 'error' => 'Mandays rate kosong pada salah satu profile.'];
            }

            $profilesData[] = [
                'recap' => $recap,
                'profile' => [
                    'position_allowance' => $positionAllowanceDecrypted,
                    'mandays_rate' => $mandaysDecrypted,
                    'grade_id' => $activeGradeId
                ]
            ];
        }

        $periodeDate = $start->toDateString();
        $existingQ = Payroll::where('employee_id', $employee->id)->where('periode', $periodeDate);
        if ($ignorePayrollId) {
            $existingQ->where('id', '!=', $ignorePayrollId);
        }
        $existing = $existingQ->first();
        if ($existing) return ['status' => false, 'error' => 'Payroll sudah ada di periode ini.'];

        return [
            'status' => true,
            'profilesData' => $profilesData,
            'recaps' => $recaps,
            'total_mandays' => $totalRecapsMandays, // combined total
            'periodFrom' => $start->toDateString(),
            'periodTo' => $end->toDateString(),
            'periode' => $periodeDate
        ];
    }

    public function runEngine($employee, $periodMonth, $ignorePayrollId = null)
    {
        $prereq = $this->validatePrerequisites($employee, $periodMonth, $ignorePayrollId);
        if (!$prereq['status']) {
            return [
                'is_calculable' => false,
                'prerequisite_status' => false,
                'blocking_warnings' => [$prereq['error']],
                'non_blocking_warnings' => []
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
        
        $isProject = $employee->employmentType->code === 'project';
        $isFixRate = $employee->employmentType->code === 'fix_rate';
        
        $gaji_pokok = 0;
        $accumulatedAllowances = [];

        $addAllowance = function($typeCode, $typeId, $amount, $mandays, $rate, $detail, $gradeName = null) use (&$accumulatedAllowances, $profilesData) {
            if (!isset($accumulatedAllowances[$typeCode])) {
                $accumulatedAllowances[$typeCode] = [
                    'allowance_type_id' => $typeId,
                    'allowance_type' => $typeCode,
                    'amount' => 0,
                    'rate_amount' => count($profilesData) > 1 ? null : $rate, // if prorated, rate is blended
                    'mandays' => 0,
                    'calculation_detail' => $detail
                ];
                if (count($profilesData) > 1) {
                    $accumulatedAllowances[$typeCode]['calculation_detail']['is_prorated'] = true;
                    $accumulatedAllowances[$typeCode]['calculation_detail']['segments'] = [];
                }
            } else {
                // accumulate numeric details
                foreach ($detail as $k => $v) {
                    if (is_numeric($v)) {
                        if (!isset($accumulatedAllowances[$typeCode]['calculation_detail'][$k])) {
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
                    'grade' => $gradeName,
                    'amount' => $amount,
                    'rate' => $rate,
                    'mandays' => $mandays
                ];
            }
        };

        foreach ($profilesData as $pd) {
            $r = $pd['recap'];
            $p = $pd['profile'];
            $segGradeId = $p['grade_id'];
            
            $segGradeName = 'Jabatan';
            if (isset($p['grade_id'])) {
                $gr = \App\Models\Grade::find($p['grade_id']);
                if ($gr) $segGradeName = $gr->name;
            }
            
            // 1. Basic Salary
            $gaji_pokok += (float)$p['mandays_rate'] * (float)$r->total_mandays;
            
            // 2. Position Allowance
            $ratio = $prereq['total_mandays'] > 0 ? ((float)$r->total_mandays / (float)$prereq['total_mandays']) : 0;
            $segPosAllow = (float)$p['position_allowance'] * $ratio;
            $trPos = AllowanceType::where('code', 'position')->first();
            if ($trPos && $segPosAllow > 0) {
                $amt = $segPosAllow;
                if ($employee->is_on_probation) {
                    $amt = $amt * 0.5;
                }
                $addAllowance($trPos->code, $trPos->id, $amt, null, $p['position_allowance'], ['is_on_probation' => $employee->is_on_probation], $segGradeName);
            }

            $getSegRate = function($typeCode) use ($segGradeId) {
                $type = AllowanceType::where('code', $typeCode)->first();
                if (!$type) return null;
                $rate = GradeAllowanceRate::where('grade_id', $segGradeId)
                    ->where('allowance_type_id', $type->id)
                    ->first();
                return [
                    'type_id' => $type->id,
                    'type_code' => $type->code,
                    'rate' => $rate ? (float)$rate->rate_amount : null
                ];
            };

            // 3. Transport trip
            $trTrip = $getSegRate('transport_trip');
            if ($trTrip && $trTrip['rate'] !== null && $r->business_trips > 0) {
                $amt = $trTrip['rate'] * $r->business_trips;
                $addAllowance($trTrip['type_code'], $trTrip['type_id'], $amt, null, $trTrip['rate'], ['num_trips' => $r->business_trips], $segGradeName);
            }

            // 4. Meal
            $trMeal = $getSegRate('meal');
            if ($trMeal && $trMeal['rate'] !== null && $r->total_mandays > 0) {
                $amt = $trMeal['rate'] * $r->total_mandays;
                $addAllowance($trMeal['type_code'], $trMeal['type_id'], $amt, $r->total_mandays, $trMeal['rate'], ['total_mandays' => $r->total_mandays], $segGradeName);
            }

            // 5. Childcare
            if ($employee->num_toddlers >= 3) {
                $trChild = $getSegRate('childcare');
                if ($trChild && $trChild['rate'] !== null) {
                    $amt = $trChild['rate'] * $ratio; // prorate childcare by days
                    $addAllowance($trChild['type_code'], $trChild['type_id'], $amt, null, $trChild['rate'], ['num_toddlers' => $employee->num_toddlers], $segGradeName);
                } else if ($trChild && $trChild['rate'] === null) {
                    $non_blocking_warnings[] = 'Childcare allowance rate kosong padahal num_toddlers >= 3.';
                }
            }

            // 6. Training
            if ($employee->is_trainer && $r->training_days > 0) {
                $trTrain = AllowanceType::where('code', 'training')->first();
                $amt = (float)$p['mandays_rate'] * 1.5 * $r->training_days;
                $addAllowance($trTrain->code, $trTrain->id, $amt, $r->training_days, null, ['multiplier' => 1.5, 'mandays_rate' => $p['mandays_rate']], $segGradeName);
            }

            // 7. Business Trip
            $trBTrip = $getSegRate('business_trip');
            if ($trBTrip && $trBTrip['rate'] !== null && $r->out_of_town_days > 0) {
                $amt = $trBTrip['rate'] * $r->out_of_town_days;
                $addAllowance($trBTrip['type_code'], $trBTrip['type_id'], $amt, $r->out_of_town_days, $trBTrip['rate'], ['out_of_town_days' => $r->out_of_town_days], $segGradeName);
            }

            // 8. Transport (WFO)
            $trHO = $getSegRate('ho_transport_meal');
            if ($trHO && $trHO['rate'] !== null && $r->wfo_days > 0) {
                $amt = $trHO['rate'] * $r->wfo_days;
                $addAllowance($trHO['type_code'], $trHO['type_id'], $amt, $r->wfo_days, $trHO['rate'], ['wfo_days' => $r->wfo_days], $segGradeName);
            }

            // 9. Transport Insurance
            $trIns = $getSegRate('transport_insurance');
            if ($trIns && $trIns['rate'] !== null && $r->wfo_days > 0) {
                $amt = $trIns['rate'] * $r->wfo_days;
                $addAllowance($trIns['type_code'], $trIns['type_id'], $amt, $r->wfo_days, $trIns['rate'], ['wfo_days' => $r->wfo_days], $segGradeName);
            }
        }

        $allowances = array_values($accumulatedAllowances);
        $total_allowances = 0;

        foreach ($allowances as $al) {
            $total_allowances += $al['amount'];
        }

        $total_deductions = 0;
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
            'allowances' => $allowances,
            'total_allowances' => $total_allowances,
            'total_deductions' => $total_deductions,
            'total_nett' => $total_nett,
            'calculation_mode' => 'auto',
            'engine_version' => self::ENGINE_VERSION,
            'message' => 'PPh 21 dan BPJS belum dihitung.'
        ];
    }

    public function calculatePreview($employeeId, $periodMonth)
    {
        $employee = Employee::with(['employmentType', 'workBasis'])->find($employeeId);
        if (!$employee) return ['is_calculable' => false, 'prerequisite_status' => false, 'blocking_warnings' => ['Employee not found']];
        return $this->runEngine($employee, $periodMonth);
    }

    public function calculateAndSave($employeeId, $periodMonth, $recordedBy)
    {
        $employee = Employee::with(['employmentType', 'workBasis'])->find($employeeId);
        if (!$employee) throw new \Exception("Employee not found");
        
        $res = $this->runEngine($employee, $periodMonth);
        if (!$res['is_calculable']) {
            throw new \Exception("Cannot calculate: " . implode(', ', $res['blocking_warnings']));
        }

        DB::beginTransaction();
        try {
            // Generate encryption string formats
            $gpEnc = CryptoService::encryptAESGCM((string)round($res['gaji_pokok']));
            $alEnc = CryptoService::encryptAESGCM((string)round($res['total_allowances']));
            $dedEnc = CryptoService::encryptAESGCM((string)round($res['total_deductions']));
            $totEnc = CryptoService::encryptAESGCM((string)round($res['total_nett']));

            $payroll = Payroll::create([
                'user_id' => $recordedBy,
                'employee_id' => $employee->id,
                'periode' => $res['periode'],
                'period_from' => $res['period_from'],
                'period_to' => $res['period_to'],
                'status' => 'draft',
                'gaji_pokok' => $res['gaji_pokok'],
                'tunjangan' => $res['total_allowances'],
                'potongan' => $res['total_deductions'],
                'total' => $res['total_nett'],
                'gaji_pokok_enc' => $gpEnc,
                'tunjangan_enc' => $alEnc,
                'potongan_enc' => $dedEnc,
                'total_enc' => $totEnc,
                'total_allowances' => $res['total_allowances'],
                'total_deductions' => $res['total_deductions'],
                'total_allowances_enc' => $alEnc,
                'total_deductions_enc' => $dedEnc,
                'calculation_mode' => 'auto',
                'engine_version' => $res['engine_version'],
                'salary_alg' => 'AES'
            ]);

            foreach ($res['allowances'] as $al) {
                PayrollAllowance::create([
                    'payroll_id' => $payroll->id,
                    'allowance_type_id' => $al['allowance_type_id'],
                    'rate_amount' => $al['rate_amount'],
                    'mandays' => $al['mandays'],
                    'amount' => $al['amount'],
                    'amount_enc' => CryptoService::encryptAESGCM((string)round($al['amount'])),
                    'calculation_detail' => $al['calculation_detail'],
                    'salary_alg' => 'AES'
                ]);
            }

            AuditLog::create([
                'user_id' => $recordedBy,
                'action' => 'PAYROLL_AUTO_CALCULATE',
                'payroll_id' => $payroll->id,
                'meta' => ['employee_id' => $employee->id, 'period_month' => $periodMonth, 'warnings' => $res['non_blocking_warnings']],
                'ip_address' => request()->ip()
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
                if (!$prereq['status']) {
                    throw new \Exception($prereq['error']);
                }
                
                $res = $this->runEngine($employee, $periodMonth);
                if (!$res['is_calculable']) {
                    throw new \Exception(implode(', ', $res['blocking_warnings']));
                }

                $gpEnc = CryptoService::encryptAESGCM((string)round($res['gaji_pokok']));
                $alEnc = CryptoService::encryptAESGCM((string)round($res['total_allowances']));
                $dedEnc = CryptoService::encryptAESGCM((string)round($res['total_deductions']));
                $totEnc = CryptoService::encryptAESGCM((string)round($res['total_nett']));

                $payroll = Payroll::create([
                    'user_id' => $recordedBy,
                    'employee_id' => $employee->id,
                    'periode' => $res['periode'],
                    'period_from' => $res['period_from'],
                    'period_to' => $res['period_to'],
                    'status' => 'draft',
                    'gaji_pokok' => $res['gaji_pokok'],
                    'tunjangan' => $res['total_allowances'],
                    'potongan' => $res['total_deductions'],
                    'total' => $res['total_nett'],
                    'gaji_pokok_enc' => $gpEnc,
                    'tunjangan_enc' => $alEnc,
                    'potongan_enc' => $dedEnc,
                    'total_enc' => $totEnc,
                    'total_allowances' => $res['total_allowances'],
                    'total_deductions' => $res['total_deductions'],
                    'total_allowances_enc' => $alEnc,
                    'total_deductions_enc' => $dedEnc,
                    'calculation_mode' => 'auto',
                    'engine_version' => $res['engine_version'],
                    'salary_alg' => 'AES'
                ]);

                foreach ($res['allowances'] as $al) {
                    PayrollAllowance::create([
                        'payroll_id' => $payroll->id,
                        'allowance_type_id' => $al['allowance_type_id'],
                        'rate_amount' => $al['rate_amount'],
                        'mandays' => $al['mandays'],
                        'amount' => $al['amount'],
                        'amount_enc' => CryptoService::encryptAESGCM((string)round($al['amount'])),
                        'calculation_detail' => $al['calculation_detail'],
                        'salary_alg' => 'AES'
                    ]);
                }
                
                DB::commit();
                $success++;
                $results[] = ['employee_id' => $employee->id, 'status' => 'success', 'payroll_id' => $payroll->id];
            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                $results[] = ['employee_id' => $employee->id, 'status' => 'failed', 'errors' => [$e->getMessage()]];
            }
        }

        AuditLog::create([
            'user_id' => $recordedBy,
            'action' => 'PAYROLL_BATCH_GENERATE',
            'payroll_id' => null,
            'meta' => ['period_month' => $periodMonth, 'total_employees' => count($employees), 'success' => $success, 'failed' => $failed],
            'ip_address' => request()->ip()
        ]);

        return [
            'period_month' => $periodMonth,
            'total_employees' => count($employees),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results
        ];
    }

    public function recalculate(Payroll $payroll, $force, $recordedBy)
    {
        if ($payroll->status !== 'draft') throw new \Exception("Hanya payroll draft yang bisa direcalculate.");
        if ($payroll->calculation_mode !== 'auto') throw new \Exception("Hanya auto payroll yang bisa direcalculate.");

        $hasOverride = $payroll->allowances()->where('is_manual_override', true)->exists();
        if ($hasOverride && !$force) {
            throw new \Exception("Terdapat manual override allowance. Recalculate ditolak tanpa force.");
        }

        $employee = $payroll->employee;
        $pm = Carbon::parse($payroll->period_from)->format('Y-m');
        
        $res = $this->runEngine($employee, $pm, $payroll->id);
        if (!$res['is_calculable']) throw new \Exception("Cannot recalculate: " . implode(', ', $res['blocking_warnings']));

        DB::beginTransaction();
        try {
            $payroll->allowances()->delete();
            $gpEnc = CryptoService::encryptAESGCM((string)round($res['gaji_pokok']));
            $alEnc = CryptoService::encryptAESGCM((string)round($res['total_allowances']));
            $dedEnc = CryptoService::encryptAESGCM((string)round($res['total_deductions']));
            $totEnc = CryptoService::encryptAESGCM((string)round($res['total_nett']));

            $payroll->update([
                'gaji_pokok' => $res['gaji_pokok'],
                'tunjangan' => $res['total_allowances'],
                'potongan' => $res['total_deductions'],
                'total' => $res['total_nett'],
                'gaji_pokok_enc' => $gpEnc,
                'tunjangan_enc' => $alEnc,
                'potongan_enc' => $dedEnc,
                'total_enc' => $totEnc,
                'total_allowances' => $res['total_allowances'],
                'total_deductions' => $res['total_deductions'],
                'total_allowances_enc' => $alEnc,
                'total_deductions_enc' => $dedEnc,
            ]);

            foreach ($res['allowances'] as $al) {
                PayrollAllowance::create([
                    'payroll_id' => $payroll->id,
                    'allowance_type_id' => $al['allowance_type_id'],
                    'rate_amount' => $al['rate_amount'],
                    'mandays' => $al['mandays'],
                    'amount' => $al['amount'],
                    'amount_enc' => CryptoService::encryptAESGCM((string)round($al['amount'])),
                    'calculation_detail' => $al['calculation_detail'],
                    'salary_alg' => 'AES'
                ]);
            }

            AuditLog::create([
                'user_id' => $recordedBy,
                'action' => 'PAYROLL_RECALCULATE',
                'payroll_id' => $payroll->id,
                'meta' => ['force' => $force, 'has_override_overwritten' => $hasOverride],
                'ip_address' => request()->ip()
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
        if ($payroll->status !== 'draft') throw new \Exception("Hanya draft payroll yang dapat di override.");
        if ($payroll->calculation_mode !== 'auto') throw new \Exception("Hanya auto payroll yang dapat di override.");
        if ($allowance->payroll_id !== $payroll->id) throw new \Exception("Allowance tidak valid untuk payroll ini.");

        DB::beginTransaction();
        try {
            $allowance->update([
                'amount' => $amount,
                'amount_enc' => CryptoService::encryptAESGCM((string)round($amount)),
                'is_manual_override' => true,
                'condition_notes' => $reason
            ]);

            $totalAllowances = $payroll->allowances()->sum('amount');
            $alEnc = CryptoService::encryptAESGCM((string)round($totalAllowances));
            $tot = $payroll->gaji_pokok + $totalAllowances - $payroll->total_deductions;
            $totEnc = CryptoService::encryptAESGCM((string)round($tot));

            $payroll->update([
                'tunjangan' => $totalAllowances,
                'total_allowances' => $totalAllowances,
                'total' => $tot,
                'tunjangan_enc' => $alEnc,
                'total_allowances_enc' => $alEnc,
                'total_enc' => $totEnc
            ]);

            AuditLog::create([
                'user_id' => $recordedBy,
                'action' => 'PAYROLL_ALLOWANCE_OVERRIDE',
                'payroll_id' => $payroll->id,
                'meta' => ['allowance_id' => $allowance->id, 'new_amount' => $amount, 'reason' => $reason],
                'ip_address' => request()->ip()
            ]);

            DB::commit();
            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
