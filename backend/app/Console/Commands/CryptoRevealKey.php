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
    protected $signature = 'crypto:reveal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Script edukasi untuk melihat bentuk asli Kunci Privat RSA yang terenkripsi oleh APP_KEY';

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

        $this->info("================================================");
        $this->info("1. BENTUK KUNCI PUBLIK (Tidak Rahasia)");
        $this->info("================================================");
        $this->line("Kunci ini disebarkan bebas untuk MENGGEMBOK kunci DEK (AES).");
        $this->line($key->public_key_pem);
        
        $this->info("\n================================================");
        $this->info("2. BENTUK KUNCI PRIVAT DI DATABASE (Terenkripsi)");
        $this->info("================================================");
        $this->line("Ini yang dilihat Hacker jika mereka berhasil menembus database MySQL-mu.");
        $this->line("Bentuknya adalah teks acak panjang karena dikunci oleh APP_KEY.");
        $this->line(substr($key->private_key_pem_enc, 0, 150) . ".... (terpotong agar rapi)");

        $this->info("\n================================================");
        $this->info("3. BENTUK ASLI KUNCI PRIVAT (Sangat Rahasia)");
        $this->info("================================================");
        $this->line("Ini adalah script simulasi saat Hacker/Sistem berhasil mendekripsi");
        $this->line("kolom di atas menggunakan fungsi bawaan Laravel (APP_KEY).\n");
        
        try {
            // PROSES MEMBUKA KUNCI:
            // Fungsi Crypt::decryptString secara otomatis akan membaca nilai 
            // 'APP_KEY' dari file .env milikmu. Jika APP_KEY cocok dengan yang 
            // digunakan saat proses pembuatan, Kunci Privat akan terbuka (telanjang).
            $privatePem = Crypt::decryptString($key->private_key_pem_enc);
            
            $this->comment($privatePem);
            
        } catch (\Exception $e) {
            $this->error("Gagal membuka kunci privat. Pastikan APP_KEY tidak berubah sejak instalasi.");
        }
    }
}
