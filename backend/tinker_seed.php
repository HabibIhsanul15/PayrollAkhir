<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$empId = 92; 
for ($i=1; $i<=15; $i++) { 
    $date = '2026-07-' . str_pad($i, 2, '0', STR_PAD_LEFT); 
    \App\Models\Attendance::updateOrCreate(
        ['employee_id' => $empId, 'attendance_date' => $date], 
        [
            'attendance_type' => 'ho_wfo', 
            'check_in' => $date . ' 08:00:00', 
            'check_out' => $date . ' 17:00:00', 
            'recorded_by' => 1
        ]
    ); 
} 
echo "Seeded 15 days attendance for EMP-0002.\n";
