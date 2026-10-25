<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Жалобы игроков: на профиль, сообщение в чате, тему или ответ форума
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Что именно обжаловано: user | message | forum_topic | forum_reply
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('reason', 32);
            $table->text('comment')->nullable();
            // Копия текста на момент жалобы: автор мог удалить или исправить
            $table->text('snapshot')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->string('resolution', 32)->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderator_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['target_user_id', 'status']);
        });

        // Журнал действий персонала: кто, что и с кем сделал
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48)->index();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['target_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('reports');
    }
};
