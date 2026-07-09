<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $c = app(\App\Http\Controllers\Api\PayrollCalculationController::class);
    $req = new \App\Http\Requests\PayrollCalculationRequest();
    $c->preview($req);
    echo "Success!\n";
} catch(\Throwable $e) {
    echo "Exception: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n" . $e->getTraceAsString();
}
