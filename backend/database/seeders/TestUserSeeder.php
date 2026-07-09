<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TestUserSeeder
 *
 * Membuat akun testing untuk setiap role yang tersedia di project ini:
 *   - staff
 *   - fat
 *   - hcga
 *   - director
 *
 * Catatan penting (AuthController):
 *   Login mensyaratkan user HARUS terhubung ke Employee yang status-nya 'active'.
 *   Oleh karena itu, setiap akun testing juga dibuatkan data employee-nya.
 *
 * Aman dijalankan berkali-kali (menggunakan firstOrCreate / updateOrCreate).
 *
 * Jalankan dengan:
 *   php artisan db:seed --class=TestUserSeeder
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Password123!';

        $accounts = [
            [
                'role'          => 'staff',
                'name'          => 'Test Staff',
                'email'         => 'test.staff@payroll.test',
                'employee_code' => 'TST-STAFF-01',
                'department'    => 'General',
                'position'      => 'Staff',
            ],
            [
                'role'          => 'fat',
                'name'          => 'Test FAT',
                'email'         => 'test.fat@payroll.test',
                'employee_code' => 'TST-FAT-01',
                'department'    => 'Finance',
                'position'      => 'Finance & Accounting Team',
            ],
            [
                'role'          => 'hcga',
                'name'          => 'Test HCGA',
                'email'         => 'test.hcga@payroll.test',
                'employee_code' => 'TST-HCGA-01',
                'department'    => 'HR',
                'position'      => 'Human Capital & General Affairs',
            ],
            [
                'role'          => 'director',
                'name'          => 'Test Director',
                'email'         => 'test.director@payroll.test',
                'employee_code' => 'TST-DIR-01',
                'department'    => 'Management',
                'position'      => 'Director',
            ],
        ];

        foreach ($accounts as $account) {
            // 1. Buat atau perbarui User
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name'     => $account['name'],
                    'password' => Hash::make($password),
                    'role'     => $account['role'],
                ]
            );

            // 2. Buat atau perbarui Employee yang terhubung ke user ini
            //    (Hanya untuk STAFF)
            if ($account['role'] === 'staff') {
                Employee::updateOrCreate(
                    ['employee_code' => $account['employee_code']],
                    [
                        'user_id'    => $user->id,
                        'name'       => $account['name'],
                        'department' => $account['department'],
                        'position'   => $account['position'],
                        'status'     => 'active',
                    ]
                );
            }

            $this->command->info(
                "✅ [{$account['role']}] {$account['email']} → OK"
            );
        }

        $this->command->newLine();
        $this->command->info('Password semua akun: ' . $password);
    }
}
