<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add late penalty column to positions (if not exists)
        if (!Schema::hasColumn('positions', 'default_late_penalty_amount')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->decimal('default_late_penalty_amount', 14, 2)->nullable()->after('default_mandays_rate');
            });
        }

        // 2. Expand calculation_type enum to include 'per_hour'
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE allowance_types MODIFY COLUMN calculation_type ENUM('per_mandays','per_trip','flat','formula','per_hour') NOT NULL");
        }

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

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE allowance_types MODIFY COLUMN calculation_type ENUM('per_mandays','per_trip','flat','formula') NOT NULL");
        }

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('default_late_penalty_amount');
        });
    }
};
