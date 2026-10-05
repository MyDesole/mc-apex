<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чанковая загрузка видео на Apex.
 *
 * Видео грузится частями: ролик на две минуты может весить сотни
 * мегабайт, и одним запросом он не пройдёт. Части складываются во
 * временную папку, после последней собираются в один файл.
 *
 * Ссылка на видео у заявки заменяется на путь к файлу: внешние хостинги
 * больше не нужны. Старое поле video_url остаётся для уже созданных
 * заявок.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_video_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('original_name', 255);
            $table->string('mime', 96);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('total_chunks');

            // Какие части уже приняты: докачка после обрыва связи
            $table->json('received_chunks')->nullable();

            // Путь к собранному файлу на диске local
            $table->string('path', 512)->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('user_bridge_techniques', function (Blueprint $table) {
            $table->string('video_path', 512)->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_bridge_techniques', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });

        Schema::dropIfExists('bridge_video_uploads');
    }
};
