<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payrolls = \App\Models\Payroll::all();
foreach ($payrolls as $payroll) {
    try {
        $alg = strtoupper((string) ($payroll->salary_alg ?: 'AES'));
        
        // Decrypt values using the original alg
        $gaji = $tunj = $pot = $tot = null;
        $cat = null;
        if ($alg === 'HYBRID') continue; // already hybrid

        $gaji = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->gaji_pokok_enc, $payroll->gaji_pokok, $alg);
        $tunj = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->tunjangan_enc,  $payroll->tunjangan,  $alg);
        $pot  = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->potongan_enc,   $payroll->potongan,   $alg);
        $tot  = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->total_enc,      $payroll->total,      $alg);
        $cat  = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->catatan_enc,    $payroll->catatan,    $alg);
        $tAllow = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->total_allowances_enc, $payroll->total_allowances, $alg);
        $tDeduc = \App\Services\CryptoService::readEncryptedOrPlainSafe($payroll->total_deductions_enc, $payroll->total_deductions, $alg);

        // Generate hybrid encryption for these values
        // Note: we can't easily recalculate via service because some payrolls might be paid and service prevents it.
        // Let's just manually re-encrypt using HYBRID.
        
        $payload = [
            'gaji_pokok' => $gaji,
            'tunjangan' => $tunj,
            'potongan' => $pot,
            'total' => $tot,
            'catatan' => $cat,
            'total_allowances' => $tAllow,
            'total_deductions' => $tDeduc,
        ];
        
        $encData = \App\Services\CryptoService::encryptHybridPayroll(array_filter($payload, fn($v) => $v !== null));
        
        $payroll->update(array_merge($encData['fields'], [
            'dek_enc' => $encData['dek_enc'],
            'enc_meta' => $encData['enc_meta'],
            'salary_alg' => 'HYBRID'
        ]));
        
        echo "Payroll ID " . $payroll->id . " updated to HYBRID.\n";
    } catch (\Exception $e) {
        echo "Error on ID " . $payroll->id . ": " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
