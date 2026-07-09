<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\SalaryProfile;

Employee::where('status', 'active')->each(function($e) {
    if (!$e->currentSalaryProfile(now()->toDateString())) {
        SalaryProfile::create([
            'employee_id' => $e->id,
            'grade_id' => $e->grade_id,
            'position' => $e->position,
            'base_salary' => 0, // FIXED to 0 instead of null
            'mandays_rate' => 0, // FIXED to 0 instead of null
            'allowance_fixed' => 0,
            'deduction_fixed' => 0,
            'effective_from' => '2020-01-01', // Backdated
            'pii_alg' => $e->pii_alg,
            'pii_key_id' => $e->pii_key_id
        ]);
        echo "Created SalaryProfile for " . $e->employee_code . PHP_EOL;
    }
});
echo "Done." . PHP_EOL;
