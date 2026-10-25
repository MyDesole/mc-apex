<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Новая косметика из магазина:
 *   skin_backdrop — сцена за 3D-скином в профиле (Незер, Энд и т.д.);
 *   card_theme    — оформление карточки игрока (PNG для соцсетей).
 *
 * null — оформление по умолчанию.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('skin_backdrop', 32)->nullable();
            $table->string('card_theme', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['skin_backdrop', 'card_theme']);
        });
    }
};
