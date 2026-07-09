<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CryptoKey;
use App\Services\Crypto\AppKeyProtector;

class CryptoGenerateRsaKey extends Command
{
    protected $signature = 'crypto:gen-rsa {name=payroll-rsa-2026-01}';
    protected $description = 'Generate RSA-2048 keypair and store in crypto_keys';

    public function handle(): int
    {
        $name = $this->argument('name');

        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if (!$res) {
            $this->error('openssl_pkey_new failed: ' . openssl_error_string());
            return self::FAILURE;
        }

        openssl_pkey_export($res, $privatePem);
        $details = openssl_pkey_get_details($res);
        $publicPem = $details['key'] ?? null;

        if (!$publicPem) {
            $this->error('Failed to get public key.');
            return self::FAILURE;
        }

        CryptoKey::where('status','active')->update(['status' => 'rotated']);

        CryptoKey::create([
            'name' => $name,
            'alg' => 'RSA-2048',
            'public_key_pem' => $publicPem,
            'private_key_pem_enc' => AppKeyProtector::enc($privatePem),
            'status' => 'active',
        ]);

        $this->info("OK: RSA key active = {$name}");
        return self::SUCCESS;
    }
}
