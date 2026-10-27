<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сообщения: реакции эмодзи и закреплённое сообщение в диалоге.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            // Одна и та же реакция от игрока — один раз
            $table->unique(['message_id', 'user_id', 'emoji']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('pinned_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_message_id');
        });

        Schema::dropIfExists('message_reactions');
    }
};
