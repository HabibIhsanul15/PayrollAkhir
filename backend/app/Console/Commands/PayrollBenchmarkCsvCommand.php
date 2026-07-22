<?php

namespace App\Console\Commands;

use App\Services\CryptoService;
use App\Services\PayrollCipherService;
use Illuminate\Console\Command;
use RuntimeException;

class PayrollBenchmarkCsvCommand extends Command
{
    protected $signature = 'payroll:benchmark-csv
        {file=storage/app/benchmark/04_benchmark_input_100.csv : Path CSV relatif terhadap folder backend atau path absolut}
        {--repeat=10 : Jumlah pengulangan}
        {--output=storage/app/benchmark-results : Folder hasil relatif terhadap folder backend atau path absolut}';

    protected $description = 'Benchmark AES-128-GCM, RSA-2048-OAEP, dan Hybrid RSA-AES menggunakan CSV payroll.';

    private const FIELDS = [
        'gaji_pokok',
        'tunjangan',
        'potongan',
        'total',
        'total_allowances',
        'total_deductions',
        'catatan',
    ];

    public function handle(PayrollCipherService $cipherService): int
    {
        $file = $this->resolvePath((string) $this->argument('file'));
        $outputDirectory = $this->resolvePath((string) $this->option('output'));
        $repeat = max(1, (int) $this->option('repeat'));

        if (! is_file($file)) {
            $this->error("File CSV tidak ditemukan: {$file}");
            return self::FAILURE;
        }

        $rows = $this->readDataset($file);

        if (count($rows) !== 100) {
            $this->warn('Dataset berisi '.count($rows).' transaksi. Rancangan penelitian menggunakan 100 transaksi.');
        }

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException("Folder hasil gagal dibuat: {$outputDirectory}");
        }

        $this->info('Dataset: '.count($rows).' transaksi');
        $this->info("Pengulangan: {$repeat} kali");
        $this->info('Melakukan warm-up agar proses inisialisasi tidak masuk hasil pengukuran...');

        foreach (['AES', 'RSA', 'HYBRID'] as $algorithm) {
            config(['crypto.payroll_write_alg' => $algorithm]);
            $pack = $cipherService->encrypt($rows[0]);
            $this->decryptPack($pack, $algorithm);
        }

        $detailResults = [];
        $algorithmOrders = [
            ['AES', 'RSA', 'HYBRID'],
            ['RSA', 'HYBRID', 'AES'],
            ['HYBRID', 'AES', 'RSA'],
        ];

        for ($iteration = 1; $iteration <= $repeat; $iteration++) {
            $this->newLine();
            $this->info("Pengulangan {$iteration}/{$repeat}");

            $order = $algorithmOrders[($iteration - 1) % count($algorithmOrders)];

            foreach ($order as $algorithm) {
                gc_collect_cycles();

                $result = $this->runOneIteration(
                    $cipherService,
                    $algorithm,
                    $rows,
                    $iteration
                );

                $detailResults[] = $result;

                $this->line(sprintf(
                    '  %-17s CREATE=%9.3f ms | READ=%8.3f ms | REPORT=%10.3f ms | STORAGE=%d byte',
                    $result['algorithm'],
                    $result['create_ms'],
                    $result['read_detail_ms'],
                    $result['report_ms'],
                    $result['storage_bytes']
                ));
            }
        }

        $summary = $this->summarize($detailResults);
        $timestamp = now()->format('Ymd_His');

        $detailPath = $outputDirectory."/benchmark_detail_{$timestamp}.csv";
        $summaryPath = $outputDirectory."/benchmark_summary_{$timestamp}.csv";

        $this->writeDetailCsv($detailPath, $detailResults);
        $this->writeSummaryCsv($summaryPath, $summary);

        $this->newLine();
        $this->info('=== RINGKASAN RATA-RATA ===');
        $this->table(
            [
                'Algoritma',
                'CREATE Avg (ms)',
                'READ Avg (ms)',
                'REPORT Avg (ms)',
                'STORAGE Avg (byte)',
            ],
            array_map(fn (array $row) => [
                $row['algorithm'],
                number_format($row['create_avg_ms'], 3, '.', ''),
                number_format($row['read_detail_avg_ms'], 3, '.', ''),
                number_format($row['report_avg_ms'], 3, '.', ''),
                number_format($row['storage_avg_bytes'], 0, '.', ''),
            ], $summary)
        );

