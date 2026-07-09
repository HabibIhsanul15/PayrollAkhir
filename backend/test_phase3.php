<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Attendance;
use App\Models\MonthlyMandaysSummary;
use App\Models\Payroll;
use App\Models\Employee;

$hcga = User::where('role', 'hcga')->first() ?? User::first();
$fat = User::where('role', 'fat')->first() ?? User::first();
$director = User::where('role', 'director')->first() ?? User::first();
$staff = User::where('role', 'employee')->first() ?? User::first();

// Pastikan user dummy ada
if (!$staff) {
    // create dummy staff
    $staff = User::create(['name' => 'Staff Test', 'email' => 'staff'.rand(10,99).'@test.com', 'password' => bcrypt('password'), 'role' => 'employee']);
}
if (!Employee::where('user_id', $staff->id)->exists()) {
    Employee::create(['user_id' => $staff->id, 'employee_code' => 'STF-'.rand(100,999), 'name' => 'Staff Test', 'department' => 'IT']);
}
$staffEmployeeId = Employee::where('user_id', $staff->id)->first()->id;

$hcgaToken = $hcga->createToken('test')->plainTextToken;
$fatToken = $fat->createToken('test')->plainTextToken;
$dirToken = $director->createToken('test')->plainTextToken;
$staffToken = $staff->createToken('test')->plainTextToken;

$baseUrl = 'http://127.0.0.1:8000/api';

// Cleanup
Payroll::where('periode', '2026-07-01')->delete();
Payroll::where('periode', '2026-10-01')->delete();
ProjectAssignment::query()->delete();
Attendance::where('employee_id', 1)->delete();
MonthlyMandaysSummary::where('employee_id', 1)->delete();

echo "=== A. Definisi final total_mandays ===\n";
echo "Rule: total_mandays = project + ho_wfo + ho_wfh + training + outside_city\n";
echo "Rule: leave, absent, holiday tidak dihitung dalam total_mandays.\n\n";

echo "=== B. Boundary test ===\n";
$prjCode = 'PRJ-'.rand(100,999);
$resPrj = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/projects", [
    'code' => $prjCode, 'name' => 'Project Test', 'status' => 'active'
]);
$projectId = $resPrj->json('id');

// Insert on 28th May
$att1 = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/attendances", [
    'employee_id' => 1, 'attendance_date' => '2026-05-28', 'attendance_type' => 'ho_wfo'
]);
// Insert on 27th June
$att2 = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/attendances", [
    'employee_id' => 1, 'attendance_date' => '2026-06-27', 'attendance_type' => 'project', 'project_id' => $projectId
]);
// Insert on 28th June
$att3 = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/attendances", [
    'employee_id' => 1, 'attendance_date' => '2026-06-28', 'attendance_type' => 'training'
]);

$sum6 = MonthlyMandaysSummary::where('employee_id', 1)->where('period_month', '2026-06')->first();
$sum7 = MonthlyMandaysSummary::where('employee_id', 1)->where('period_month', '2026-07')->first();

echo "Data 2026-05-28 dan 2026-06-27 masuk periode 2026-06.\n";
echo "Actual 2026-06 total_mandays: " . $sum6->total_mandays . " (Expected: 2)\n";
echo "Data 2026-06-28 masuk periode 2026-07.\n";
echo "Actual 2026-07 total_mandays: " . $sum7->total_mandays . " (Expected: 1)\n\n";


echo "=== C. Observer test ===\n";
$idAtt1 = $att1->json('id');
$idAtt2 = $att2->json('id');
$idAtt3 = $att3->json('id');

// Update attendance type
echo "1. Update ho_wfo ke leave pada tgl 2026-05-28 (periode 2026-06)\n";
Http::withToken($hcgaToken)->acceptJson()->put("$baseUrl/attendances/$idAtt1", [
    'attendance_type' => 'leave'
]);
$sum6 = $sum6->fresh();
echo "Actual 2026-06 total_mandays : " . $sum6->total_mandays . " (Expected: 1)\n";
echo "Actual 2026-06 mandays_leave : " . $sum6->mandays_leave . " (Expected: 1)\n";
echo "Actual 2026-06 mandays_ho_wfo: " . $sum6->mandays_ho_wfo . " (Expected: 0)\n";

