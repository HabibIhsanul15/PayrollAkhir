<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllowanceType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'calculation_type',
        'applies_to',
        'display_order',
        'description',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function positionRates()
    {
        return $this->hasMany(PositionAllowanceRate::class, 'allowance_type_id');
    }

    public function payrollAllowances()
    {
        return $this->hasMany(PayrollAllowance::class);
    }
}
