<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add rejection_reason column
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });

        // Expand enum to include 'cancelled'
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mutation_requests MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mutation_requests MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
        }
    }
};
