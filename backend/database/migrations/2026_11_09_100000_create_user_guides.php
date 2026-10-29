<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Путеводитель по сайту: какие шаги игрок прошёл или пропустил,
 * свернул ли панель. Стартовые монеты выдаются по шагам.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guides', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            // id шага => done | skipped
            $table->json('steps')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guides');
    }
};