        $this->newLine();
        $this->info("Detail hasil : {$detailPath}");
        $this->info("Ringkasan    : {$summaryPath}");

        return self::SUCCESS;
    }

    private function runOneIteration(
        PayrollCipherService $cipherService,
        string $algorithm,
        array $rows,
        int $iteration
    ): array {
        config(['crypto.payroll_write_alg' => $algorithm]);

        $packs = [];

        $start = hrtime(true);
        foreach ($rows as $row) {
            $packs[] = $cipherService->encrypt($row);
        }
        $createMs = $this->elapsedMs($start);

        $storageBytes = 0;
        foreach ($packs as $pack) {
            $encoded = json_encode($pack, JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('Payload hasil enkripsi gagal diubah menjadi JSON.');
            }
            $storageBytes += strlen($encoded);
        }

        $start = hrtime(true);
        $readResult = $this->decryptPack($packs[0], $algorithm);
        $readDetailMs = $this->elapsedMs($start);
        $this->assertSamePlain($rows[0], $readResult, $algorithm, 1);

        $start = hrtime(true);
        foreach ($packs as $index => $pack) {
            $plain = $this->decryptPack($pack, $algorithm);
            $this->assertSamePlain($rows[$index], $plain, $algorithm, $index + 1);
        }
        $reportMs = $this->elapsedMs($start);

        $count = count($rows);

        return [
            'iteration' => $iteration,
            'algorithm' => $this->algorithmLabel($algorithm),
            'data_count' => $count,
            'create_ms' => round($createMs, 6),
            'read_detail_ms' => round($readDetailMs, 6),
            'report_ms' => round($reportMs, 6),
            'storage_bytes' => $storageBytes,
            'storage_kb' => round($storageBytes / 1024, 6),
            'create_per_data_ms' => round($createMs / $count, 6),
            'report_per_data_ms' => round($reportMs / $count, 6),
        ];
    }

    private function decryptPack(array $pack, string $algorithm): array
    {
        if ($algorithm === 'HYBRID') {
            return CryptoService::decryptHybridPayrollRow([
                'dek_enc' => $pack['dek_enc'],
                'enc_meta' => $pack['enc_meta'],
                ...$pack['fields'],
            ]);
        }

        $result = [];

        foreach (self::FIELDS as $field) {
            $payload = $pack['fields'][$field.'_enc'] ?? null;

            if ($payload === null || $payload === '') {
                $result[$field] = '';
                continue;
            }

            $result[$field] = $algorithm === 'RSA'
                ? CryptoService::decryptRSA($payload)
                : CryptoService::decryptAESGCM($payload);
        }

        return $result;
    }

    private function readDataset(string $file): array
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new RuntimeException("CSV gagal dibuka: {$file}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('Header CSV tidak ditemukan.');
        }

        // Menghapus BOM UTF-8 dari header pertama.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $index = array_flip(array_map('trim', $header));
        $required = [
            'test_id',
            'employee_code',
            'periode',
            ...self::FIELDS,
        ];

        foreach ($required as $column) {
            if (! array_key_exists($column, $index)) {
                fclose($handle);
                throw new RuntimeException("Kolom CSV wajib tidak ditemukan: {$column}");
            }
        }

        $rows = [];

        while (($csvRow = fgetcsv($handle)) !== false) {
            if ($csvRow === [null] || count(array_filter($csvRow, fn (mixed $value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $plain = [];

            foreach (self::FIELDS as $field) {
                $plain[$field] = (string) ($csvRow[$index[$field]] ?? '');
            }

            $rows[] = $plain;
        }

        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('Dataset CSV tidak memiliki baris data.');
        }

        return $rows;
    }

    private function assertSamePlain(
        array $expected,
        array $actual,
        string $algorithm,
        int $rowNumber
    ): void {
        foreach (self::FIELDS as $field) {
            $expectedValue = (string) ($expected[$field] ?? '');
            $actualValue = (string) ($actual[$field] ?? '');

            if ($expectedValue !== $actualValue) {
                throw new RuntimeException(
                    "Validasi gagal pada {$algorithm}, data ke-{$rowNumber}, field {$field}. ".
                    "Expected={$expectedValue}; Actual={$actualValue}"
                );
            }
        }
    }

    private function summarize(array $details): array
    {
        $groups = [];

        foreach ($details as $row) {
            $groups[$row['algorithm']][] = $row;
        }

        $summary = [];

        foreach ($groups as $algorithm => $rows) {
            $summary[] = [
                'algorithm' => $algorithm,
                'repeat_count' => count($rows),
                'data_count' => $rows[0]['data_count'],
                ...$this->metricStats($rows, 'create_ms', 'create'),
                ...$this->metricStats($rows, 'read_detail_ms', 'read_detail'),
                ...$this->metricStats($rows, 'report_ms', 'report'),
                ...$this->metricStats($rows, 'storage_bytes', 'storage'),
            ];
        }

        usort($summary, function (array $left, array $right): int {
            $order = [
                'AES-128-GCM' => 1,
                'RSA-2048-OAEP' => 2,
                'Hybrid RSA-AES' => 3,
            ];

            return ($order[$left['algorithm']] ?? 99) <=> ($order[$right['algorithm']] ?? 99);
        });

        return $summary;
    }

    private function metricStats(array $rows, string $column, string $prefix): array
    {
        $values = array_map(fn (array $row) => (float) $row[$column], $rows);
        $average = array_sum($values) / count($values);

        return [
            "{$prefix}_avg_".($prefix === 'storage' ? 'bytes' : 'ms') => round($average, 6),
            "{$prefix}_min_".($prefix === 'storage' ? 'bytes' : 'ms') => round(min($values), 6),
            "{$prefix}_max_".($prefix === 'storage' ? 'bytes' : 'ms') => round(max($values), 6),
        ];
    }

    private function writeDetailCsv(string $path, array $rows): void
    {
        $headers = [
            'pengulangan',
            'algoritma',
            'jumlah_data',
            'create_ms',
            'read_detail_ms',
            'report_ms',
            'storage_bytes',
            'storage_kb',
            'create_per_data_ms',
            'report_per_data_ms',
        ];

        $this->writeCsv($path, $headers, array_map(fn (array $row) => [
            $row['iteration'],
            $row['algorithm'],
            $row['data_count'],
            $row['create_ms'],
            $row['read_detail_ms'],
            $row['report_ms'],
            $row['storage_bytes'],
            $row['storage_kb'],
            $row['create_per_data_ms'],
            $row['report_per_data_ms'],
        ], $rows));
    }

    private function writeSummaryCsv(string $path, array $rows): void
    {
        $headers = [
            'algoritma',
            'jumlah_pengulangan',
            'jumlah_data',
            'create_avg_ms',
            'create_min_ms',
            'create_max_ms',
            'read_detail_avg_ms',
            'read_detail_min_ms',
            'read_detail_max_ms',
            'report_avg_ms',
            'report_min_ms',
            'report_max_ms',
            'storage_avg_bytes',
            'storage_min_bytes',
            'storage_max_bytes',
        ];

        $this->writeCsv($path, $headers, array_map(fn (array $row) => [
            $row['algorithm'],
            $row['repeat_count'],
            $row['data_count'],
            $row['create_avg_ms'],
            $row['create_min_ms'],
            $row['create_max_ms'],
            $row['read_detail_avg_ms'],
            $row['read_detail_min_ms'],
            $row['read_detail_max_ms'],
            $row['report_avg_ms'],
            $row['report_min_ms'],
            $row['report_max_ms'],
            $row['storage_avg_bytes'],
            $row['storage_min_bytes'],
            $row['storage_max_bytes'],
        ], $rows));
    }

    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("File hasil gagal dibuat: {$path}");
        }

        // BOM agar teks terbaca baik saat dibuka dengan Excel.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    private function elapsedMs(int $start): float
    {
        return (hrtime(true) - $start) / 1_000_000;
    }

    private function algorithmLabel(string $algorithm): string
    {
        return match ($algorithm) {
            'AES' => 'AES-128-GCM',
            'RSA' => 'RSA-2048-OAEP',
            'HYBRID' => 'Hybrid RSA-AES',
            default => $algorithm,
        };
    }

    private function resolvePath(string $path): string
    {
        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
        ) {
            return $path;
        }

        return base_path($path);
    }
}
