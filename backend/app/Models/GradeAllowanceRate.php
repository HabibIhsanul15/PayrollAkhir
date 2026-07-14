<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GradeAllowanceRate extends Model
{
    protected $fillable = [
        'grade_id',
        'allowance_type_id',
        'rate_amount',
        'rate_multiplier',
        'rate_formula',
        'requires_condition',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'rate_amount' => 'decimal:2',
        'rate_multiplier' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function allowanceType()
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $range) use ($date) {
                $range->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }
}
