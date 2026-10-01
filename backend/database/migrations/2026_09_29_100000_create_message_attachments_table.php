<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();

            // null — файл загружен, но сообщение ещё не отправлено
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_image')->default(false);

            $table->timestamps();

            $table->index('message_id');
            $table->index(['user_id', 'message_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            // Тело может быть пустым, если сообщение состоит только из вложения
            $table->text('body')->nullable()->change();

            $table->timestamp('edited_at')->nullable();

            // Ускоряет выборку непрочитанных и сортировку истории
            $table->index(['conversation_id', 'id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn('edited_at');
        });

        Schema::dropIfExists('message_attachments');
    }
};
