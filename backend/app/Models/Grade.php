<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'code',
        'name',
        'level',
        'description',
        'is_active',
        'default_mandays_rate',
    ];

    protected $casts = [
        'level'     => 'integer',
        'is_active' => 'boolean',
        'default_mandays_rate' => 'float',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'grade_id');
    }

    public function allowanceRates()
    {
        return $this->hasMany(GradeAllowanceRate::class, 'grade_id');
    }
}
