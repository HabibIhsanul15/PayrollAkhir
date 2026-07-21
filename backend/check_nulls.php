<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
$tableKey = 'Tables_in_' . env('DB_DATABASE', 'payroll_ta');

foreach ($tables as $table) {
    $tableName = current((array)$table);
    $columns = DB::select('SHOW COLUMNS FROM ' . $tableName);
    $count = DB::table($tableName)->count();
    
    if ($count == 0) continue;
    
    foreach ($columns as $col) {
        if ($col->Null === 'YES') {
            $nullCount = DB::table($tableName)->whereNull($col->Field)->count();
            if ($nullCount == $count) {
                echo "Table: $tableName | Column: {$col->Field} is 100% NULL ($count rows)\n";
            }
        }
    }
}
echo "Done.\n";
