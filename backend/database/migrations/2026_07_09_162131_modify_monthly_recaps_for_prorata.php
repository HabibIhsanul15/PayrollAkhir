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
        Schema::table('monthly_recaps', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['employee_id']);
                $table->dropUnique('monthly_recap_emp_period_unique');
            }
            
            // Add salary_profile_id column
            $table->foreignId('salary_profile_id')->nullable()->constrained('salary_profiles')->nullOnDelete();
        });

        Schema::table('monthly_recaps', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                // Re-add foreign key
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                
                // Add new composite unique index
                $table->unique(['employee_id', 'period_month', 'salary_profile_id'], 'recap_emp_period_profile_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_recaps', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropUnique('recap_emp_period_profile_unique');
                $table->dropForeign(['salary_profile_id']);
            }
            $table->dropColumn('salary_profile_id');
            
            if (DB::getDriverName() !== 'sqlite') {
                $table->unique(['employee_id', 'period_month'], 'monthly_recap_emp_period_unique');
            }
        });
    }
};
