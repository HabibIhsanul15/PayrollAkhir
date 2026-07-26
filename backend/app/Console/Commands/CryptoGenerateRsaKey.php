<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use App\Models\CryptoKey;
use App\Services\Crypto\AppKeyProtector;

class CryptoGenerateRsaKey extends Command
{
    protected $signature = 'crypto:gen-rsa {name=payroll-rsa-2026-01}';
    protected $description = 'Generate RSA-2048 keypair and store in crypto_keys';

    public function handle(): int
    {
        $name = $this->argument('name');

        $config = ['config' => 'C:\xampp\php\extras\ssl\openssl.cnf'];
        if (!file_exists('C:\xampp\php\extras\ssl\openssl.cnf')) {
            $config['config'] = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\extras\ssl\openssl.cnf';
        }

        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ] + $config);

        if (!$res) {
            $this->error('openssl_pkey_new failed: ' . openssl_error_string());
            return self::FAILURE;
        }

        openssl_pkey_export($res, $privatePem, null, $config);
        $details = openssl_pkey_get_details($res);
        $publicPem = $details['key'] ?? null;

        if (!$publicPem) {
            $this->error('Failed to get public key.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $publicPem, $privatePem): void {
                // Kunci aktif yang ada dikunci lalu dirotasi sebelum key baru
                // dibuat. Unique guard di database menjadi lapisan terakhir
                // bila ada dua proses rotasi berjalan bersamaan.
                CryptoKey::where('status', 'active')->lockForUpdate()->update(['status' => 'rotated']);

                CryptoKey::create([
                    'name' => $name,
                    'alg' => 'RSA-2048',
                    'public_key_pem' => $publicPem,
                    'private_key_pem_enc' => AppKeyProtector::enc($privatePem),
                    'status' => 'active',
                ]);
            });
        } catch (QueryException) {
            $this->error('Rotasi RSA gagal: database menolak lebih dari satu key aktif.');

            return self::FAILURE;
        }

        $this->info("OK: RSA key active = {$name}");
        return self::SUCCESS;
    }
}
