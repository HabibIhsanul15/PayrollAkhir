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
        $projectEmployment = \App\Models\EmploymentType::where('code', 'project')->first();
        $mandaysBasis = \App\Models\WorkBasis::where('code', 'mandays')->first();

        if (!$staffPosition || !$projectEmployment || !$mandaysBasis) {
            $this->command->warn('Missing base master data (Position/Employment/Basis). Make sure standard seeders are run first.');
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            $email = "dummy.user{$i}@payroll.test";
            $name = "Karyawan Dummy {$i}";

            $user = \App\Models\User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => 'staff']
            );

            $employee = \App\Models\Employee::updateOrCreate(
                ['employee_code' => "DUMMY-" . str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'name' => $name,
                    'join_date' => now()->subMonths(rand(1, 24))->toDateString(),
                    'department' => 'Operations',
                    'position' => $staffPosition->name,
                    'status' => 'active',
                    'position_id' => $staffPosition->id,
                    'employment_type_id' => $projectEmployment->id,
                    'work_basis_id' => $mandaysBasis->id,
                    'is_trainer' => false,
                    'is_on_probation' => false,
                    'num_toddlers' => rand(0, 3),
                    'nik_enc' => \App\Services\CryptoService::encryptAESGCM(str_pad(rand(10000000, 99999999), 16, '0', STR_PAD_RIGHT)),
                    'npwp_enc' => \App\Services\CryptoService::encryptAESGCM(str_pad(rand(10000000, 99999999), 15, '0', STR_PAD_RIGHT)),
                    'phone_enc' => \App\Services\CryptoService::encryptAESGCM('0812' . rand(10000000, 99999999)),
                    'address_enc' => \App\Services\CryptoService::encryptAESGCM('Jl. Dummy No. ' . $i . ', Jakarta'),
                    'bank_name' => 'BCA',
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