// Update date cross boundary
echo "\n2. Update date 2026-06-27 (periode 2026-06) menjadi 2026-06-29 (periode 2026-07)\n";
$sum7 = $sum7->fresh();
echo "BEFORE -> Periode 2026-06 total_mandays: " . $sum6->total_mandays . " | Periode 2026-07 total_mandays: " . $sum7->total_mandays . "\n";
$putRes = Http::withToken($hcgaToken)->acceptJson()->put("$baseUrl/attendances/$idAtt2", [
    'attendance_date' => '2026-06-29',
    'attendance_type' => 'project',
    'project_id' => $projectId
]);
if ($putRes->status() !== 200) echo "ERROR PUT: " . $putRes->body() . "\n";
$sum6 = $sum6->fresh();
$sum7 = $sum7->fresh();
echo "AFTER  -> Periode 2026-06 total_mandays: " . $sum6->total_mandays . " (Expected: 0) | Periode 2026-07 total_mandays: " . $sum7->total_mandays . " (Expected: 2)\n";

// Delete attendance
echo "\n3. Delete attendance tgl 2026-06-28 (training)\n";
Http::withToken($hcgaToken)->acceptJson()->delete("$baseUrl/attendances/$idAtt3");
$sum7 = $sum7->fresh();
echo "Actual 2026-07 total_mandays : " . $sum7->total_mandays . " (Expected: 1)\n\n";

echo "=== D. Assignment observer test ===\n";
echo "1. Create assignment 5 trips\n";
$asg = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/project-assignments", [
    'employee_id' => 1, 'project_id' => $projectId, 'period_month' => '2026-07', 'num_trips' => 5
]);
$sum7 = $sum7->fresh();
echo "Actual 2026-07 num_trips: " . $sum7->num_trips . " (Expected: 5)\n";

echo "2. Update assignment to 8 trips\n";
Http::withToken($hcgaToken)->acceptJson()->put("$baseUrl/project-assignments/" . $asg->json('id'), [
    'num_trips' => 8
]);
$sum7 = $sum7->fresh();
echo "Actual 2026-07 num_trips: " . $sum7->num_trips . " (Expected: 8)\n";

echo "3. Delete assignment\n";
Http::withToken($hcgaToken)->acceptJson()->delete("$baseUrl/project-assignments/" . $asg->json('id'));
$sum7 = $sum7->fresh();
echo "Actual 2026-07 num_trips: " . $sum7->num_trips . " (Expected: 0)\n\n";

echo "=== E. Finalization guard test ===\n";
echo "1. HCGA Finalize periode 2026-07\n";
$fin = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/mandays-summaries/{$sum7->id}/finalize");
echo "Finalize HTTP: " . $fin->status() . " (Expected: 200)\n";

echo "2. Create attendance di periode finalized\n";
$att4 = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/attendances", [
    'employee_id' => 1, 'attendance_date' => '2026-07-05', 'attendance_type' => 'ho_wfo'
]);
echo "Create attendance HTTP: " . $att4->status() . " (Expected: 422)\n";

echo "3. Create assignment di periode finalized\n";
$asg2 = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/project-assignments", [
    'employee_id' => 1, 'project_id' => $projectId, 'period_month' => '2026-07', 'num_trips' => 1
]);
echo "Create assignment HTTP: " . $asg2->status() . " (Expected: 422)\n";

echo "4. Unfinalize tanpa payroll\n";
$unf = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/mandays-summaries/{$sum7->id}/unfinalize");
echo "Unfinalize HTTP: " . $unf->status() . " (Expected: 200)\n";

