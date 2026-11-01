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

    public const STORAGE = 'Питомец в Кладовке на спаривании — дождись возвращения.';
    public const DUNGEON = 'Питомец в подземелье — сначала закончи поход.';

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

        $inventoryIds = array_map('intval', $inventoryIds);

        // Порядок важен: аукцион перекрывает экспедицию, экспедиция — бой
        $busy = DB::table('pet_battles')->whereIn('inventory_id', $inventoryIds)->where('status', 'active')
            ->pluck('inventory_id')->mapWithKeys(fn ($id) => [(int) $id => self::BATTLE])->all();

        foreach (DB::table('pet_expeditions')->whereIn('inventory_id', $inventoryIds)->whereNull('claimed_at')->pluck('inventory_id') as $id) {
            $busy[(int) $id] = self::EXPEDITION;
        }

        // Кладовка: родители в паре, пока не вернулись с потомком
        $pairs = DB::table('pet_breedings')->whereNull('claimed_at')->whereNotNull('ends_at')
            ->where(fn ($q) => $q->whereIn('parent1_id', $inventoryIds)->orWhereIn('parent2_id', $inventoryIds))
            ->get(['parent1_id', 'parent2_id']);
        foreach ($pairs as $pair) {
            foreach ([(int) $pair->parent1_id, (int) $pair->parent2_id] as $id) {
                if (in_array($id, $inventoryIds, true)) {
                    $busy[$id] = self::STORAGE;
                }
            }
        }

        // Подземелье: отряд в активном походе
        $owners = DB::table('user_inventory')->whereIn('id', $inventoryIds)->select('user_id');
        foreach (DB::table('dungeon_runs')->where('status', 'active')->whereIn('user_id', $owners)->pluck('squad') as $squad) {
            foreach ((array) json_decode((string) $squad, true) as $id) {
                if (in_array((int) $id, $inventoryIds, true)) {
                    $busy[(int) $id] = self::DUNGEON;
                }
            }
        }

        foreach (DB::table('pet_auctions')->whereIn('inventory_id', $inventoryIds)->where('status', 'active')->pluck('inventory_id') as $id) {
            $busy[(int) $id] = self::AUCTION;
        }

        return $busy;
    }
}
