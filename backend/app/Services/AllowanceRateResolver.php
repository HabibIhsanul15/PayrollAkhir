<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\PositionAllowanceRate;
use Carbon\CarbonInterface;

class AllowanceRateResolver
{
    public function resolveByCode(
        int $positionId,
        string $allowanceCode
    ): ?PositionAllowanceRate {
        $type = AllowanceType::query()
            ->where('code', $allowanceCode)
            ->where('is_active', true)
            ->first();

        if (! $type) {
            return null;
        }

        return $this->resolve($positionId, $type->id);
    }

    public function resolve(
        int $positionId,
        int $allowanceTypeId
    ): ?PositionAllowanceRate {
        return PositionAllowanceRate::query()
            ->with('allowanceType')
            ->where('position_id', $positionId)
            ->where('allowance_type_id', $allowanceTypeId)
            ->where('is_active', true)
            ->first();
    }
}
