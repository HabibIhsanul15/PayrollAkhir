<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\Grade;
use App\Models\EmploymentType;
use App\Models\WorkBasis;
use App\Models\SalaryProfile;
use App\Services\PayrollCalculationService;
use App\Services\CryptoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DummyPayrollSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Menyiapkan Data Karyawan (Test Staff)...');

        $user = User::where('email', 'test.staff@payroll.test')->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test Staff',
                'email' => 'test.staff@payroll.test',
                'password' => Hash::make('Password123!'),
                'role' => 'staff'
            ]);
        }

        $grade = Grade::where('code', 'STF')->first() ?? Grade::first();
        $empType = EmploymentType::where('code', 'fix_rate')->first() ?? EmploymentType::first();
        $workBasis = WorkBasis::where('code', 'monthly')->first() ?? WorkBasis::first();

        if (!$grade || !$empType || !$workBasis) {
            $this->command->error('Pastikan Master Data (Grade, Employment Type, Work Basis) sudah dised!');
            return;
        }

        $emp = Employee::updateOrCreate(
            ['employee_code' => 'TST-STAFF-01'],
            [
                'user_id' => $user->id,
                'name' => 'Test Staff',
                'department' => 'Technology',
                'position' => 'Software Engineer',
                'status' => 'active',
                'grade_id' => $grade->id,
                'employment_type_id' => $empType->id,
                'work_basis_id' => $workBasis->id,
                'bank_name' => 'Bank BCA',
                'bank_account_name' => 'Test Staff',
                'bank_account_number' => '1234567890',
                'bank_account_number_enc' => CryptoService::encryptAESGCM('1234567890'),
                'pii_alg' => 'AES',
            ]
        );

        $this->command->info('Membuat Salary Profile...');
        $baseSal = 7000000;
        $profile = SalaryProfile::updateOrCreate(
            ['employee_id' => $emp->id, 'effective_from' => '2026-01-01'],
            [
                'grade_id' => $grade->id,
                'position' => 'Software Engineer',
                'base_salary' => $baseSal,
                'base_salary_enc' => CryptoService::encryptAESGCM((string)$baseSal),
                'mandays_rate' => 100000,
                'mandays_rate_enc' => CryptoService::encryptAESGCM('100000'),
                'allowance_fixed' => 0,
                'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
                'deduction_fixed' => 0,
                'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
                'salary_alg' => 'AES',
            ]
        );

        $this->command->info('Membuat Rekap Bulanan Karyawan (Periode 2026-10)...');
        $periodMonth = '2026-10';
        DB::table('monthly_recaps')->where('employee_id', $emp->id)->where('period_month', $periodMonth)->delete();

        DB::table('monthly_recaps')->insert([
            'employee_id' => $emp->id,
            'period_month' => $periodMonth,
            'wfo_days' => 22,
            'wfh_days' => 0,
            'out_of_town_days' => 0,
            'business_trips' => 0,
            'training_days' => 0,
            'overtime_hours' => 0,
            'total_mandays' => 22,
            'is_finalized' => true,
            'finalized_at' => now(),
            'finalized_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Generate Payroll (via PayrollCalculationService)...');
        $service = new PayrollCalculationService();
        
        // Hapus payroll lama jika ada
        DB::table('payrolls')->where('employee_id', $emp->id)->where('periode', '2026-10-01')->delete();
        
        try {
            $payroll = $service->calculateAndSave($emp->id, $periodMonth, $user->id);
            
            // Add dummy deductions
            $payroll->deductions()->create([
                'deduction_type' => 'bpjs_kesehatan',
                'deduction_label' => 'BPJS KESEHATAN',
                'amount' => 50000,
                'amount_enc' => CryptoService::encryptAESGCM((string)50000)
            ]);
            
            $payroll->deductions()->create([
                'deduction_type' => 'pph_21',
                'deduction_label' => 'PPH. PASAL 21',
                'amount' => 150000,
                'amount_enc' => CryptoService::encryptAESGCM((string)150000)
            ]);

            $payroll->total_deductions = $payroll->total_deductions + 200000;
            $payroll->total_deductions_enc = CryptoService::encryptAESGCM((string)$payroll->total_deductions);
            $payroll->total = $payroll->total - 200000;
            $payroll->total_enc = CryptoService::encryptAESGCM((string)$payroll->total);
            $payroll->save();

            $this->command->info('✅ Payroll berhasil di-generate!');
            $this->command->info('ID Payroll: ' . $payroll->id);
            $this->command->info('Total Gaji: Rp ' . number_format($payroll->total, 0, ',', '.'));
        } catch (\Exception $e) {
            $this->command->error('Gagal generate payroll: ' . $e->getMessage());
        }
    }
}
