<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Grade;

echo "Updating grade default salaries...\n";

$rates = [
    'BOD' => ['base' => 50000000, 'mandays' => 1500000],
    'G1' => ['base' => 50000000, 'mandays' => 1500000],
    'PD' => ['base' => 30000000, 'mandays' => 1000000],
    'GM' => ['base' => 25000000, 'mandays' => 800000],
    'PM' => ['base' => 20000000, 'mandays' => 600000],
    'MANAGER' => ['base' => 15000000, 'mandays' => 500000],
    'CONSULTANT' => ['base' => 12000000, 'mandays' => 400000],
    'SUPERVISOR' => ['base' => 8000000, 'mandays' => 300000],
    'STAFF' => ['base' => 5000000, 'mandays' => 200000],
];

foreach ($rates as $code => $rate) {
    $grade = Grade::where('code', $code)->first();
    if ($grade) {
        $grade->update([
            'default_base_salary' => $rate['base'],
            'default_mandays_rate' => $rate['mandays']
        ]);
        echo "Updated $code\n";
    }
}

echo "Done.\n";
