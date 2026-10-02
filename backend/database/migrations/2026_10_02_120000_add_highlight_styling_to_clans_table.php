<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Оформление подсветки клана.
 *
 * Раньше подсветка была одна на всех — жёлтая рамка. Теперь к ней
 * добавляются цвет и эффект: покупаются в магазине как косметика
 * и применяются к своему клану.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            // Ключ цвета подсветки из набора (gold, crimson, cyan, ...)
            $table->string('highlight_color', 32)->nullable()->after('highlight_until');

            // Ключ эффекта (glow, pulse, animated, ...)
            $table->string('highlight_effect', 32)->nullable()->after('highlight_color');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn(['highlight_color', 'highlight_effect']);
        });
    }
};
