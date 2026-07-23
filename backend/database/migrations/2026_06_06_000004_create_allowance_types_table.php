<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->enum('calculation_type', ['per_mandays', 'per_trip', 'flat', 'formula', 'per_hour', 'per_toddler']);
            $table->string('input_source', 50)->nullable();
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowance_types');
    }
};
