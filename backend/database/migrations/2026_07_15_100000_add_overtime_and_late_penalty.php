<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No schema alterations needed as they are merged into base migrations.

        // 3. Add AllowanceType for overtime
        if (DB::getDriverName() !== 'sqlite') {
            \App\Models\AllowanceType::updateOrCreate(
                ['code' => 'overtime'],
                [
                    'name' => 'Tunjangan Lembur',
                    'calculation_type' => 'per_hour',
                    'applies_to' => 'all',
                    'display_order' => 9,
                    'description' => 'Tunjangan lembur per jam kerja',
                    'is_active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        \App\Models\AllowanceType::where('code', 'overtime')->delete();

        // Schema reversions handled by base migrations
    }
};
