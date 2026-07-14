<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllowanceType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'calculation_type',
        'input_source',
        'applies_to',
        'display_order',
        'description',
        'is_active',
        'condition_field',
        'condition_operator',
        'condition_value',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'condition_value' => 'decimal:2',
    ];

    public function gradeRates()
    {
        return $this->hasMany(GradeAllowanceRate::class, 'allowance_type_id');
    }

    public function payrollAllowances()
    {
        return $this->hasMany(PayrollAllowance::class);
    }
}
