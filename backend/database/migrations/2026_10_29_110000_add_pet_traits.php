<?php

use App\Domains\Players\Support\PetTraits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Уникальные питомцы: у каждого экземпляра в инвентаре свои черты
 * (окрас, фон, аксессуар) и номер; у игрока — черты надетого питомца.
 * Уже выданным питомцам черты выпадают при миграции.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->json('pet_traits')->nullable();
            $table->unsignedInteger('pet_serial')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('pet_traits')->nullable();
        });

        // Чеканим уже выданных питомцев по порядку получения
        $rows = DB::table('user_inventory as ui')
            ->join('shop_items as si', 'si.id', '=', 'ui.shop_item_id')
            ->where('si.type', 'profile_pet')
            ->orderBy('ui.id')
            ->get(['ui.id', 'ui.user_id', 'ui.shop_item_id', 'ui.equipped_at']);

        $serials = [];

        foreach ($rows as $row) {
            $serials[$row->shop_item_id] = ($serials[$row->shop_item_id] ?? 0) + 1;
            $traits = PetTraits::roll();

            DB::table('user_inventory')->where('id', $row->id)->update([
                'pet_traits' => json_encode($traits),
                'pet_serial' => $serials[$row->shop_item_id],
            ]);

            if ($row->equipped_at) {
                DB::table('users')->where('id', $row->user_id)->update([
                    'pet_traits' => json_encode($traits + ['serial' => $serials[$row->shop_item_id]]),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pet_traits');
        });

        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn(['pet_traits', 'pet_serial']);
        });
    }
};
