<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Индексы под горячие запросы питомцев.
 *
 * - pet_interactions(inventory_id, action): опыт питомца (xpTotals, топ по уровню)
 *   ищет по inventory_id, а уникальный индекс начинается с owner_id — был полный скан.
 * - user_inventory(pet_rating): топ арены сортирует по рейтингу.
 * user_inventory(user_id, shop_item_id) уже есть — user_inventory_user_item_plain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_interactions', function (Blueprint $table) {
            $table->index(['inventory_id', 'action'], 'pet_interactions_inventory_action');
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->index('pet_rating', 'user_inventory_pet_rating');
        });
    }

    public function down(): void
    {
        Schema::table('pet_interactions', function (Blueprint $table) {
            $table->dropIndex('pet_interactions_inventory_action');
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropIndex('user_inventory_pet_rating');
        });
    }
};
