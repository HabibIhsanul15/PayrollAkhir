<?php

namespace Database\Seeders;

use App\Models\AllowanceType;
use App\Models\CryptoKey;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\PositionAllowanceRate;
use App\Models\SalaryProfile;
use App\Models\User;
use App\Services\SensitiveFieldCipherService;
use App\Services\PayrollCalculationService;
use App\Support\PayrollPeriodResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class PayrollDemoSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    private int $repairedPositionCiphers = 0;

    public function run(): void
    {
        $now = now();
        $currentPeriodMonth = PayrollPeriodResolver::currentMonth();
        $completedPeriodMonth = Carbon::createFromFormat('!Y-m', $currentPeriodMonth)
            ->subMonth()
            ->format('Y-m');
        $completedAt = Carbon::parse(PayrollPeriodResolver::forMonth($completedPeriodMonth)->end_date)->endOfDay();
        $this->seedActiveRsaKey();

        $positions = $this->seedPositions();
        $allowances = $this->seedAllowanceTypes();
        $this->seedAllowanceRates($positions, $allowances);

        $fat = $this->seedUser('Finance Admin', 'fat@payroll.test', 'fat');
        $this->seedUser('Admin HCGA', 'hcga@payroll.test', 'hcga');
        $director = $this->seedUser('Direktur', 'director@payroll.test', 'director');

        $employees = [
            [
                'name' => 'Andi Pratama',
                'email' => 'andi.pegawai@payroll.test',
                'code' => 'EMP-0001',
                'position' => $positions['pegawai'],
                'base_salary' => 250000,
                'bank' => 'BCA',
                'account' => '1234567801',
                'wfo_days' => 20,
                'num_toddlers' => 0,
            ],
            [
                'name' => 'Siti Rahma',
                'email' => 'siti.pegawai@payroll.test',
                'code' => 'EMP-0002',
                'position' => $positions['supervisor'],
                'base_salary' => 350000,
                'bank' => 'Mandiri',
                'account' => '1234567802',
                'wfo_days' => 21,
                'num_toddlers' => 0,
            ],
            [
                'name' => 'Rina Wijaya',
                'email' => 'rina.pegawai@payroll.test',
                'code' => 'EMP-0003',
                'position' => $positions['manager'],
                'base_salary' => 450000,
                'bank' => 'BNI',
                'account' => '1234567803',
                'wfo_days' => 19,
                'num_toddlers' => 0,
            ],
            [
                'name' => 'Habib',
                'email' => 'habib.pegawai@payroll.test',
                'code' => 'EMP-0004',
                'position' => $positions['project_director'],
                'base_salary' => 250000,
                'bank' => 'BRI',
                'account' => '0987877734',
                'wfo_days' => 5,
                'num_toddlers' => 0,
            ],
        ];

        foreach ($employees as $data) {
            $user = $this->seedUser($data['name'], $data['email'], 'staff');
            $this->seedEmployee($data, $user, $fat, $director, $completedPeriodMonth, $completedAt, $now);
        }

        $this->command?->info("Data demo payroll periode {$completedPeriodMonth} yang belum ada berhasil dibuat tanpa menimpa data yang sudah ada.");
        if ($this->repairedPositionCiphers > 0) {
            $this->command?->warn("{$this->repairedPositionCiphers} data enkripsi jabatan yang tidak konsisten telah dipulihkan dengan nilai potongan keterlambatan Rp0.");
        }
        $this->command?->info('Password demo untuk akun yang baru dibuat: '.self::PASSWORD);
    }

    /** @return array<string, Position> */
    private function seedPositions(): array
    {
        $rows = [
            'project_director' => [
                'code' => 'pd',
                'name' => 'Project Director',
                'level' => 4,
                'description' => 'Jabatan pimpinan proyek.',
                'default_base_salary_amount' => 250000,
                'default_late_penalty_amount' => 0,
            ],
            'manager' => [
                'code' => 'manager',
                'name' => 'Manager',
                'level' => 5,
                'description' => 'Jabatan manajerial.',
                'default_base_salary_amount' => 450000,
                'default_late_penalty_amount' => 0,
            ],
            'supervisor' => [
                'code' => 'supervisor',
                'name' => 'Supervisor',
                'level' => 7,
                'description' => 'Jabatan pengawas operasional.',
                'default_base_salary_amount' => 350000,
                'default_late_penalty_amount' => 0,
            ],
            'pegawai' => [
                'code' => 'staff',
                'name' => 'Pegawai',
                'level' => 8,
                'description' => 'Jabatan pegawai operasional.',
                'default_base_salary_amount' => 250000,
                'default_late_penalty_amount' => 0,
            ],
        ];

        foreach ($rows as $key => $row) {
            $baseSalary = $row['default_base_salary_amount'];
            $latePenalty = $row['default_late_penalty_amount'];
            unset($row['default_base_salary_amount']);
            unset($row['default_late_penalty_amount']);

            $position = Position::firstOrCreate(['code' => $row['code']], [
                ...$row,
                'is_active' => true,
                ...app(SensitiveFieldCipherService::class)->encryptAttributes([
                    'default_base_salary_amount' => $baseSalary,
                    'default_late_penalty_amount' => $latePenalty,
                ]),
            ]);

            // Seeder lama mengenkripsi ulang gaji pokok tanpa menyertakan nilai
            // potongan keterlambatan. Akibatnya, cipher lama memakai DEK berbeda.
            // Perbaikan ini hanya menyentuh field yang sudah tidak dapat didekripsi.
            if (! $position->wasRecentlyCreated) {
                $this->repairUnreadableLatePenaltyCipher($position, $latePenalty);
            }

            $rows[$key] = $position;
        }

        return $rows;
    }

    /** @return array<string, AllowanceType> */
    private function seedAllowanceTypes(): array
    {
        $rows = [
            'meal' => [
                'code' => 'meal',
                'name' => 'Tunjangan Makan',
                'calculation_type' => 'per_mandays',
                'input_source' => 'total_mandays',
                'display_order' => 1,
                'description' => 'Dihitung dari total hari dibayar.',
            ],
            'transport_trip' => [
                'code' => 'transport_trip',
                'name' => 'Tunjangan Transport Luar Kota',
                'calculation_type' => 'per_mandays',
                'input_source' => 'out_of_town_days',
                'display_order' => 2,
                'description' => 'Dihitung dari jumlah hari luar kota.',
            ],
            'position' => [
                'code' => 'position',
                'name' => 'Tunjangan Jabatan',
                'calculation_type' => 'flat',
                'input_source' => null,
                'display_order' => 3,
                'description' => 'Nominal tetap sesuai jabatan.',
            ],
            'toddler' => [
                'code' => 'tunjangan_anak',
                'name' => 'Tunjangan Anak',
                'calculation_type' => 'per_toddler',
                'input_source' => 'total_mandays',
                'display_order' => 4,
                'description' => 'Tunjangan untuk anak balita karyawan.',
            ],
        ];

        foreach ($rows as $key => $row) {
            $rows[$key] = AllowanceType::firstOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
        }

        return $rows;
    }

    /** @param array<string, Position> $positions @param array<string, AllowanceType> $allowances */
    private function seedAllowanceRates(array $positions, array $allowances): void
    {
        $rates = [
            'pegawai' => ['meal' => 25000, 'transport_trip' => 150000],
            'supervisor' => ['meal' => 30000, 'transport_trip' => 200000, 'position' => 500000],
            'manager' => ['meal' => 35000, 'transport_trip' => 250000, 'position' => 1000000],
            'project_director' => ['meal' => 50000, 'transport_trip' => 250000, 'position' => 4000000, 'toddler' => 1500000],
        ];

        foreach ($rates as $positionKey => $allowanceRates) {
            foreach ($allowanceRates as $allowanceKey => $amount) {
                PositionAllowanceRate::firstOrCreate([
                    'position_id' => $positions[$positionKey]->id,
                    'allowance_type_id' => $allowances[$allowanceKey]->id,
                ], [
                    'is_active' => true,
                    ...app(SensitiveFieldCipherService::class)->encryptAttributes([
                        'rate_amount' => $amount,
                    ]),
                ]);
            }
        }
    }

    private function seedUser(string $name, string $email, string $role): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
        ]);
    }

    private function seedActiveRsaKey(): void
    {
        $activeKey = CryptoKey::where('status', 'active')->latest('id')->first();
        if ($activeKey) {
            $this->assertRsaKeyIsUsable($activeKey);

            return;
        }

        $configPath = 'C:\\xampp\\php\\extras\\ssl\\openssl.cnf';
        if (! is_file($configPath)) {
            $configPath = 'C:\\xampp\\apache\\conf\\openssl.cnf';
        }
        if (! is_file($configPath)) {
            throw new \RuntimeException('Berkas konfigurasi OpenSSL tidak ditemukan.');
        }

        $config = ['config' => $configPath];
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ] + $config);

        if ($keyPair === false || ! openssl_pkey_export($keyPair, $privateKeyPem, null, $config)) {
            throw new \RuntimeException('Gagal membuat pasangan kunci RSA untuk data demo.');
        }

        $details = openssl_pkey_get_details($keyPair);
        if (! is_array($details) || empty($details['key'])) {
            throw new \RuntimeException('Kunci publik RSA demo tidak tersedia.');
        }

        $key = CryptoKey::create([
            'name' => 'demo-rsa-'.now()->format('Y-m-d'),
            'alg' => 'RSA-2048',
            'public_key_pem' => $details['key'],
            'private_key_pem_enc' => Crypt::encryptString($privateKeyPem),
            'status' => 'active',
        ]);

        // Jangan biarkan seeder selesai dengan key yang tidak dapat dipakai.
        // Ini menangkap mismatch APP_KEY sebelum ada payroll yang terenkripsi.
        $this->assertRsaKeyIsUsable($key);
    }

    private function assertRsaKeyIsUsable(CryptoKey $key): void
    {
        try {
            $privateKeyPem = Crypt::decryptString($key->private_key_pem_enc);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Kunci RSA aktif tidak dapat dibuka dengan APP_KEY saat ini. '
                .'Untuk lingkungan lokal, jalankan migrate:fresh --seed agar key dan data dibuat ulang bersamaan.',
                previous: $e,
            );
        }

        if (openssl_pkey_get_private($privateKeyPem) === false) {
            throw new \RuntimeException('Private key RSA aktif tidak valid.');
        }
    }

    /** @param array<string, mixed> $data */
    private function seedEmployee(
        array $data,
        User $user,
        User $fat,
        User $director,
        string $completedPeriodMonth,
        Carbon $completedAt,
        Carbon $now,
    ): void
    {
        /** @var Position $position */
        $position = $data['position'];
        $effectiveFrom = $now->copy()->subYear()->startOfMonth()->toDateString();
        $cipher = app(SensitiveFieldCipherService::class);

        $employee = Employee::firstOrCreate(['employee_code' => $data['code']], [
            'user_id' => $user->id,
            'name' => $data['name'],
            'join_date' => $effectiveFrom,
            'position_id' => $position->id,
            'status' => 'active',
            'num_toddlers' => (int) ($data['num_toddlers'] ?? 0),
            'bank_name' => $data['bank'],
            'bank_account_name' => $data['name'],
            ...$cipher->encryptAttributes([
                'nik' => '3174000000000'.substr((string) $data['code'], -3),
                'npwp' => '123456789012'.substr((string) $data['code'], -3),
                'phone' => '0812345678'.substr((string) $data['code'], -1),
                'address' => 'Jakarta, Indonesia',
                'bank_account_number' => (string) $data['account'],
            ], 'pii_alg', 'pii_key_id'),
        ]);

        $profile = SalaryProfile::firstOrCreate([
            'employee_id' => $employee->id,
            'effective_from' => $effectiveFrom,
        ], [
            'position_id' => $position->id,
            // Nominal mengikuti tarif tunjangan jabatan pada master posisi.
            // Null menandakan tidak ada override khusus untuk pegawai ini.
            ...$cipher->encryptAttributes([
                'base_salary_amount' => $data['base_salary'],
                'position_allowance' => null,
                'allowance_fixed' => 0,
                'deduction_fixed' => 0,
            ]),
        ]);

        $employee->jobHistories()->firstOrCreate(['start_date' => $effectiveFrom], [
            'position_id' => $position->id,
            'status' => 'active',
            'notes' => 'Data demo awal.',
        ]);

        $recap = MonthlyRecap::firstOrCreate([
            'employee_id' => $employee->id,
            'salary_profile_id' => $profile->id,
            'period_month' => $completedPeriodMonth,
        ], [
            'wfo_days' => $data['wfo_days'],
            'wfh_days' => 0,
            'out_of_town_days' => 0,
            'training_days' => 0,
            'overtime_hours' => 0,
            'late_count' => 0,
            'total_mandays' => $data['wfo_days'],
            'is_finalized' => true,
            'finalized_at' => $completedAt,
            'finalized_by' => $fat->id,
        ]);

        if ($recap->wasRecentlyCreated) {
            $this->seedCompletedPayroll($employee, $fat, $director, $completedPeriodMonth, $completedAt);
        }
    }

    private function seedCompletedPayroll(
        Employee $employee,
        User $fat,
        User $director,
        string $periodMonth,
        Carbon $completedAt,
    ): void {
        $period = PayrollPeriodResolver::forMonth($periodMonth);
        $payroll = Payroll::query()
            ->where('employee_id', $employee->id)
            ->whereDate('periode', $period->start_date)
            ->first();

        if ($payroll) {
            return;
        }

        $payroll = app(PayrollCalculationService::class)->calculateAndSave(
            $employee->id,
            $periodMonth,
            $fat->id,
        );

        $payroll->update([
            'status' => 'paid',
            'requested_by' => $fat->id,
            'requested_at' => $completedAt->copy()->subDays(3),
            'approved_by' => $director->id,
            'approved_at' => $completedAt->copy()->subDays(2),
            'paid_by' => $fat->id,
            'paid_at' => $completedAt,
            'paid_ref' => 'SEED-'.str_replace('-', '', $periodMonth).'-'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT),
            'paid_note' => 'Data demo pembayaran payroll periode '.$periodMonth.'.',
        ]);
    }

    private function repairUnreadableLatePenaltyCipher(Position $position, float|int $fallbackLatePenalty): void
    {
        $cipher = app(SensitiveFieldCipherService::class);

        try {
            $baseSalary = $cipher->decryptField($position, 'default_base_salary_amount');
        } catch (\Throwable $e) {
            $this->command?->warn("Jabatan {$position->code} tidak diperbaiki karena gaji pokoknya juga tidak dapat didekripsi.");

            return;
        }

        try {
            $cipher->decryptField($position, 'default_late_penalty_amount');

            return;
        } catch (\Throwable $e) {
            // Nilai cipher lama sudah tidak bisa dipulihkan. Gunakan nilai default
            // demo (Rp0), lalu enkripsi ulang bersama gaji pokok yang masih utuh.
        }

        $position->update($cipher->encryptAttributes([
            'default_base_salary_amount' => $baseSalary,
            'default_late_penalty_amount' => $fallbackLatePenalty,
        ]));

        $this->repairedPositionCiphers++;
    }
}
