<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Рейд «Разлом»: группы по 3 игрока (по 3 питомца), пошаговый синхронный
 * бой с таймером раунда. Осколки Разлома — валюта уникальной лавки;
 * raid_banned_until — штраф за лив из боя.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raids', function (Blueprint $table) {
            $table->id();
            $table->timestamp('slot_at');
            // lobby — набор группы, active — идёт бой, won / lost — итог, expired — не начали вовремя
            $table->string('status', 10)->default('lobby');
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('boss_hp')->default(0);
            $table->unsignedInteger('boss_max')->default(0);
            $table->unsignedSmallInteger('round')->default(0);
            $table->timestamp('round_ends_at')->nullable();
            // Сколько игроков вышло из боя: каждый ливер усиливает босса
            $table->unsignedTinyInteger('deserters')->default(0);
            $table->json('log')->nullable();
            $table->json('last_round')->nullable();
            $table->timestamps();

            $table->index(['status', 'slot_at']);
        });

        Schema::create('raid_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Бойцы: id питомца, имя, характеристики и текущее здоровье
            $table->json('units');
            // Выбранные приёмы на этот раунд: id питомца → приём
            $table->json('moves')->nullable();
            $table->unsignedTinyInteger('afk_rounds')->default(0);
            $table->timestamp('left_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['raid_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('raid_banned_until')->nullable();
            $table->unsignedInteger('rift_shards')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['raid_banned_until', 'rift_shards']);
        });

        Schema::dropIfExists('raid_members');
        Schema::dropIfExists('raids');
    }
};
