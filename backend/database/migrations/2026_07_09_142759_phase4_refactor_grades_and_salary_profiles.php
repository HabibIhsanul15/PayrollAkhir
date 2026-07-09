<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Move default_base_salary to grade_allowance_rates
        $positionType = DB::table('allowance_types')->where('code', 'position')->first();
        if ($positionType) {
            $grades = DB::table('grades')->get();
            foreach ($grades as $grade) {
                if ($grade->default_base_salary !== null) {
                    DB::table('grade_allowance_rates')->updateOrInsert(
                        ['grade_id' => $grade->id, 'allowance_type_id' => $positionType->id],
                        ['rate_amount' => $grade->default_base_salary, 'updated_at' => now()]
                    );
                }
            }
        }

        // 2. Drop default_base_salary from grades
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('default_base_salary');
        });

        // 3. Rename base_salary to position_allowance in salary_profiles
        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->renameColumn('base_salary', 'position_allowance');
            $table->renameColumn('base_salary_enc', 'position_allowance_enc');
        });
    }

    public function down(): void
    {
        Schema::table('salary_profiles', function (Blueprint $table) {
            $table->renameColumn('position_allowance', 'base_salary');
            $table->renameColumn('position_allowance_enc', 'base_salary_enc');
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('default_base_salary', 14, 2)->nullable()->after('description');
        });
    }
};
