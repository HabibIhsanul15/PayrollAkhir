<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollDeduction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'calculation_detail' => 'array',
        'is_manual_override' => 'boolean',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
