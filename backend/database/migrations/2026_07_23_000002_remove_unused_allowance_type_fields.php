<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove unused eligibility fields from allowance types.
     */
    public function up(): void
    {
        $columns = [];

        foreach (['condition_field', 'condition_operator', 'condition_value', 'applies_to'] as $column) {
            if (Schema::hasColumn('allowance_types', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('allowance_types', function (Blueprint $table) use ($columns) {
                $table->dropIndex(['applies_to', 'is_active']);
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * Restore the original optional fields when rolling the migration back.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('allowance_types', 'applies_to')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->string('condition_field', 50)->nullable();
                $table->string('condition_operator', 10)->nullable();
                $table->decimal('condition_value', 14, 2)->nullable();
                $table->enum('applies_to', ['all', 'project_only', 'fix_rate_only'])->default('all');
                $table->index(['applies_to', 'is_active']);
            });
        }
    }
};
