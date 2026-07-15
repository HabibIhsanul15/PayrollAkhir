<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'display_order',
        'description',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function specialDeductions()
    {
        return $this->hasMany(SpecialDeduction::class);
    }
}
