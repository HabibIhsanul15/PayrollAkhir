<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\EmploymentType;
use App\Models\WorkBasis;
use App\Models\AllowanceType;
use App\Models\GradeAllowanceRate;
use App\Models\SalaryProfile;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\MonthlyMandaysSummary;
use App\Models\Payroll;
use App\Models\PayrollAllowance;
use App\Models\AuditLog;
use App\Services\CryptoService;

// Setup tokens
$fat = User::where('role', 'fat')->first() ?? User::first();
$dir = User::where('role', 'director')->first() ?? User::first();
$fatToken = $fat->createToken('test')->plainTextToken;
$dirToken = $dir->createToken('test')->plainTextToken;
$baseUrl = 'http://127.0.0.1:8004/api';

echo "1. Migrate Status\n";
echo trim(shell_exec('php artisan migrate:status | findstr 2026_06_06_122002_add_engine_version_to_payrolls_table')) . "\n";

echo "\n2. Route List\n";
echo trim(shell_exec('php artisan route:list | findstr payrolls/auto')) . "\n";

// Prep data for testing
$periodMonth = '2026-06';
$periodeDate = '2026-06-01';

// Cleanup old
Payroll::where('periode', $periodeDate)->delete();
Employee::where('name', 'LIKE', 'Test Phase 4%')->delete();
MonthlyMandaysSummary::where('period_month', $periodMonth)->delete();
AuditLog::where('action', 'LIKE', 'PAYROLL_%')->delete();
// We also need to clean up assignments and profiles linked to these test employees
// Note: delete cascade might handle some, but to be sure:
ProjectAssignment::where('period_month', $periodMonth)->delete();

// Setup employee 1 (Project + Mandays, num_toddlers=3, is_trainer=true)
$grade1 = Grade::firstOrCreate(['code' => 'G1'], ['name' => 'Grade 1', 'level' => 1]);
$empTypeProj = EmploymentType::firstOrCreate(['code' => 'project'], ['name' => 'Project']);
$wbMandays = WorkBasis::firstOrCreate(['code' => 'mandays'], ['name' => 'Mandays']);

$emp1 = Employee::create([
    'user_id' => null,
    'employee_code' => 'EMP4-01',
    'name' => 'Test Phase 4 A',
    'department' => 'IT',
    'status' => 'active',
    'grade_id' => $grade1->id,
    'employment_type_id' => $empTypeProj->id,
    'work_basis_id' => $wbMandays->id,
    'num_toddlers' => 3,
    'is_trainer' => true,
    'is_on_probation' => false
]);

// Salary Profile
$sal1 = SalaryProfile::create([
    'employee_id' => $emp1->id,
    'effective_from' => '2020-01-01',
    'base_salary' => 0,
    'base_salary_enc' => CryptoService::encryptAESGCM('0'),
    'mandays_rate' => 100000,
    'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
    'salary_alg' => 'AES'
]);

// Projects & Assignment
$projMealFalse = Project::firstOrCreate(['code' => 'PRJ-M-F'], ['name' => 'PRJ F', 'status' => 'active', 'is_client_provide_meal' => false]);
$projMealTrue = Project::firstOrCreate(['code' => 'PRJ-M-T'], ['name' => 'PRJ T', 'status' => 'active', 'is_client_provide_meal' => true]);

ProjectAssignment::create(['employee_id' => $emp1->id, 'project_id' => $projMealFalse->id, 'period_month' => $periodMonth, 'mandays' => 10]);
ProjectAssignment::create(['employee_id' => $emp1->id, 'project_id' => $projMealTrue->id, 'period_month' => $periodMonth, 'mandays' => 10]);

// Summary is auto created by ProjectAssignmentObserver. Let's update it.
$sum1 = MonthlyMandaysSummary::where('employee_id', $emp1->id)->where('period_month', $periodMonth)->first();
if (!$sum1) {
    $sum1 = MonthlyMandaysSummary::create([
        'employee_id' => $emp1->id, 'period_month' => $periodMonth, 'period_from' => '2026-05-28', 'period_to' => '2026-06-27'
    ]);
}
$sum1->update([
    'mandays_project' => 20,
    'mandays_training' => 2,
    'total_mandays' => 22,
    'num_trips' => 5,
    'is_finalized' => true
]);

