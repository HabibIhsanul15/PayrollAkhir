<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\AllowanceType;
use App\Models\PositionAllowanceRate;
use Illuminate\Database\Seeder;

class PositionAllowanceRateSeeder extends Seeder
{
    public function run(): void
    {
        $positions = Position::all()->pluck('id', 'code');
        $allowances = AllowanceType::all()->pluck('id', 'code');

        if ($positions->isEmpty() || $allowances->isEmpty()) {
            $this->command->error('positions or AllowanceTypes are empty. Run seeders first.');
            return;
        }

        $rates = [
            // transport_trip
            ['Position' => 'bod', 'allowance' => 'transport_trip', 'rate_amount' => 365000],
            ['Position' => 'pd', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['Position' => 'pm', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['Position' => 'gm', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['Position' => 'manager', 'allowance' => 'transport_trip', 'rate_amount' => 325000],
            ['Position' => 'consultant', 'allowance' => 'transport_trip', 'rate_amount' => 285000],
            ['Position' => 'supervisor', 'allowance' => 'transport_trip', 'rate_amount' => 285000],
            ['Position' => 'staff', 'allowance' => 'transport_trip', 'rate_amount' => 285000],

            // meal
            ['Position' => 'bod', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'pd', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'pm', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'gm', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'manager', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'consultant', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'supervisor', 'allowance' => 'meal', 'rate_amount' => 25000],
            ['Position' => 'staff', 'allowance' => 'meal', 'rate_amount' => 25000],

            // position
            ['Position' => 'pd', 'allowance' => 'position', 'rate_amount' => 2500000],
            ['Position' => 'gm', 'allowance' => 'position', 'rate_amount' => 2500000],
            ['Position' => 'pm', 'allowance' => 'position', 'rate_amount' => 1200000],
            ['Position' => 'manager', 'allowance' => 'position', 'rate_amount' => 1200000],

            // childcare
            ['Position' => 'pd', 'allowance' => 'childcare', 'rate_amount' => 2500000],
            ['Position' => 'pm', 'allowance' => 'childcare', 'rate_amount' => 1600000],
            ['Position' => 'consultant', 'allowance' => 'childcare', 'rate_amount' => 1000000],

            // training
            ['Position' => 'bod', 'allowance' => 'training', 'rate_amount' => 250000],
            ['Position' => 'pd', 'allowance' => 'training', 'rate_amount' => 220000],
            ['Position' => 'pm', 'allowance' => 'training', 'rate_amount' => 200000],
            ['Position' => 'gm', 'allowance' => 'training', 'rate_amount' => 200000],
            ['Position' => 'manager', 'allowance' => 'training', 'rate_amount' => 180000],
            ['Position' => 'consultant', 'allowance' => 'training', 'rate_amount' => 150000],
            ['Position' => 'supervisor', 'allowance' => 'training', 'rate_amount' => 150000],
            ['Position' => 'staff', 'allowance' => 'training', 'rate_amount' => 150000],

            // business_trip
            ['Position' => 'gm', 'allowance' => 'business_trip', 'rate_amount' => 200000],
            ['Position' => 'manager', 'allowance' => 'business_trip', 'rate_amount' => 130000],
            ['Position' => 'supervisor', 'allowance' => 'business_trip', 'rate_amount' => 100000],
            ['Position' => 'staff', 'allowance' => 'business_trip', 'rate_amount' => 75000],

            // ho_transport_meal
            ['Position' => 'gm', 'allowance' => 'ho_transport_meal', 'rate_amount' => 30000],
            ['Position' => 'manager', 'allowance' => 'ho_transport_meal', 'rate_amount' => 30000],
            ['Position' => 'supervisor', 'allowance' => 'ho_transport_meal', 'rate_amount' => 20000],
            ['Position' => 'staff', 'allowance' => 'ho_transport_meal', 'rate_amount' => 20000],

            // transport_insurance
            ['Position' => 'bod', 'allowance' => 'transport_insurance', 'rate_amount' => 100000],
            ['Position' => 'pd', 'allowance' => 'transport_insurance', 'rate_amount' => 60000],
            ['Position' => 'pm', 'allowance' => 'transport_insurance', 'rate_amount' => 35000],
            ['Position' => 'gm', 'allowance' => 'transport_insurance', 'rate_amount' => 60000],
            ['Position' => 'manager', 'allowance' => 'transport_insurance', 'rate_amount' => 35000],
            ['Position' => 'consultant', 'allowance' => 'transport_insurance', 'rate_amount' => 25000],
            ['Position' => 'supervisor', 'allowance' => 'transport_insurance', 'rate_amount' => 25000],
            ['Position' => 'staff', 'allowance' => 'transport_insurance', 'rate_amount' => 25000],
        ];

        foreach ($rates as $rate) {
            $positionId = $positions[$rate['Position']] ?? null;
            $allowanceId = $allowances[$rate['allowance']] ?? null;

            if ($positionId && $allowanceId) {
                PositionAllowanceRate::updateOrCreate([
                    'position_id' => $positionId,
                    'allowance_type_id' => $allowanceId,
                ], [
                    'rate_amount' => $rate['rate_amount'] ?? null,
                    'is_active' => true,
                ]);
            }
        }
    }
}
