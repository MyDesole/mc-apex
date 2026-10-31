<?php

namespace App\Domains\Players\Services;

use App\Domains\Players\Support\Dungeons;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Крафт экипировки: три одинаковых материала — случайный предмет того же
 * тира (слот и редкость случайны).
 */
class DungeonCraftService
{
    public const COST = 3;

    /** Сколько каких материалов у игрока. */
    public function materials(User $user): array
    {
        return DB::table('dungeon_materials')
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->material => (int) $row->quantity])
            ->all();
    }

    /** Крафт: списать материалы и выдать предмет. */
    public function craft(User $user, string $material): array
    {
        $def = Dungeons::MATERIALS[$material] ?? null;

        if (! $def) {
            throw new RuntimeException('Такого материала нет.');
        }

        $row = DB::table('dungeon_materials')
            ->where('user_id', $user->id)
            ->where('material', $material)
            ->first();

        if (! $row || (int) $row->quantity < self::COST) {
            throw new RuntimeException('Нужно ' . self::COST . ' «' . $def['name'] . '».');
        }

        // Предмет строго своего тира: не выпадет предмет ниже ступени
        $pool = array_keys(array_filter(Dungeons::GEAR, fn (array $gear) => $gear['stage'] === $def['stage']));
        $slug = $pool[array_rand($pool)];
        $rarity = Dungeons::rollRarity($def['stage'], false);

        $id = DB::transaction(function () use ($user, $material, $row, $slug, $rarity) {
            $left = (int) $row->quantity - self::COST;

            if ($left > 0) {
                DB::table('dungeon_materials')->where('id', $row->id)->update(['quantity' => $left, 'updated_at' => now()]);
            } else {
                DB::table('dungeon_materials')->where('id', $row->id)->delete();
            }

            return app(PetGearService::class)->grant($user, $slug, $rarity);
        });

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
