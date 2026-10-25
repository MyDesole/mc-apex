<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Макет профиля из магазина: другая расстановка блоков, цвета, фон
 * и шрифты страницы игрока. Пусто — обычный профиль.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_layout', 32)->nullable()->after('card_theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_layout');
        });
    }
};
