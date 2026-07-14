<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'encrypt_ms' => 'float',
        'decrypt_ms' => 'float',
        'db_ms' => 'float',
        'total_ms' => 'float',
        'cipher_bytes' => 'integer',
        'meta' => 'array',
    ];
}
