<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Покупки привилегий Minecraft-сервера за Рубли. Выдачу делает очередь
 * minecraft_grants (плагин забирает команды), здесь — журнал и лимиты.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privilege_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 32);
            $table->unsignedInteger('price');
            $table->unsignedBigInteger('grant_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'item_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privilege_orders');
    }
};
