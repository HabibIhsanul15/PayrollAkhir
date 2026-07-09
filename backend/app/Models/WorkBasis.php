<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkBasis extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'work_basis_id');
    }
}
