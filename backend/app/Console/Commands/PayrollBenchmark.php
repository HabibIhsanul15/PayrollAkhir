<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Payroll;
use App\Models\User;
use App\Models\PerfLog;
use App\Services\CryptoService;

class PayrollBenchmark extends Command
{
    protected $signature = 'payroll:benchmark
        {--mode=create : create|read}
        {--n=100 : jumlah record}
        {--employee_id= : employee id (wajib untuk mode=create)}
        {--user_id= : user id pembuat payroll (optional, default ambil user pertama)}
        {--cat_len=100 : panjang catatan (konsisten untuk fairness)}
        {--start_periode=2026-01-01 : tanggal periode awal (Y-m-d)}';

    protected $description = 'Benchmark payroll crypto: generate payroll (create) dan uji read/decrypt. Simpan hasil ke perf_logs.';

    public function handle(): int
    {
        $mode = strtolower((string) $this->option('mode'));
        $n = (int) $this->option('n');
        $alg = strtoupper((string) env('PAYROLL_WRITE_ALG', 'AES')); // dari .env

        if ($n <= 0) {
            $this->error("n harus > 0");
            return self::FAILURE;
        }

        if (!in_array($mode, ['create','read'], true)) {
            $this->error("mode harus create atau read");
            return self::FAILURE;
        }

        $this->info("=== PAYROLL BENCHMARK ===");
        $this->info("Mode: {$mode}");
        $this->info("Alg (from .env PAYROLL_WRITE_ALG): {$alg}");
        $this->info("N: {$n}");

        if ($mode === 'create') {
            $employeeId = (int) $this->option('employee_id');
            if ($employeeId <= 0) {
                $this->error("--employee_id wajib untuk mode=create");
                return self::FAILURE;
            }

            $userIdOpt = $this->option('user_id');
            $userId = $userIdOpt ? (int) $userIdOpt : (int) (User::query()->value('id') ?? 0);
            if ($userId <= 0) {
                $this->error("Tidak menemukan user untuk user_id. Isi --user_id secara manual.");
                return self::FAILURE;
            }

            $catLen = (int) $this->option('cat_len');
            if ($catLen < 0) $catLen = 0;

            $startPeriode = (string) $this->option('start_periode');
            $periode0 = Carbon::parse($startPeriode)->startOfMonth();

            // Catatan konsisten panjangnya
            $catatan = $catLen > 0 ? str_repeat('A', $catLen) : null;

            $this->line("Employee ID: {$employeeId}");
            $this->line("User ID: {$userId}");
            $this->line("Periode mulai: " . $periode0->toDateString());
            $this->line("Catatan length: {$catLen}");

            $created = 0;
            for ($i = 0; $i < $n; $i++) {
                // periode harus unik per employee (karena store() kamu ada validasi duplikat)
                $periode = (clone $periode0)->addMonths($i);

                // angka random tapi konsisten range (fairness)
                $gaji = random_int(3_000_000, 10_000_000);
                $tunj = random_int(0, 2_000_000);
                $pot  = random_int(0, 1_000_000);
                $total = $gaji + $tunj - $pot;

                // ==== encrypt timer ====
                $t0_enc = hrtime(true);

                $gaji_enc  = $this->encryptByAlg((string) $gaji, $alg);
                $tunj_enc  = $this->encryptByAlg((string) $tunj, $alg);
                $pot_enc   = $this->encryptByAlg((string) $pot,  $alg);
                $total_enc = $this->encryptByAlg((string) $total, $alg);
                $cat_enc   = $catatan !== null ? $this->encryptByAlg($catatan, $alg) : null;

                $enc_ms = (hrtime(true) - $t0_enc) / 1e6;

                // key id (konsisten sama store kamu)
                $keyId = match ($alg) {
                    'RSA'    => CryptoService::rsaKeyId(),
                    'HYBRID' => CryptoService::rsaKeyId(),
                    default  => CryptoService::keyId(),
                };

                // ==== db timer ====
                $t0_db = hrtime(true);

                $payroll = Payroll::create([
                    'user_id'     => $userId,
                    'employee_id' => $employeeId,
                    'periode'     => $periode->toDateString(),

                    'gaji_pokok' => null,
                    'tunjangan'  => null,
                    'potongan'   => null,
                    'total'      => null,
                    'catatan'    => null,

                    'gaji_pokok_enc' => $gaji_enc,
                    'tunjangan_enc'  => $tunj_enc,
                    'potongan_enc'   => $pot_enc,
                    'total_enc'      => $total_enc,
                    'catatan_enc'    => $cat_enc,

                    'salary_alg'    => $alg,
                    'salary_key_id' => $keyId,
                ]);

                $db_ms = (hrtime(true) - $t0_db) / 1e6;

                $total_ms = $enc_ms + $db_ms; // khusus benchmark create: fokus encrypt+db

                PerfLog::create([
                    'scenario' => 'CREATE',
                    'alg' => $alg,
                    'payroll_id' => $payroll->id,
                    'encrypt_ms' => $enc_ms,
                    'db_ms' => $db_ms,
                    'total_ms' => $total_ms,
                    'meta' => [
                        'read_mode' => env('PAYROLL_READ_MODE'),
                        'storage_mode' => env('SALARY_STORAGE_MODE'),
                        'cat_len' => $catLen,
                        'periode' => $periode->toDateString(),
                    ],
                ]);

                $created++;
                if ($created % 10 === 0) {
                    $this->info("Created: {$created}/{$n}");
                }
            }

            $this->info("DONE create. Total dibuat: {$created}");
            $this->info("Silakan lanjut mode=read untuk uji decrypt.");
            return self::SUCCESS;
        }

        // =========================
        // MODE READ
        // =========================
        // Ambil N payroll terbaru sesuai alg (salary_alg)
        $rows = Payroll::query()
            ->where('salary_alg', $alg)
            ->orderByDesc('id')
            ->limit($n)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("Tidak ada payroll untuk alg {$alg}. Jalankan mode=create dulu.");
            return self::SUCCESS;
        }

