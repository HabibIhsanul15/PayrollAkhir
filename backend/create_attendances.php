<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\Attendance;
use App\Services\MandaysRecalculationService;
use Carbon\Carbon;

$employee = Employee::where('employee_code', 'EMP-0001')->first();
if (!$employee) die("Employee not found.\n");

$periodMonth = '2026-07';
[$start, $end] = MandaysRecalculationService::getPeriodDates($periodMonth);

// Clear existing attendance for this period just in case
Attendance::where('employee_id', $employee->id)
    ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
    ->delete();

$currentDate = $start->copy();
$wfhCount = 0;

while ($currentDate <= $end) {
    if (!$currentDate->isWeekend()) {
        $type = 'ho_wfo';
        if ($wfhCount < 3) {
            $type = 'ho_wfh';
            $wfhCount++;
        }
        
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => $currentDate->toDateString(),
            'attendance_type' => $type,
            'is_auto_generated' => true,
        ]);
    }
    $currentDate->addDay();
}

echo "Created attendances for " . $employee->name . "\n";
