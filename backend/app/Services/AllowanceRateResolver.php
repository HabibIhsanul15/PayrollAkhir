<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\PositionAllowanceRate;
use Carbon\CarbonInterface;

class AllowanceRateResolver
{
    public function resolveByCode(
        int $positionId,
        string $allowanceCode,
        CarbonInterface|string $date,
        ?string $employmentTypeCode = null
    ): ?PositionAllowanceRate {
        $type = AllowanceType::query()
            ->where('code', $allowanceCode)
            ->where('is_active', true)
            ->first();

        if (! $type || ! $this->appliesToEmploymentType($type, $employmentTypeCode)) {
            return null;
        }

        return $this->resolve($positionId, $type->id, $date);
    }

    public function resolve(
        int $positionId,
        int $allowanceTypeId,
        CarbonInterface|string $date
    ): ?PositionAllowanceRate {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return PositionAllowanceRate::query()
            ->with('allowanceType')
            ->where('position_id', $positionId)
            ->where('allowance_type_id', $allowanceTypeId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function appliesToEmploymentType(AllowanceType $type, ?string $employmentTypeCode): bool
    {
        return true;
    }
}
