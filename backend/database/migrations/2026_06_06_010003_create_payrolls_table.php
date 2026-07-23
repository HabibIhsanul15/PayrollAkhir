<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('payrolls', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
        $table->date('periode');
        $table->string('status', 20)->default('draft');
        
        $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('requested_at')->nullable();
        $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('approved_at')->nullable();
        $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('paid_at')->nullable();
        
        $table->string('paid_proof_path', 255)->nullable();
        $table->foreignId('paid_proof_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('paid_proof_uploaded_at')->nullable();
        
        $table->string('paid_ref', 120)->nullable();
        $table->text('paid_note')->nullable();
        $table->text('approval_note')->nullable();

        $table->longText('gaji_pokok_enc')->nullable();
        $table->longText('tunjangan_enc')->nullable();
        $table->longText('potongan_enc')->nullable();
        
        $table->string('calculation_mode', 20)->default('manual');
        $table->date('period_from')->nullable();
        $table->date('period_to')->nullable();

        $table->timestamp('calculated_at')->nullable();

        $table->longText('total_enc')->nullable();

        $table->string('salary_alg', 20)->default('AES');
        $table->string('salary_key_id', 50)->nullable();
        $table->longText('dek_enc')->nullable();
        $table->longText('enc_meta')->nullable();

        $table->timestamps();

        $table->unique(['employee_id', 'periode']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
