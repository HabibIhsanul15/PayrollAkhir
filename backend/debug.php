<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Attendance;
use App\Models\MonthlyMandaysSummary;
use App\Models\Project;

Attendance::where('employee_id', 1)->delete();
MonthlyMandaysSummary::where('employee_id', 1)->delete();
Project::where('code', 'DEBUG_PRJ')->delete();

$prj = Project::create([
    'code' => 'DEBUG_PRJ',
    'name' => 'Debug',
    'status' => 'active'
]);

$att1 = Attendance::create([
    'employee_id' => 1,
    'attendance_date' => '2026-05-28',
    'attendance_type' => 'leave',
    'recorded_by' => 1
]);

$att2 = Attendance::create([
    'employee_id' => 1,
    'attendance_date' => '2026-06-27',
    'attendance_type' => 'project',
    'project_id' => $prj->id,
    'recorded_by' => 1
]);

$sum6 = MonthlyMandaysSummary::where('period_month', '2026-06')->first();
$sum7 = MonthlyMandaysSummary::where('period_month', '2026-07')->first();
echo "Before Update:\n";
echo "2026-06: " . ($sum6 ? $sum6->total_mandays : 'NULL') . "\n";
echo "2026-07: " . ($sum7 ? $sum7->total_mandays : 'NULL') . "\n";

$att2->update(['attendance_date' => '2026-06-28']);

$sum6 = $sum6->fresh();
$sum7 = MonthlyMandaysSummary::where('period_month', '2026-07')->first();
echo "\nAfter Update:\n";
echo "2026-06: " . ($sum6 ? $sum6->total_mandays : 'NULL') . "\n";
echo "2026-07: " . ($sum7 ? $sum7->total_mandays : 'NULL') . "\n";

echo "\nCheck DB Attendance:\n";
foreach (Attendance::all() as $a) {
    echo "ID: $a->id, Date: $a->attendance_date, Type: $a->attendance_type\n";
}

echo "\nCheck DB Summaries:\n";
foreach (MonthlyMandaysSummary::where('employee_id', 1)->get() as $s) {
    echo "Period: $s->period_month, Total: $s->total_mandays, Leave: $s->mandays_leave, Project: $s->mandays_project\n";
}
