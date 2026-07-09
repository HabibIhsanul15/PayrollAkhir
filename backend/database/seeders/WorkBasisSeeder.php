<?php

namespace Database\Seeders;

use App\Models\WorkBasis;
use Illuminate\Database\Seeder;

class WorkBasisSeeder extends Seeder
{
    public function run(): void
    {
        $bases = [
            [
                'code' => 'mandays',
                'name' => 'Mandays Rate',
                'description' => 'Mandays Rate basis',
                'is_active' => true,
            ],
            [
                'code' => 'monthly',
                'name' => 'Monthly Fixed',
                'description' => 'Monthly Fixed basis',
                'is_active' => true,
            ],
        ];

        foreach ($bases as $base) {
            WorkBasis::updateOrCreate(['code' => $base['code']], $base);
        }
    }
}
