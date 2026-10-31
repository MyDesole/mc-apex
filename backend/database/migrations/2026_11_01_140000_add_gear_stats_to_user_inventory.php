<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Статы экземпляра экипировки.
 *
 * Предмет магазина общий для всех, а редкость и характеристики выпадают
 * каждому свои — поэтому они живут на строке инвентаря, а не в metadata
 * предмета. В магазине metadata остаётся базой (common), а gear_stats
 * перекрывает её конкретными числами.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->json('gear_stats')->nullable()->after('pet_gear');
        });
    }

    public function down(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn('gear_stats');
        });
    }
};
