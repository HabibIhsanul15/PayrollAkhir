<?php

namespace App\Models;

use App\Services\SensitiveFieldCipherService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAllowance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'mandays' => 'integer',
        'calculation_detail' => 'array',
        'enc_meta' => 'array',
    ];

    protected $hidden = [
        'amount_enc',
        'salary_alg',
        'salary_key_id',
        'dek_enc',
        'enc_meta',
    ];

    public function getAmountAttribute(mixed $value): ?float
    {
        if ($value !== null && $value !== '') {
            return (float) $value;
        }

        $amount = app(SensitiveFieldCipherService::class)->decryptField($this, 'amount');

        return $amount === null || $amount === '' ? null : (float) $amount;
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function allowanceType()
    {
        return $this->belongsTo(AllowanceType::class);
    }
}
