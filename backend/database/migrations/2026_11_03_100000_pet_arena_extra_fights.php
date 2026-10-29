<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Дополнительные бои на арене за монеты: сколько игрок купил сегодня.
 * Каждый следующий бой за день дороже предыдущего.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_arena_extra_fights', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedSmallInteger('bought')->default(0);
            $table->timestamps();

            $table->primary(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_arena_extra_fights');
    }
};
