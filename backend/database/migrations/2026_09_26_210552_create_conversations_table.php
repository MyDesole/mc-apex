<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'clan_message']);

            // для direct: нормализуем пару (всегда user_min < user_max), чтобы не создавать два диалога
            $table->foreignId('user_min_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_max_id')->nullable()->constrained('users')->cascadeOnDelete();

            // для clan_message: клан и автор
            $table->foreignId('clan_id')->nullable()->constrained('clans')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_min_id', 'user_max_id']);
            $table->unique(['clan_id', 'author_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
