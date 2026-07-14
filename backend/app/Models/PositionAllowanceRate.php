<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PositionAllowanceRate extends Model
{
    protected $fillable = [
        'position_id',
        'allowance_type_id',
        'rate_amount',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'rate_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
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
