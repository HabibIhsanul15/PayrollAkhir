<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'code' => '10100',
                'name' => 'Kas & Bank',
                'group' => 'Aset',
                'normal_balance' => 'debit',
                'description' => 'Mencatat saldo kas dan bank perusahaan yang digunakan untuk operasional dan pembayaran gaji.',
                'is_active' => true,
            ],
            [
                'code' => '20100',
                'name' => 'Utang Gaji Karyawan',
                'group' => 'Liabilitas',
                'normal_balance' => 'credit',
                'description' => 'Mencatat kewajiban jangka pendek atas pembayaran gaji bersih karyawan yang sudah disetujui namun belum ditransfer.',
                'is_active' => true,
            ],
            [
                'code' => '20200',
                'name' => 'Utang Potongan Gaji',
                'group' => 'Liabilitas',
                'normal_balance' => 'credit',
                'description' => 'Menampung sementara potongan gaji karyawan (kasbon, denda, dsb) sebelum dialokasikan ke akun rincian masing-masing.',
                'is_active' => true,
            ],
            [
                'code' => '50100',
                'name' => 'Beban Gaji Pokok',
                'group' => 'Beban',
                'normal_balance' => 'debit',
                'description' => 'Mencatat beban gaji pokok karyawan bulanan dan harian.',
                'is_active' => true,
            ],
            [
                'code' => '50200',
                'name' => 'Beban Tunjangan',
                'group' => 'Beban',
                'normal_balance' => 'debit',
                'description' => 'Mencatat seluruh beban tunjangan variabel dan tunjangan tetap yang diberikan kepada karyawan.',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            ChartOfAccount::updateOrCreate(
                ['code' => $acc['code']],
                $acc
            );
        }
    }
}
