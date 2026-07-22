<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Password123!';
        
        $staffPosition = Position::where('code', 'staff')->first();
        $managerPosition = Position::where('code', 'manager')->first();
        $spvPosition = Position::where('code', 'supervisor')->first();
        $directorPosition = Position::where('code', 'board-of-directors')->first() ?? $managerPosition;

        $employees = [
            ['name' => 'Test Director', 'email' => 'test.director@payroll.test', 'role' => 'director', 'code' => 'DIR-001', 'Position' => $directorPosition, 'dept' => 'Board of Directors', 'rate' => '1000000', 'bank' => 'BCA', 'rek' => '8730123456'],
            ['name' => 'Test HCGA', 'email' => 'test.hcga@payroll.test', 'role' => 'hcga', 'code' => 'HR-001', 'Position' => $managerPosition, 'dept' => 'Human Capital', 'rate' => '500000', 'bank' => 'Mandiri', 'rek' => '1370001234567'],
            ['name' => 'Test FAT', 'email' => 'test.fat@payroll.test', 'role' => 'fat', 'code' => 'FIN-001', 'Position' => $managerPosition, 'dept' => 'Finance', 'rate' => '500000', 'bank' => 'BNI', 'rek' => '0234567890'],
            ['name' => 'Test Staff', 'email' => 'test.staff@payroll.test', 'role' => 'staff', 'code' => 'OPS-001', 'Position' => $staffPosition, 'dept' => 'Operations', 'rate' => '150000', 'bank' => 'BRI', 'rek' => '0123456789'],
            ['name' => 'Andi Saputra', 'email' => 'andi@payroll.test', 'role' => 'staff', 'code' => 'OPS-002', 'Position' => $staffPosition, 'dept' => 'Operations', 'rate' => '150000', 'bank' => 'BCA', 'rek' => '8730987654'],
            ['name' => 'Joko Anwar', 'email' => 'joko@payroll.test', 'role' => 'staff', 'code' => 'MKT-001', 'Position' => $spvPosition, 'dept' => 'Marketing', 'rate' => '250000', 'bank' => 'Mandiri', 'rek' => '1370009876543'],
            ['name' => 'Rina Melati', 'email' => 'rinam@payroll.test', 'role' => 'staff', 'code' => 'IT-001', 'Position' => $spvPosition, 'dept' => 'IT', 'rate' => '300000', 'bank' => 'BCA', 'rek' => '8730112233'],
        ];

        foreach ($employees as $emp) {
            $user = User::updateOrCreate(
                ['email' => $emp['email']],
                ['name' => $emp['name'], 'password' => Hash::make($password), 'role' => $emp['role']]
            );

            $employee = Employee::updateOrCreate(
                ['employee_code' => $emp['code']],
                [
                    'user_id' => $user->id,
                    'name' => $emp['name'],
                    'join_date' => now()->subMonths(rand(12, 36))->toDateString(),
                    'department' => $emp['dept'],
                    'position' => $emp['Position']?->name ?? 'Staff',
                    'status' => 'active',
                    'position_id' => $emp['Position']?->id,
                    'is_trainer' => false,
                    'is_on_probation' => false,
                    'num_toddlers' => rand(0, 2),
                    'nik_enc' => \App\Services\CryptoService::encryptAESGCM('317' . rand(1000000000000, 9999999999999)),
                    'npwp_enc' => \App\Services\CryptoService::encryptAESGCM(rand(10, 99) . '.' . rand(100, 999) . '.' . rand(100, 999) . '.' . rand(1, 9) . '-' . rand(100, 999) . '.000'),
                    'phone_enc' => \App\Services\CryptoService::encryptAESGCM('0812' . rand(10000000, 99999999)),
                    'address_enc' => \App\Services\CryptoService::encryptAESGCM('Jl. Jendral Sudirman No. ' . rand(1, 100) . ', Jakarta'),
                    'bank_name' => $emp['bank'],
                    'bank_account_name' => $emp['name'],
                    'bank_account_number_enc' => \App\Services\CryptoService::encryptAESGCM($emp['rek']),
                    'pii_alg' => 'AES',
                    'pii_key_id' => \App\Services\CryptoService::keyId(),
                ]
            );

            if ($emp['Position']) {
                $employee->salaryProfiles()->updateOrCreate(
                    ['effective_from' => now()->subMonths(12)->startOfMonth()->toDateString()],
                    [
                        'position_id' => $emp['Position']->id,
                        'position' => $emp['Position']->name,
                        'position_allowance_enc' => \App\Services\CryptoService::encryptAESGCM('0'),
                        'base_salary_amount_enc' => \App\Services\CryptoService::encryptAESGCM($emp['rate']),
                        'allowance_fixed_enc' => \App\Services\CryptoService::encryptAESGCM('0'),
                        'deduction_fixed_enc' => \App\Services\CryptoService::encryptAESGCM('0'),
                        'salary_alg' => 'AES',
                        'salary_key_id' => \App\Services\CryptoService::keyId(),
                    ]
                );

                $employee->jobHistories()->updateOrCreate(
                    ['start_date' => now()->subMonths(12)->startOfMonth()->toDateString()],
                    [
                        'position_id' => $emp['Position']->id,
                        'position' => $emp['Position']->name,
                        'status' => 'active'
                    ]
                );
            }
        }

        // 3. Buat 5 Kandidat Staff (Hanya di tabel Employee, belum punya user/akun login)
        for ($i = 1; $i <= 5; $i++) {
            $user = User::firstOrCreate(
                ['email' => "kandidat{$i}@payroll.test"],
                [
                    'name' => "Kandidat Pegawai $i",
                    'password' => Hash::make('Password123!'),
                    'role' => 'staff',
                ]
            );

            $employee = Employee::updateOrCreate(
                ['employee_code' => 'KND' . str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'name' => 'Kandidat Pegawai ' . $i,
                    'status' => 'active',
                    'join_date' => now()->subDays(rand(1, 30))->toDateString(),
                    'department' => 'Operations',
                    'position' => $staffPosition->name,
                    'status' => 'active',
                    'position_id' => $staffPosition->id,
                    'is_trainer' => false,
                    'is_on_probation' => true,
                    'num_toddlers' => 0,
                    'nik_enc' => \App\Services\CryptoService::encryptAESGCM('317' . rand(1000000000000, 9999999999999)),
                    'npwp_enc' => \App\Services\CryptoService::encryptAESGCM(rand(10, 99) . '.' . rand(100, 999) . '.' . rand(100, 999) . '.' . rand(1, 9) . '-' . rand(100, 999) . '.000'),
                    'phone_enc' => \App\Services\CryptoService::encryptAESGCM('0813' . rand(10000000, 99999999)),
                    'address_enc' => \App\Services\CryptoService::encryptAESGCM('Jl. Gatot Subroto No. ' . rand(1, 100) . ', Jakarta'),
                    'bank_name' => 'BNI',
                    'bank_account_name' => 'Kandidat Pegawai ' . $i,
                    'bank_account_number_enc' => \App\Services\CryptoService::encryptAESGCM((string)rand(1000000000, 9999999999)),
                    'pii_alg' => 'AES',
                    'pii_key_id' => \App\Services\CryptoService::keyId(),
                ]
            );

            if ($staffPosition) {
                $employee->salaryProfiles()->updateOrCreate(
                    ['effective_from' => now()->startOfMonth()->toDateString()],
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
                    ['start_date' => now()->startOfMonth()->toDateString()],
                    [
                        'position_id' => $staffPosition->id,
                        'position' => $staffPosition->name,
                        'status' => 'active'
                    ]
                );
            }
        }

        $this->command->info('Berhasil membuat Akun dan Data Pegawai yang realistis lengkap dengan NIK, NPWP, Rekening, dll.');
        $this->command->info('Password semua akun: Password123!');
    }
}
