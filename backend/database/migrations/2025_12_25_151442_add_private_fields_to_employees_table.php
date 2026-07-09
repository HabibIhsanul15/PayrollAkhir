<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nik', 32)->nullable()->after('name');
            $table->string('npwp', 32)->nullable()->after('nik');

            $table->string('phone', 30)->nullable()->after('npwp');
            $table->text('address')->nullable()->after('phone');

            $table->string('bank_name', 100)->nullable()->after('address');
            $table->string('bank_account_name', 150)->nullable()->after('bank_name');
            $table->string('bank_account_number', 50)->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'nik','npwp','phone','address',
                'bank_name','bank_account_name','bank_account_number'
            ]);
        });
    }
};
