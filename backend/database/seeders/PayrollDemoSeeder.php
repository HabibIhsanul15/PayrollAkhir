<?php

namespace Database\Seeders;

use App\Models\AllowanceType;
use App\Models\CryptoKey;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\PositionAllowanceRate;
use App\Models\SalaryProfile;
use App\Models\User;
use App\Services\CryptoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class PayrollDemoSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $now = now();
        $periodMonth = PayrollPeriod::currentMonth();
        PayrollPeriod::forMonth($periodMonth);
        $this->seedActiveRsaKey();

        $positions = $this->seedPositions();
        $allowances = $this->seedAllowanceTypes();
        $this->seedAllowanceRates($positions, $allowances);

        $fat = $this->seedUser('Finance Admin', 'fat@payroll.test', 'fat');
        $this->seedUser('Admin HCGA', 'hcga@payroll.test', 'hcga');
        $this->seedUser('Direktur', 'director@payroll.test', 'director');

        $employees = [
            [
                'name' => 'Andi Pratama',
                'email' => 'andi.pegawai@payroll.test',
                'code' => 'PGW-001',
                'department' => 'Operasional',
                'position' => $positions['pegawai'],
                'base_salary' => 250000,
                'bank' => 'BCA',
                'account' => '1234567801',
                'wfo_days' => 20,
                'business_trips' => 1,
            ],
            [
                'name' => 'Siti Rahma',
                'email' => 'siti.pegawai@payroll.test',
                'code' => 'PGW-002',
                'department' => 'Keuangan',
                'position' => $positions['supervisor'],
                'base_salary' => 350000,
                'bank' => 'Mandiri',
                'account' => '1234567802',
                'wfo_days' => 21,
                'business_trips' => 0,
            ],
            [
                'name' => 'Rina Wijaya',
                'email' => 'rina.pegawai@payroll.test',
                'code' => 'PGW-003',
                'department' => 'Human Capital',
                'position' => $positions['manager'],
                'base_salary' => 450000,
                'bank' => 'BNI',
                'account' => '1234567803',
                'wfo_days' => 19,
                'business_trips' => 2,
            ],
        ];

        foreach ($employees as $data) {
            $user = $this->seedUser($data['name'], $data['email'], 'staff');
            $this->seedEmployee($data, $user, $fat, $periodMonth, $now);
        }

        $this->command?->info('Data demo payroll berhasil dibuat.');
        $this->command?->info('Password semua akun: '.self::PASSWORD);
    }

    /** @return array<string, Position> */
    private function seedPositions(): array
    {
        $rows = [
            'manager' => [
                'code' => 'manager',
                'name' => 'Manager',
                'level' => 5,
                'description' => 'Jabatan manajerial.',
                'default_base_salary_amount' => 450000,
            ],
            'supervisor' => [
                'code' => 'supervisor',
                'name' => 'Supervisor',
                'level' => 7,
                'description' => 'Jabatan pengawas operasional.',
                'default_base_salary_amount' => 350000,
            ],
            'pegawai' => [
                'code' => 'staff',
                'name' => 'Pegawai',
                'level' => 8,
                'description' => 'Jabatan pegawai operasional.',
                'default_base_salary_amount' => 250000,
            ],
        ];

        foreach ($rows as $key => $row) {
            $rows[$key] = Position::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
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
                'name' => 'Tunjangan Transport Perjalanan',
                'calculation_type' => 'per_trip',
                'input_source' => 'business_trips',
                'display_order' => 2,
                'description' => 'Dihitung dari jumlah perjalanan dinas.',
            ],
            'position' => [
                'code' => 'position',
                'name' => 'Tunjangan Jabatan',
                'calculation_type' => 'flat',
                'input_source' => null,
                'display_order' => 3,
                'description' => 'Nominal tetap sesuai jabatan.',
            ],
        ];

        foreach ($rows as $key => $row) {
            $rows[$key] = AllowanceType::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]);
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
        ];

        foreach ($rates as $positionKey => $allowanceRates) {
            foreach ($allowanceRates as $allowanceKey => $amount) {
                PositionAllowanceRate::updateOrCreate([
                    'position_id' => $positions[$positionKey]->id,
                    'allowance_type_id' => $allowances[$allowanceKey]->id,
                ], [
                    'rate_amount' => $amount,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function seedUser(string $name, string $email, string $role): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
        ]);
    }

    private function seedActiveRsaKey(): void
    {
        if (CryptoKey::where('status', 'active')->exists()) {
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

        CryptoKey::create([
            'name' => 'demo-rsa-'.now()->format('Y-m-d'),
            'alg' => 'RSA-2048',
            'public_key_pem' => $details['key'],
            'private_key_pem_enc' => Crypt::encryptString($privateKeyPem),
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function seedEmployee(array $data, User $user, User $fat, string $periodMonth, Carbon $now): void
    {
        /** @var Position $position */
        $position = $data['position'];
        $effectiveFrom = $now->copy()->subYear()->startOfMonth()->toDateString();
        $keyId = CryptoService::keyId();

        $employee = Employee::updateOrCreate(['employee_code' => $data['code']], [
            'user_id' => $user->id,
            'name' => $data['name'],
            'join_date' => $effectiveFrom,
            'department' => $data['department'],
            'position' => $position->name,
            'position_id' => $position->id,
            'status' => 'active',
            'num_toddlers' => 0,
            'nik_enc' => CryptoService::encryptAESGCM('317400000000'.substr((string) $data['code'], -3)),
            'npwp_enc' => CryptoService::encryptAESGCM('12.345.678.9-012.000'),
            'phone_enc' => CryptoService::encryptAESGCM('0812345678'.substr((string) $data['code'], -1)),
            'address_enc' => CryptoService::encryptAESGCM('Jakarta, Indonesia'),
            'bank_name' => $data['bank'],
            'bank_account_name' => $data['name'],
            'bank_account_number_enc' => CryptoService::encryptAESGCM((string) $data['account']),
            'pii_alg' => 'AES',
            'pii_key_id' => $keyId,
        ]);

        $profile = SalaryProfile::updateOrCreate([
            'employee_id' => $employee->id,
            'effective_from' => $effectiveFrom,
        ], [
            'position_id' => $position->id,
            'position' => $position->name,
            'base_salary_amount_enc' => CryptoService::encryptAESGCM((string) $data['base_salary']),
            'position_allowance_enc' => CryptoService::encryptAESGCM('0'),
            'allowance_fixed_enc' => CryptoService::encryptAESGCM('0'),
            'deduction_fixed_enc' => CryptoService::encryptAESGCM('0'),
            'salary_alg' => 'AES',
            'salary_key_id' => $keyId,
        ]);

        $employee->jobHistories()->updateOrCreate(['start_date' => $effectiveFrom], [
            'position_id' => $position->id,
            'position' => $position->name,
            'status' => 'active',
            'notes' => 'Data demo awal.',
        ]);

        MonthlyRecap::updateOrCreate([
            'employee_id' => $employee->id,
            'salary_profile_id' => $profile->id,
            'period_month' => $periodMonth,
        ], [
            'wfo_days' => $data['wfo_days'],
            'wfh_days' => 0,
            'out_of_town_days' => 0,
            'business_trips' => $data['business_trips'],
            'training_days' => 0,
            'overtime_hours' => 0,
            'late_count' => 0,
            'total_mandays' => $data['wfo_days'],
            'is_finalized' => true,
            'finalized_at' => $now,
            'finalized_by' => $fat->id,
        ]);
    }
}
