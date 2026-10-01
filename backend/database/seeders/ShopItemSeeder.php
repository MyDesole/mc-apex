<?php

namespace Database\Seeders;

use App\Models\ShopItem;
use Illuminate\Database\Seeder;

/**
 * Каталог магазина по умолчанию: косметика, которая уже поддерживается
 * профилем (id рамок/эффектов совпадают с frontend/src/data/profileCustomization.js),
 * плюс приоритет тир-теста и наборы монет.
 *
 * Сидер идемпотентный: повторный запуск обновляет цены, но не плодит дубли.
 */
class ShopItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // === Рамки аватара ===
            ['slug' => 'frame-purple', 'name' => 'Фиолетовая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'purple', 'rarity' => 'common', 'price' => 250, 'sort_order' => 10, 'metadata' => ['icon' => 'frame', 'color' => '#7c3aed']],
            ['slug' => 'frame-cyan', 'name' => 'Голубая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'cyan', 'rarity' => 'common', 'price' => 250, 'sort_order' => 20, 'metadata' => ['icon' => 'frame', 'color' => '#06b6d4']],
            ['slug' => 'frame-green', 'name' => 'Зелёная рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'green', 'rarity' => 'common', 'price' => 250, 'sort_order' => 30, 'metadata' => ['icon' => 'frame', 'color' => '#22c55e']],
            ['slug' => 'frame-gold', 'name' => 'Золотая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'gold', 'rarity' => 'rare', 'price' => 900, 'sort_order' => 40, 'metadata' => ['icon' => 'frame', 'color' => '#facc15']],
            ['slug' => 'frame-orange', 'name' => 'Оранжевая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'orange', 'rarity' => 'rare', 'price' => 900, 'sort_order' => 50, 'metadata' => ['icon' => 'frame', 'color' => '#f97316']],
            ['slug' => 'frame-pink', 'name' => 'Розовая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'pink', 'rarity' => 'rare', 'price' => 900, 'sort_order' => 60, 'metadata' => ['icon' => 'frame', 'color' => '#ec4899']],
            ['slug' => 'frame-red', 'name' => 'Красная рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'red', 'rarity' => 'rare', 'price' => 900, 'sort_order' => 70, 'metadata' => ['icon' => 'frame', 'color' => '#ef4444']],
            ['slug' => 'frame-rainbow', 'name' => 'Радужная рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'rainbow', 'rarity' => 'epic', 'price' => 3000, 'sort_order' => 80, 'metadata' => ['icon' => 'frame', 'color' => '#a855f7']],
            ['slug' => 'frame-season1', 'name' => 'Рамка «Сезон 1»', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'season1', 'rarity' => 'epic', 'price' => 3500, 'sort_order' => 90, 'metadata' => ['icon' => 'snow', 'color' => '#06b6d4']],
            ['slug' => 'frame-legendary', 'name' => 'Легендарная рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'legendary', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 100, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],

            // === Эффекты профиля ===
            ['slug' => 'effect-glow', 'name' => 'Свечение', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'glow', 'rarity' => 'common', 'price' => 400, 'sort_order' => 110, 'metadata' => ['icon' => 'sparkles', 'color' => '#7c3aed']],
            ['slug' => 'effect-pulse', 'name' => 'Пульсация', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'pulse', 'rarity' => 'rare', 'price' => 1200, 'sort_order' => 120, 'metadata' => ['icon' => 'sparkles', 'color' => '#06b6d4']],
            ['slug' => 'effect-gradient', 'name' => 'Градиент', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'gradient', 'rarity' => 'rare', 'price' => 1400, 'sort_order' => 130, 'metadata' => ['icon' => 'palette', 'color' => '#8b5cf6']],
            ['slug' => 'effect-fire', 'name' => 'Огонь', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'fire', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 140, 'metadata' => ['icon' => 'flame', 'color' => '#ef4444']],
            ['slug' => 'effect-ice', 'name' => 'Лёд', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'ice', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 150, 'metadata' => ['icon' => 'snow', 'color' => '#06b6d4']],
            ['slug' => 'effect-legendary', 'name' => 'Легендарный эффект', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'legendary', 'rarity' => 'legendary', 'price' => 12000, 'sort_order' => 160, 'metadata' => ['icon' => 'bolt', 'color' => '#facc15']],

            // === Акцентные цвета ===
            ['slug' => 'accent-teal', 'name' => 'Бирюзовый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#14b8a6', 'rarity' => 'common', 'price' => 200, 'sort_order' => 170, 'metadata' => ['icon' => 'target', 'color' => '#14b8a6']],
            ['slug' => 'accent-pink', 'name' => 'Розовый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#ec4899', 'rarity' => 'common', 'price' => 200, 'sort_order' => 180, 'metadata' => ['icon' => 'target', 'color' => '#ec4899']],
            ['slug' => 'accent-gold', 'name' => 'Золотой акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#eab308', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 190, 'metadata' => ['icon' => 'target', 'color' => '#eab308']],
            ['slug' => 'accent-crimson', 'name' => 'Багровый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#f43f5e', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 200, 'metadata' => ['icon' => 'target', 'color' => '#f43f5e']],

            // === Бейджи (надеваются в профиль, видны другим) ===
            ['slug' => 'badge-supporter', 'name' => 'Бейдж «Саппортер»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'supporter', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 210, 'metadata' => ['icon' => 'heart', 'color' => '#a855f7']],
            ['slug' => 'badge-veteran', 'name' => 'Бейдж «Ветеран»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'veteran', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 220, 'metadata' => ['icon' => 'medal', 'color' => '#f97316']],
            ['slug' => 'badge-apex', 'name' => 'Бейдж «APEX»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'apex', 'rarity' => 'legendary', 'price' => 15000, 'sort_order' => 230, 'metadata' => ['icon' => 'trophy', 'color' => '#facc15']],

            // === Главное: приоритет тир-теста ===
            [
                'slug' => 'tier-priority-pass',
                'name' => 'Приоритет тир-теста',
                'description' => 'Ваша заявка встаёт первой в очереди тестеров. Действует на одну заявку и сгорает после её завершения.',
                'type' => ShopItem::TYPE_TIER_PRIORITY,
                'effect_value' => 'priority',
                'rarity' => 'rare',
                'price' => 800,
                'is_consumable' => true,
                'is_repeatable' => true,
                'max_quantity' => 10,
                'sort_order' => 1,
                'metadata' => ['icon' => 'rocket', 'color' => '#f97316', 'boost_weight' => 100],
            ],
            [
                'slug' => 'tier-priority-pass-x5',
                'name' => 'Приоритет ×5',
                'description' => 'Пять приоритетных заявок на тир-тест со скидкой.',
                'type' => ShopItem::TYPE_TIER_PRIORITY,
                'effect_value' => 'priority',
                'rarity' => 'epic',
                'price' => 3500,
                'is_consumable' => true,
                'is_repeatable' => true,
                'max_quantity' => 10,
                'sort_order' => 2,
                'metadata' => ['icon' => 'rocket', 'color' => '#f97316', 'boost_weight' => 100, 'charges' => 5],
            ],

            // === Наборы монет (для админа: выдача за ивенты/промокоды) ===
            [
                'slug' => 'coin-pack-small',
                'name' => 'Набор 500 ApexCoin',
                'type' => ShopItem::TYPE_COIN_BUNDLE,
                'effect_value' => 'coins',
                'rarity' => 'common',
                'price' => 0,
                'is_active' => false,
                'sort_order' => 900,
                'metadata' => ['icon' => 'coin', 'color' => '#facc15', 'amount' => 500],
            ],
        ];

        foreach ($items as $item) {
            ShopItem::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge([
                    'is_active' => true,
                    'is_consumable' => false,
                    'is_repeatable' => false,
                ], $item)
            );
        }
    }
}
