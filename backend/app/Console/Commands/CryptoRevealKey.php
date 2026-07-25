<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CryptoKey;

class CryptoRevealKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:reveal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifikasi status kunci RSA tanpa menampilkan material kunci privat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Ambil Kunci Aktif dari Database
        $key = CryptoKey::where('status', 'active')->first();

        if (!$key) {
            $this->error("Tidak ada kunci kriptografi yang aktif di database!");
            return;
        }

        $this->info('Kunci RSA aktif ditemukan.');
        $this->line('ID: '.$key->id);
        $this->line('Algoritma: '.$key->alg);
        $this->line('Public key tersedia: '.($key->public_key_pem !== '' ? 'ya' : 'tidak'));
        $this->line('Private key tersimpan terenkripsi: '.($key->private_key_pem_enc !== '' ? 'ya' : 'tidak'));
        $this->comment('Material private key dan ciphertext tidak pernah ditampilkan oleh command ini.');
    }
}
