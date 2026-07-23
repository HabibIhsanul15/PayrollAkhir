<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove employee flags that have no UI control and no remaining payroll rule.
     */
    public function up(): void
    {
        $columns = [];

        foreach (['is_trainer', 'is_on_probation'] as $column) {
            if (Schema::hasColumn('employees', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('employees', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Restore the flags with their original false default when rolled back.
     */
    public function down(): void
    {
        $columns = [];

        foreach (['is_trainer', 'is_on_probation'] as $column) {
            if (! Schema::hasColumn('employees', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('employees', function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->boolean($column)->default(false);
                }
            });
        }
    }
};
