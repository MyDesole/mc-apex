<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Время последней активности игрока.
 *
 * Нужно для статуса «онлайн»: мгновенно его даёт presence-канал Reverb,
 * а это поле служит запасным вариантом — если Reverb недоступен или
 * человек только что закрыл сайт, статус всё равно определяется.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->index()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
