<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoKey extends Model
{
    protected $fillable = [
        'name','alg','public_key_pem','private_key_pem_enc','status'
    ];
}
