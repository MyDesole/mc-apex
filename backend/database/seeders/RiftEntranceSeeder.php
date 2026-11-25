<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use Illuminate\Database\Seeder;

class RiftEntranceSeeder extends Seeder
{
    public function run(): void
    {
        ShopItem::firstOrCreate(['slug' => 'rift-entrance'], [
            'name' => 'Появление «Разрыв пространства»',
            'description' => 'Световой разлом раскрывается в титановый портал, выпускает ударную волну и затухающие искры. Короткое кинематографичное появление профиля.',
            'type' => 'profile_entrance', 'effect_value' => 'rift', 'rarity' => 'legendary', 'price' => 120000,
            'is_active' => true, 'is_consumable' => false, 'is_repeatable' => false, 'sort_order' => 0,
            'metadata' => ['icon' => 'sparkles', 'color' => '#80e9f4'],
        ]);
    }
}
