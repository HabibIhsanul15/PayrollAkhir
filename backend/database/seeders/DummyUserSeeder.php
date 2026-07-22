<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = \Illuminate\Support\Facades\Hash::make('Password123!');
        $staffPosition = \App\Models\Position::where('code', 'staff')->first();

        if (!$staffPosition) {
            $this->command->warn('Missing base master data (Position). Make sure standard seeders are run first.');
            return;
        }

        $names = ['Ahmad Subagyo', 'Nina Susanti', 'Wahyu Setiawan', 'Lia Mulyani', 'Eko Prasetyo', 'Dina Puspita', 'Taufik Hidayat', 'Rika Amalia', 'Surya Saputra', 'Maya Indah'];

        for ($i = 1; $i <= 10; $i++) {
            $name = $names[$i-1];
            $email = strtolower(str_replace(' ', '', $name)) . "@payroll.test";

            $user = \App\Models\User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => 'staff']
            );

            $employee = \App\Models\Employee::updateOrCreate(
                ['employee_code' => "EMP-" . str_pad($i + 100, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'name' => $name,
                    'join_date' => now()->subMonths(rand(12, 48))->toDateString(),
                    'department' => 'Operations',
                    'position' => $staffPosition->name,
                    'status' => 'active',
                    'position_id' => $staffPosition->id,
                    'is_trainer' => false,
                    'is_on_probation' => false,
                    'num_toddlers' => rand(0, 3),
                    'nik_enc' => \App\Services\CryptoService::encryptAESGCM('317' . rand(1000000000000, 9999999999999)),
                    'npwp_enc' => \App\Services\CryptoService::encryptAESGCM(rand(10, 99) . '.' . rand(100, 999) . '.' . rand(100, 999) . '.' . rand(1, 9) . '-' . rand(100, 999) . '.000'),
                    'phone_enc' => \App\Services\CryptoService::encryptAESGCM('081' . rand(100000000, 999999999)),
                    'address_enc' => \App\Services\CryptoService::encryptAESGCM('Jl. Kebon Jeruk No. ' . $i . ', Jakarta'),
                    'bank_name' => ['BCA', 'Mandiri', 'BNI', 'BRI'][array_rand(['BCA', 'Mandiri', 'BNI', 'BRI'])],
                    'bank_account_name' => $name,
                    'bank_account_number_enc' => \App\Services\CryptoService::encryptAESGCM((string)rand(1000000000, 9999999999)),
                    'pii_alg' => 'AES',
                    'pii_key_id' => \App\Services\CryptoService::keyId(),
                ]
            );

            $employee->salaryProfiles()->updateOrCreate(
                ['effective_from' => now()->subMonths(12)->startOfMonth()->toDateString()],
                [
                    'position_id' => $staffPosition->id,
                    'position' => $staffPosition->name,
                    'position_allowance_enc' => \App\Services\CryptoService::encryptAESGCM('0'),
                    'base_salary_amount_enc' => \App\Services\CryptoService::encryptAESGCM('100000'),
                    'allowance_fixed_enc' => \App\Services\CryptoService::encryptAESGCM('0'),
                    'deduction_fixed_enc' => \App\Services\CryptoService::encryptAESGCM('0'),
                    'salary_alg' => 'AES',
                    'salary_key_id' => \App\Services\CryptoService::keyId(),
                ]
            );

            $employee->jobHistories()->updateOrCreate(
                ['start_date' => now()->subMonths(12)->startOfMonth()->toDateString()],
                [
                    'position_id' => $staffPosition->id,
                    'position' => $staffPosition->name,
                    'status' => 'active',
                    'notes' => 'Karyawan Dummy',
                ]
            );
        }

        $this->command->info('10 Dummy Users (with Employees) seeded successfully.');
    }
}
