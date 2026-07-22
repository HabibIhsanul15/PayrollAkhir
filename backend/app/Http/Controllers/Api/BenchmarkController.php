<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CryptoService;
use Closure;
use Illuminate\Http\Request;

class BenchmarkController extends Controller
{
    public function index(Request $request)
    {
        $count = (int) $request->query('count', 100);

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
        $results[] = $this->runTest('AES-128', $count, function() use ($dummyPlainStr) {
            return CryptoService::encryptAESGCM($dummyPlainStr);
        }, function(string $ct) {
            return CryptoService::decryptAESGCM($ct);
        });

        // 2. RSA-2048
        $results[] = $this->runTest('RSA-2048', $count, function() use ($dummyPlainStr) {
            return CryptoService::encryptRSA($dummyPlainStr);
        }, function(string $ct) {
            return CryptoService::decryptRSA($ct);
        });

        // 3. Hybrid RSA-AES
        $results[] = $this->runHybridTest('Hybrid RSA-AES', $count, $dummyPlain);

        return response()->json([
            'count' => $count,
            'results' => $results
        ]);
    }

    private function runTest(string $alg, int $count, Closure $encryptFn, Closure $decryptFn): array
    {
        $ciphertexts = [];
        
        $start = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            $ciphertexts[] = $encryptFn();
        }
        $createTime = (microtime(true) - $start) * 1000;

        $start = microtime(true);
        $decryptFn($ciphertexts[0]);
        $readDetailTime = (microtime(true) - $start) * 1000;

        $start = microtime(true);
        foreach ($ciphertexts as $ct) {
            $decryptFn($ct);
        }
        $reportTime = (microtime(true) - $start) * 1000;

        return [
            'alg' => $alg,
            'create_ms' => round($createTime, 3),
            'read_detail_ms' => round($readDetailTime, 3),
            'report_ms' => round($reportTime, 3)
        ];
    }

    private function runHybridTest(string $alg, int $count, array $dummyFields): array
    {
        $rows = [];
        
        $start = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            $rows[] = CryptoService::encryptHybridPayroll($dummyFields);
        }
        $createTime = (microtime(true) - $start) * 1000;

        $dbRows = [];
        foreach ($rows as $r) {
            $dbRow = ['dek_enc' => $r['dek_enc'], 'enc_meta' => $r['enc_meta']];
            foreach ($r['fields'] as $k => $v) {
                $dbRow[$k] = $v;
            }
            $dbRows[] = $dbRow;
        }

        $start = microtime(true);
        CryptoService::decryptHybridPayrollRow($dbRows[0]);
        $readDetailTime = (microtime(true) - $start) * 1000;

        $start = microtime(true);
        foreach ($dbRows as $row) {
            CryptoService::decryptHybridPayrollRow($row);
        }
        $reportTime = (microtime(true) - $start) * 1000;

        return [
            'alg' => $alg,
            'create_ms' => round($createTime, 3),
            'read_detail_ms' => round($readDetailTime, 3),
            'report_ms' => round($reportTime, 3)
        ];
    }
}
