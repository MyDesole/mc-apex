<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            // сохраняем диапазон тира на момент победы — на случай, если турнир потом переименуют
            $table->string('min_tier', 2)->nullable();
            $table->string('max_tier', 2)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'tournament_id']);
            $table->index(['user_id', 'max_tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_wins');
    }
};
