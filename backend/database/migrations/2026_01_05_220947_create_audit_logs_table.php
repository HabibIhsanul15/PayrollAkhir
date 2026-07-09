<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50); // VIEW_PAYROLL, DOWNLOAD_PAYROLL_PDF, dll
            $table->unsignedBigInteger('payroll_id')->nullable();

            $table->string('ip_address', 45)->nullable();   // IPv4/IPv6
            $table->text('user_agent')->nullable();

            $table->json('meta')->nullable();              // opsional (role, masked, dll)
            $table->timestamps();

            // index biar query cepat
            $table->index(['action']);
            $table->index(['user_id']);
            $table->index(['payroll_id']);

            // FK opsional (kalau kamu mau strict)
            // $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            // $table->foreign('payroll_id')->references('id')->on('payrolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
