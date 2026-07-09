<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Payroll;
use App\Models\AllowanceType;
use App\Models\PayrollAllowance;
use App\Models\PayrollDeduction;

$fat = User::where('role', 'fat')->first() ?? User::first();
$director = User::where('role', 'director')->first() ?? User::first();
$fatToken = $fat->createToken('test')->plainTextToken;
$dirToken = $director->createToken('test')->plainTextToken;

$legacy = Payroll::whereDoesntHave('allowances')->whereDoesntHave('deductions')->first() ?? Payroll::first();
$legacyId = $legacy ? $legacy->id : 1;

$baseUrl = 'http://127.0.0.1:8000/api';

echo "\n=== 3. Legacy Payroll Detail Test (ID: $legacyId) ===\n";
$res3 = Http::withToken($fatToken)->get("$baseUrl/payrolls/$legacyId");
echo "Status: " . $res3->status() . "\n";
$data3 = $res3->json();
echo "Allowances empty? " . (empty($data3['allowances']) ? 'YES' : 'NO') . "\n";
echo "Deductions empty? " . (empty($data3['deductions']) ? 'YES' : 'NO') . "\n";
echo "Gaji Pokok: " . $data3['gaji_pokok'] . "\n";
echo "Total: " . $data3['total'] . "\n";

echo "\n=== 4. Legacy PDF Test ===\n";
$res4 = Http::withToken($fatToken)->get("$baseUrl/payrolls/$legacyId/pdf");
echo "PDF Status: " . $res4->status() . "\n";
echo "Content-Type: " . $res4->header('Content-Type') . "\n";

echo "\n=== 5. Legacy Report Test ===\n";
$month = substr(Payroll::find($legacyId)->periode, 0, 7);
$res5 = Http::withToken($dirToken)->get("$baseUrl/reports/payroll?month=$month");
echo "Report Status: " . $res5->status() . "\n";
$data5 = $res5->json();
echo "Total rows: " . (count($data5['rows'] ?? [])) . "\n";
$found = collect($data5['rows'])->firstWhere('id', $legacyId);
echo "Legacy ID found in report? " . ($found ? 'YES' : 'NO') . "\n";

echo "\n=== 6. Dummy Breakdown Test ===\n";
$payroll = Payroll::where('status', 'draft')->first();
if (!$payroll) {
    // buat draft baru untuk employee_id 1
    $payroll = Payroll::create([
        'employee_id' => 1,
        'periode' => '2026-06-01',
        'status' => 'draft',
        'gaji_pokok' => 10000000,
        'tunjangan' => 2000000,
        'potongan' => 500000,
        'total' => 11500000,
        'total_allowances' => 2000000,
        'total_deductions' => 500000,
        'total_allowances_enc' => App\Services\CryptoService::encryptAESGCM('2000000'),
        'total_deductions_enc' => App\Services\CryptoService::encryptAESGCM('500000'),
        'salary_alg' => 'AES',
    ]);
} else {
    // update it
    $payroll->update([
        'total_allowances' => 2000000,
        'total_deductions' => 500000,
        'total_allowances_enc' => App\Services\CryptoService::encryptAESGCM('2000000'),
        'total_deductions_enc' => App\Services\CryptoService::encryptAESGCM('500000'),
        'salary_alg' => 'AES',
    ]);
}
$allowanceType = AllowanceType::firstOrCreate(['name' => 'Tunjangan Transport', 'code' => 'TRP', 'is_active' => true]);

PayrollAllowance::updateOrCreate(
    ['payroll_id' => $payroll->id, 'allowance_type_id' => $allowanceType->id],
    [
        'amount' => 2000000, 
        'amount_enc' => App\Services\CryptoService::encryptAESGCM('2000000'),
        'salary_alg' => 'AES',
        'condition_met' => true
    ]
);

PayrollDeduction::updateOrCreate(
    ['payroll_id' => $payroll->id, 'deduction_type' => 'late_penalty'],
    [
        'amount' => 500000, 
        'amount_enc' => App\Services\CryptoService::encryptAESGCM('500000'),
        'salary_alg' => 'AES',
        'deduction_label' => 'Terlambat'
    ]
);

$res6 = Http::withToken($fatToken)->get("$baseUrl/payrolls/{$payroll->id}");
echo "Dummy Status: " . $res6->status() . "\n";
$data6 = $res6->json();
echo "Allowances count: " . count($data6['allowances']) . "\n";
echo "Deductions count: " . count($data6['deductions']) . "\n";
echo "First Allowance Amount: " . $data6['allowances'][0]['amount'] . "\n";
echo "Total Allowances (decrypted): " . $data6['total_allowances'] . "\n";

echo "\n=== 7. PDF dengan Breakdown Test ===\n";
$res7 = Http::withToken($fatToken)->get("$baseUrl/payrolls/{$payroll->id}/pdf");
echo "Breakdown PDF Status: " . $res7->status() . "\n";
echo "Content-Type: " . $res7->header('Content-Type') . "\n";

echo "\n=== 8. Manual Payroll Lifecycle Regression ===\n";
// Create
$randMonth = rand(1, 12);
$randMonthStr = str_pad($randMonth, 2, '0', STR_PAD_LEFT);
$resCreate = Http::withToken($fatToken)->post("$baseUrl/payrolls", [
    'employee_id' => 1,
    'periode' => "2027-$randMonthStr-01",
    'gaji_pokok' => 5000000,
    'tunjangan' => 500000,
    'potongan' => 100000,
    'catatan' => 'Test Lifecycle',
]);
echo "Create Status: " . $resCreate->status() . "\n";
if ($resCreate->status() !== 201) {
    echo "Create Error: " . $resCreate->body() . "\n";
}
$jsonCreate = $resCreate->json();
$newPayrollId = $jsonCreate['id'] ?? $jsonCreate['data']['id'] ?? null;
echo "New ID: " . $newPayrollId . "\n";
if (!$newPayrollId) {
    print_r($jsonCreate);
}

if ($newPayrollId) {
    // Request
    $resReq = Http::withToken($fatToken)->post("$baseUrl/payrolls/{$newPayrollId}/request-payment");
    echo "Request Status: " . $resReq->status() . "\n";

    // Approve
    $resApprove = Http::withToken($dirToken)->post("$baseUrl/payrolls/{$newPayrollId}/approve", [
        'note' => 'OK'
    ]);
    echo "Approve Status: " . $resApprove->status() . "\n";

    // Mark Paid
    $resPaid = Http::withToken($fatToken)->post("$baseUrl/payrolls/{$newPayrollId}/mark-paid", [
        'paid_ref' => 'REF-001',
    ]);
    echo "Paid Status: " . $resPaid->status() . "\n";

    $final = Payroll::find($newPayrollId);
    echo "Final Status di DB: " . $final->status . "\n";
}
