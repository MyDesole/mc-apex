<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // К чему приложен файл: topic | reply
            $table->string('attachable_type', 32)->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();

            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            // Картинки показываем превью, остальное — ссылкой на скачивание
            $table->boolean('is_image')->default(false);

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_attachments');
    }
};
