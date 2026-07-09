<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobHistory extends Model
{
    protected $fillable = [
        'employee_id',
        'grade_id',
        'position',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
}
