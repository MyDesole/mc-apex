<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use Illuminate\Database\Seeder;

class SingularityFrameSeeder extends Seeder
{
    public function run(): void
    {
        ShopItem::firstOrCreate(['slug' => 'singularity-frame'], [
            'name' => 'Рамка «Сингулярность»',
            'description' => 'Титановый реактор: встречное вращение световых колец, орбитальные частицы и волна энергии. Детализированная графика без пиксель-арта.',
            'type' => 'avatar_frame', 'effect_value' => 'singularity', 'rarity' => 'legendary', 'price' => 85000,
            'is_active' => true, 'is_consumable' => false, 'is_repeatable' => false, 'sort_order' => 0,
            'metadata' => ['icon' => 'sparkles', 'color' => '#80e9f4'],
        ]);
    }
}
