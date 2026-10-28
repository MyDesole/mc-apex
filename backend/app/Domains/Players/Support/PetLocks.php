<?php

namespace App\Domains\Players\Support;

use Illuminate\Support\Facades\DB;

/**
 * Чем сейчас занят питомец: на торгах, в экспедиции или в бою.
 * Занятого нельзя продать, подарить, выставить или отправить ещё куда-то.
 */
class PetLocks
{
    /** Причина занятости или null, если питомец свободен. */
    public static function reason(int $inventoryId): ?string
    {
        if (DB::table('pet_auctions')->where('inventory_id', $inventoryId)->where('status', 'active')->exists()) {
            return 'Питомец выставлен на аукцион — сначала дождись конца торгов.';
        }

        if (DB::table('pet_expeditions')->where('inventory_id', $inventoryId)->whereNull('claimed_at')->exists()) {
            return 'Питомец в экспедиции — дождись возвращения и забери добычу.';
        }

        if (DB::table('pet_battles')->where('inventory_id', $inventoryId)->where('status', 'active')->exists()) {
            return 'Питомец сейчас на арене — сначала закончи бой.';
        }

        return null;
    }
}
