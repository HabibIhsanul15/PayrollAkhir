<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryProfile extends Model
{
    protected $fillable = [
        'employee_id',
        'position_id',
        'position',

        // ciphertext
        'base_salary_amount_enc',
        'position_allowance_enc',
        'allowance_fixed_enc',
        'deduction_fixed_enc',

        'effective_from',

        // metadata enkripsi
        'salary_alg',
        'salary_key_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'position_id' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
