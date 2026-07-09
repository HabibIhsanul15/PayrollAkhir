<?php

// verify_phase1.php
// Script to programmatically verify all Phase 1 implementation details.

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\EmploymentType;
use App\Models\WorkBasis;
use App\Models\AllowanceType;
use App\Models\GradeAllowanceRate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function hr($title) {
    echo "\n======================================================================\n";
    echo "  $title\n";
    echo "======================================================================\n";
}

function runRequest($kernel, $method, $uri, $token = null, $body = []) {
    \Illuminate\Support\Facades\Auth::forgetGuards();
    
    $server = [
        'HTTP_ACCEPT' => 'application/json',
    ];
    if ($token) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }
    $req = Request::create($uri, $method, $body, [], [], $server);
    $res = $kernel->handle($req);
    $content = $res->getContent();
    $data = json_decode($content, true);
    return [
        'status' => $res->getStatusCode(),
        'data' => $data
    ];
}

// ---------------------------------------------------------
// 1. DATABASE VERIFICATION
// ---------------------------------------------------------
hr("1. DATABASE VERIFICATION");

echo "Checking tables content...\n";

// Employment Types
$empTypesCount = EmploymentType::count();
$empTypes = EmploymentType::all()->pluck('name', 'code')->toArray();
echo "Employment Types count: $empTypesCount\n";
print_r($empTypes);

// Work Bases
$workBasesCount = WorkBasis::count();
$workBases = WorkBasis::all()->pluck('name', 'code')->toArray();
echo "Work Bases count: $workBasesCount\n";
print_r($workBases);

// Grades (Make sure trainer is not there)
$gradesCount = Grade::count();
$grades = Grade::orderBy('level')->pluck('name', 'code')->toArray();
echo "Grades count: $gradesCount\n";
print_r($grades);
if (array_key_exists('trainer', $grades)) {
    echo "❌ ERROR: Grade trainer found!\n";
} else {
    echo "✅ SUCCESS: No grade trainer found!\n";
}

// Allowance Types
$allowanceCount = AllowanceType::count();
$allowances = AllowanceType::orderBy('display_order')->pluck('name', 'code')->toArray();
echo "Allowance Types count: $allowanceCount\n";
print_r($allowances);

// Grade Allowance Rates
$ratesCount = GradeAllowanceRate::count();
echo "Grade Allowance Rates count: $ratesCount\n";

// ---------------------------------------------------------
// 2. AUTHORIZATION TEST
// ---------------------------------------------------------
hr("2. AUTHORIZATION TEST");

// Get Users
$hcgaUser = User::where('role', 'hcga')->first();
$fatUser = User::where('role', 'fat')->first();
$staffUser = User::where('role', 'staff')->first();
$directorUser = User::where('role', 'director')->first();

// Create Sanctum Tokens
$hcgaToken = $hcgaUser ? $hcgaUser->createToken('hcga-test')->plainTextToken : null;
$fatToken = $fatUser ? $fatUser->createToken('fat-test')->plainTextToken : null;
$staffToken = $staffUser ? $staffUser->createToken('staff-test')->plainTextToken : null;
$directorToken = $directorUser ? $directorUser->createToken('director-test')->plainTextToken : null;

// Test HCGA (CRUD)
echo "\nTesting HCGA authorizations:\n";
$resGrades = runRequest($kernel, 'GET', '/api/master/grades', $hcgaToken);
echo "  GET /api/master/grades -> Status: {$resGrades['status']}\n";

$newGradePayload = [
    'code' => 'test_g',
    'name' => 'Test Grade',
    'level' => 10,
    'description' => 'Temporary test grade',
    'is_active' => true
];
$resCreate = runRequest($kernel, 'POST', '/api/master/grades', $hcgaToken, $newGradePayload);
echo "  POST /api/master/grades -> Status: {$resCreate['status']}\n";
$testGradeId = $resCreate['data']['id'] ?? null;

if ($testGradeId) {
    $resUpdate = runRequest($kernel, 'PUT', "/api/master/grades/$testGradeId", $hcgaToken, ['name' => 'Updated Test Grade']);
    echo "  PUT /api/master/grades/$testGradeId -> Status: {$resUpdate['status']} (Name: " . ($resUpdate['data']['name'] ?? '') . ")\n";

    $resDelete = runRequest($kernel, 'DELETE', "/api/master/grades/$testGradeId", $hcgaToken);
    echo "  DELETE /api/master/grades/$testGradeId -> Status: {$resDelete['status']}\n";
}

$resAllowance = runRequest($kernel, 'GET', '/api/master/allowance-types', $hcgaToken);
echo "  GET /api/master/allowance-types -> Status: {$resAllowance['status']}\n";

$resGAR = runRequest($kernel, 'GET', '/api/master/grade-allowance-rates', $hcgaToken);
echo "  GET /api/master/grade-allowance-rates -> Status: {$resGAR['status']}\n";


