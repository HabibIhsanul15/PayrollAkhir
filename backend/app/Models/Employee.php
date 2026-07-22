<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'name',
        'join_date',
        'department',
        'position',
        'status',

        'bank_name',
        'bank_account_name',

        // data pribadi terenkripsi
        'nik_enc',
        'npwp_enc',
        'phone_enc',
        'address_enc',
        'bank_account_number_enc',

        // metadata
        'pii_alg',
        'pii_key_id',

        // Phase 1 fields
        'position_id',
        'num_toddlers',
        'is_trainer',
        'is_on_probation',
    ];

    protected $casts = [
        'join_date' => 'date',
        'num_toddlers' => 'integer',
        'is_trainer' => 'boolean',
        'is_on_probation' => 'boolean',
    ];

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }


    public function payrolls()
    {
        return $this->hasMany(\App\Models\Payroll::class, 'employee_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function salaryProfiles()
    {
        return $this->hasMany(\App\Models\SalaryProfile::class, 'employee_id');
    }

    public function currentSalaryProfile($date = null)
    {
        $date = $date ?: now()->toDateString();

        return $this->salaryProfiles()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function payrollReadiness($date = null): array
    {
        $missing = [];

        if ($this->status !== 'active') {
            $missing[] = 'Status karyawan tidak aktif';
        }
        if (! $this->position_id) {
            $missing[] = 'Jabatan belum dipilih';
        }
        if (! $this->currentSalaryProfile($date)) {
            $missing[] = 'Profil gaji efektif belum tersedia';
        }

        return [
            'ready' => $missing === [],
            'missing' => $missing,
        ];
    }

    public function monthlyRecaps()
    {
        return $this->hasMany(MonthlyRecap::class);
    }

    public function jobHistories()
    {
        return $this->hasMany(JobHistory::class);
    }
}
