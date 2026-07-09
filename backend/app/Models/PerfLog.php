<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfLog extends Model
{
    protected $fillable = [
    'scenario','alg',
    'payroll_id',
    'encrypt_ms',
    'decrypt_ms',
    'db_ms',
    'total_ms',
    'meta'
    ];

    protected $casts = [
    'meta' => 'array',
    ];

}
