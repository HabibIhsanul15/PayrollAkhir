<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number')->unique();
            $table->string('journal_type'); // ACCRUAL, PAYMENT, ADJUSTMENT
            $table->date('transaction_date');
            $table->string('reference_type')->nullable(); // e.g. payroll_period
            $table->string('reference_id')->nullable();   // e.g. 2026-07
            $table->text('description')->nullable();
            $table->string('status')->default('posted'); // posted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
