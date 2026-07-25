<?php

namespace App\Models;

use App\Services\SensitiveFieldCipherService;
use Illuminate\Database\Eloquent\Model;

class SalaryProfile extends Model
{
    protected $fillable = [
        'employee_id',
        'position_id',

        // ciphertext
        'base_salary_amount_enc',
        'position_allowance_enc',
        'allowance_fixed_enc',
        'deduction_fixed_enc',

        'dek_enc',
        'enc_meta',

        'effective_from',

        // metadata enkripsi
        'salary_alg',
        'salary_key_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'position_id' => 'integer',
        'enc_meta' => 'array',
    ];

    protected $hidden = [
        'base_salary_amount_enc',
        'position_allowance_enc',
        'allowance_fixed_enc',
        'deduction_fixed_enc',
        'salary_alg',
        'salary_key_id',
        'dek_enc',
        'enc_meta',
    ];

    public function getBaseSalaryAmountAttribute(mixed $value): ?float
    {
        return $this->decryptedAmount('base_salary_amount', $value);
    }

    public function getPositionAllowanceAttribute(mixed $value): ?float
    {
        return $this->decryptedAmount('position_allowance', $value);
    }

    public function getAllowanceFixedAttribute(mixed $value): ?float
    {
        return $this->decryptedAmount('allowance_fixed', $value);
    }

    public function getDeductionFixedAttribute(mixed $value): ?float
    {
        return $this->decryptedAmount('deduction_fixed', $value);
    }

    private function decryptedAmount(string $field, mixed $legacyValue): ?float
    {
        if ($legacyValue !== null && $legacyValue !== '') {
            return (float) $legacyValue;
        }

        $amount = app(SensitiveFieldCipherService::class)->decryptField($this, $field);

        return $amount === null || $amount === '' ? null : (float) $amount;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
