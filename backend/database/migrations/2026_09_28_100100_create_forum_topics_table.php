<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('forum_categories')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->string('title', 200);
            $table->string('slug', 220)->nullable();
            $table->text('body');

            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);

            // Кто последний ответил — для списка тем
            $table->foreignId('last_reply_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();

            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);

            // Мягкое удаление: модерация может скрыть тему, не теряя историю
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();

            $table->index(['category_id', 'is_pinned', 'last_reply_at']);
            $table->index('last_reply_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topics');
    }
};
