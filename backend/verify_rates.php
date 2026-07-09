<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\GradeAllowanceRate;
use App\Models\AllowanceType;
use App\Models\Grade;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

$kernel = $app->make(Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function runRequest($kernel, $method, $uri, $token = null, $body = []) {
    \Illuminate\Support\Facades\Auth::forgetGuards();
    $server = ['HTTP_ACCEPT' => 'application/json'];
    if ($token) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }
    
    // Simulate multipart/form-data for uploads
    $files = [];
    if (isset($body['_file_name'])) {
        $files['proof'] = \Illuminate\Http\Testing\File::image($body['_file_name']);
        unset($body['_file_name']);
    }

    $req = Request::create($uri, $method, $body, [], $files, $server);
    $res = $kernel->handle($req);
    return [
        'status' => $res->getStatusCode(),
        'data' => json_decode($res->getContent(), true)
    ];
}

echo "=== CHECKING GRADE ALLOWANCE RATES ===\n";
// 1. Group by Allowance Type
$counts = GradeAllowanceRate::select('allowance_type_id', DB::raw('count(*) as total'))
    ->groupBy('allowance_type_id')
    ->get();

foreach ($counts as $c) {
    $type = AllowanceType::find($c->allowance_type_id);
    echo "- " . $type->name . " (" . $type->code . "): " . $c->total . " entries\n";
}

$total = GradeAllowanceRate::count();
echo "Total entries: $total\n";

// 2. Check for duplicates
$duplicates = GradeAllowanceRate::select('grade_id', 'allowance_type_id', 'effective_from', DB::raw('count(*) as count'))
    ->groupBy('grade_id', 'allowance_type_id', 'effective_from')
    ->havingRaw('count(*) > 1')
    ->get();

if ($duplicates->count() > 0) {
    echo "\n[WARNING] Found duplicate combinations!\n";
    foreach ($duplicates as $d) {
        echo "Grade: $d->grade_id, Type: $d->allowance_type_id, From: $d->effective_from -> Count: $d->count\n";
    }
} else {
    echo "\n[OK] No duplicate combinations found.\n";
}

// 3. Check for trainer grade
$trainerGrade = Grade::where('code', 'trainer')->orWhere('name', 'like', '%trainer%')->first();
if ($trainerGrade) {
    echo "\n[WARNING] Found trainer grade in grades table: " . $trainerGrade->name . "\n";
} else {
    echo "\n[OK] No trainer grade found in grades table.\n";
}

echo "\n=== TESTING PAYROLL LIFECYCLE ===\n";

// Prep Users
$hcgaToken = User::where('role', 'hcga')->first()->createToken('hcga')->plainTextToken;
$fatToken = User::where('role', 'fat')->first()->createToken('fat')->plainTextToken;
$directorToken = User::where('role', 'director')->first()->createToken('director')->plainTextToken;

// Prep Employee
$grade = Grade::where('code', 'staff')->first();
$emp = Employee::create([
    'employee_code' => 'TEST-PAYROLL-1',
    'name' => 'Payroll Lifecycle Tester',
    'status' => 'active',
    'grade_id' => $grade->id,
]);

// Set Salary Profile
runRequest($kernel, 'POST', "/api/employees/{$emp->id}/salary-profiles", $hcgaToken, [
    'base_salary' => 5000000,
    'effective_from' => '2026-06-01',
]);

// 1. DRAFT (by FAT)
$payload = [
    'employee_id' => $emp->id,
    'periode' => '2026-06-01',
    'gaji_pokok' => 5000000,
    'tunjangan' => 200000,
    'potongan' => 50000,
    'catatan' => 'Testing lifecycle'
];
$resCreate = runRequest($kernel, 'POST', '/api/payrolls', $fatToken, $payload);
echo "1. Create Draft (FAT): Status {$resCreate['status']}\n";
$payrollId = $resCreate['data']['data']['id'] ?? null;

if ($payrollId) {
    $p1 = \App\Models\Payroll::find($payrollId);
    echo "   -> Status in DB: {$p1->status}\n";

    // 2. REQUEST PAYMENT (by FAT)
    $resReq = runRequest($kernel, 'POST', "/api/payrolls/$payrollId/request-payment", $fatToken);
    echo "2. Request Payment (FAT): Status {$resReq['status']}\n";
    $p2 = \App\Models\Payroll::find($payrollId);
    echo "   -> Status in DB: {$p2->status}\n";

    // 3. APPROVE (by Director)
    $resApp = runRequest($kernel, 'POST', "/api/payrolls/$payrollId/approve", $directorToken, [
        'approval_note' => 'OK Approved'
    ]);
    echo "3. Approve (Director): Status {$resApp['status']}\n";
    $p3 = \App\Models\Payroll::find($payrollId);
    echo "   -> Status in DB: {$p3->status}\n";

    // 4. MARK PAID (by FAT)
    $resPaid = runRequest($kernel, 'POST', "/api/payrolls/$payrollId/mark-paid", $fatToken, [
        'payment_note' => 'Paid via transfer',
        '_file_name' => 'proof.png'
    ]);
    echo "4. Mark Paid (FAT): Status {$resPaid['status']}\n";
    if ($resPaid['status'] === 422) {
        print_r($resPaid['data']);
    }
    $p4 = \App\Models\Payroll::find($payrollId);
    echo "   -> Status in DB: {$p4->status}\n";

    // Cleanup
    $p4->delete();
}

$emp->salaryProfiles()->delete();
$emp->delete();
DB::table('personal_access_tokens')->delete();
echo "\nTesting Finished.\n";
