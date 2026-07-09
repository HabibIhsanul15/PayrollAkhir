<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crypto_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // payroll-rsa-2026-01
            $table->string('alg');  // RSA-2048
            $table->text('public_key_pem');
            $table->text('private_key_pem_enc'); // encrypted by APP_KEY
            $table->string('status')->default('active'); // active/rotated/revoked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_keys');
    }
};

