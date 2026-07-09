<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProjectAssignment;
use Illuminate\Support\Facades\Http;

echo "CLI Config: " . json_encode(DB::connection()->getConfig()) . "\n";
$empId = \App\Models\Employee::first()->id ?? 1;
$projId = \App\Models\Project::first()->id ?? 1;

ProjectAssignment::where('period_month', '2026-06')->delete();
ProjectAssignment::create(['employee_id' => $empId, 'project_id' => $projId, 'period_month' => '2026-06', 'mandays' => 10]);

echo "CLI Count: " . ProjectAssignment::count() . "\n";
echo "Web Count: " . collect(Http::get('http://127.0.0.1:8000/api/debug-assg')->json())->count() . "\n";
