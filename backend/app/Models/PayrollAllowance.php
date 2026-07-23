<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAllowance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'mandays' => 'integer',
        'rate_amount' => 'float',
        'calculation_detail' => 'array',
        'is_manual_override' => 'boolean',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function allowanceType()
    {
        return $this->belongsTo(AllowanceType::class);
    }
}
