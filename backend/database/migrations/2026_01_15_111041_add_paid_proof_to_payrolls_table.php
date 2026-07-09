<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // bukti transfer / proof pembayaran
            $table->string('paid_proof_path')->nullable()->after('paid_at');
            $table->unsignedBigInteger('paid_proof_uploaded_by')->nullable()->after('paid_proof_path');
            $table->timestamp('paid_proof_uploaded_at')->nullable()->after('paid_proof_uploaded_by');

            // optional: nomor referensi & catatan pembayaran
            $table->string('paid_ref', 120)->nullable()->after('paid_proof_uploaded_at');
            $table->text('paid_note')->nullable()->after('paid_ref');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'paid_proof_path',
                'paid_proof_uploaded_by',
                'paid_proof_uploaded_at',
                'paid_ref',
                'paid_note',
            ]);
        });
    }
};
