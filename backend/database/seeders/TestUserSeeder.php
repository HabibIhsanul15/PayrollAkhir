<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkBasis;
use App\Services\CryptoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat akun testing dasar dan memastikan akun staff sudah payroll-ready.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Password123!';
        $effectiveFrom = now()->startOfMonth()->toDateString();
        
        $staffPosition = Position::where('code', 'staff')->first();
        $managerPosition = Position::where('code', 'manager')->first();
        $spvPosition = Position::where('code', 'spv')->first();
        
        $projectEmployment = EmploymentType::where('code', 'project')->first();
        $officeEmployment = EmploymentType::where('code', 'office')->first();
        
        $mandaysBasis = WorkBasis::where('code', 'mandays')->first();
        $monthlyBasis = WorkBasis::where('code', 'monthly')->first();

        // 1. Akun Manajemen (Tidak Punya Employee Record - atau khusus login role)
        $managementAccounts = [
            ['role' => 'fat', 'name' => 'Test FAT', 'email' => 'test.fat@payroll.test'],
            ['role' => 'hcga', 'name' => 'Test HCGA', 'email' => 'test.hcga@payroll.test'],
            ['role' => 'director', 'name' => 'Test Director', 'email' => 'test.director@payroll.test'],
        ];

        foreach ($managementAccounts as $acc) {
            User::updateOrCreate(
                ['email' => $acc['email']],
                ['name' => $acc['name'], 'password' => Hash::make($password), 'role' => $acc['role']]
            );
        }

        // 2. Buat 5 Data Karyawan Lengkap (Ditambah akun test.staff@payroll.test lama)
        $completeEmployees = [
            ['name' => 'Test Staff', 'email' => 'test.staff@payroll.test', 'code' => 'TST-STAFF-01', 'Position' => $staffPosition, 'basis' => $mandaysBasis, 'emp_type' => $projectEmployment, 'rate' => '100000'],
            ['name' => 'Budi Santoso', 'email' => 'budi@payroll.test', 'code' => 'EMP-001', 'Position' => $staffPosition, 'basis' => $mandaysBasis, 'emp_type' => $projectEmployment, 'rate' => '100000'],
            ['name' => 'Siti Aminah', 'email' => 'siti@payroll.test', 'code' => 'EMP-002', 'Position' => $staffPosition, 'basis' => $mandaysBasis, 'emp_type' => $projectEmployment, 'rate' => '120000'],
            ['name' => 'Andi Wijaya', 'email' => 'andi@payroll.test', 'code' => 'EMP-003', 'Position' => $spvPosition, 'basis' => $monthlyBasis, 'emp_type' => $officeEmployment, 'rate' => '250000'],
            ['name' => 'Rina Melati', 'email' => 'rina@payroll.test', 'code' => 'EMP-004', 'Position' => $spvPosition, 'basis' => $monthlyBasis, 'emp_type' => $officeEmployment, 'rate' => '250000'],
            ['name' => 'Joko Anwar', 'email' => 'joko@payroll.test', 'code' => 'EMP-005', 'Position' => $managerPosition, 'basis' => $monthlyBasis, 'emp_type' => $officeEmployment, 'rate' => '500000'],
        ];

        foreach ($completeEmployees as $emp) {
            $user = User::updateOrCreate(
                ['email' => $emp['email']],
                ['name' => $emp['name'], 'password' => Hash::make($password), 'role' => 'staff']
            );

            $employee = Employee::updateOrCreate(
                ['employee_code' => $emp['code']],
                [
                    'user_id' => $user->id,
                    'name' => $emp['name'],
                    'join_date' => now()->subMonths(6)->toDateString(),
                    'department' => 'Operations',
                    'position' => $emp['Position']?->name ?? 'Staff',
                    'status' => 'active',
                    'position_id' => $emp['Position']?->id,
                    'employment_type_id' => $emp['emp_type']?->id,
                    'work_basis_id' => $emp['basis']?->id,
                    'is_trainer' => false,
                    'is_on_probation' => false,
                    'num_toddlers' => rand(0, 2),
                    'nik_enc' => CryptoService::encryptAESGCM(str_pad(rand(10000000, 99999999), 16, '0', STR_PAD_RIGHT)),
                    'npwp_enc' => CryptoService::encryptAESGCM(str_pad(rand(10000000, 99999999), 15, '0', STR_PAD_RIGHT)),
                    'phone_enc' => CryptoService::encryptAESGCM('0812' . rand(10000000, 99999999)),
                    'address_enc' => CryptoService::encryptAESGCM('Jl. Mawar No. ' . rand(1, 100) . ', Jakarta'),
                    'bank_name' => 'BCA',
                    'bank_account_name' => $emp['name'],
                    'bank_account_number_enc' => CryptoService::encryptAESGCM((string)rand(1000000000, 9999999999)),
                    'pii_alg' => 'AES',
                    'pii_key_id' => CryptoService::keyId(),
                ]
            );

            if ($emp['Position']) {
                $employee->salaryProfiles()->updateOrCreate(
                    ['effective_from' => now()->subMonths(6)->startOfMonth()->toDateString()],
                    [
                        'position_id' => $emp['Position']->id,
                        'position' => $emp['Position']->name,
                        'position_allowance' => 0,
                        'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
                        'mandays_rate' => null,
                        'mandays_rate_enc' => CryptoService::encryptAESGCM($emp['rate']),
                        'allowance_fixed' => 0,
                        'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
                        'deduction_fixed' => 0,
                        'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
                        'salary_alg' => 'AES',
                        'salary_key_id' => CryptoService::keyId(),
                    ]
                );

                $employee->jobHistories()->updateOrCreate(
                    ['start_date' => now()->subMonths(6)->startOfMonth()->toDateString()],
                    [
                        'position_id' => $emp['Position']->id,
                        'position' => $emp['Position']->name,
                        'status' => 'active',
                        'notes' => 'Karyawan lama',
                    ]
                );
            }
        }

        // 3. Buat 5 User (Role Staff) yang belum punya data Employee (Bisa diinput dari nol oleh HR)
        for ($i = 1; $i <= 5; $i++) {
            User::updateOrCreate(
                ['email' => "newstaff{$i}@payroll.test"],
                ['name' => "Kandidat Staff {$i}", 'password' => Hash::make($password), 'role' => 'staff']
            );
        }

        $this->command->info('Berhasil membuat 3 Akun Manajemen, 5 Karyawan Lengkap, dan 5 Kandidat Staff.');
        $this->command->info('Password semua akun: '.$password);
    }
}
