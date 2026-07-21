<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'periode',

        'status',
        'requested_by', 'requested_at',
        'approved_by', 'approved_at',
        'paid_by', 'paid_at',
        'approval_note',

        // ciphertext
        'gaji_pokok_enc','tunjangan_enc','potongan_enc','total_enc','catatan_enc',
        
        // breakdown fields
        'calculation_mode', 'calculated_at',
        'total_allowances_enc', 'total_deductions_enc',
        'engine_version', 'period_from', 'period_to',

        // ✅ HYBRID fields
        'dek_enc',
        'enc_meta',

        // meta algoritma
        'salary_alg',
        'salary_key_id',

        'paid_proof_path',
        'paid_proof_uploaded_by',
        'paid_proof_uploaded_at',
        'paid_ref',
        'paid_note',

    ];

    protected $hidden = [
        'gaji_pokok_enc',
        'tunjangan_enc',
        'potongan_enc',
        'total_enc',
        'catatan_enc',
        'total_allowances_enc',
        'total_deductions_enc',

        // optional: kalau kamu mau sembunyikan juga
        // 'dek_enc',
        // 'enc_meta',
    ];

    protected $casts = [
        'periode' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'calculated_at' => 'datetime',

        // ✅ supaya enc_meta otomatis jadi array
        'enc_meta' => 'array',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function allowances()
    {
        return $this->hasMany(PayrollAllowance::class);
    }

    public function deductions()
    {
        return $this->hasMany(PayrollDeduction::class);
    }
}
