<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutationRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'requested_date' => 'date',
        'effective_date' => 'date',
    ];

    protected $appends = ['activation_status'];

    /**
     * Status penerapan jabatan dibedakan dari status persetujuan.
     * Pengajuan yang sudah disetujui tetapi tanggal efektifnya masih di masa
     * depan tetap berstatus "scheduled" sampai hari efektif tersebut tiba.
     */
    public function getActivationStatusAttribute(): ?string
    {
        if ($this->status !== 'approved' || ! $this->effective_date instanceof Carbon) {
            return null;
        }

        return $this->effective_date->isFuture() ? 'scheduled' : 'active';
    }

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
