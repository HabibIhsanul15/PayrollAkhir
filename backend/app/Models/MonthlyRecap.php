<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyRecap extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    
    protected $casts = [
        'wfo_days' => 'decimal:2',
        'wfh_days' => 'decimal:2',
        'out_of_town_days' => 'decimal:2',
        'business_trips' => 'integer',
        'training_days' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'total_mandays' => 'decimal:2',
        'is_finalized' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
