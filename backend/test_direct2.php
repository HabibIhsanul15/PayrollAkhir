<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$obj = $app->make(\App\Http\Controllers\Api\PayrollCalculationController::class);
echo "Class: " . get_class($obj) . "\n";
echo "Has previewCalculation: " . (method_exists($obj, 'previewCalculation') ? 'YES' : 'NO') . "\n";
