<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бридж — основной режим игрока: очков в бридже больше, чем в PvP.
 * Тогда на сайте вместо тира показываем звание бриджера.
 * Флаг пересчитывает RatingService вместе с очками рейтинга.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bridge_primary')->default(false)->after('rating_points');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bridge_primary');
        });
    }
};
