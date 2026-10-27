<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Уровень у каждого питомца свой: действия (погладить, покормить,
 * поиграть) привязаны к экземпляру (строке инвентаря). 0 — питомец без
 * записи в инвентаре (у админа). Раз в день — на каждого питомца.
 *
 * Старые действия достаются первому надетому питомцу хозяина.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_interactions', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_id')->default(0);
        });

        // Старый опыт — первому надетому питомцу
        foreach (DB::table('users')->whereNotNull('profile_pets')->get(['id', 'profile_pets']) as $user) {
            $first = json_decode((string) $user->profile_pets, true)[0]['inventory_id'] ?? null;

            if ($first) {
                DB::table('pet_interactions')->where('owner_id', $user->id)->update(['inventory_id' => $first]);
            }
        }

        Schema::table('pet_interactions', function (Blueprint $table) {
            // owner_id остаётся в индексе (owner_id, created_at) — внешнему ключу хватит
            $table->dropUnique(['owner_id', 'actor_id', 'action', 'day']);
            $table->unique(['owner_id', 'inventory_id', 'actor_id', 'action', 'day'], 'pet_interactions_daily');
        });
    }

    public function down(): void
    {
        Schema::table('pet_interactions', function (Blueprint $table) {
            $table->dropUnique('pet_interactions_daily');
            $table->dropColumn('inventory_id');
        });

        Schema::table('pet_interactions', function (Blueprint $table) {
            $table->unique(['owner_id', 'actor_id', 'action', 'day']);
        });
    }
};
