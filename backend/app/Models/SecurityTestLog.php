<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityTestLog extends Model
{
    protected $fillable = [
        'test_name','alg','payroll_id','result','expected','actual','meta'
    ];
    
    protected $casts = ['meta' => 'array'];

}
