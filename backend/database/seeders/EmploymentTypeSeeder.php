<?php

namespace Database\Seeders;

use App\Models\EmploymentType;
use Illuminate\Database\Seeder;

class EmploymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'project',
                'name' => 'Project Partner',
                'description' => 'Project Partner (mandays basis)',
                'is_active' => true,
            ],
            [
                'code' => 'fix_rate',
                'name' => 'Fix Rate (Head Office)',
                'description' => 'Fix Rate Partner (Head Office basis)',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            EmploymentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
