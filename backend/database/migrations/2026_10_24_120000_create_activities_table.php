<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лента событий сайта: «Сейчас на APEX».
 *
 * Каждая запись — готовое к показу событие: кто (actor), что (type)
 * и подробности (payload). Подробности хранятся снимком на момент
 * события: если клан потом переименуют, в ленте останется прежнее имя —
 * так и должно быть у истории.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->string('type', 40)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
