<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Starting data cleanup...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

$tables = [
    'journal_items',
    'journal_entries',
    'payroll_allowances',
    'payroll_deductions',
    'payrolls',
    'monthly_mandays_summaries',
    'attendances',
    'project_assignments',
    'projects',
    'salary_profiles',
    'employees',
];

foreach ($tables as $table) {
    try {
        DB::table($table)->truncate();
        echo "Truncated: $table\n";
    } catch (\Exception $e) {
        echo "Error truncating $table: " . $e->getMessage() . "\n";
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Data cleanup finished.\n";
