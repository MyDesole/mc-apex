<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('name');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // Итоги игрока в закрытом сезоне — снимок рейтинга на момент закрытия
        Schema::create('season_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->string('tier', 2);
            $table->unsignedInteger('tier_score');
            $table->timestamps();

            $table->unique(['season_id', 'user_id']);
            $table->index(['season_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_results');
        Schema::dropIfExists('seasons');
    }
};
