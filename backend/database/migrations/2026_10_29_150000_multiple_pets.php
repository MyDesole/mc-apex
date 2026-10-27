<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Несколько питомцев: одного и того же питомца можно иметь несколько
 * экземпляров (у каждого свои черты, номер и имя), а на профиле
 * гуляют до трёх сразу.
 *
 * Уникальность (user_id, shop_item_id) снимаем: для питомцев каждая
 * строка — отдельный экземпляр. Для остальных предметов одна строка на
 * предмет по-прежнему держит код (firstOrNew при покупке).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            // Сначала обычные индексы: на (user_id, …) опирается внешний ключ
            $table->index('user_id', 'user_inventory_user_id_plain');
            $table->index(['user_id', 'shop_item_id'], 'user_inventory_user_item_plain');
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'shop_item_id']);
            $table->string('pet_name', 20)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('profile_pets')->nullable();
        });

        // Надетый питомец — первым в списке; его имя переезжает в экземпляр
        $rows = DB::table('user_inventory as ui')
            ->join('shop_items as si', 'si.id', '=', 'ui.shop_item_id')
            ->join('users as u', 'u.id', '=', 'ui.user_id')
            ->where('si.type', 'profile_pet')
            ->whereNotNull('ui.equipped_at')
            ->get(['ui.id', 'ui.user_id', 'ui.pet_traits', 'ui.pet_serial', 'si.effect_value', 'u.pet_name']);

        foreach ($rows as $row) {
            DB::table('user_inventory')->where('id', $row->id)->update(['pet_name' => $row->pet_name]);

            DB::table('users')->where('id', $row->user_id)->update([
                'profile_pets' => json_encode([[
                    'inventory_id' => $row->id,
                    'pet' => $row->effect_value,
                    'traits' => json_decode((string) $row->pet_traits, true),
                    'serial' => $row->pet_serial,
                    'name' => $row->pet_name,
                ]]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_pets');
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn('pet_name');
            $table->unique(['user_id', 'shop_item_id']);
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropIndex('user_inventory_user_item_plain');
            $table->dropIndex('user_inventory_user_id_plain');
        });
    }
};
