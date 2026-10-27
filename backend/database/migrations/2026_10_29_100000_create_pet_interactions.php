<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Питомец профиля оживает: его можно погладить, покормить и поиграть
 * с ним (раз в день каждому), от этого растёт уровень. Хозяин может
 * дать питомцу имя.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pet_name', 20)->nullable();
        });

        Schema::create('pet_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 12);
            $table->date('day');
            $table->timestamp('created_at')->useCurrent();

            // Одно действие каждого вида в день от каждого гостя
            $table->unique(['owner_id', 'actor_id', 'action', 'day']);
            $table->index(['owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_interactions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pet_name');
        });
    }
};
