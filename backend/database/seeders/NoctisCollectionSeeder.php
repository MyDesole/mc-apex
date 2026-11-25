<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NoctisCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $parts = [
            ['frame', 'Рамка «Лунная корона»', 'avatar_frame', 55000, 'legendary', 'Гравированное серебро, аметисты и медленно вращающийся лунный блик.'],
            ['scene', 'Фон «Лунная обсерватория»', 'skin_backdrop', 40000, 'epic', 'Детализированная сцена за 3D-скином: затмение, обсидиан и плавное движение лунного света.'],
            ['layout', 'Профиль «Затмение»', 'profile_layout', 60000, 'legendary', 'Обсерватория на фоне профиля, тёмное стекло панелей и серебряные акценты.'],
            ['name', 'Ник «Лунное серебро»', 'name_effect', 25000, 'epic', 'Плавный серебряно-аметистовый перелив ника.'],
            ['bubble', 'Сообщения «Лунная печать»', 'chat_bubble', 35000, 'epic', 'Тёмный перламутр, серебряная кромка и мягкое свечение полумесяца.'],
        ];
        DB::transaction(function () use ($parts) {
            foreach ($parts as $i => [$slug, $name, $type, $price, $rarity, $description]) {
                ShopItem::firstOrCreate(['slug' => 'noctis-'.$slug], [
                    'name' => $name, 'description' => $description, 'type' => $type,
                    'effect_value' => 'noctis', 'rarity' => $rarity, 'price' => $price,
                    'is_active' => true, 'is_consumable' => false, 'is_repeatable' => false,
                    'sort_order' => $i, 'metadata' => ['collection' => 'noctis', 'icon' => 'sparkles', 'color' => '#c3acd9'],
                ]);
            }
            ShopItem::firstOrCreate(['slug' => 'bundle-noctis'], [
                'name' => 'Набор «Затмение»', 'description' => 'Пять предметов NOCTIS: детализированное серебро, аметист и лунная обсерватория. Без пиксель-арта. Плавные анимации, только оформление.',
                'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'noctis', 'rarity' => 'legendary', 'price' => 149900,
                'is_active' => true, 'is_consumable' => false, 'is_repeatable' => false, 'sort_order' => 0,
                'metadata' => ['theme' => 'noctis', 'mood' => 'Коллекция', 'icon' => 'sparkles', 'color' => '#c3acd9', 'items' => array_map(fn ($p) => 'noctis-'.$p[0], $parts)],
            ]);
        });
    }
}
