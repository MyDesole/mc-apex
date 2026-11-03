<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Итоги дуэлей Alley, присланные плагином сервера
        Schema::create('minecraft_duels', function (Blueprint $table) {
            $table->id();
            $table->string('match_key', 64)->unique();
            $table->string('kit', 48)->index();
            $table->string('winner_name', 32)->index();
            $table->string('loser_name', 32)->index();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('loser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('ranked')->default(false);
            $table->timestamp('played_at')->index();
            $table->timestamps();

            $table->index(['winner_user_id', 'played_at']);
            $table->index(['loser_user_id', 'played_at']);
        });

        // Очередь выдач на сервере: покупка в магазине -> команды LuckPerms
        Schema::create('minecraft_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_item_id')->nullable()->constrained('shop_items')->nullOnDelete();
            $table->string('minecraft_username', 32);
            $table->json('commands');
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_grants');
        Schema::dropIfExists('minecraft_duels');
    }
};
