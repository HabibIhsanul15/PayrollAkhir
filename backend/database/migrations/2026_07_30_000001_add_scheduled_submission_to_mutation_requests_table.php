<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->date('scheduled_submission_date')->nullable()->after('requested_date');
            $table->timestamp('submitted_at')->nullable()->after('scheduled_submission_date');
        });

        // Riwayat lama selalu dianggap sudah diajukan saat record dibuat.
        DB::table('mutation_requests')->update([
            'scheduled_submission_date' => DB::raw('COALESCE(requested_date, DATE(created_at))'),
            'submitted_at' => DB::raw('created_at'),
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mutation_requests MODIFY status ENUM('scheduled', 'pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('mutation_requests')
                ->where('status', 'scheduled')
                ->update(['status' => 'cancelled']);

            DB::statement("ALTER TABLE mutation_requests MODIFY status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'scheduled_submission_date']);
        });
    }
};
