<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;

$u = User::where('email', 'fat@example.com')->first();
if ($u) {
    Employee::updateOrCreate(
        ['user_id' => $u->id],
        [
            'employee_code' => 'FAT-001',
            'name' => 'Test FAT',
            'status' => 'active',
            'department' => 'Finance',
        ]
    );
    echo "Employee for FAT created successfully.\n";
} else {
    echo "FAT user not found.\n";
}
