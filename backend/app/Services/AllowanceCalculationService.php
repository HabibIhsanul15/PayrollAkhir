<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\PositionAllowanceRate;
use App\Models\MonthlyRecap;
use Carbon\CarbonInterface;

class AllowanceCalculationService
{
    public function __construct(private AllowanceRateResolver $rateResolver) {}

    public function calculate(
        Employee $employee,
        MonthlyRecap $recap,
        int $positionId,
        CarbonInterface|string $date,
        float $baseSalaryAmount,
        float $segmentRatio
    ): array {
        $results = [];

        $types = AllowanceType::query()
            ->where('is_active', true)
            ->where('code', '!=', 'position')
            ->orderBy('display_order')
            ->get();

        foreach ($types as $type) {
            $rate = $this->rateResolver->resolve($positionId, $type->id);
            if (! $rate) {
                continue;
            }

            $units = $this->units($employee, $recap, $type);
            $amount = $this->amount($type, $rate, $units, $baseSalaryAmount, $segmentRatio);

            if ($amount <= 0) {
                continue;
            }

            $results[] = [
                'type' => $type,
                'rate' => $rate,
                'amount' => $amount,
                'units' => $units,
                'detail' => [
                    'calculation_type' => $type->calculation_type,
                    'units' => $units,
                ],
            ];
        }

        return $results;
    }

    private function amount(
        AllowanceType $type,
        PositionAllowanceRate $rate,
        float $units,
        float $baseSalaryAmount,
        float $segmentRatio
    ): float {
        $rateAmount = (float) ($rate->rate_amount ?? 0);

        return match ($type->calculation_type) {
            'flat' => $rateAmount * $segmentRatio,
            'per_mandays', 'per_trip' => $rateAmount * $units,
            default => 0.0,
        };
    }

    private function units(Employee $employee, MonthlyRecap $recap, AllowanceType $type): float
    {
        if ($type->calculation_type === 'per_trip') {
            return (float) ($recap->business_trips ?? 0);
        }

        if ($type->calculation_type === 'per_mandays') {
            if ($type->code === 'training') {
                return (float) ($recap->training_days ?? 0);
            }
            return (float) ($recap->total_mandays ?? 0);
        }

        return 1.0;
    }
}
