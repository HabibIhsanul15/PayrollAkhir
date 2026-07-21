<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MutationRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = \App\Models\Employee::where('status', 'active')->get();
        $positions = \App\Models\Position::all();

        if ($employees->isEmpty() || $positions->isEmpty()) {
            return;
        }

        $types = ['promotion', 'demotion'];
        $statuses = ['pending', 'approved', 'rejected'];

        for ($i = 0; $i < 10; $i++) {
            $employee = $employees->random();
            $targetPosition = $positions->where('id', '!=', $employee->position_id)->random() ?? $positions->random();

            \App\Models\MutationRequest::create([
                'employee_id' => $employee->id,
                'target_position_id' => $targetPosition->id,
                'mutation_type' => $types[array_rand($types)],
                'effective_date' => \Carbon\Carbon::now()->addMonths(rand(0, 2))->startOfMonth(),
                'reason' => 'Contoh pengajuan dari seeder ke-' . ($i + 1),
                'status' => $statuses[array_rand($statuses)],
                'requested_by' => 1,
            ]);
        }
    }
}
