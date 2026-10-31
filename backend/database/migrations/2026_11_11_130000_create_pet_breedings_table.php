<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Скрещивание питомцев: кто с кем и кто получился. Кулдаун считается по
 * записям за последние сутки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_breedings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent1_id');
            $table->unsignedBigInteger('parent2_id');
            $table->unsignedBigInteger('offspring_id');
            $table->timestamps();

            $table->index(['parent1_id', 'created_at']);
            $table->index(['parent2_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_breedings');
    }
};
