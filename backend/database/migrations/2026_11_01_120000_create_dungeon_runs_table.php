<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Походы в подземелья.
 *
 * Состояние похода — json: этаж, зал, здоровье отряда, лог и добыча.
 * Так поход переживает перезагрузку страницы и продолжается с другого
 * устройства, а серверу не нужно держать игру в памяти.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dungeon_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dungeon', 32);
            $table->string('status', 16)->default('active');
            $table->json('squad');
            $table->json('state');
            $table->unsignedInteger('coins')->default(0);
            $table->timestamps();

            // Активный поход ищется на каждом открытии экрана
            $table->index(['user_id', 'status']);
            // Дневной лимит считается по created_at
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeon_runs');
    }
};