        $this->line("Payroll ditemukan untuk READ: " . $rows->count());

        $done = 0;
        foreach ($rows as $p) {
            $t0_total = hrtime(true);

            // DB time untuk read (simulasi fetch fields sudah terjadi; kita ukur minimal)
            // Jika mau DB ms beneran: lakukan fresh query by id:
            $t0_db = hrtime(true);
            $fresh = Payroll::query()->find($p->id);
            $db_ms = (hrtime(true) - $t0_db) / 1e6;

            // decrypt time
            $t0_dec = hrtime(true);

            // paksa decrypt (CIPHER_ONLY)
            $gaji  = CryptoService::readEncryptedOrPlain($fresh->gaji_pokok_enc, $fresh->gaji_pokok, $alg);
            $tunj  = CryptoService::readEncryptedOrPlain($fresh->tunjangan_enc,  $fresh->tunjangan,  $alg);
            $pot   = CryptoService::readEncryptedOrPlain($fresh->potongan_enc,   $fresh->potongan,   $alg);
            $total = CryptoService::readEncryptedOrPlain($fresh->total_enc,      $fresh->total,      $alg);
            $cat   = CryptoService::readEncryptedOrPlain($fresh->catatan_enc,    $fresh->catatan,    $alg);

            // biar compiler ga anggap unused (sekalian sanity)
            if ($gaji === null || $total === null) {
                // nggak fail, tapi kasih hint
                $this->warn("Decrypt returned null for payroll_id={$fresh->id}");
            }

            $dec_ms = (hrtime(true) - $t0_dec) / 1e6;
            $total_ms = (hrtime(true) - $t0_total) / 1e6;

            PerfLog::create([
                'scenario' => 'READ_DETAIL',
                'alg' => $alg,
                'payroll_id' => $fresh->id,
                'decrypt_ms' => $dec_ms,
                'db_ms' => $db_ms,
                'total_ms' => $total_ms,
                'meta' => [
                    'masked' => false,
                ],
            ]);

            $done++;
            if ($done % 10 === 0) {
                $this->info("Read: {$done}/" . $rows->count());
            }
        }

        $this->info("DONE read. Total dibaca: {$done}");
        return self::SUCCESS;
    }

    private function encryptByAlg(string $plain, string $alg): string
    {
        return match ($alg) {
            'RSA'    => CryptoService::encryptRSA($plain),
            'HYBRID' => CryptoService::encryptHybrid($plain),
            default  => CryptoService::encryptAESGCM($plain),
        };
    }
}
