<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // nullable: pegawai bisa ada tanpa akun
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('employee_code')->unique(); // NIP internal
            $table->string('name');

            $table->string('nik', 32)->nullable();
            $table->longText('nik_enc')->nullable();
            $table->string('npwp', 32)->nullable();
            $table->longText('npwp_enc')->nullable();
            $table->string('phone', 32)->nullable();
            $table->longText('phone_enc')->nullable();
            $table->text('address')->nullable();
            $table->longText('address_enc')->nullable();
            $table->string('pii_alg', 20)->default('AES');
            $table->string('pii_key_id', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->longText('bank_account_number_enc')->nullable();

            $table->string('department')->nullable();
            $table->string('position')->nullable();

            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->foreignId('work_basis_id')->nullable()->constrained('work_bases')->nullOnDelete();

            $table->unsignedTinyInteger('num_toddlers')->default(0)->comment('Jumlah balita, digunakan untuk syarat Tunjangan Pengasuh');
            $table->boolean('is_trainer')->default(false)->comment('Kategori trainer, digunakan untuk Tunjangan Training (1.5x rate)');
            $table->boolean('is_on_probation')->default(false)->comment('Masa percobaan promosi, Tunjangan Jabatan 50%');

            $table->enum('status', ['active','inactive'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