// Allowance Types
$types = ['transport_trip', 'meal', 'position', 'childcare', 'training', 'business_trip', 'ho_transport_meal', 'transport_insurance'];
foreach ($types as $tc) {
    AllowanceType::firstOrCreate(['code' => $tc], ['name' => ucfirst(str_replace('_', ' ', $tc))]);
}
function setRate($grade, $code, $rate) {
    $type = AllowanceType::where('code', $code)->first();
    GradeAllowanceRate::updateOrCreate(['grade_id' => $grade->id, 'allowance_type_id' => $type->id], ['rate_amount' => $rate, 'effective_from' => '2020-01-01']);
}
setRate($grade1, 'transport_trip', 10000);
setRate($grade1, 'meal', 20000); // Only applies to projMealFalse (10 mandays) => 200000
setRate($grade1, 'position', 500000);
setRate($grade1, 'childcare', 300000);
// training rate is skipped
// transport_insurance
setRate($grade1, 'transport_insurance', 5000); // mandays_project = 20 => 100000

echo "\n3. Prerequisite Failed Test\n";
// Unfinalize summary
$sum1->update(['is_finalized' => false]);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (Unfinalized): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$sum1->update(['is_finalized' => true]);

// Employee inactive
$emp1->update(['status' => 'inactive']);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (Inactive): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$emp1->update(['status' => 'active']);

// Grade kosong
$emp1->update(['grade_id' => null]);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (No Grade): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$emp1->update(['grade_id' => $grade1->id]);

// Employment type kosong
$emp1->update(['employment_type_id' => null]);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (No EmpType): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$emp1->update(['employment_type_id' => $empTypeProj->id]);

// Work basis kosong
$emp1->update(['work_basis_id' => null]);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (No WorkBasis): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$emp1->update(['work_basis_id' => $wbMandays->id]);

// Salary profile kosong
$prof1 = SalaryProfile::where('employee_id', $emp1->id)->first();
$prof1->delete();
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (No Profile): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$prof1 = SalaryProfile::create(['employee_id' => $emp1->id, 'base_salary' => 0, 'base_salary_enc' => \App\Services\CryptoService::encryptAESGCM('0'), 'mandays_rate' => 100000, 'mandays_rate_enc' => \App\Services\CryptoService::encryptAESGCM('100000'), 'is_active' => true]);

// Mandays_rate kosong untuk mandays
$prof1->update(['mandays_rate' => null, 'mandays_rate_enc' => null]);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (No MandaysRate): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$prof1->update(['mandays_rate' => 100000, 'mandays_rate_enc' => \App\Services\CryptoService::encryptAESGCM('100000')]);

// Summary tidak ada
$sum1->delete();
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (No Summary): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$sum1 = MonthlyMandaysSummary::create(['employee_id' => $emp1->id, 'period_month' => $periodMonth, 'period_from' => '2026-05-28', 'period_to' => '2026-06-27', 'is_finalized' => true, 'total_mandays' => 22, 'mandays_project' => 20]);

// Payroll sudah ada
$dummyP = Payroll::create(['user_id' => $fat->id, 'employee_id' => $emp1->id, 'periode' => '2026-06-01', 'status' => 'draft']);
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", ['employee_id' => $emp1->id, 'period_month' => $periodMonth]);
echo "Error (Duplicate): " . json_encode($resPre->json('blocking_warnings')) . "\n";
$dummyP->delete();

echo "\n4. Mismatch Assignment vs Summary Test\n";
$sum1->update(['mandays_project' => 99]); // mismatch!
$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", [
    'employee_id' => $emp1->id, 'period_month' => $periodMonth
]);
echo "Status: " . $resPre->status() . "\n";
echo "Mismatch Warning: " . json_encode($resPre->json('blocking_warnings')) . "\n";