// Test FAT (Read-only, no write)
echo "\nTesting FAT authorizations:\n";
$resGradesFat = runRequest($kernel, 'GET', '/api/master/grades', $fatToken);
echo "  GET /api/master/grades -> Status: {$resGradesFat['status']}\n";

$resCreateFat = runRequest($kernel, 'POST', '/api/master/grades', $fatToken, $newGradePayload);
echo "  POST /api/master/grades -> Status: {$resCreateFat['status']} (Expected: 403)\n";


// Test Staff (No read, no write)
echo "\nTesting Staff authorizations:\n";
$resGradesStaff = runRequest($kernel, 'GET', '/api/master/grades', $staffToken);
echo "  GET /api/master/grades -> Status: {$resGradesStaff['status']} (Expected: 403)\n";


// Test Director (No read, no write)
echo "\nTesting Director authorizations:\n";
$resGradesDir = runRequest($kernel, 'GET', '/api/master/grades', $directorToken);
echo "  GET /api/master/grades -> Status: {$resGradesDir['status']} (Expected: 403)\n";


// ---------------------------------------------------------
// 3. BACKWARD COMPATIBILITY
// ---------------------------------------------------------
hr("3. BACKWARD COMPATIBILITY");

echo "Creating a backward compatible employee (grade_id, employment_type_id, work_basis_id are NULL)...\n";
$oldEmp = Employee::create([
    'employee_code' => 'OLD-EMP-99',
    'name' => 'Old Employee Legacy',
    'department' => 'Operations',
    'position' => 'Legacy Clerk',
    'status' => 'active',
    'grade_id' => null,
    'employment_type_id' => null,
    'work_basis_id' => null,
]);

echo "Employee created. ID: {$oldEmp->id}\n";

// Open employee detail via API
$resShowOld = runRequest($kernel, 'GET', "/api/employees/{$oldEmp->id}", $hcgaToken);
echo "GET /api/employees/{$oldEmp->id} -> Status: {$resShowOld['status']}\n";
echo "Grade ID: " . var_export($resShowOld['data']['grade_id'], true) . "\n";

// Edit and Save employee detail via API
$resUpdateOld = runRequest($kernel, 'PUT', "/api/employees/{$oldEmp->id}", $hcgaToken, [
    'name' => 'Old Employee Updated',
]);
echo "PUT /api/employees/{$oldEmp->id} -> Status: {$resUpdateOld['status']}\n";
echo "Updated Name in response: " . ($resUpdateOld['data']['employee']['name'] ?? '') . "\n";

// Cleanup old employee
$oldEmp->delete();
echo "Cleanup completed.\n";


// ---------------------------------------------------------
// 4. NEW EMPLOYEE CREATION
// ---------------------------------------------------------
hr("4. NEW EMPLOYEE CREATION");

$grade = Grade::where('code', 'staff')->first();
$empType = EmploymentType::where('code', 'project')->first();
$workBasis = WorkBasis::where('code', 'mandays')->first();

echo "Creating new employee with grade 'staff', employment_type 'project', work_basis 'mandays'...\n";

$newEmpPayload = [
    'employee_code' => 'NEW-EMP-100',
    'name' => 'New Project Partner',
    'department' => 'Consulting',
    'position' => 'Junior Staff',
    'status' => 'active',
    'grade_id' => $grade->id,
    'employment_type_id' => $empType->id,
    'work_basis_id' => $workBasis->id,
    'num_toddlers' => 3,
    'is_trainer' => true,
    'is_on_probation' => true,
    'nik' => '1234567890123456',
    'npwp' => '12.345.678.9-012.000',
    'phone' => '081234567890',
    'address' => 'Sudirman St. No 10',
    'bank_name' => 'BCA',
    'bank_account_name' => 'New Project Partner',
    'bank_account_number' => '12345678',
];

$resCreateEmp = runRequest($kernel, 'POST', '/api/employees', $hcgaToken, $newEmpPayload);
echo "POST /api/employees -> Status: {$resCreateEmp['status']}\n";
$newEmpId = $resCreateEmp['data']['employee']['id'] ?? null;

if ($newEmpId) {
    echo "Employee created successfully. ID: $newEmpId\n";

    // Show details to verify they display correctly
    $resShowNew = runRequest($kernel, 'GET', "/api/employees/$newEmpId", $hcgaToken);
    echo "GET /api/employees/$newEmpId -> Status: {$resShowNew['status']}\n";
    echo "Verify fields:\n";
    echo "  - grade_id: " . var_export($resShowNew['data']['grade_id'], true) . " (Expected: {$grade->id})\n";
    echo "  - employment_type_id: " . var_export($resShowNew['data']['employment_type_id'], true) . " (Expected: {$empType->id})\n";
    echo "  - work_basis_id: " . var_export($resShowNew['data']['work_basis_id'], true) . " (Expected: {$workBasis->id})\n";
    echo "  - num_toddlers: " . var_export($resShowNew['data']['num_toddlers'], true) . " (Expected: 3)\n";
    echo "  - is_trainer: " . var_export($resShowNew['data']['is_trainer'], true) . " (Expected: true)\n";
    echo "  - is_on_probation: " . var_export($resShowNew['data']['is_on_probation'], true) . " (Expected: true)\n";
    echo "  - grade code: " . ($resShowNew['data']['grade']['code'] ?? 'null') . "\n";
    echo "  - employment_type code: " . ($resShowNew['data']['employment_type']['code'] ?? 'null') . "\n";
    echo "  - work_basis code: " . ($resShowNew['data']['work_basis']['code'] ?? 'null') . "\n";
}


