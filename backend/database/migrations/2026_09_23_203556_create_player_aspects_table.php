<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_aspects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['pvp', 'bedwars']); // p-ранг / b-ранг
            $table->unsignedTinyInteger('block_placing')->default(0);  // БП
            $table->unsignedTinyInteger('rotka')->default(0);           // Ротка
            $table->unsignedTinyInteger('movement')->default(0);        // Мувмент
            $table->unsignedTinyInteger('building')->default(0);        // Строительство
            $table->unsignedTinyInteger('ppl')->default(0);             // ППЛ
            $table->timestamps();

            $table->unique(['user_id', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_aspects');
    }
};
