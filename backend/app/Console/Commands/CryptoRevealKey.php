<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CryptoKey;
use Illuminate\Support\Facades\Crypt;

class CryptoRevealKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:reveal
                            {--public : Tampilkan PEM public key aktif}
                            {--public-base64 : Tampilkan public key lengkap tanpa header/footer PEM}
                            {--components : Tampilkan komponen public RSA n dan e dalam hex}
                            {--test-private : LOCAL saja: tampilkan private key PEM untuk demonstrasi TA}
                            {--test-private-base64 : LOCAL saja: tampilkan private key lengkap tanpa PEM untuk demonstrasi TA}
                            {--test-private-components : LOCAL saja: tampilkan komponen private RSA untuk demonstrasi TA}
                            {--force : Konfirmasi eksplisit untuk opsi test private key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tampilkan status, fingerprint, dan opsional public key RSA aktif';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = CryptoKey::where('status', 'active')->first();

        if (! $key) {
            $this->error('Tidak ada kunci kriptografi yang aktif di database!');

            return self::FAILURE;
        }

        $publicKey = openssl_pkey_get_public($key->public_key_pem);
        if ($publicKey === false) {
            $this->error('Public key aktif tidak valid atau rusak.');

            return self::FAILURE;
        }

        $details = openssl_pkey_get_details($publicKey);
        if ($details === false) {
            $this->error('Detail public key RSA tidak dapat dibaca.');

            return self::FAILURE;
        }
        $fingerprint = strtoupper(implode(':', str_split(hash('sha256', $key->public_key_pem), 2)));

        $this->info('Kunci RSA aktif ditemukan.');
        $this->table(['Properti', 'Nilai'], [
            ['ID', $key->id],
            ['Nama', $key->name],
            ['Status', $key->status],
            ['Algoritma', $key->alg],
            ['Ukuran RSA', ($details['bits'] ?? '-') . ' bit'],
            ['Fingerprint SHA-256 public key', $fingerprint],
            ['Private key tersimpan terenkripsi', $key->private_key_pem_enc !== '' ? 'ya' : 'tidak'],
        ]);

        $outputOptions = [
            'public' => $this->option('public'),
            'public-base64' => $this->option('public-base64'),
            'components' => $this->option('components'),
            'test-private' => $this->option('test-private'),
            'test-private-base64' => $this->option('test-private-base64'),
            'test-private-components' => $this->option('test-private-components'),
        ];
        $selectedOptions = array_keys(array_filter($outputOptions));

        if (count($selectedOptions) > 1) {
            $this->error('Gunakan tepat satu opsi output key.');

            return self::FAILURE;
        }

        if ($this->option('public')) {
            $this->newLine();
            $this->info('Public key PEM:');
            $this->line($key->public_key_pem);
        } elseif ($this->option('public-base64')) {
            $publicKeyBase64 = preg_replace(
                '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/',
                '',
                $key->public_key_pem
            );

            $this->newLine();
            $this->info('Public key Base64 (format DER, tanpa PEM):');
            $this->line($publicKeyBase64);
        } elseif ($this->option('components')) {
            $this->info('Komponen public RSA (hex):');
            $this->showRsaComponents($details, ['n', 'e']);
        } elseif ($this->option('test-private') || $this->option('test-private-base64') || $this->option('test-private-components')) {
            if (! $this->laravel->environment('local')) {
                $this->error('Private key hanya boleh ditampilkan pada APP_ENV=local.');

                return self::FAILURE;
            }

            if (! $this->option('force')) {
                $this->error('Tambahkan --force untuk mengonfirmasi tampilan private key pada lingkungan testing.');

                return self::FAILURE;
            }

            try {
                $privatePem = Crypt::decryptString($key->private_key_pem_enc);
            } catch (\Throwable) {
                $this->error('Private key tidak dapat dibuka dengan APP_KEY saat ini.');

                return self::FAILURE;
            }

            $this->warn('MODE DEMO TA: jangan salin private key ke laporan, Git, atau aplikasi lain.');

            if ($this->option('test-private')) {
                $this->newLine();
                $this->info('Private key PEM (khusus local/testing):');
                $this->line($privatePem);
            } elseif ($this->option('test-private-base64')) {
                $privateKeyBase64 = preg_replace(
                    '/-----BEGIN (?:RSA )?PRIVATE KEY-----|-----END (?:RSA )?PRIVATE KEY-----|\s+/',
                    '',
                    $privatePem
                );

                $this->newLine();
                $this->info('Private key Base64 (tanpa PEM, khusus local/testing):');
                $this->line($privateKeyBase64);
            } else {
                $privateKey = openssl_pkey_get_private($privatePem);
                if ($privateKey === false) {
                    $this->error('Private key RSA tidak valid.');

                    return self::FAILURE;
                }

                $privateDetails = openssl_pkey_get_details($privateKey);
                if ($privateDetails === false) {
                    $this->error('Detail private key RSA tidak dapat dibaca.');

                    return self::FAILURE;
                }
                $this->info('Komponen private RSA (hex, khusus local/testing):');
                $this->showRsaComponents($privateDetails, ['n', 'e', 'd', 'p', 'q', 'dmp1', 'dmq1', 'iqmp']);
            }
        } else {
            $this->comment('Gunakan --public, --public-base64, atau --components; opsi private tersedia khusus demo pada APP_ENV=local.');
        }

        $this->comment('DEK dan ciphertext tidak pernah ditampilkan oleh command ini.');

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $details
     * @param array<int, string> $components
     */
    private function showRsaComponents(array $details, array $components): void
    {
        $labels = [
            'n' => 'n (modulus)',
            'e' => 'e (public exponent)',
            'd' => 'd (private exponent)',
            'p' => 'p (prime factor)',
            'q' => 'q (prime factor)',
            'dmp1' => 'd mod (p - 1)',
            'dmq1' => 'd mod (q - 1)',
            'iqmp' => 'q⁻¹ mod p',
        ];

        $rsa = $details['rsa'] ?? [];
        $rows = [];
        foreach ($components as $component) {
            $value = $rsa[$component] ?? null;
            $rows[] = [
                $labels[$component] ?? $component,
                is_string($value) ? strtoupper(bin2hex($value)) : '-',
            ];
        }

        $this->table(['Komponen', 'Nilai hex'], $rows);
    }
}