echo "5. Unfinalize dengan payroll requested\n";
$pr = Payroll::create([
    'user_id' => $fat->id,
    'employee_id' => 1, 'periode' => '2026-07-01', 'status' => 'requested',
    'gaji_pokok_enc' => App\Services\CryptoService::encryptAESGCM('5000000'),
    'salary_alg' => 'AES'
]);
Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/mandays-summaries/{$sum7->id}/finalize");
$unfFail = Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/mandays-summaries/{$sum7->id}/unfinalize");
echo "Unfinalize w/ Payroll HTTP: " . $unfFail->status() . " (Expected: 422)\n";
$pr->delete();
Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/mandays-summaries/{$sum7->id}/unfinalize"); // cleanup
echo "\n";


echo "=== F. Permission test ===\n";
echo "HCGA create project HTTP: " . Http::withToken($hcgaToken)->acceptJson()->post("$baseUrl/projects", [
    'code' => 'PRJ-'.rand(1000,9999), 'name' => 'Test'
])->status() . " (Expected: 201)\n";

echo "FAT read projects HTTP: " . Http::withToken($fatToken)->acceptJson()->get("$baseUrl/projects")->status() . " (Expected: 200)\n";
echo "FAT create project HTTP: " . Http::withToken($fatToken)->acceptJson()->post("$baseUrl/projects", [
    'code' => 'PRJ-'.rand(1000,9999), 'name' => 'Test'
])->status() . " (Expected: 403)\n";

echo "Director read projects HTTP: " . Http::withToken($dirToken)->acceptJson()->get("$baseUrl/projects")->status() . " (Expected: 200)\n";

echo "Staff read his own summary HTTP: " . Http::withToken($staffToken)->acceptJson()->get("$baseUrl/mandays-summaries")->status() . " (Expected: 200)\n";
echo "Staff create attendance HTTP: " . Http::withToken($staffToken)->acceptJson()->post("$baseUrl/attendances", [
    'employee_id' => $staffEmployeeId, 'attendance_date' => '2026-07-01', 'attendance_type' => 'ho_wfo'
])->status() . " (Expected: 403)\n\n";

echo "=== G. Payroll regression test ===\n";
// Create draft payroll using FAT (HCGA cannot create payrolls)
$payrollRes = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls", [
    'employee_id' => 1,
    'periode' => '2026-10',
    'gaji_pokok' => 1000000,
    'grade_code' => 'TEST',
    'allowances' => [],
    'deductions' => []
]);
echo "1. Create Draft HTTP: " . $payrollRes->status() . " (Expected: 201)\n";
if ($payrollRes->status() !== 201) {
    echo "ERROR: " . json_encode($payrollRes->json()) . "\n";
}
$pid = \App\Models\Payroll::latest()->first()->id;

$req = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/$pid/request-payment");
if ($req->status() !== 200) echo "ERROR Req: " . $req->body() . "\n";
echo "2. Request Payment HTTP: " . $req->status() . " (Expected: 200)\n";

$appr = Http::withToken($dirToken)->acceptJson()->post("$baseUrl/payrolls/$pid/approve");
echo "3. Approve HTTP: " . $appr->status() . " (Expected: 200)\n";

// Generate dummy PDF
$dummyPdf = "%PDF-1.4\n%DummyPDF";
$tmpFile = tempnam(sys_get_temp_dir(), 'proof_') . '.pdf';
file_put_contents($tmpFile, $dummyPdf);

$paid = Http::withToken($fatToken)->attach('proof', file_get_contents($tmpFile), 'proof.pdf', ['Content-Type' => 'application/pdf'])
    ->post("$baseUrl/payrolls/$pid/mark-paid");
unlink($tmpFile);

echo "4. Mark Paid HTTP: " . $paid->status() . " (Expected: 200)\n";
$prFinal = Payroll::find($pid);
echo "Final Payroll Status: " . ($prFinal ? $prFinal->status : 'NOT FOUND') . " (Expected: paid)\n";

echo "\n=== H. Kesimpulan ===\n";
echo "Phase 3 Siap Ditutup.\n";
