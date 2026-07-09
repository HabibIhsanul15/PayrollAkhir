<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unsignedBigInteger('rsa_key_id')->nullable()->after('salary_key_id');
            $table->longText('dek_enc')->nullable()->after('rsa_key_id');
            $table->json('enc_meta')->nullable()->after('dek_enc');

            $table->foreign('rsa_key_id')->references('id')->on('crypto_keys');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['rsa_key_id']);
            $table->dropColumn(['rsa_key_id','dek_enc','enc_meta']);
        });
    }
};

