<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutationRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function targetPosition()
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