// Fix mismatch
$sum1->update(['mandays_project' => 20]);


$resPre = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", [
    'employee_id' => $emp1->id, 'period_month' => $periodMonth
]);
echo "Status: " . $resPre->status() . "\n";
$data = $resPre->json();
echo "Body: \n" . json_encode($data, JSON_PRETTY_PRINT) . "\n";
echo "Gaji Pokok: " . $data['gaji_pokok'] . " (Expected: 2200000)\n"; // 22 total_mandays * 100000
$al = collect($data['allowances'])->pluck('amount', 'allowance_type')->toArray();
echo "Transport Trip: " . ($al['transport_trip'] ?? 0) . " (Expected: 50000)\n";
echo "Meal: " . ($al['meal'] ?? 0) . " (Expected: 200000)\n";
echo "Position: " . ($al['position'] ?? 0) . " (Expected: 500000)\n";
echo "Childcare: " . ($al['childcare'] ?? 0) . " (Expected: 300000)\n";
echo "Training: " . ($al['training'] ?? 0) . " (Expected: 300000)\n"; // 100000 * 1.5 * 2
echo "Trans Insurance: " . ($al['transport_insurance'] ?? 0) . " (Expected: 100000)\n";
echo "Total Allowances: " . $data['total_allowances'] . " (Expected: 1450000)\n";
echo "Total Nett: " . $data['total_nett'] . " (Expected: 3650000)\n";

echo "\n6. Auto Calculate Insert Test\n";
$resAuto = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/auto", [
    'employee_id' => $emp1->id, 'period_month' => $periodMonth
]);
echo "Status: " . $resAuto->status() . " (Expected: 201)\n";
$payroll = Payroll::find($resAuto->json('id'));
echo "Payroll Status: " . $payroll->status . " (Expected: draft)\n";
echo "Payroll Mode: " . $payroll->calculation_mode . " (Expected: auto)\n";
echo "Engine Version: " . $payroll->engine_version . " (Expected: v1.0)\n";

echo "\n7. Encryption Read Test\n";
$gpPlain = CryptoService::decryptAESGCM($payroll->gaji_pokok_enc);
echo "Gaji Pokok Plain: " . $payroll->gaji_pokok . " | Decrypted: " . $gpPlain . "\n";
$alPlain = CryptoService::decryptAESGCM($payroll->total_allowances_enc);
echo "Total Allowances Plain: " . $payroll->tunjangan . " | Decrypted: " . $alPlain . "\n";
$dedPlain = CryptoService::decryptAESGCM($payroll->total_deductions_enc);
echo "Total Deductions Plain: " . $payroll->potongan . " | Decrypted: " . $dedPlain . "\n";
$totPlain = CryptoService::decryptAESGCM($payroll->total_enc);
echo "Total Plain: " . $payroll->total . " | Decrypted: " . $totPlain . "\n";

// check tunjangan_enc and potongan_enc as they should be same as total_allowances/deductions
$tunjPlain = CryptoService::decryptAESGCM($payroll->tunjangan_enc);
echo "Tunjangan Plain: " . $payroll->tunjangan . " | Decrypted: " . $tunjPlain . "\n";
$potPlain = CryptoService::decryptAESGCM($payroll->potongan_enc);
echo "Potongan Plain: " . $payroll->potongan . " | Decrypted: " . $potPlain . "\n";

