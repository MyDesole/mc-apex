<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            // Ответ на конкретное сообщение (вложенность в один уровень)
            $table->foreignId('parent_id')->nullable()->constrained('forum_replies')->nullOnDelete();

            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);

            $table->timestamp('edited_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();

            $table->index(['topic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_replies');
    }
};
