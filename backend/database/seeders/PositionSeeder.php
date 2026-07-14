<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [
                'code' => 'bod',
                'name' => 'Board of Directors',
                'level' => 1,
                'description' => 'Board of Directors',
                'is_active' => true,
            ],
            [
                'code' => 'pd',
                'name' => 'Project Director',
                'level' => 2,
                'description' => 'Project Director',
                'is_active' => true,
            ],
            [
                'code' => 'pm',
                'name' => 'Project Manager',
                'level' => 3,
                'description' => 'Project Manager',
                'is_active' => true,
            ],
            [
                'code' => 'gm',
                'name' => 'General Manager',
                'level' => 4,
                'description' => 'General Manager',
                'is_active' => true,
            ],
            [
                'code' => 'manager',
                'name' => 'Manager',
                'level' => 5,
                'description' => 'Manager',
                'is_active' => true,
            ],
            [
                'code' => 'consultant',
                'name' => 'Consultant',
                'level' => 6,
                'description' => 'Consultant',
                'is_active' => true,
            ],
            [
                'code' => 'supervisor',
                'name' => 'Supervisor',
                'level' => 7,
                'description' => 'Supervisor',
                'is_active' => true,
            ],
            [
                'code' => 'staff',
                'name' => 'Staff',
                'level' => 8,
                'description' => 'Staff',
                'is_active' => true,
            ],
        ];

        foreach ($positions as $Position) {
            Position::updateOrCreate(['code' => $Position['code']], $Position);
        }
    }
}