$nonZeroAllowance = $payroll->allowances->where('amount', '>', 0)->first();
$amtPlain = CryptoService::decryptAESGCM($nonZeroAllowance->amount_enc);
echo "Allowance (".$nonZeroAllowance->allowance_type.") Plain: " . $nonZeroAllowance->amount . " | Decrypted: " . $amtPlain . "\n";
echo "\n8. Manual Override Test\n";
$resPatch = Http::withToken($fatToken)->acceptJson()->patch("$baseUrl/payrolls/{$payroll->id}/allowances/{$nonZeroAllowance->id}", [
    'amount' => 999999, 'override_reason' => 'Test Override'
]);
echo "Patch Status: " . $resPatch->status() . " (Expected: 200)\n";
$payroll->refresh();
$nonZeroAllowance->refresh();
echo "Is Override: " . ($nonZeroAllowance->is_manual_override ? 'true' : 'false') . "\n";
echo "Reason: " . $nonZeroAllowance->condition_notes . "\n";
echo "New Tunjangan Total: " . $payroll->total_allowances . "\n";

echo "\n9. Recalculate Rule Test\n";
// Without force, should be blocked because of override
$resRecalcBlocked = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/{$payroll->id}/recalculate");
echo "Recalc Blocked Status: " . $resRecalcBlocked->status() . " (Expected: 422)\n";
echo "Error: " . $resRecalcBlocked->json('message') . "\n";

// Before recalculate, let's check what the database says:
$sumCheck = MonthlyMandaysSummary::where('employee_id', $emp1->id)->where('period_month', $periodMonth)->first();
$assgCheck = ProjectAssignment::where('employee_id', $emp1->id)->where('period_month', $periodMonth)->sum('mandays');
echo "DB Check Before Recalc - Summary: " . $sumCheck->mandays_project . " | Assg: " . $assgCheck . "\n";

// With force, should succeed
$resRecalcForce = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/{$payroll->id}/recalculate", ['force' => true]);
echo "Recalc Force Status: " . $resRecalcForce->status() . " (Expected: 200)\n";
if ($resRecalcForce->status() !== 200) {
    echo "Force Error: " . $resRecalcForce->body() . "\n";
}
$payroll->refresh();
echo "Tunjangan Total after recalc: " . $payroll->total_allowances . " (Expected: 1450000)\n";

// Recalc non-draft should fail
$payroll->update(['status' => 'requested']);
$resRecalcReq = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/{$payroll->id}/recalculate");
echo "Recalc Requested Status: " . $resRecalcReq->status() . " (Expected: 422)\n";

echo "\n10. Fix Rate / Monthly Formula Test\n";
$empTypeFixRate = EmploymentType::firstOrCreate(['code' => 'fix_rate'], ['name' => 'Fix Rate']);
$wbMonthly = WorkBasis::firstOrCreate(['code' => 'monthly'], ['name' => 'Monthly']);
$emp3 = Employee::firstOrCreate(['employee_code' => 'EMP4-03'], [
    'user_id' => null, 'name' => 'Test Fix Rate',
    'department' => 'IT', 'status' => 'active', 'grade_id' => $grade1->id, 'employment_type_id' => $empTypeFixRate->id, 'work_basis_id' => $wbMonthly->id,
    'is_trainer' => true // to test training
]);
$prof3 = SalaryProfile::where('employee_id', $emp3->id)->first();
if ($prof3) $prof3->delete();
$prof3 = SalaryProfile::create(['employee_id' => $emp3->id, 'base_salary' => 8000000, 'base_salary_enc' => \App\Services\CryptoService::encryptAESGCM('8000000'), 'mandays_rate' => 150000, 'mandays_rate_enc' => \App\Services\CryptoService::encryptAESGCM('150000'), 'is_active' => true]);
$sum3 = MonthlyMandaysSummary::where('employee_id', $emp3->id)->where('period_month', $periodMonth)->first();
if ($sum3) $sum3->delete();
$sum3 = MonthlyMandaysSummary::create(['employee_id' => $emp3->id, 'period_month' => $periodMonth, 'period_from' => '2026-05-28', 'period_to' => '2026-06-27', 'is_finalized' => true, 'total_mandays' => 20, 'mandays_ho_wfo' => 5, 'mandays_ho_wfh' => 5, 'mandays_outside_city' => 2, 'mandays_training' => 1]);

