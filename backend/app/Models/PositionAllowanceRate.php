<?php

namespace App\Models;

use App\Services\SensitiveFieldCipherService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PositionAllowanceRate extends Model
{
    protected $fillable = [
        'position_id',
        'allowance_type_id',
        'rate_amount_enc',
        'is_active',
        'salary_alg',
        'salary_key_id',
        'dek_enc',
        'enc_meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enc_meta' => 'array',
    ];

    protected $hidden = [
        'rate_amount_enc',
        'salary_alg',
        'salary_key_id',
        'dek_enc',
        'enc_meta',
    ];

    protected $appends = [
        'rate_amount',
    ];

    public function getRateAmountAttribute(mixed $value): ?float
    {
        $amount = app(SensitiveFieldCipherService::class)->decryptField($this, 'rate_amount');

        return $amount === null || $amount === '' ? null : (float) $amount;
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function allowanceType()
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }
}
