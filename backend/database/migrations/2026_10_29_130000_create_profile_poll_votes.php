<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Опрос в профиле: голоса гостей. Блок опроса живёт в раскладке
 * (users.profile_blocks), голос привязан к id блока.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('block_id', 16);
            $table->unsignedTinyInteger('option');
            $table->timestamps();

            // Один голос от игрока в одном опросе (переголосовать можно)
            $table->unique(['owner_id', 'block_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_poll_votes');
    }
};