// ---------------------------------------------------------
// 5. SALARY PROFILE VERIFICATION
// ---------------------------------------------------------
hr("5. SALARY PROFILE VERIFICATION");

if ($newEmpId) {
    echo "Creating salary profile with mandays_rate...\n";

    $salaryPayload = [
        'base_salary' => 6000000,
        'allowance_fixed' => 500000,
        'deduction_fixed' => 200000,
        'effective_from' => '2026-06-01',
        'daily_rate' => 150000,
        'overtime_rate_per_hour' => 50000,
        'late_penalty_per_minute' => 1000,
        'mandays_rate' => 300000,
    ];

    $resSalary = runRequest($kernel, 'POST', "/api/employees/$newEmpId/salary-profiles", $hcgaToken, $salaryPayload);
    echo "POST /api/employees/$newEmpId/salary-profiles -> Status: {$resSalary['status']}\n";
    
    // Fetch profile and check fields
    $resGetSalary = runRequest($kernel, 'GET', "/api/employees/$newEmpId/salary-profile?date=2026-06-02", $hcgaToken);
    echo "GET /api/employees/$newEmpId/salary-profile -> Status: {$resGetSalary['status']}\n";
    echo "Verify base & encryption-intact fields:\n";
    echo "  - base_salary: " . ($resGetSalary['data']['base_salary'] ?? '') . " (Expected: 6000000)\n";
    echo "  - allowance_fixed: " . ($resGetSalary['data']['allowance_fixed'] ?? '') . " (Expected: 500000)\n";
    echo "  - deduction_fixed: " . ($resGetSalary['data']['deduction_fixed'] ?? '') . " (Expected: 200000)\n";
    echo "  - mandays_rate: " . ($resGetSalary['data']['mandays_rate'] ?? '') . " (Expected: 300000)\n";
}


// ---------------------------------------------------------
// 6. PAYROLL Legacy Flow
// ---------------------------------------------------------
hr("6. PAYROLL Legacy Flow");

if ($newEmpId) {
    echo "Verifying existing (manual) payroll flow is fully intact and works...\n";

    $payrollPayload = [
        'employee_id' => $newEmpId,
        'periode' => '2026-06-01',
        'gaji_pokok' => 6000000,
        'tunjangan' => 500000,
        'potongan' => 200000,
        'catatan' => 'Manual test payroll',
    ];

    // Create payroll (DRAFT)
    $resPayCreate = runRequest($kernel, 'POST', '/api/payrolls', $fatToken, $payrollPayload);
    echo "  POST /api/payrolls (Create DRAFT) -> Status: {$resPayCreate['status']}\n";
    $payrollId = $resPayCreate['data']['payroll']['id'] ?? null;

    if ($payrollId) {
        // Request Payment
        $resReq = runRequest($kernel, 'POST', "/api/payrolls/$payrollId/request-payment", $fatToken);
        echo "  POST /api/payrolls/$payrollId/request-payment -> Status: {$resReq['status']}\n";

        // Approve
        $resApp = runRequest($kernel, 'POST', "/api/payrolls/$payrollId/approve", $directorToken, ['approval_note' => 'Approved programmatic test']);
        echo "  POST /api/payrolls/$payrollId/approve -> Status: {$resApp['status']}\n";

        // Mark Paid (need upload, but we check endpoint returns expected error or success)
        echo "  Note: Mark paid requires file upload. We can check the route exists and is authenticated.\n";
        
        // Cleanup payroll and employee
        DB::table('payrolls')->where('id', $payrollId)->delete();
        echo "  Cleaned up temporary payroll.\n";
    }

    // Cleanup test employee and their salary profiles
    DB::table('salary_profiles')->where('employee_id', $newEmpId)->delete();
    DB::table('employees')->where('id', $newEmpId)->delete();
    echo "  Cleaned up temporary employee & salary profiles.\n";
}

// Delete Sanctum Tokens
if ($hcgaUser) $hcgaUser->tokens()->delete();
if ($fatUser) $fatUser->tokens()->delete();
if ($staffUser) $staffUser->tokens()->delete();
if ($directorUser) $directorUser->tokens()->delete();

echo "\nVerification script finished!\n";
