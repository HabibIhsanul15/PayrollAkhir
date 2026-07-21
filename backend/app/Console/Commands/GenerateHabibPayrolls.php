<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\Payroll;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;

class GenerateHabibPayrolls extends Command
{
    protected $signature = 'generate:habib-payrolls';
    protected $description = 'Generate 10 paid payrolls for Habib';

    public function handle(PayrollCalculationService $calcService)
    {
        $employeeId = 7;
        $employee = Employee::find($employeeId);
        if (!$employee) {
            $this->error("Employee 7 (Habib) not found.");
            return;
        }

        $recordedBy = 15; // User ID for Habib's director/creator

        // Start from 10 months ago
        $start = Carbon::now()->subMonths(10)->startOfMonth();

        for ($i = 0; $i < 10; $i++) {
            $month = $start->copy()->addMonths($i);
            $periodMonth = $month->format('Y-m');

            $this->info("Generating for $periodMonth...");

            // 1. Delete existing payroll for this month if any
            $existingPayroll = Payroll::where('employee_id', $employeeId)->where('periode', $month->format('Y-m-01'))->first();
            if ($existingPayroll) {
                $existingPayroll->allowances()->delete();
                $existingPayroll->deductions()->delete();
                $existingPayroll->delete();
            }

            // 2. Delete existing recap
            MonthlyRecap::where('employee_id', $employeeId)->where('period_month', $periodMonth)->delete();

            // 3. Create Recap
            $recap = MonthlyRecap::create([
                'employee_id' => $employeeId,
                'period_month' => $periodMonth,
                'total_mandays' => rand(20, 22),
                'total_wfo' => rand(15, 20),
                'total_wfh' => rand(0, 5),
                'total_out_of_town' => rand(0, 2),
                'position_id' => $employee->position_id,
                'salary_profile_id' => 7,
                'position_name' => 'Consultant',
                'base_salary_basis' => 'daily',
                'base_salary_amount' => 150000,
                'effective_from' => $month->format('Y-m-01'),
                'effective_to' => $month->endOfMonth()->format('Y-m-d'),
                'is_prorated' => false,
                'is_finalized' => true,
            ]);

            // 4. Calculate and save payroll
            try {
                $payroll = $calcService->calculateAndSave($employeeId, $periodMonth, $recordedBy);
                
                // 5. Mark as paid
                $payroll->status = 'paid';
                $payroll->paid_ref = 'TRF-' . str_replace('-', '', $periodMonth) . '-' . str_pad($payroll->id, 5, '0', STR_PAD_LEFT);
                $payroll->paid_at = $month->endOfMonth()->addDays(2)->format('Y-m-d H:i:s');
                $payroll->save();

                $this->info("Payroll {$payroll->id} created for $periodMonth.");
            } catch (\Exception $e) {
                $this->error("Error for $periodMonth: " . $e->getMessage());
            }
        }
        
        $this->info("Done generating 10 payrolls for Habib.");
    }
}
