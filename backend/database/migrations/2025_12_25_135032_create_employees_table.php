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
            $table->string('department')->nullable();
            $table->string('position')->nullable();

            $table->enum('status', ['active','inactive'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
