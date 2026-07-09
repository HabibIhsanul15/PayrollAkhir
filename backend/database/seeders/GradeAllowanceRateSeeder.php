<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\AllowanceType;
use App\Models\GradeAllowanceRate;
use Illuminate\Database\Seeder;

class GradeAllowanceRateSeeder extends Seeder
{
    public function run(): void
    {
        $grades = Grade::all()->pluck('id', 'code');
        $allowances = AllowanceType::all()->pluck('id', 'code');

        if ($grades->isEmpty() || $allowances->isEmpty()) {
            $this->command->error('Grades or AllowanceTypes are empty. Run seeders first.');
            return;
        }

        $rates = [
            // transport_trip
            ['grade' => 'bod', 'allowance' => 'transport_trip', 'rate_amount' => 365000],
            ['grade' => 'pd', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['grade' => 'pm', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['grade' => 'gm', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['grade' => 'manager', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['grade' => 'consultant', 'allowance' => 'transport_trip', 'rate_amount' => 285000],
            ['grade' => 'supervisor', 'allowance' => 'transport_trip', 'rate_amount' => 285000],
            ['grade' => 'staff', 'allowance' => 'transport_trip', 'rate_amount' => 285000],

            // meal
            ['grade' => 'bod', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'pd', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'pm', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'gm', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'manager', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'consultant', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'supervisor', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['grade' => 'staff', 'allowance' => 'meal', 'rate_amount' => 25000],

            // position
            ['grade' => 'pd', 'allowance' => 'position', 'rate_amount' => 2500000],
            ['grade' => 'gm', 'allowance' => 'position', 'rate_amount' => 2500000],
            ['grade' => 'pm', 'allowance' => 'position', 'rate_amount' => 1200000],
            ['grade' => 'manager', 'allowance' => 'position', 'rate_amount' => 1200000],

            // childcare
            ['grade' => 'pd', 'allowance' => 'childcare', 'rate_amount' => 2500000, 'requires_condition' => 'num_toddlers>=3'],
            ['grade' => 'pm', 'allowance' => 'childcare', 'rate_amount' => 1600000, 'requires_condition' => 'num_toddlers>=3'],
            ['grade' => 'consultant', 'allowance' => 'childcare', 'rate_amount' => 1000000, 'requires_condition' => 'num_toddlers>=3'],

            // training (Trainer gets 1.5x multiplier)
            ['grade' => 'bod', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'pd', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'pm', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'gm', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'manager', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'consultant', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'supervisor', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],
            ['grade' => 'staff', 'allowance' => 'training', 'rate_multiplier' => 1.5000, 'rate_formula' => '1.5 * mandays_rate', 'requires_condition' => 'is_trainer=1'],

            // business_trip
            ['grade' => 'gm', 'allowance' => 'business_trip', 'rate_amount' => 200000],
            ['grade' => 'manager', 'allowance' => 'business_trip', 'rate_amount' => 130000],
            ['grade' => 'supervisor', 'allowance' => 'business_trip', 'rate_amount' => 100000],
            ['grade' => 'staff', 'allowance' => 'business_trip', 'rate_amount' => 75000],

            // ho_transport_meal
            ['grade' => 'gm', 'allowance' => 'ho_transport_meal', 'rate_amount' => 30000],
            ['grade' => 'manager', 'allowance' => 'ho_transport_meal', 'rate_amount' => 30000],
            ['grade' => 'supervisor', 'allowance' => 'ho_transport_meal', 'rate_amount' => 20000],
            ['grade' => 'staff', 'allowance' => 'ho_transport_meal', 'rate_amount' => 20000],

            // transport_insurance
            ['grade' => 'bod', 'allowance' => 'transport_insurance', 'rate_amount' => 100000],
            ['grade' => 'pd', 'allowance' => 'transport_insurance', 'rate_amount' => 60000],
            ['grade' => 'pm', 'allowance' => 'transport_insurance', 'rate_amount' => 35000],
            ['grade' => 'gm', 'allowance' => 'transport_insurance', 'rate_amount' => 60000],
            ['grade' => 'manager', 'allowance' => 'transport_insurance', 'rate_amount' => 35000],
            ['grade' => 'consultant', 'allowance' => 'transport_insurance', 'rate_amount' => 25000],
            ['grade' => 'supervisor', 'allowance' => 'transport_insurance', 'rate_amount' => 25000],
            ['grade' => 'staff', 'allowance' => 'transport_insurance', 'rate_amount' => 25000],
        ];

        foreach ($rates as $rate) {
            $gradeId = $grades[$rate['grade']] ?? null;
            $allowanceId = $allowances[$rate['allowance']] ?? null;

            if ($gradeId && $allowanceId) {
                GradeAllowanceRate::updateOrCreate([
                    'grade_id' => $gradeId,
                    'allowance_type_id' => $allowanceId,
                    'effective_from' => '2026-01-01',
                ], [
                    'rate_amount' => $rate['rate_amount'] ?? null,
                    'rate_multiplier' => $rate['rate_multiplier'] ?? null,
                    'rate_formula' => $rate['rate_formula'] ?? null,
                    'requires_condition' => $rate['requires_condition'] ?? null,
                    'effective_to' => null,
                    'is_active' => true,
                ]);
            }
        }
    }
}
