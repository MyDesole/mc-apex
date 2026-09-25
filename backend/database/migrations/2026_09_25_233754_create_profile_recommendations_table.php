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
        Schema::create('profile_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5, опционально
            $table->boolean('is_hidden')->default(false);      // цель может скрыть
            $table->timestamps();

            $table->unique(['author_id', 'target_id']);        // один отзыв на пару
            $table->index(['target_id', 'created_at']);        // для выдачи списка
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_recommendations');
    }
};
