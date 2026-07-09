<?php
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Payroll;

echo "=== 2. SCHEMA VERIFICATION ===\n";
echo "Payrolls columns (Phase 2): \n";
print_r(array_intersect(Schema::getColumnListing('payrolls'), ['total_allowances', 'total_deductions', 'calculation_mode', 'calculated_at', 'total_allowances_enc', 'total_deductions_enc']));

echo "\nPayroll Allowances columns: \n";
print_r(Schema::getColumnListing('payroll_allowances'));

echo "\nPayroll Deductions columns: \n";
print_r(Schema::getColumnListing('payroll_deductions'));

// Tokens for CURL
$fat = User::where('email', 'fat@example.com')->first();
if ($fat) file_put_contents('fat_token.txt', $fat->createToken('test')->plainTextToken);

$director = User::where('email', 'director@example.com')->first();
if ($director) file_put_contents('dir_token.txt', $director->createToken('test')->plainTextToken);

$legacy = Payroll::whereDoesntHave('allowances')->whereDoesntHave('deductions')->where('status', 'paid')->first() ?? Payroll::first();
if ($legacy) file_put_contents('legacy_id.txt', $legacy->id);

echo "\nSaved tokens and legacy ID for curl tests.\n";
