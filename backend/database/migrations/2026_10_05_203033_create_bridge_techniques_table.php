<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Виды бриджа: то, что игрок умеет и что подтверждает тестер.
 *
 * Список живёт в базе, а не в коде: виды можно добавлять без выкладки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_techniques', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 96);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_techniques');
    }
};
