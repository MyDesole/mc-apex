<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Что лайкнули: topic | reply
            $table->string('likeable_type', 32);
            $table->unsignedBigInteger('likeable_id');

            $table->timestamps();

            // Один лайк на объект от одного игрока
            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
            $table->index(['likeable_type', 'likeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_likes');
    }
};
