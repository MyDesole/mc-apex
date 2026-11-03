<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\Dungeons;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Кузница: четыре рецепта из материалов и снаряжения подземелий.
 *
 *  1. Обычный крафт — 3 материала ступени → случайный предмет этой ступени.
 *  2. Ковка на ядре — ядро босса + 3 материала ступени → предмет выбранного
 *     слота, редкость не ниже эпической.
 *  3. Усиление — два свободных предмета одного слота и редкости + монеты →
 *     предмет того же слота и ступени на редкость выше.
 *  4. Переплавка — свободный предмет → материалы его ступени.
 *
 * Всё идёт в одной транзакции со строками под замком: повторный запрос не
 * потратит материалы дважды.
 */
class DungeonCraftService
{
    public const COST = 3;
    public const CORE_COST = 1;
    public const CORE_MATERIALS = 3;
    public const UPGRADE_COINS_PER_STAGE = 1500;

    /** Сколько материалов даёт переплавка предмета по редкости. */
    public const DISMANTLE_YIELD = ['common' => 1, 'rare' => 2, 'epic' => 3, 'legendary' => 5];

    private const RARITIES = ['common', 'rare', 'epic', 'legendary'];

    public function __construct(private readonly PetGearService $gear)
    {
    }

    /** Сколько каких материалов у игрока. */
    public function materials(User $user): array
    {
        return DB::table('dungeon_materials')
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->material => (int) $row->quantity])
            ->all();
    }

    /** Обычный крафт: материалы ступени → случайный предмет. */
    public function craft(User $user, string $material): array
    {
        $def = Dungeons::MATERIALS[$material] ?? null;

        if (! $def || ! empty($def['core']) || ! empty($def['bound'])) {
            throw new RuntimeException('Такого материала нет.');
        }

        return DB::transaction(function () use ($user, $material, $def) {
            $this->spend($user, [$material => self::COST]);

            $pool = array_keys(array_filter(Dungeons::GEAR, fn (array $gear) => empty($gear['relic']) && $gear['stage'] === $def['stage']));
            $slug = $pool[array_rand($pool)];

            return $this->result($user, $slug, Dungeons::rollRarity($def['stage'], false));
        });
    }

    /** Ковка на ядре босса: выбранный слот и редкость не ниже эпической. */
    public function forge(User $user, string $core, string $slot): array
    {
        $def = Dungeons::MATERIALS[$core] ?? null;

        if (! $def || empty($def['core'])) {
            throw new RuntimeException('Это не ядро босса.');
        }

        if (! in_array($slot, Dungeons::SLOTS, true)) {
            throw new RuntimeException('Такого слота нет.');
        }

        $stageMaterial = Dungeons::materialForStage((int) $def['stage']);

        return DB::transaction(function () use ($user, $core, $slot, $def, $stageMaterial) {
            $this->spend($user, [$core => self::CORE_COST, $stageMaterial => self::CORE_MATERIALS]);

            $pool = array_keys(array_filter(Dungeons::GEAR, fn (array $gear) => empty($gear['relic']) && $gear['stage'] === $def['stage'] && $gear['slot'] === $slot));
            $slug = $pool[array_rand($pool)];

            $rarity = Dungeons::rollRarity($def['stage'], true);
            if (array_search($rarity, self::RARITIES, true) < array_search('epic', self::RARITIES, true)) {
                $rarity = 'epic';
            }

            return $this->result($user, $slug, $rarity);
        });
    }

    /** Усиление: два одинаковых предмета и монеты → предмет на редкость выше. */
    public function upgrade(User $user, int $firstId, int $secondId): array
    {
        if ($firstId === $secondId) {
            throw new RuntimeException('Нужны два разных предмета.');
        }

        return DB::transaction(function () use ($user, $firstId, $secondId) {
            // Строки под замок: тот же предмет не уйдёт в два усиления
            UserInventory::whereIn('id', [$firstId, $secondId])->lockForUpdate()->get();

            $a = $this->freeItem($user, $firstId);
            $b = $this->freeItem($user, $secondId);

            if ($a['slot'] !== $b['slot'] || $a['rarity'] !== $b['rarity']) {
                throw new RuntimeException('Предметы должны быть одного слота и одной редкости.');
            }

            $next = self::RARITIES[array_search($a['rarity'], self::RARITIES, true) + 1] ?? null;
            if (! $next) {
                throw new RuntimeException('Легендарное усилить нельзя.');
            }

            $stage = (int) (Dungeons::GEAR[$a['slug']]['stage'] ?? 1);
            $price = self::UPGRADE_COINS_PER_STAGE * $stage;

            CoinService::debit($user, $price, CoinTransaction::SOURCE_PURCHASE, 'Кузница: усиление снаряжения');

            UserInventory::whereIn('id', [$firstId, $secondId])->delete();

            $pool = array_keys(array_filter(Dungeons::GEAR, fn (array $gear) => empty($gear['relic']) && $gear['stage'] === $stage && $gear['slot'] === $a['slot']));
            $slug = $pool[array_rand($pool)];

            return $this->result($user, $slug, $next);
        });
    }

    /** Переплавка: свободный предмет → материалы его ступени. */
    public function dismantle(User $user, int $itemId): array
    {
        return DB::transaction(function () use ($user, $itemId) {
            UserInventory::whereKey($itemId)->lockForUpdate()->get();

            $item = $this->freeItem($user, $itemId);
            $stage = (int) (Dungeons::GEAR[$item['slug']]['stage'] ?? 1);
            $material = Dungeons::materialForStage($stage);
            $count = self::DISMANTLE_YIELD[$item['rarity']] ?? 1;

            UserInventory::whereKey($itemId)->delete();

            DB::table('dungeon_materials')->upsert(
                ['user_id' => $user->id, 'material' => $material, 'quantity' => $count, 'created_at' => now(), 'updated_at' => now()],
                ['user_id', 'material'],
                ['quantity' => DB::raw('quantity + ' . $count)],
            );

            return [
                'dismantled' => $item['name'],
                'material' => $material,
                'count' => $count,
                'materials' => $this->materials($user),
            ];
        });
    }

    /** Описание рецептов для интерфейса кузницы. */
    public function recipes(): array
    {
        return [
            'craft_cost' => self::COST,
            'core_cost' => self::CORE_COST,
            'core_materials' => self::CORE_MATERIALS,
            'upgrade_coins_per_stage' => self::UPGRADE_COINS_PER_STAGE,
            'dismantle_yield' => self::DISMANTLE_YIELD,
        ];
    }

    /** Списать материалы под замком; не хватает — исключение без списаний. */
    private function spend(User $user, array $need): void
    {
        $rows = DB::table('dungeon_materials')->where('user_id', $user->id)->whereIn('material', array_keys($need))->lockForUpdate()->get()->keyBy('material');

        foreach ($need as $material => $count) {
            if ((int) ($rows[$material]->quantity ?? 0) < $count) {
                $name = Dungeons::MATERIALS[$material]['name'] ?? $material;

                throw new RuntimeException("Нужно {$count} × «{$name}».");
            }
        }

        foreach ($need as $material => $count) {
            $left = (int) $rows[$material]->quantity - $count;

            if ($left > 0) {
                DB::table('dungeon_materials')->where('id', $rows[$material]->id)->update(['quantity' => $left, 'updated_at' => now()]);
            } else {
                DB::table('dungeon_materials')->where('id', $rows[$material]->id)->delete();
            }
        }
    }

    /** Свой предмет снаряжения, не надетый ни на кого. */
    private function freeItem(User $user, int $id): array
    {
        $item = collect($this->gear->bag($user))->firstWhere('inventory_id', $id);

        if (! $item) {
            throw new RuntimeException('Предмет не найден.');
        }

        if ($item['worn_by']) {
            throw new RuntimeException('Сначала сними предмет с питомца.');
        }

        return $item;
    }

    private function result(User $user, string $slug, string $rarity): array
    {
        $id = $this->gear->grant($user, $slug, $rarity);
        $gear = Dungeons::GEAR[$slug];
        $stats = Dungeons::scaledStats($gear, $rarity);

        return [
            'crafted' => [
                'inventory_id' => $id,
                'slug' => $slug,
                'name' => $gear['name'],
                'sprite' => $gear['sprite'],
                'slot' => $gear['slot'],
                'slot_name' => Dungeons::slotName((string) $gear['slot']),
                'rarity' => $rarity,
                'rarity_name' => Dungeons::rarityName($rarity),
                ...$stats,
            ],
            'materials' => $this->materials($user),
        ];
    }
}
