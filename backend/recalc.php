<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = app(\App\Services\PayrollService::class);
$svc->calculate(2026, 7);
echo "Recalculated!\n";
