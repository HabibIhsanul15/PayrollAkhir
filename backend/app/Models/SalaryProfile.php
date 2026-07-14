<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryProfile extends Model
{
    protected $fillable = [
        'employee_id',
        'grade_id',
        'position',
        'base_salary_basis',

        // plaintext (transisi)
        'base_salary_amount',
        'position_allowance',
        'allowance_fixed',
        'deduction_fixed',
        'daily_rate',
        'overtime_rate_per_hour',
        'late_penalty_per_minute',
        'mandays_rate',

        // ciphertext
        'base_salary_amount_enc',
        'position_allowance_enc',
        'allowance_fixed_enc',
        'deduction_fixed_enc',
        'daily_rate_enc',
        'overtime_rate_per_hour_enc',
        'late_penalty_per_minute_enc',
        'mandays_rate_enc',

        'effective_from',

        // metadata enkripsi
        'salary_alg',
        'salary_key_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'grade_id' => 'integer',
        'base_salary_amount' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
