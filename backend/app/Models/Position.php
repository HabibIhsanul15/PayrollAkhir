<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'code',
        'name',
        'level',
        'description',
        'base_salary_basis',
        'default_base_salary_amount',
        'is_active',
        'default_mandays_rate',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
        'default_base_salary_amount' => 'float',
        'default_mandays_rate' => 'float',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function allowanceRates()
    {
        return $this->hasMany(PositionAllowanceRate::class, 'position_id');
    }

    public function salaryProfiles()
    {
        return $this->hasMany(SalaryProfile::class);
    }

    public function jobHistories()
    {
        return $this->hasMany(JobHistory::class);
    }
}
