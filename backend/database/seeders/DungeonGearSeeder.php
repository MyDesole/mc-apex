<?php

namespace Database\Seeders;

use App\Domains\Players\Support\Dungeons;
use App\Domains\Shop\Models\ShopItem;
use Illuminate\Database\Seeder;

/**
 * Экипировка подземелий как предметы магазина.
 *
 * Предметы выключены (is_active = false): в витрине их не видно, купить
 * нельзя — только найти в подземелье. Так они остаются обычными строками
 * инвентаря, и с ними работают продажа, подарок и аукцион.
 *
 * Характеристики лежат в metadata: слот, атака, защита, здоровье,
 * скорость, ступень и sprite для карточки. Сидер идемпотентен —
 * повторный запуск обновляет характеристики, если их поправили
 * в Dungeons::GEAR.
 */
class DungeonGearSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Dungeons::GEAR as $slug => $gear) {
            ShopItem::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $gear['name'],
                    'description' => 'Экипировка для питомца. Найдена в подземелье.',
                    'type' => ShopItem::TYPE_PET_GEAR,
                    'rarity' => $gear['rarity'],
                    'price' => 0,
                    'is_active' => false,
                    'is_consumable' => false,
                    'is_repeatable' => true,
                    'sort_order' => 900 + $gear['stage'],
                    'metadata' => [
                        'slot' => $gear['slot'],
                        'sprite' => $gear['sprite'] ?? null,
                        'atk' => $gear['atk'],
                        'def' => $gear['def'],
                        'hp' => $gear['hp'],
                        'spd' => $gear['spd'],
                        'stage' => $gear['stage'],
                    ],
                ],
            );
        }

        $this->command?->info('Экипировка подземелий: ' . count(Dungeons::GEAR) . ' предметов.');
    }
}
