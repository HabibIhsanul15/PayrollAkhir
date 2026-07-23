<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyRecap extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    
    protected $casts = [
        'wfo_days' => 'integer',
        'wfh_days' => 'integer',
        'out_of_town_days' => 'integer',
        'business_trips' => 'integer',
        'training_days' => 'integer',
        'overtime_hours' => 'integer',
        'late_count' => 'integer',
        'total_mandays' => 'integer',
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
