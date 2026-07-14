<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Grade;
use App\Models\SalaryProfile;
use App\Models\User;
use App\Models\WorkBasis;
use App\Services\CryptoService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollCipherService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DummyPayrollSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Menyiapkan data payroll demo (3 user)...');

        $grade = Grade::where('code', 'staff')->first() ?? Grade::first();
        $employmentType = EmploymentType::where('code', 'project')->first() ?? EmploymentType::first();
        $workBasis = WorkBasis::where('code', 'mandays')->first() ?? WorkBasis::first();

        if (! $grade || ! $employmentType || ! $workBasis) {
            $this->command->error('Pastikan master data grade, employment type, dan work basis sudah tersedia.');
            return;
        }

        $effectiveFrom = now()->startOfMonth()->toDateString();
        $periodMonth = now()->format('Y-m');
        $periodeDate = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth()->toDateString();

        // Bersihkan data staff lama agar persis hanya 3 data demo
        $staffUsers = User::where('role', 'staff')->get();
        foreach($staffUsers as $u) {
            if($u->employee) {
                $u->employee->salaryProfiles()->delete();
                $u->employee->jobHistories()->delete();
                DB::table('monthly_recaps')->where('employee_id', $u->employee->id)->delete();
                $payrollIds = DB::table('payrolls')->where('employee_id', $u->employee->id)->pluck('id');
                DB::table('payroll_allowances')->whereIn('payroll_id', $payrollIds)->delete();
                DB::table('payrolls')->where('employee_id', $u->employee->id)->delete();
                $u->employee->delete();
            }
            $u->delete();
        }

        $service = app(PayrollCalculationService::class);
        $cipherService = app(PayrollCipherService::class);

        // Skala 3 data user demo
        $demos = [
            ['name' => 'Budi Santoso', 'code' => 'EMP-001', 'wfo' => 22, 'rate' => 200000, 'generate' => false],
            ['name' => 'Siti Aminah', 'code' => 'EMP-002', 'wfo' => 20, 'rate' => 250000, 'generate' => false],
            ['name' => 'Agus Pratama', 'code' => 'EMP-003', 'wfo' => 15, 'rate' => 150000, 'generate' => false],
        ];

        foreach($demos as $i => $d) {
            $user = User::create([
                'name' => $d['name'],
                'email' => "staff{$i}@payroll.test",
                'password' => Hash::make('Password123!'),
                'role' => 'staff',
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $d['name'],
                'employee_code' => $d['code'],
                'department' => 'Operations',
                'position' => $grade->name,
                'status' => 'active',
                'grade_id' => $grade->id,
                'employment_type_id' => $employmentType->id,
                'work_basis_id' => $workBasis->id,
                'bank_name' => 'Bank BCA',
                'bank_account_name' => $d['name'],
                'bank_account_number_enc' => CryptoService::encryptAESGCM('1234567890'),
                'pii_alg' => 'AES',
                'pii_key_id' => CryptoService::keyId(),
            ]);

            $profile = SalaryProfile::create([
                'employee_id' => $employee->id,
                'effective_from' => $effectiveFrom,
                'grade_id' => $grade->id,
                'position' => $grade->name,
                'position_allowance' => 0,
                'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
                'mandays_rate' => null,
                'mandays_rate_enc' => CryptoService::encryptAESGCM((string)$d['rate']),
                'allowance_fixed' => 0,
                'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
                'deduction_fixed' => 0,
                'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
                'salary_alg' => 'AES',
                'salary_key_id' => CryptoService::keyId(),
            ]);

            DB::table('monthly_recaps')->insert([
                'employee_id' => $employee->id,
                'salary_profile_id' => $profile->id,
                'period_month' => $periodMonth,
                'wfo_days' => $d['wfo'],
                'wfh_days' => 0,
                'out_of_town_days' => 0,
                'business_trips' => 0,
                'training_days' => 0,
                'overtime_hours' => 0,
                'total_mandays' => $d['wfo'],
                'is_finalized' => true,
                'finalized_at' => now(),
                'finalized_by' => User::where('role', 'fat')->first()->id ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($d['generate']) {
                $fatId = User::where('role', 'fat')->first()->id ?? $user->id;
                $service->calculateAndSave($employee->id, $periodMonth, $fatId);
            }
            $this->command->info("User {$d['name']} berhasil disiapkan.");
        }
        $this->command->info('Data demo selesai di-generate. Silakan periksa halaman Payroll Processing Grid.');
    }
}
