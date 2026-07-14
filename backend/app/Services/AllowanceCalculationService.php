<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\GradeAllowanceRate;
use App\Models\MonthlyRecap;
use Carbon\CarbonInterface;

class AllowanceCalculationService
{
    public function __construct(private AllowanceRateResolver $rateResolver) {}

    public function calculate(
        Employee $employee,
        MonthlyRecap $recap,
        int $gradeId,
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
            $rate = $this->rateResolver->resolve($gradeId, $type->id, $date);
            if (! $rate || ! $this->conditionMatches($employee, $type)) {
                continue;
            }

            $units = $this->units($employee, $recap, $type->input_source);
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
                    'input_source' => $type->input_source,
                    'units' => $units,
                    'rate_multiplier' => $rate->rate_multiplier !== null ? (float) $rate->rate_multiplier : null,
                ],
            ];
        }

        return $results;
    }

    private function amount(
        AllowanceType $type,
        GradeAllowanceRate $rate,
        float $units,
        float $baseSalaryAmount,
        float $segmentRatio
    ): float {
        $rateAmount = (float) ($rate->rate_amount ?? 0);

        return match ($type->calculation_type) {
            'flat' => $rateAmount * $segmentRatio,
            'per_mandays', 'per_trip' => $rateAmount * $units,
            'formula' => $rate->rate_multiplier !== null
                ? $baseSalaryAmount * (float) $rate->rate_multiplier * $units
                : $rateAmount * $segmentRatio,
            default => 0.0,
        };
    }

    private function units(Employee $employee, MonthlyRecap $recap, ?string $source): float
    {
        if ($source && isset($recap->{$source})) {
            return (float) $recap->{$source};
        }

        if ($source && isset($employee->{$source})) {
            return (float) $employee->{$source};
        }

        return 1.0;
    }

    private function conditionMatches(Employee $employee, AllowanceType $type): bool
    {
        if (! $type->condition_field) {
            return true;
        }

        $actual = $employee->{$type->condition_field};
        $expected = (float) $type->condition_value;

        return match ($type->condition_operator) {
            '>=' => (float) $actual >= $expected,
            '>' => (float) $actual > $expected,
            '<=' => (float) $actual <= $expected,
            '<' => (float) $actual < $expected,
            '!=' => (float) $actual !== $expected,
            '=' => (float) $actual === $expected,
            default => false,
        };
    }
}
