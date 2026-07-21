<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CryptoService;

class PayrollBenchmarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:benchmark {--count=100 : Number of iterations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark AES-128, RSA-2048, and Hybrid RSA-AES as defined in BAB 4 TA';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        $this->info("Running BAB 4 TA Benchmark with {$count} iterations...");

        $dummyPlain = [
            'gaji_pokok' => '5000000',
            'tunjangan' => '2000000',
            'potongan' => '500000',
            'total' => '6500000',
            'catatan' => 'Gaji bulan ini'
        ];
        $dummyPlainStr = json_encode($dummyPlain);

        $results = [];

        // 1. AES-128
        $this->info("Testing AES-128...");
        $results[] = $this->runTest('AES-128', $count, function() use ($dummyPlainStr) {
            return CryptoService::encryptAESGCM($dummyPlainStr);
        }, function($ct) {
            return CryptoService::decryptAESGCM($ct);
        });

        // 2. RSA-2048
        $this->info("Testing RSA-2048...");
        $results[] = $this->runTest('RSA-2048', $count, function() use ($dummyPlainStr) {
            return CryptoService::encryptRSA($dummyPlainStr);
        }, function($ct) {
            return CryptoService::decryptRSA($ct);
        });

        // 3. Hybrid RSA-AES
        $this->info("Testing Hybrid RSA-AES...");
        $results[] = $this->runHybridTest('Hybrid RSA-AES', $count, $dummyPlain);

        $this->newLine();
        $this->info("=== Hasil Pengujian Performa (Sesuai Tabel 4.2 BAB 4 TA) ===");
        $this->table(
            ['Metode Enkripsi', 'Jumlah Data Payroll', 'CREATE - Enkripsi (ms)', 'READ_DETAIL - Dekripsi (ms)', 'REPORT - Dekripsi (ms)'],
            array_map(function($r) use ($count) {
                return [
                    $r['alg'],
                    $count . ' data',
                    number_format($r['create_ms'], 3, ',', '.'),
                    number_format($r['read_detail_ms'], 3, ',', '.'),
                    number_format($r['report_ms'], 3, ',', '.'),
                ];
            }, $results)
        );
        
        $this->newLine();
        $this->info("Benchmark Selesai.");
    }

    private function runTest($alg, $count, $encryptFn, $decryptFn)
    {
        $ciphertexts = [];
        
        // CREATE (Encryption Time)
        $start = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            $ciphertexts[] = $encryptFn();
        }
        $createTime = (microtime(true) - $start) * 1000;

        // READ_DETAIL (Decryption Time single)
        $start = microtime(true);
        $decryptFn($ciphertexts[0]);
        $readDetailTime = (microtime(true) - $start) * 1000;

        // REPORT (Decryption Time all)
        $start = microtime(true);
        foreach ($ciphertexts as $ct) {
            $decryptFn($ct);
        }
        $reportTime = (microtime(true) - $start) * 1000;

        return [
            'alg' => $alg,
            'create_ms' => $createTime,
            'read_detail_ms' => $readDetailTime,
            'report_ms' => $reportTime
        ];
    }

    private function runHybridTest($alg, $count, $dummyFields)
    {
        $rows = [];
        
        // CREATE (Encryption Time)
        $start = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            $rows[] = CryptoService::encryptHybridPayroll($dummyFields);
        }
        $createTime = (microtime(true) - $start) * 1000;

        // Prepare rows for decryption
        $dbRows = [];
        foreach ($rows as $r) {
            // enc_meta needs to be an array for decryptHybridPayrollRow
            $dbRow = ['dek_enc' => $r['dek_enc'], 'enc_meta' => $r['enc_meta']];
            foreach ($r['fields'] as $k => $v) {
                $dbRow[$k] = $v;
            }
            $dbRows[] = $dbRow;
        }

        // READ_DETAIL (Decryption Time single)
        $start = microtime(true);
        CryptoService::decryptHybridPayrollRow($dbRows[0]);
        $readDetailTime = (microtime(true) - $start) * 1000;

        // REPORT (Decryption Time all)
        $start = microtime(true);
        foreach ($dbRows as $row) {
            CryptoService::decryptHybridPayrollRow($row);
        }
        $reportTime = (microtime(true) - $start) * 1000;

        return [
            'alg' => $alg,
            'create_ms' => $createTime,
            'read_detail_ms' => $readDetailTime,
            'report_ms' => $reportTime
        ];
    }
}
