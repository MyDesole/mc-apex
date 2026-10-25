<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Очки общего рейтинга: среднее трёх тиров + турниры + клановые войны.
 * Хранятся в users.rating_points и пересчитываются по событиям
 * (RatingService). После миграции: php artisan apex:rating-recalc
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('rating_points')->default(0)->index();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            // Сколько очков рейтинга получает победитель — задаётся при создании
            $table->unsignedInteger('win_points')->default(50);
        });

        Schema::table('season_results', function (Blueprint $table) {
            $table->unsignedInteger('rating_points')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('season_results', fn (Blueprint $table) => $table->dropColumn('rating_points'));
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropColumn('win_points'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['rating_points']);
            $table->dropColumn('rating_points');
        });
    }
};
