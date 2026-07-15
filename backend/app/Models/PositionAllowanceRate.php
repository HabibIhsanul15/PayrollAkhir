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
        'is_active',
    ];

    protected $casts = [
        'rate_amount' => 'decimal:2',
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
}
