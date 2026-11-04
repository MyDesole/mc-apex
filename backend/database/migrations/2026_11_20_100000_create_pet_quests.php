<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ежедневные и недельные задания питомцев и Жетоны удачи: валюта азарта
 * (сундуки и колесо), которую дают задания. Рубли заданиями не выдаются.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('quest', 32);
            // d:2026-11-20 для суточных, w:2026-47 для недельных (по Москве)
            $table->string('period', 16);
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quest', 'period']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('pet_tokens')->default(0)->after('rubles');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('pet_tokens'));
        Schema::dropIfExists('pet_quests');
    }
};
