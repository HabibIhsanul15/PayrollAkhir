<?php

namespace Database\Seeders;

use App\Models\AllowanceType;
use Illuminate\Database\Seeder;

class AllowanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'transport_trip',
                'name' => 'Transport Trip',
                'calculation_type' => 'per_trip',
                'applies_to' => 'all',
                'display_order' => 1,
                'description' => 'Transport Trip (perpindahan project)',
                'is_active' => true,
            ],
            [
                'code' => 'meal',
                'name' => 'Tunjangan Makan',
                'calculation_type' => 'per_mandays',
                'applies_to' => 'all',
                'display_order' => 2,
                'description' => 'Tunjangan Makan di project',
                'is_active' => true,
            ],
            [
                'code' => 'position',
                'name' => 'Tunjangan Jabatan',
                'calculation_type' => 'flat',
                'applies_to' => 'all',
                'display_order' => 3,
                'description' => 'Tunjangan Jabatan (promosi/probation)',
                'is_active' => true,
            ],
            [
                'code' => 'childcare',
                'name' => 'Tunjangan Pengasuh (Childcare)',
                'calculation_type' => 'flat',
                'applies_to' => 'all',
                'display_order' => 4,
                'description' => 'Tunjangan Pengasuh (Childcare) untuk Project Partner',
                'is_active' => true,
            ],
            [
                'code' => 'training',
                'name' => 'Tunjangan Training',
                'calculation_type' => 'per_mandays',
                'applies_to' => 'all',
                'display_order' => 5,
                'description' => 'Tunjangan Training (Trainer)',
                'is_active' => true,
            ],
            [
                'code' => 'business_trip',
                'name' => 'Tunjangan Perjalanan Dinas (Luar Kota)',
                'calculation_type' => 'per_mandays',
                'applies_to' => 'all',
                'display_order' => 6,
                'description' => 'Tunjangan Perjalanan Dinas (Luar Kota) untuk Fix Rate Partner',
                'is_active' => true,
            ],
            [
                'code' => 'ho_transport_meal',
                'name' => 'Tunjangan Transport & Makan (Harian HO)',
                'calculation_type' => 'per_mandays',
                'applies_to' => 'all',
                'display_order' => 7,
                'description' => 'Tunjangan Transport & Makan (Harian HO) untuk Fix Rate Partner',
                'is_active' => true,
            ],
            [
                'code' => 'transport_insurance',
                'name' => 'Tunjangan Transport & Asuransi (Luar Kota)',
                'calculation_type' => 'per_mandays',
                'applies_to' => 'all',
                'display_order' => 8,
                'description' => 'Tunjangan Transport & Asuransi (Luar Kota / Project)',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            AllowanceType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
