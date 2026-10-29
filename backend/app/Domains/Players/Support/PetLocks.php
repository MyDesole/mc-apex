<?php

namespace App\Domains\Players\Support;

use Illuminate\Support\Facades\DB;

/**
 * Чем сейчас занят питомец: на торгах, в экспедиции или в бою.
 * Занятого нельзя продать, подарить, выставить или отправить ещё куда-то.
 */
class PetLocks
{
    public const AUCTION = 'Питомец выставлен на аукцион — сначала дождись конца торгов.';
    public const EXPEDITION = 'Питомец в экспедиции — дождись возвращения и забери добычу.';
    public const BATTLE = 'Питомец сейчас на арене — сначала закончи бой.';

    /** Причина занятости или null, если питомец свободен. */
    public static function reason(int $inventoryId): ?string
    {
        return self::reasons([$inventoryId])[$inventoryId] ?? null;
    }

    /**
     * Причины для списка питомцев — три запроса на весь список.
     *
     * @param  array<int>  $inventoryIds
     * @return array<int, string>  inventory_id => причина (свободных нет в ответе)
     */
    public static function reasons(array $inventoryIds): array
    {
        if (! $inventoryIds) {
            return [];
        }

        // Порядок важен: аукцион перекрывает экспедицию, экспедиция — бой
        $busy = DB::table('pet_battles')->whereIn('inventory_id', $inventoryIds)->where('status', 'active')
            ->pluck('inventory_id')->mapWithKeys(fn ($id) => [(int) $id => self::BATTLE])->all();

        foreach (DB::table('pet_expeditions')->whereIn('inventory_id', $inventoryIds)->whereNull('claimed_at')->pluck('inventory_id') as $id) {
            $busy[(int) $id] = self::EXPEDITION;
        }

        foreach (DB::table('pet_auctions')->whereIn('inventory_id', $inventoryIds)->where('status', 'active')->pluck('inventory_id') as $id) {
            $busy[(int) $id] = self::AUCTION;
        }

        return $busy;
    }
}