// Add GradeAllowanceRate for business_trip and ho_transport_meal
setRate($grade1, 'business_trip', 150000);
setRate($grade1, 'ho_transport_meal', 50000);

$resFixRate = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/preview-calculation", [
    'employee_id' => $emp3->id, 'period_month' => $periodMonth
]);
$dataFix = $resFixRate->json();
echo "Fix Rate Status: " . $resFixRate->status() . "\n";
echo "Fix Rate Body: \n" . json_encode($dataFix, JSON_PRETTY_PRINT) . "\n";

$sumAllowances = collect($dataFix['allowances'])->sum('amount');
echo "SUM Allowances Array: " . $sumAllowances . "\n";
echo "Total Allowances Response: " . $dataFix['total_allowances'] . "\n";
echo "Reconciliation Match: " . ($sumAllowances == $dataFix['total_allowances'] ? 'TRUE' : 'FALSE') . "\n";

echo "\n11. Batch Generate Partial Success\n";
$emp2 = Employee::create([
    'user_id' => null, 'employee_code' => 'EMP4-02', 'name' => 'Test Phase 4 B (No Profile)',
    'department' => 'IT', 'status' => 'active', 'grade_id' => $grade1->id, 'employment_type_id' => $empTypeProj->id, 'work_basis_id' => $wbMandays->id
]);
// $emp2 has NO salary profile, NO summary -> will fail
$resBatch = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/batch-generate", [
    'period_month' => $periodMonth
]);
echo "Batch Status: " . $resBatch->status() . " (Expected: 200)\n";
$batchData = $resBatch->json();
echo "Success: " . $batchData['success_count'] . " | Failed: " . $batchData['failed_count'] . "\n";
foreach ($batchData['results'] as $r) {
    if ($r['employee_id'] == $emp1->id) {
        echo "Emp1 Status: " . $r['status'] . " (Expected: failed duplicate)\n";
    }
    if ($r['employee_id'] == $emp2->id) {
        echo "Emp2 Status: " . $r['status'] . " (Expected: failed no profile/summary)\n";
    }
    if ($r['employee_id'] == $emp3->id) {
        echo "Emp3 Status: " . $r['status'] . " (Expected: success)\n";
    }
}

echo "\n11. Regression Manual Legacy\n";
$resMan = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls", [
    'employee_id' => $emp2->id,
    'periode' => '2026-12',
    'gaji_pokok' => 5000000,
    'grade_code' => 'M',
    'allowances' => [], 'deductions' => []
]);
echo "Manual Create Status: " . $resMan->status() . " (Expected: 201)\n";
$pid = $resMan->json('data.id');
echo "PID: " . $pid . "\n";
$rp = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/$pid/request-payment");
echo "RP Status: " . $rp->status() . "\n";
$ap = Http::withToken($dirToken)->acceptJson()->post("$baseUrl/payrolls/$pid/approve");
echo "AP Status: " . $ap->status() . "\n";
$dummyPdf = "%PDF-1.4\n%DummyPDF";
$tmpFile = tempnam(sys_get_temp_dir(), 'proof_') . '.pdf';
file_put_contents($tmpFile, $dummyPdf);
$paid = Http::withToken($fatToken)->acceptJson()->attach('proof', file_get_contents($tmpFile), 'proof.pdf', ['Content-Type' => 'application/pdf'])
    ->post("$baseUrl/payrolls/$pid/mark-paid");
echo "Manual Mark Paid Status: " . $paid->status() . " (Expected: 200)\n";
if ($paid->status() !== 200) {
    echo "Mark Paid Error: " . $paid->body() . "\n";
}
unlink($tmpFile);

echo "\n12. Audit Log Validation\n";
foreach (AuditLog::whereIn('action', ['PAYROLL_AUTO_CALCULATE', 'PAYROLL_RECALCULATE', 'PAYROLL_ALLOWANCE_OVERRIDE', 'PAYROLL_BATCH_GENERATE'])->get() as $log) {
    echo "Action: " . $log->action . "\n";
}

echo "\nDONE\n";
