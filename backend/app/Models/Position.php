<?php

namespace App\Models;

use App\Services\SensitiveFieldCipherService;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'code',
        'name',
        'level',
        'description',
        'default_base_salary_amount_enc',
        'is_active',
        'default_late_penalty_amount_enc',
        'salary_alg',
        'salary_key_id',
        'dek_enc',
        'enc_meta',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
        'enc_meta' => 'array',
    ];

    protected $hidden = [
        'default_base_salary_amount_enc',
        'default_late_penalty_amount_enc',
        'salary_alg',
        'salary_key_id',
        'dek_enc',
        'enc_meta',
    ];

    protected $appends = [
        'default_base_salary_amount',
        'default_late_penalty_amount',
    ];

    public function getDefaultBaseSalaryAmountAttribute(mixed $value): ?float
    {
        return $this->decryptedAmount('default_base_salary_amount');
    }

    public function getDefaultLatePenaltyAmountAttribute(mixed $value): ?float
    {
        return $this->decryptedAmount('default_late_penalty_amount');
    }

    private function decryptedAmount(string $field): ?float
    {
        $value = app(SensitiveFieldCipherService::class)->decryptField($this, $field);

        return $value === null || $value === '' ? null : (float) $value;
    }

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
