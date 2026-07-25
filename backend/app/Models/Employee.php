<?php

namespace App\Models;

use App\Services\SensitiveFieldCipherService;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /** @var array<int, string> */
    private const PII_FIELDS = [
        'nik',
        'npwp',
        'phone',
        'address',
        'bank_account_number',
    ];

    /** @var array<string, ?string> */
    private array $decryptedPiiCache = [];

    private bool $decryptedPiiLoaded = false;

    protected $fillable = [
        'user_id',
        'employee_code',
        'name',
        'join_date',
        'status',

        'bank_name',
        'bank_account_name',

        // data pribadi terenkripsi
        'nik_enc',
        'npwp_enc',
        'phone_enc',
        'address_enc',
        'bank_account_number_enc',
        'dek_enc',
        'enc_meta',

        // metadata
        'pii_alg',
        'pii_key_id',

        // Phase 1 fields
        'position_id',
        'num_toddlers',
    ];

    protected $casts = [
        'join_date' => 'date',
        'num_toddlers' => 'integer',
        'enc_meta' => 'array',
    ];

    protected $hidden = [
        'nik_enc',
        'npwp_enc',
        'phone_enc',
        'address_enc',
        'bank_account_number_enc',
        'pii_alg',
        'pii_key_id',
        'dek_enc',
        'enc_meta',
    ];

    public function getNikAttribute(mixed $value): ?string
    {
        return $this->decryptedPii('nik', $value);
    }

    public function getNpwpAttribute(mixed $value): ?string
    {
        return $this->decryptedPii('npwp', $value);
    }

    public function getPhoneAttribute(mixed $value): ?string
    {
        return $this->decryptedPii('phone', $value);
    }

    public function getAddressAttribute(mixed $value): ?string
    {
        return $this->decryptedPii('address', $value);
    }

    public function getBankAccountNumberAttribute(mixed $value): ?string
    {
        return $this->decryptedPii('bank_account_number', $value);
    }

    private function decryptedPii(string $field, mixed $legacyValue): ?string
    {
        if ($legacyValue !== null && $legacyValue !== '') {
            return (string) $legacyValue;
        }

        // Seluruh PII pada employee memakai DEK yang sama. Dekripsi sekaligus
        // lalu simpan selama hidup model ini agar private RSA/DEK tidak dibuka
        // ulang untuk setiap accessor (nik, npwp, phone, alamat, rekening).
        if (! $this->decryptedPiiLoaded) {
            $this->decryptedPiiCache = app(SensitiveFieldCipherService::class)->decrypt(
                $this,
                self::PII_FIELDS,
                'pii_alg'
            );
            $this->decryptedPiiLoaded = true;
        }

        return $this->decryptedPiiCache[$field] ?? null;
    }

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
