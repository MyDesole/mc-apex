<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Чейнджлог (что изменилось на сайте) и роадмап (цели проекта).
 * Оба ведёт администратор в админке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            // Ссылка на пост: /changelog/{slug} — её копируют в Telegram-канал
            $table->string('slug', 180)->unique();
            $table->string('version', 32)->nullable();
            // feature — новое, improvement — улучшение, fix — исправление
            $table->string('category', 16)->default('feature');
            $table->text('body');
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });

        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            // planned — в планах, in_progress — в работе, done — готово
            $table->string('status', 16)->default('planned');
            /*
             * Прогресс цели: metric — посчитать автоматически (активные игроки,
             * сыгранные КВ, турниры), иначе admin вводит current вручную.
             */
            $table->string('metric', 32)->nullable();
            $table->unsignedInteger('progress_current')->nullable();
            $table->unsignedInteger('progress_target')->nullable();
            $table->string('eta', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Текущие цели проекта — сразу на странице, админ потом правит
        $now = now();
        DB::table('roadmap_items')->insert([
            ['title' => '100 активных игроков', 'description' => 'Собрать живое сообщество: 100 игроков, которые заходят на сайт хотя бы раз в месяц.', 'status' => 'in_progress', 'metric' => 'active_players', 'progress_current' => null, 'progress_target' => 100, 'eta' => null, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Провести несколько клановых войн', 'description' => 'Кланы вызывают друг друга, собирают бойцов и фиксируют результаты на сайте.', 'status' => 'in_progress', 'metric' => 'clan_wars', 'progress_current' => null, 'progress_target' => 5, 'eta' => null, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Провести турнир', 'description' => 'Первый турнир APEX с сеткой, чемпионом и наградами.', 'status' => 'planned', 'metric' => 'tournaments', 'progress_current' => null, 'progress_target' => 1, 'eta' => null, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Открыть Minecraft-сервер для всех', 'description' => 'Публичный доступ к серверу APEX: заходи и играй без приглашения.', 'status' => 'planned', 'metric' => null, 'progress_current' => null, 'progress_target' => null, 'eta' => null, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_items');
        Schema::dropIfExists('changelog_entries');
    }
};
