<?php

namespace App\Services;

use App\Models\AllowanceType;
use App\Models\GradeAllowanceRate;
use Carbon\CarbonInterface;

class AllowanceRateResolver
{
    public function resolveByCode(
        int $gradeId,
        string $allowanceCode,
        CarbonInterface|string $date,
        ?string $employmentTypeCode = null
    ): ?GradeAllowanceRate {
        $type = AllowanceType::query()
            ->where('code', $allowanceCode)
            ->where('is_active', true)
            ->first();

        if (! $type || ! $this->appliesToEmploymentType($type, $employmentTypeCode)) {
            return null;
        }

        return $this->resolve($gradeId, $type->id, $date);
    }

    public function resolve(
        int $gradeId,
        int $allowanceTypeId,
        CarbonInterface|string $date
    ): ?GradeAllowanceRate {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return GradeAllowanceRate::query()
            ->with('allowanceType')
            ->where('grade_id', $gradeId)
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
