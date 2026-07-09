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

// Temukan atau buat Employee untuk test mask nominal
$employeeUser = User::where('role', 'employee')->first();
if (!$employeeUser) {
    $employeeUser = User::create(['name'=>'Emp', 'email'=>'emp'.rand().'@test.com', 'password'=>bcrypt('password'), 'role'=>'employee']);
}

$fatToken = $fat->createToken('test')->plainTextToken;
$dirToken = $director->createToken('test')->plainTextToken;
$empToken = $employeeUser->createToken('test')->plainTextToken;

$baseUrl = 'http://127.0.0.1:8000/api';

echo "=== 1. Lifecycle Regression (with valid proof) ===\n";
// Bikin Draft
$randYear = rand(2060, 2080);
$randMonth = rand(1, 12);
$randMonthStr = str_pad($randMonth, 2, '0', STR_PAD_LEFT);
$resCreate = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls", [
    'employee_id' => 1,
    'periode' => "$randYear-$randMonthStr-01",
    'gaji_pokok' => 5000000,
    'tunjangan' => 500000,
    'potongan' => 100000,
    'catatan' => 'Test Lifecycle',
]);
$jsonCreate = $resCreate->json();
$newId = $jsonCreate['id'] ?? $jsonCreate['data']['id'] ?? null;
echo "Create Draft HTTP: " . $resCreate->status() . "\n";
if (!$newId) {
    echo "Error: " . $resCreate->body() . "\n";
    exit;
}

// Request Payment
$resReq = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls/{$newId}/request-payment");
echo "Request Payment HTTP: " . $resReq->status() . "\n";

// Approve
$resApprove = Http::withToken($dirToken)->acceptJson()->post("$baseUrl/payrolls/{$newId}/approve", ['note' => 'OK']);
echo "Approve HTTP: " . $resApprove->status() . "\n";

// Mark Paid dengan lampiran proof
file_put_contents('dummy_proof.pdf', "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n2 0 obj\n<</Type/Pages/Count 0/Kids[]>>\nendobj\nxref\n0 3\n0000000000 65535 f\n0000000015 00000 n\n0000000062 00000 n\ntrailer\n<</Size 3/Root 1 0 R>>\nstartxref\n113\n%%EOF");
$resPaid = Http::withToken($fatToken)->acceptJson()
    ->attach('proof', file_get_contents('dummy_proof.pdf'), 'dummy_proof.pdf')
    ->post("$baseUrl/payrolls/{$newId}/mark-paid", [
        'paid_ref' => 'REF-' . rand(1000, 9999),
    ]);
echo "Mark Paid HTTP: " . $resPaid->status() . "\n";
if ($resPaid->status() !== 200) {
    echo "Mark Paid Error: " . $resPaid->body() . "\n";
}

$final = Payroll::find($newId);
echo "Final DB status: " . $final->status . "\n";
echo "paid_proof_path: " . ($final->paid_proof_path ? 'Terisi ('.$final->paid_proof_path.')' : 'Kosong') . "\n";
echo "paid_by: " . ($final->paid_by ? 'Terisi ('.$final->paid_by.')' : 'Kosong') . "\n";
echo "paid_at: " . ($final->paid_at ? 'Terisi ('.$final->paid_at.')' : 'Kosong') . "\n";

echo "\n=== 2. Report Legacy (Data Paid) ===\n";
// Menggunakan payroll ID yang baru saja jadi Paid di atas (tanpa breakdown allowances/deductions = merepresentasikan data legacy lama)
$month = substr($final->periode, 0, 7);
$resReport = Http::withToken($dirToken)->acceptJson()->get("$baseUrl/reports/payroll?month=$month");
echo "Report HTTP: " . $resReport->status() . "\n";
$reportData = $resReport->json();
$found = collect($reportData['rows'] ?? [])->firstWhere('id', $newId);
if ($found) {
    echo "Data Paid Muncul di Response? YES\n";
    echo "Gaji Pokok: " . $found['gaji_pokok'] . "\n";
    echo "Total: " . $found['total'] . "\n";
} else {
    echo "Data Paid Muncul di Response? NO\n";
}

echo "\n=== 5. Dummy Breakdown JSON (FAT vs Employee) ===\n";
// Bikin dummy draft khusus untuk ngetest masked role
$randDummyYear = rand(2030, 2050);
$randDummyMonthStr = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
$resCreateDummy = Http::withToken($fatToken)->acceptJson()->post("$baseUrl/payrolls", [
    'employee_id' => 1,
    'periode' => "$randDummyYear-$randDummyMonthStr-01",
    'gaji_pokok' => 10000000,
    'tunjangan' => 2000000,
    'potongan' => 500000,
    'catatan' => 'Dummy breakdown test',
]);
if ($resCreateDummy->status() !== 201) {
    echo "Dummy Create Error: " . $resCreateDummy->body() . "\n";
    exit;
}
$jsonDummy = $resCreateDummy->json();
$dummyId = $jsonDummy['id'] ?? $jsonDummy['data']['id'] ?? $jsonDummy['payroll']['id'] ?? null;
if (!$dummyId) {
    echo "Dummy ID Not Found. JSON: "; print_r($jsonDummy); exit;
}
$dummyPayroll = Payroll::find($dummyId);
$allowanceType = AllowanceType::firstOrCreate(['name' => 'Tunjangan Transport', 'code' => 'TRP', 'is_active' => true]);

$dummyPayroll->update([
    'total_allowances_enc' => App\Services\CryptoService::encryptAESGCM('2000000'),
    'total_deductions_enc' => App\Services\CryptoService::encryptAESGCM('500000'),
]);
PayrollAllowance::create([
    'payroll_id' => $dummyId, 'allowance_type_id' => $allowanceType->id,
    'amount' => 2000000, 'amount_enc' => App\Services\CryptoService::encryptAESGCM('2000000'),
    'salary_alg' => 'AES', 'condition_met' => true
]);
PayrollDeduction::create([
    'payroll_id' => $dummyId, 'deduction_type' => 'late_penalty',
    'amount' => 500000, 'amount_enc' => App\Services\CryptoService::encryptAESGCM('500000'),
    'salary_alg' => 'AES', 'deduction_label' => 'Terlambat'
]);

$resFat = Http::withToken($fatToken)->acceptJson()->get("$baseUrl/payrolls/$dummyId");
$jsonFat = $resFat->json();
echo "[FAT View]\n";
echo "Allowances count: " . count($jsonFat['allowances']) . "\n";
echo "Deductions count: " . count($jsonFat['deductions']) . "\n";
echo "First Allowance Amount: " . $jsonFat['allowances'][0]['amount'] . "\n";

$resEmp = Http::withToken($empToken)->acceptJson()->get("$baseUrl/payrolls/$dummyId");
$jsonEmp = $resEmp->json();
echo "[Employee View] (No Access)\n";
echo "Masked state: " . ($jsonEmp['masked'] ? 'true' : 'false') . "\n";
echo "First Allowance Amount: " . ($jsonEmp['allowances'][0]['amount'] ?? 'MASKED/NULL') . "\n";
echo "Total Allowances: " . ($jsonEmp['total_allowances'] ?? 'MASKED/NULL') . "\n";
