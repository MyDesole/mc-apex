<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подвиды бриджа: уточнения внутри вида.
 *
 * Показываются пиллами под названием вида, например «Held Telly Bridge» →
 * «с удержанием», «на 1.8», «с разворотом».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_technique_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technique_id')->constrained('bridge_techniques')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 96);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Внутри одного вида ключ уникален
            $table->unique(['technique_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_technique_variants');
    }
};
