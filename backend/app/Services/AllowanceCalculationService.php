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
            $amount = $this->amount($employee, $type, $rate, $units, $baseSalaryAmount, $segmentRatio);

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
        Employee $employee,
        AllowanceType $type,
        PositionAllowanceRate $rate,
        float $units,
        float $baseSalaryAmount,
        float $segmentRatio
    ): float {
        $rateAmount = (float) ($rate->rate_amount ?? 0);

        $baseAmount = match ($type->calculation_type) {
            'flat' => $rateAmount * $segmentRatio,
            'per_mandays', 'per_trip' => $rateAmount * $units,
            'per_toddler' => $rateAmount * (float) ($employee->num_toddlers ?? 0) * $segmentRatio,
            default => 0.0,
        };

        return $baseAmount;
    }

    private function units(Employee $employee, MonthlyRecap $recap, AllowanceType $type): float
    {
        $source = $type->input_source ?: (
            $type->code === 'training'
                ? 'training_days'
                : match ($type->calculation_type) {
                    'per_trip' => 'business_trips',
                    'per_mandays' => 'total_mandays',
                    default => null,
                }
        );

        return match ($source) {
            'training_days' => (float) ($recap->training_days ?? 0),
            'out_of_town_days' => (float) ($recap->out_of_town_days ?? 0),
            'wfo_days' => (float) ($recap->wfo_days ?? 0),
            'wfh_days' => (float) ($recap->wfh_days ?? 0),
            'business_trips' => (float) ($recap->business_trips ?? 0),
            'total_mandays' => (float) ($recap->total_mandays ?? 0),
            default => 1.0,
        };
    }
}
