<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Grade;
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
        $staffGrade = Grade::where('code', 'staff')->first();
        $projectEmployment = EmploymentType::where('code', 'project')->first();
        $mandaysBasis = WorkBasis::where('code', 'mandays')->first();

        $accounts = [
            [
                'role' => 'staff',
                'name' => 'Test Staff',
                'email' => 'test.staff@payroll.test',
                'employee_code' => 'TST-STAFF-01',
                'department' => 'General',
                'position' => 'Staff',
            ],
            [
                'role' => 'fat',
                'name' => 'Test FAT',
                'email' => 'test.fat@payroll.test',
                'employee_code' => 'TST-FAT-01',
                'department' => 'Finance',
                'position' => 'Finance & Accounting Team',
            ],
            [
                'role' => 'hcga',
                'name' => 'Test HCGA',
                'email' => 'test.hcga@payroll.test',
                'employee_code' => 'TST-HCGA-01',
                'department' => 'HR',
                'position' => 'Human Capital & General Affairs',
            ],
            [
                'role' => 'director',
                'name' => 'Test Director',
                'email' => 'test.director@payroll.test',
                'employee_code' => 'TST-DIR-01',
                'department' => 'Management',
                'position' => 'Director',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'role' => $account['role'],
                ]
            );

            if ($account['role'] === 'staff') {
                $employee = Employee::updateOrCreate(
                    ['employee_code' => $account['employee_code']],
                    [
                        'user_id' => $user->id,
                        'name' => $account['name'],
                        'department' => $account['department'],
                        'position' => $staffGrade?->name ?? $account['position'],
                        'status' => 'active',
                        'grade_id' => $staffGrade?->id,
                        'employment_type_id' => $projectEmployment?->id,
                        'work_basis_id' => $mandaysBasis?->id,
                        'is_trainer' => false,
                        'is_on_probation' => false,
                        'num_toddlers' => 0,
                    ]
                );

                if ($staffGrade) {
                    $employee->salaryProfiles()->updateOrCreate(
                        ['effective_from' => $effectiveFrom],
                        [
                            'grade_id' => $staffGrade->id,
                            'position' => $staffGrade->name,
                            'position_allowance' => 0,
                            'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
                            'mandays_rate' => null,
                            'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
                            'allowance_fixed' => 0,
                            'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
                            'deduction_fixed' => 0,
                            'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
                            'salary_alg' => 'AES',
                            'salary_key_id' => CryptoService::keyId(),
                        ]
                    );

                    $employee->jobHistories()->updateOrCreate(
                        ['start_date' => $effectiveFrom],
                        [
                            'grade_id' => $staffGrade->id,
                            'position' => $staffGrade->name,
                            'status' => 'active',
                            'notes' => 'Seed data akun testing staff',
                        ]
                    );
                }
            } else {
                $staleEmployee = Employee::where('employee_code', $account['employee_code'])->first();

                if ($staleEmployee && ! $staleEmployee->payrolls()->exists()) {
                    $staleEmployee->salaryProfiles()->delete();
                    $staleEmployee->jobHistories()->delete();
                    $staleEmployee->delete();
                }
            }

            $this->command->info("[{$account['role']}] {$account['email']} -> OK");
        }

        $this->command->newLine();
        $this->command->info('Password semua akun: '.$password);
    }
}
