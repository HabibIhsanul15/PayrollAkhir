<?php

namespace App\Models;

use App\Services\SensitiveFieldCipherService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialDeduction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
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

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductionType()
    {
        return $this->belongsTo(DeductionType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
