<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подсветка клана как платная услуга.
 *
 * Раньше is_highlighted включался галочкой в настройках клана — бесплатно
 * и бессрочно. Теперь это предмет магазина: покупка включает подсветку
 * на срок, по истечении он снимается.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            // До какого момента клан подсвечен (null — не подсвечен)
            $table->timestamp('highlight_until')->nullable()->after('is_highlighted');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn('highlight_until');
        });
    }
};
