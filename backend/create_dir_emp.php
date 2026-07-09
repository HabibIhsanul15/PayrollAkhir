<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;

$u = User::where('role', 'director')->first();
if (!$u) die("Director user not found\n");

$e = Employee::where('user_id', $u->id)->first();
if (!$e) {
    // Get grade for director or fallback to first
    $grade = \App\Models\Grade::where('code', 'DIR')->first() ?? \App\Models\Grade::first();
    $empType = \App\Models\EmploymentType::first();
    $workBasis = \App\Models\WorkBasis::first();

    $e = Employee::create([
        'user_id' => $u->id,
        'employee_code' => 'DIR-0001',
        'name' => $u->name,
        'status' => 'active',
        'department' => 'Board of Directors',
        'position' => 'Director',
        'grade_id' => $grade->id,
        'employment_type_id' => $empType->id,
        'work_basis_id' => $workBasis->id,
        'join_date' => '2020-01-01',
        'date_of_birth' => '1980-01-01',
        'gender' => 'male',
        'address' => 'Jakarta',
        'phone_number' => '081234567890',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_name' => $u->name,
    ]);
    
    // Also create salary profile so we don't get errors later
    \App\Models\SalaryProfile::create([
        'employee_id' => $e->id,
        'effective_from' => '2020-01-01',
        'base_salary' => 0,
        'mandays_rate' => 0,
        'grade_id' => $grade->id,
        'position' => 'Director'
    ]);
    
    echo "Employee DIR-0001 created for Director\n";
} else {
    echo "Employee already exists for Director\n";
}
