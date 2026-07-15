<?php 
require 'vendor/autoload.php'; 
$app = require_once 'bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 
$req = request()->merge(['position_id' => 7, 'name' => 'Rina Melati Test']); 
$user = App\Models\User::where('role', 'hcga')->first() ?? App\Models\User::first();
$user->role = 'hcga';
$req->setUserResolver(fn() => $user); 
echo json_encode(app()->make('App\Http\Controllers\Api\EmployeeController')->update($req, App\Models\Employee::where('employee_code', 'EMP-004')->first())->getData());
