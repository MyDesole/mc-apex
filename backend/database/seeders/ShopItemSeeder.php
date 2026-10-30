<?php

namespace Database\Seeders;

use App\Domains\Shop\Models\ShopItem;
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

            // === Эффекты профиля в стиле Minecraft (частицы и полотна) ===
            ['slug' => 'effect-rain', 'name' => 'Дождь', 'description' => 'Капли дождя падают по шапке профиля.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'rain', 'rarity' => 'common', 'price' => 500, 'sort_order' => 161, 'metadata' => ['icon' => 'snow', 'color' => '#60a5fa']],
            ['slug' => 'effect-snow', 'name' => 'Снегопад', 'description' => 'Пиксельные снежинки медленно кружат в профиле.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'snow', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 162, 'metadata' => ['icon' => 'snow', 'color' => '#e0f2fe']],
            ['slug' => 'effect-embers', 'name' => 'Искры Незера', 'description' => 'Раскалённые искры поднимаются снизу, как над лавой.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'embers', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 163, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'effect-redstone', 'name' => 'Редстоун-пыль', 'description' => 'Красная пыль мерцает, будто по профилю идёт сигнал.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'redstone', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 164, 'metadata' => ['icon' => 'bolt', 'color' => '#ef4444']],
            ['slug' => 'effect-ender', 'name' => 'Частицы Края', 'description' => 'Фиолетовые частицы эндермена парят вокруг.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'ender', 'rarity' => 'epic', 'price' => 3800, 'sort_order' => 165, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc']],
            ['slug' => 'effect-xp', 'name' => 'Опыт', 'description' => 'Сферы опыта поднимаются по профилю.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'xp', 'rarity' => 'epic', 'price' => 3800, 'sort_order' => 166, 'metadata' => ['icon' => 'star', 'color' => '#a3e635']],
            ['slug' => 'effect-enchant', 'name' => 'Зачарование', 'description' => 'Переливающийся блеск, как у зачарованного меча.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'enchant', 'rarity' => 'epic', 'price' => 4200, 'sort_order' => 167, 'metadata' => ['icon' => 'sword', 'color' => '#a78bfa']],
            ['slug' => 'effect-aurora', 'name' => 'Северное сияние', 'description' => 'Зелёно-бирюзовое сияние плывёт по шапке профиля.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'aurora', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 168, 'metadata' => ['icon' => 'leaf', 'color' => '#34d399']],
            ['slug' => 'effect-sculk', 'name' => 'Скалк', 'description' => 'Сигнал скалка расходится кольцами из глубины.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'sculk', 'rarity' => 'legendary', 'price' => 9500, 'sort_order' => 169, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf']],

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

            // === Подсветка клана (покупает лидер, действует на весь клан) ===
            [
                'slug' => 'clan-highlight-30',
                'name' => 'Подсветка клана на 30 дней',
                'description' => 'Ваш клан выделяется в списке кланов и в топе. Купить может только лидер. Повторная покупка продлевает срок.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT,
                'effect_value' => 'highlight',
                'rarity' => 'epic',
                'price' => 2500,
                'is_consumable' => false,
                // Продлевать можно: повторная покупка добавляет дни
                'is_repeatable' => true,
                'sort_order' => 300,
                'metadata' => ['icon' => 'star', 'color' => '#facc15', 'days' => 30],
            ],

            // === Цвета подсветки клана (нужна активная подсветка) ===
            [
                'slug' => 'clan-highlight-color-gold',
                'name' => 'Подсветка: золотая',
                'description' => 'Золотое оформление подсветки клана. Нужна активная подсветка.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'gold',
                'rarity' => 'common',
                'price' => 400,
                'sort_order' => 310,
                'metadata' => ['icon' => 'star', 'color' => '#facc15', 'kind' => 'color'],
            ],
            [
                'slug' => 'clan-highlight-color-crimson',
                'name' => 'Подсветка: багровая',
                'description' => 'Багровое оформление подсветки клана. Нужна активная подсветка.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'crimson',
                'rarity' => 'common',
                'price' => 400,
                'sort_order' => 311,
                'metadata' => ['icon' => 'star', 'color' => '#ef4444', 'kind' => 'color'],
            ],
            [
                'slug' => 'clan-highlight-color-cyan',
                'name' => 'Подсветка: голубая',
                'description' => 'Голубое оформление подсветки клана. Нужна активная подсветка.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'cyan',
                'rarity' => 'rare',
                'price' => 700,
                'sort_order' => 312,
                'metadata' => ['icon' => 'star', 'color' => '#06b6d4', 'kind' => 'color'],
            ],
            [
                'slug' => 'clan-highlight-color-violet',
                'name' => 'Подсветка: фиолетовая',
                'description' => 'Фиолетовое оформление подсветки клана. Нужна активная подсветка.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'violet',
                'rarity' => 'rare',
                'price' => 700,
                'sort_order' => 313,
                'metadata' => ['icon' => 'star', 'color' => '#8b5cf6', 'kind' => 'color'],
            ],
            [
                'slug' => 'clan-highlight-color-emerald',
                'name' => 'Подсветка: изумрудная',
                'description' => 'Изумрудное оформление подсветки клана. Нужна активная подсветка.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'emerald',
                'rarity' => 'rare',
                'price' => 700,
                'sort_order' => 314,
                'metadata' => ['icon' => 'star', 'color' => '#22c55e', 'kind' => 'color'],
            ],
            [
                'slug' => 'clan-highlight-color-rose',
                'name' => 'Подсветка: розовая',
                'description' => 'Розовое оформление подсветки клана. Нужна активная подсветка.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'rose',
                'rarity' => 'epic',
                'price' => 1100,
                'sort_order' => 315,
                'metadata' => ['icon' => 'star', 'color' => '#ec4899', 'kind' => 'color'],
            ],

            // === Эффекты подсветки клана (нужна активная подсветка) ===
            [
                'slug' => 'clan-highlight-effect-glow',
                'name' => 'Эффект: свечение',
                'description' => 'Мягкое свечение вокруг карточки клана.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'glow',
                'rarity' => 'common',
                'price' => 600,
                'sort_order' => 320,
                'metadata' => ['icon' => 'sparkles', 'color' => '#8b5cf6', 'kind' => 'effect'],
            ],
            [
                'slug' => 'clan-highlight-effect-pulse',
                'name' => 'Эффект: пульсация',
                'description' => 'Подсветка клана плавно пульсирует.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'pulse',
                'rarity' => 'rare',
                'price' => 1200,
                'sort_order' => 321,
                'metadata' => ['icon' => 'sparkles', 'color' => '#06b6d4', 'kind' => 'effect'],
            ],
            [
                'slug' => 'clan-highlight-effect-gradient',
                'name' => 'Эффект: градиент',
                'description' => 'Переливающийся градиент по карточке клана.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'gradient',
                'rarity' => 'epic',
                'price' => 1800,
                'sort_order' => 322,
                'metadata' => ['icon' => 'palette', 'color' => '#a855f7', 'kind' => 'effect'],
            ],
            [
                'slug' => 'clan-highlight-effect-fire',
                'name' => 'Эффект: огонь',
                'description' => 'Огненное оформление карточки клана.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'fire',
                'rarity' => 'epic',
                'price' => 2200,
                'sort_order' => 323,
                'metadata' => ['icon' => 'flame', 'color' => '#ef4444', 'kind' => 'effect'],
            ],
            [
                'slug' => 'clan-highlight-effect-ice',
                'name' => 'Эффект: лёд',
                'description' => 'Ледяное оформление карточки клана.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'ice',
                'rarity' => 'epic',
                'price' => 2200,
                'sort_order' => 324,
                'metadata' => ['icon' => 'snow', 'color' => '#06b6d4', 'kind' => 'effect'],
            ],
            [
                'slug' => 'clan-highlight-effect-aurora',
                'name' => 'Эффект: северное сияние',
                'description' => 'Переливающееся северное сияние на карточке клана.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'aurora',
                'rarity' => 'legendary',
                'price' => 3200,
                'sort_order' => 325,
                'metadata' => ['icon' => 'bolt', 'color' => '#22c55e', 'kind' => 'effect'],
            ],
            [
                'slug' => 'clan-highlight-effect-legendary',
                'name' => 'Эффект: легендарный',
                'description' => 'Золотое сияние с блеском — самый заметный вариант подсветки.',
                'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE,
                'effect_value' => 'legendary',
                'rarity' => 'legendary',
                'price' => 4500,
                'sort_order' => 326,
                'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'kind' => 'effect'],
            ],

            // === Рамки в стиле блоков Minecraft ===
            ['slug' => 'frame-emerald', 'name' => 'Изумрудная рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'emerald', 'rarity' => 'rare', 'price' => 1200, 'sort_order' => 101, 'metadata' => ['icon' => 'gem', 'color' => '#10b981']],
            ['slug' => 'frame-diamond', 'name' => 'Алмазная рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'diamond', 'rarity' => 'epic', 'price' => 2800, 'sort_order' => 102, 'metadata' => ['icon' => 'gem', 'color' => '#5eead4']],
            ['slug' => 'frame-redstone', 'name' => 'Редстоун-рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'redstone', 'rarity' => 'rare', 'price' => 1200, 'sort_order' => 103, 'metadata' => ['icon' => 'bolt', 'color' => '#dc2626']],
            ['slug' => 'frame-obsidian', 'name' => 'Обсидиановая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'obsidian', 'rarity' => 'epic', 'price' => 3200, 'sort_order' => 104, 'metadata' => ['icon' => 'shield', 'color' => '#6d28d9']],
            ['slug' => 'frame-ender', 'name' => 'Рамка Края', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'ender', 'rarity' => 'epic', 'price' => 3600, 'sort_order' => 105, 'metadata' => ['icon' => 'sparkles', 'color' => '#14b8a6']],
            ['slug' => 'frame-netherite', 'name' => 'Незеритовая рамка', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'netherite', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 106, 'metadata' => ['icon' => 'crown', 'color' => '#a8a29e']],

            // === Акценты-руды ===
            ['slug' => 'accent-emerald', 'name' => 'Изумрудный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#10b981', 'rarity' => 'common', 'price' => 300, 'sort_order' => 201, 'metadata' => ['icon' => 'target', 'color' => '#10b981']],
            ['slug' => 'accent-diamond', 'name' => 'Алмазный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#22d3ee', 'rarity' => 'common', 'price' => 300, 'sort_order' => 202, 'metadata' => ['icon' => 'target', 'color' => '#22d3ee']],
            ['slug' => 'accent-amethyst', 'name' => 'Аметистовый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#c084fc', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 203, 'metadata' => ['icon' => 'target', 'color' => '#c084fc']],
            ['slug' => 'accent-copper', 'name' => 'Медный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#f59e0b', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 204, 'metadata' => ['icon' => 'target', 'color' => '#f59e0b']],

            // === Бейджи ===
            ['slug' => 'badge-duelist', 'name' => 'Бейдж «Дуэлянт»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'duelist', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 224, 'metadata' => ['icon' => 'sword', 'color' => '#ef4444']],
            ['slug' => 'badge-builder', 'name' => 'Бейдж «Строитель»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'builder', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 225, 'metadata' => ['icon' => 'box', 'color' => '#a06b3c']],
            ['slug' => 'badge-speedrun', 'name' => 'Бейдж «Спидраннер»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'speedrun', 'rarity' => 'epic', 'price' => 4200, 'sort_order' => 226, 'metadata' => ['icon' => 'bolt', 'color' => '#22d3ee']],
            ['slug' => 'badge-guardian', 'name' => 'Бейдж «Страж»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'guardian', 'rarity' => 'epic', 'price' => 4200, 'sort_order' => 227, 'metadata' => ['icon' => 'shield', 'color' => '#4ade80']],
            ['slug' => 'badge-dragon', 'name' => 'Бейдж «Убийца дракона»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'dragon', 'rarity' => 'legendary', 'price' => 12000, 'sort_order' => 228, 'metadata' => ['icon' => 'flame', 'color' => '#a855f7']],

            // === Фон 3D-скина в профиле ===
            ['slug' => 'backdrop-overworld', 'name' => 'Фон «Верхний мир»', 'description' => 'Трава, небо и облака за вашим 3D-скином в профиле.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'overworld', 'rarity' => 'common', 'price' => 500, 'sort_order' => 400, 'metadata' => ['icon' => 'leaf', 'color' => '#4ade80']],
            ['slug' => 'backdrop-ocean', 'name' => 'Фон «Океан»', 'description' => 'Глубина, призмарин и пузыри за 3D-скином.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'ocean', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 401, 'metadata' => ['icon' => 'snow', 'color' => '#22d3ee']],
            ['slug' => 'backdrop-nether', 'name' => 'Фон «Незер»', 'description' => 'Незерак и лава за 3D-скином.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'nether', 'rarity' => 'epic', 'price' => 3000, 'sort_order' => 402, 'metadata' => ['icon' => 'flame', 'color' => '#ef4444']],
            ['slug' => 'backdrop-end', 'name' => 'Фон «Край»', 'description' => 'Эндерняк и звёзды пустоты за 3D-скином.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'end', 'rarity' => 'epic', 'price' => 3500, 'sort_order' => 403, 'metadata' => ['icon' => 'star', 'color' => '#e9e5a8']],
            ['slug' => 'backdrop-deep-dark', 'name' => 'Фон «Глубокая тьма»', 'description' => 'Скалк, мерцающий в темноте, за 3D-скином.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'deep_dark', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 404, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf']],

            // === Тема карточки игрока ===
            ['slug' => 'card-theme-emerald', 'name' => 'Карточка «Изумруд»', 'description' => 'Изумрудное оформление карточки игрока для соцсетей.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'emerald', 'rarity' => 'rare', 'price' => 1200, 'sort_order' => 450, 'metadata' => ['icon' => 'frame', 'color' => '#10b981']],
            ['slug' => 'card-theme-nether', 'name' => 'Карточка «Незер»', 'description' => 'Огненная карточка игрока.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'nether', 'rarity' => 'epic', 'price' => 2500, 'sort_order' => 451, 'metadata' => ['icon' => 'frame', 'color' => '#ef4444']],
            ['slug' => 'card-theme-end', 'name' => 'Карточка «Край»', 'description' => 'Карточка в цветах Края.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'end', 'rarity' => 'epic', 'price' => 2500, 'sort_order' => 452, 'metadata' => ['icon' => 'frame', 'color' => '#c084fc']],
            ['slug' => 'card-theme-gold', 'name' => 'Карточка «Золото»', 'description' => 'Золотая карточка — для тех, кому есть чем гордиться.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'gold', 'rarity' => 'legendary', 'price' => 7000, 'sort_order' => 453, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],

            // === Макеты профиля: другая расстановка блоков, цвета, фон, шрифты ===
            ['slug' => 'layout-nether-forge', 'name' => 'Макет «Адская кузня»', 'description' => 'Профиль из незерака и лавы: колонка с ачивками слева, раскалённые рамки и огненные заголовки.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'nether_forge', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 470, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'layout-end-void', 'name' => 'Макет «Пустота Края»', 'description' => 'Звёздная пустота: профиль в одну колонку по центру, ачивки и друзья — витриной под шапкой.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'end_void', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 471, 'metadata' => ['icon' => 'star', 'color' => '#c084fc']],
            ['slug' => 'layout-terminal', 'name' => 'Макет «Терминал»', 'description' => 'Зелёный моноширинный шрифт, строки развёртки и острые углы — профиль как экран командного блока. Статистика идёт первой.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'terminal', 'rarity' => 'rare', 'price' => 4000, 'sort_order' => 472, 'metadata' => ['icon' => 'doc', 'color' => '#22c55e']],
            ['slug' => 'layout-book', 'name' => 'Макет «Книга и перо»', 'description' => 'Светлый пергамент, тёмные чернила и шрифт с засечками — профиль как страница из книги. Совсем не похож на остальные.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'book', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 473, 'metadata' => ['icon' => 'doc', 'color' => '#d6b98c']],
            ['slug' => 'layout-synthwave', 'name' => 'Макет «Синтвейв»', 'description' => 'Неоновый закат, сетка до горизонта и розово-голубое свечение. Аспекты поднимаются наверх.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'synthwave', 'rarity' => 'epic', 'price' => 7000, 'sort_order' => 474, 'metadata' => ['icon' => 'bolt', 'color' => '#ec4899']],
            ['slug' => 'layout-royal', 'name' => 'Макет «Тронный зал»', 'description' => 'Золото на тёмном пурпуре, двойные рамки и торжественные заголовки. Шапка по центру.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'royal', 'rarity' => 'legendary', 'price' => 12000, 'sort_order' => 475, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],
            ['slug' => 'layout-ancient-city', 'name' => 'Макет «Древний город»', 'description' => 'Скалк и глубинный сланец, бирюзовое мерцание. Боковая колонка слева, широкая лента профиля.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'ancient_city', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 476, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf']],
            ['slug' => 'layout-frost', 'name' => 'Макет «Ледяной пик»', 'description' => 'Плотный лёд и иней: матовое стекло, круглые углы и холодный свет. Ачивки и друзья — над профилем.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'frost', 'rarity' => 'rare', 'price' => 4500, 'sort_order' => 477, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc']],

            // === Милая косметика: рамки с украшениями, эффекты, пастельные макеты ===
            ['slug' => 'frame-daisy', 'name' => 'Рамка «Ромашки»', 'description' => 'Белые ромашки с жёлтой серединкой по кругу аватара.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'daisy', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 107, 'metadata' => ['icon' => 'leaf', 'color' => '#fde68a']],
            ['slug' => 'frame-hearts', 'name' => 'Рамка «Сердечки»', 'description' => 'Розовые сердечки покачиваются вокруг аватара.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'hearts', 'rarity' => 'rare', 'price' => 1500, 'sort_order' => 108, 'metadata' => ['icon' => 'heart', 'color' => '#fb7185']],
            ['slug' => 'frame-sakura', 'name' => 'Рамка «Сакура»', 'description' => 'Нежно-розовая рамка с цветами сакуры.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'sakura', 'rarity' => 'epic', 'price' => 3200, 'sort_order' => 109, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'frame-marshmallow', 'name' => 'Рамка «Зефир»', 'description' => 'Пастельная радуга со звёздочками — самая сладкая рамка.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'marshmallow', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 110, 'metadata' => ['icon' => 'star', 'color' => '#c4b5fd']],
            ['slug' => 'effect-bubbles', 'name' => 'Мыльные пузыри', 'description' => 'Переливающиеся пузыри медленно всплывают по профилю.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'bubbles', 'rarity' => 'common', 'price' => 700, 'sort_order' => 170, 'metadata' => ['icon' => 'sparkles', 'color' => '#bae6fd']],
            ['slug' => 'effect-hearts', 'name' => 'Сердечки', 'description' => 'Розовые сердечки поднимаются по шапке профиля.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'hearts', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 171, 'metadata' => ['icon' => 'heart', 'color' => '#fb7185']],
            ['slug' => 'effect-sparkles', 'name' => 'Блёстки', 'description' => 'Золотые и розовые звёздочки мерцают по всему профилю.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'sparkles', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 172, 'metadata' => ['icon' => 'sparkles', 'color' => '#fde68a']],
            ['slug' => 'effect-sakura-petals', 'name' => 'Лепестки сакуры', 'description' => 'Лепестки кружатся и плавно падают по профилю.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'sakura_petals', 'rarity' => 'epic', 'price' => 4200, 'sort_order' => 173, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'layout-sakura', 'name' => 'Макет «Сакура»', 'description' => 'Светлый розовый профиль с лепестками, круглыми углами и цветочками у заголовков.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'sakura', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 478, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'layout-strawberry-milk', 'name' => 'Макет «Клубничное молоко»', 'description' => 'Пастельный горошек, кремовые карточки и сердечки. Ачивки и друзья — над профилем.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'strawberry_milk', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 479, 'metadata' => ['icon' => 'heart', 'color' => '#f472b6']],
            ['slug' => 'layout-cotton-candy', 'name' => 'Макет «Сахарная вата»', 'description' => 'Розово-голубые облака на сиреневом небе и ник с пастельным градиентом.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'cotton_candy', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 480, 'metadata' => ['icon' => 'sparkles', 'color' => '#f9a8d4']],

            /*
             * === Коллекция «Майнкрафт оживает» ===
             * Анимированные рамки-украшения (корона, ушки, рога, пчёлы на
             * орбите), эффекты с пиксельными картинками и бейджи-спрайты.
             * is_active = false — эксклюзив наборов: по отдельности не продаётся.
             */
            ['slug' => 'frame-hellfire', 'name' => 'Рамка «Адское пламя»', 'description' => 'Языки пламени Незера лижут аватар по кругу.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'hellfire', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 111, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'frame-nether-horns', 'name' => 'Рамка «Рога Незера»', 'description' => 'Обсидиановые рога с раскалёнными кончиками над аватаром.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'nether_horns', 'rarity' => 'epic', 'price' => 5500, 'sort_order' => 112, 'is_active' => false, 'metadata' => ['icon' => 'flame', 'color' => '#ef4444']],
            ['slug' => 'frame-warden', 'name' => 'Рамка «Варден»', 'description' => 'Сенсоры скалка пульсируют, а от аватара расходится звуковой удар.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'warden', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 113, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf']],
            ['slug' => 'frame-creeper', 'name' => 'Рамка «Криппер»', 'description' => 'Криппер выглядывает из-за аватара, а рамка шипит и мигает, как перед взрывом.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'creeper', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 114, 'metadata' => ['icon' => 'bolt', 'color' => '#4ade80', 'sprite' => 'creeper']],
            ['slug' => 'frame-beehive', 'name' => 'Рамка «Улей»', 'description' => 'Медовая рамка, вокруг которой кружат три пчёлки.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'beehive', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 115, 'metadata' => ['icon' => 'heart', 'color' => '#facc15', 'sprite' => 'bee']],

            // Неактивна: нет в магазине и сундуках, выдаётся только из админки
            ['slug' => 'pet-crow', 'name' => 'Питомец «Ворона»', 'description' => 'Редчайшая ворона: её нельзя выбить, только получить от администрации.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'crow', 'rarity' => 'legendary', 'price' => 0, 'sort_order' => 559, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#3f3f46', 'sprite' => 'crow']],

            // Шестая волна питомцев
            ['slug' => 'pet-owl', 'name' => 'Питомец «Совёнок»', 'description' => 'Совёнок летает над профилем по ночам и всё замечает.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'owl', 'rarity' => 'rare', 'price' => 4500, 'sort_order' => 570, 'metadata' => ['icon' => 'star', 'color' => '#a16207', 'sprite' => 'owl']],
            ['slug' => 'pet-frog', 'name' => 'Питомец «Лягушонок»', 'description' => 'Квакает, прыгает и уверяет, что он заколдованный принц.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'frog', 'rarity' => 'rare', 'price' => 3500, 'sort_order' => 571, 'metadata' => ['icon' => 'leaf', 'color' => '#4ade80', 'sprite' => 'frog']],
            ['slug' => 'pet-bunny', 'name' => 'Питомец «Зайчик»', 'description' => 'Белый зайчик с розовыми ушками скачет по шапке профиля.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'bunny', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 572, 'metadata' => ['icon' => 'heart', 'color' => '#f8fafc', 'sprite' => 'bunny']],
            ['slug' => 'pet-panda', 'name' => 'Питомец «Панда»', 'description' => 'Пухлая панда кувыркается и просит бамбук.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'panda', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 573, 'metadata' => ['icon' => 'heart', 'color' => '#e5e7eb', 'sprite' => 'panda']],
            ['slug' => 'pet-shroom', 'name' => 'Питомец «Грибочек»', 'description' => 'Мухоморчик с румяными щеками. Съедобный — наверное.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'shroom', 'rarity' => 'rare', 'price' => 4000, 'sort_order' => 574, 'metadata' => ['icon' => 'leaf', 'color' => '#dc2626', 'sprite' => 'shroom']],
            ['slug' => 'pet-octopus', 'name' => 'Питомец «Осьминожка»', 'description' => 'Восемь лап — восемь обнимашек. Иногда пускает чернила.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'octopus', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 575, 'metadata' => ['icon' => 'heart', 'color' => '#c084fc', 'sprite' => 'octopus']],
            ['slug' => 'pet-capybara', 'name' => 'Питомец «Капибара»', 'description' => 'Самый спокойный питомец на сервере. С апельсином на голове.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'capybara', 'rarity' => 'epic', 'price' => 7000, 'sort_order' => 576, 'metadata' => ['icon' => 'heart', 'color' => '#a16207', 'sprite' => 'capybara']],
            ['slug' => 'pet-jelly', 'name' => 'Питомец «Медуза»', 'description' => 'Светящаяся медуза парит над профилем.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'jelly', 'rarity' => 'epic', 'price' => 7000, 'sort_order' => 577, 'metadata' => ['icon' => 'sparkles', 'color' => '#67e8f9', 'sprite' => 'jelly']],
            ['slug' => 'pet-phoenix', 'name' => 'Питомец «Феникс»', 'description' => 'Огненная птица кружит над профилем и восстаёт из пепла.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'phoenix', 'rarity' => 'legendary', 'price' => 14000, 'sort_order' => 578, 'metadata' => ['icon' => 'flame', 'color' => '#f97316', 'sprite' => 'phoenix']],
            ['slug' => 'pet-unicorn', 'name' => 'Питомец «Единорог»', 'description' => 'Единорог с золотым рогом. Да, они существуют.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'unicorn', 'rarity' => 'legendary', 'price' => 15000, 'sort_order' => 579, 'metadata' => ['icon' => 'sparkles', 'color' => '#f0abfc', 'sprite' => 'unicorn']],
            ['slug' => 'frame-axolotl', 'name' => 'Рамка «Аксолотль»', 'description' => 'Розовые жабры аксолотля покачиваются по бокам аватара.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'axolotl', 'rarity' => 'epic', 'price' => 4200, 'sort_order' => 116, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'sprite' => 'axolotl']],
            ['slug' => 'frame-cat-ears', 'name' => 'Рамка «Котоушки»', 'description' => 'Кошачьи ушки на аватаре — иногда дёргаются.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'cat_ears', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 117, 'metadata' => ['icon' => 'heart', 'color' => '#fb923c', 'sprite' => 'cat']],
            ['slug' => 'frame-crown', 'name' => 'Рамка «Корона»', 'description' => 'Пиксельная золотая корона с камнями парит над аватаром и сверкает.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'crown', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 118, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'sprite' => 'crown']],
            ['slug' => 'frame-halo', 'name' => 'Рамка «Нимб»', 'description' => 'Светящийся нимб над аватаром и мягкое сияние вокруг.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'halo', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 119, 'metadata' => ['icon' => 'star', 'color' => '#fef08a']],
            ['slug' => 'frame-diamond-orbit', 'name' => 'Рамка «Алмазная орбита»', 'description' => 'Алмазы вращаются вокруг аватара.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'diamond_orbit', 'rarity' => 'legendary', 'price' => 8500, 'sort_order' => 120, 'metadata' => ['icon' => 'sparkles', 'color' => '#67e8f9', 'sprite' => 'diamond']],

            ['slug' => 'effect-inferno', 'name' => 'Инферно', 'description' => 'Пепел, угли и жар Незера: профиль будто стоит у лавового озера.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'inferno', 'rarity' => 'legendary', 'price' => 9500, 'sort_order' => 174, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'effect-creeper-boom', 'name' => 'Ссссс… БУМ', 'description' => 'Криппер подкрадывается, мигает — и профиль озаряет взрыв.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'creeper_boom', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 175, 'metadata' => ['icon' => 'bolt', 'color' => '#4ade80', 'sprite' => 'creeper']],
            ['slug' => 'effect-bees', 'name' => 'Пчёлки', 'description' => 'Пиксельные пчёлы перелетают через профиль.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'bees', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 176, 'metadata' => ['icon' => 'heart', 'color' => '#facc15', 'sprite' => 'bee']],
            ['slug' => 'effect-totem', 'name' => 'Тотем бессмертия', 'description' => 'Вспышка и золотисто-зелёный вихрь частиц, как при срабатывании тотема.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'totem', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 177, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'sprite' => 'totem']],
            ['slug' => 'effect-lightning', 'name' => 'Гроза', 'description' => 'Тёмное небо и удары молний прямо по шапке профиля.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'lightning', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 178, 'metadata' => ['icon' => 'bolt', 'color' => '#a5b4fc']],
            ['slug' => 'effect-diamond-rain', 'name' => 'Алмазный дождь', 'description' => 'С неба сыплются алмазы. Богато.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'diamond_rain', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 179, 'metadata' => ['icon' => 'sparkles', 'color' => '#67e8f9', 'sprite' => 'diamond']],
            ['slug' => 'effect-pixel-hearts', 'name' => 'Пиксельные сердца', 'description' => 'Сердечки здоровья из Minecraft поднимаются по профилю.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'pixel_hearts', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 180, 'metadata' => ['icon' => 'heart', 'color' => '#ef4444', 'sprite' => 'heart']],
            ['slug' => 'effect-cat-paws', 'name' => 'Кошачьи следы', 'description' => 'Невидимый котик проходит по профилю и оставляет следы лапок.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'cat_paws', 'rarity' => 'rare', 'price' => 2200, 'sort_order' => 181, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'sprite' => 'paw']],

            ['slug' => 'badge-soul-eater', 'name' => 'Бейдж «Пожиратель душ»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'soul_eater', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 229, 'is_active' => false, 'metadata' => ['icon' => 'flame', 'color' => '#ef4444', 'sprite' => 'skull', 'anim' => 'glow']],
            ['slug' => 'badge-warden', 'name' => 'Бейдж «Варден»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'warden', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 231, 'is_active' => false, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf', 'sprite' => 'warden', 'anim' => 'pulse']],
            ['slug' => 'badge-tnt', 'name' => 'Бейдж «TNT»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'tnt', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 232, 'metadata' => ['icon' => 'bolt', 'color' => '#ef4444', 'sprite' => 'tnt', 'anim' => 'shake']],
            ['slug' => 'badge-bee', 'name' => 'Бейдж «Пчёлка»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'bee', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 233, 'metadata' => ['icon' => 'heart', 'color' => '#facc15', 'sprite' => 'bee', 'anim' => 'bounce']],
            ['slug' => 'badge-axolotl', 'name' => 'Бейдж «Аксолотль»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'axolotl', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 234, 'is_active' => false, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'sprite' => 'axolotl', 'anim' => 'bounce']],
            ['slug' => 'badge-cat', 'name' => 'Бейдж «Котик»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'cat', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 235, 'is_active' => false, 'metadata' => ['icon' => 'heart', 'color' => '#fb923c', 'sprite' => 'cat', 'anim' => 'wiggle']],
            ['slug' => 'badge-totem', 'name' => 'Бейдж «Бессмертный»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'totem', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 236, 'is_active' => false, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'sprite' => 'totem', 'anim' => 'glow']],
            ['slug' => 'badge-creeper', 'name' => 'Бейдж «Криппер»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'creeper', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 237, 'metadata' => ['icon' => 'bolt', 'color' => '#4ade80', 'sprite' => 'creeper', 'anim' => 'wiggle']],
            ['slug' => 'badge-diamond', 'name' => 'Бейдж «Алмаз»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'diamond', 'rarity' => 'epic', 'price' => 3500, 'sort_order' => 238, 'metadata' => ['icon' => 'sparkles', 'color' => '#67e8f9', 'sprite' => 'diamond', 'anim' => 'spin']],

            // === Наборы: тематические коллекции со скидкой ===
            ['slug' => 'bundle-inferno', 'name' => 'Набор «Адское пекло»', 'description' => 'Пламя, рога и жар Незера. Для тех, кто приходит не поговорить.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'inferno', 'rarity' => 'legendary', 'price' => 19900, 'sort_order' => 1, 'metadata' => ['icon' => 'flame', 'color' => '#f97316', 'theme' => 'nether', 'mood' => 'Брутально', 'items' => ['frame-hellfire', 'frame-nether-horns', 'effect-inferno', 'badge-soul-eater', 'backdrop-nether', 'card-theme-nether']]],
            ['slug' => 'bundle-deep-dark', 'name' => 'Набор «Глубокая тьма»', 'description' => 'Варден слышит каждый шаг. Скалк, звуковой удар и Древний город.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'deep_dark', 'rarity' => 'legendary', 'price' => 24000, 'sort_order' => 2, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf', 'theme' => 'deep_dark', 'mood' => 'Брутально', 'items' => ['frame-warden', 'effect-sculk', 'badge-warden', 'backdrop-deep-dark', 'layout-ancient-city']]],
            ['slug' => 'bundle-royal', 'name' => 'Набор «Королевская сокровищница»', 'description' => 'Корона, тотем бессмертия, золотая карточка и тронный зал.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'royal', 'rarity' => 'legendary', 'price' => 32000, 'sort_order' => 3, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'theme' => 'royal', 'mood' => 'Роскошно', 'items' => ['frame-crown', 'effect-totem', 'badge-totem', 'card-theme-gold', 'layout-royal']]],
            ['slug' => 'bundle-creeper', 'name' => 'Набор «Криперская вечеринка»', 'description' => 'Ссссс… Криппер на аватаре, взрыв в профиле и TNT в бейджах.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'creeper', 'rarity' => 'epic', 'price' => 8500, 'sort_order' => 4, 'metadata' => ['icon' => 'bolt', 'color' => '#4ade80', 'theme' => 'creeper', 'mood' => 'Весело', 'items' => ['frame-creeper', 'effect-creeper-boom', 'badge-tnt', 'badge-creeper']]],
            ['slug' => 'bundle-bees', 'name' => 'Набор «Пасека»', 'description' => 'Жжж! Пчёлки кружат вокруг аватара и летают по профилю.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'bees', 'rarity' => 'epic', 'price' => 7900, 'sort_order' => 5, 'metadata' => ['icon' => 'heart', 'color' => '#facc15', 'theme' => 'bees', 'mood' => 'Мило', 'items' => ['frame-beehive', 'effect-bees', 'badge-bee', 'effect-sparkles']]],
            ['slug' => 'bundle-axolotl', 'name' => 'Набор «Аксолотль»', 'description' => 'Розовые жабры, пузыри и сахарная вата. Самый милый обитатель пещер.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'axolotl', 'rarity' => 'epic', 'price' => 10900, 'sort_order' => 6, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'theme' => 'axolotl', 'mood' => 'Мило', 'items' => ['frame-axolotl', 'effect-pixel-hearts', 'effect-bubbles', 'badge-axolotl', 'layout-cotton-candy']]],
            ['slug' => 'bundle-cat', 'name' => 'Набор «Котик»', 'description' => 'Ушки, следы лапок и котик в бейджах. Мяу.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'cat', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 7, 'metadata' => ['icon' => 'heart', 'color' => '#fb923c', 'theme' => 'cat', 'mood' => 'Мило', 'items' => ['frame-cat-ears', 'effect-cat-paws', 'badge-cat']]],

            /*
             * === Премиум-коллекция: голограммы, неон, космос, киберпанк ===
             * Живые рамки и эффекты на CSS, бейджи с анимированным оформлением
             * (metadata.style). is_active = false — эксклюзив наборов.
             */
            ['slug' => 'frame-holo', 'name' => 'Рамка «Голограмма»', 'description' => 'Радужное голографическое кольцо переливается, по нему бегут блики.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'holo', 'rarity' => 'legendary', 'price' => 9500, 'sort_order' => 121, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc']],
            ['slug' => 'frame-neon', 'name' => 'Рамка «Неон»', 'description' => 'Две неоновые трубки — розовая и бирюзовая — светятся и иногда моргают.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'neon', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 122, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9']],
            ['slug' => 'frame-black-hole', 'name' => 'Рамка «Чёрная дыра»', 'description' => 'Аккреционный диск раскалённой материи вращается вокруг аватара.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'black_hole', 'rarity' => 'legendary', 'price' => 12000, 'sort_order' => 123, 'metadata' => ['icon' => 'star', 'color' => '#f97316']],
            ['slug' => 'frame-glitch', 'name' => 'Рамка «Глитч»', 'description' => 'Контур рассыпается на красный и бирюзовый, по аватару бегут помехи.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'glitch', 'rarity' => 'epic', 'price' => 5500, 'sort_order' => 124, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8']],
            ['slug' => 'frame-hud', 'name' => 'Рамка «Кибер-прицел»', 'description' => 'Сегменты прицела вращаются навстречу друг другу, уголки сжимаются на цели.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'hud', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 125, 'is_active' => false, 'metadata' => ['icon' => 'target', 'color' => '#22d3ee']],
            ['slug' => 'frame-plasma', 'name' => 'Рамка «Плазма»', 'description' => 'Кольцо живой энергии бурлит и вращается.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'plasma', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 126, 'metadata' => ['icon' => 'sparkles', 'color' => '#a855f7']],
            ['slug' => 'frame-planets', 'name' => 'Рамка «Планетарий»', 'description' => 'Три планеты на разных орбитах кружат вокруг аватара.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'planets', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 127, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#3b82f6']],
            ['slug' => 'frame-chrome', 'name' => 'Рамка «Жидкий металл»', 'description' => 'Хромированное кольцо с бегущими бликами, как ртуть.', 'type' => ShopItem::TYPE_AVATAR_FRAME, 'effect_value' => 'chrome', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 128, 'metadata' => ['icon' => 'frame', 'color' => '#e2e8f0']],

            ['slug' => 'effect-matrix', 'name' => 'Матрица', 'description' => 'Зелёные колонки символов стекают по профилю. Добро пожаловать в реальный мир.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'matrix', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 182, 'metadata' => ['icon' => 'doc', 'color' => '#22c55e']],
            ['slug' => 'effect-warp', 'name' => 'Гиперпрыжок', 'description' => 'Звёзды вытягиваются в лучи — профиль уходит в варп.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'warp', 'rarity' => 'legendary', 'price' => 9500, 'sort_order' => 183, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#818cf8']],
            ['slug' => 'effect-neon-rain', 'name' => 'Неоновый дождь', 'description' => 'Розовые и бирюзовые струи дождя над светящимся полом ночного города.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'neon_rain', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 184, 'metadata' => ['icon' => 'bolt', 'color' => '#f472b6']],
            ['slug' => 'effect-galaxy', 'name' => 'Галактика', 'description' => 'Туманность медленно дрейфует, звёзды мерцают.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'galaxy', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 185, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa']],
            ['slug' => 'effect-glitch', 'name' => 'Сбой системы', 'description' => 'Полосы помех с RGB-сдвигом рвут шапку профиля.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'glitch', 'rarity' => 'epic', 'price' => 5500, 'sort_order' => 186, 'is_active' => false, 'metadata' => ['icon' => 'bolt', 'color' => '#ff2a6d']],
            ['slug' => 'effect-confetti', 'name' => 'Конфетти', 'description' => 'Праздник в профиле: цветная бумага кувыркается в воздухе.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'confetti', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 187, 'metadata' => ['icon' => 'gift', 'color' => '#f472b6']],
            ['slug' => 'effect-fireflies', 'name' => 'Светлячки', 'description' => 'Тёплые огоньки блуждают по профилю и мягко мигают.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'fireflies', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 188, 'metadata' => ['icon' => 'sparkles', 'color' => '#d9f99d']],
            ['slug' => 'effect-bokeh', 'name' => 'Боке', 'description' => 'Большие размытые огни плывут, как в ночном городе не в фокусе.', 'type' => ShopItem::TYPE_PROFILE_EFFECT, 'effect_value' => 'bokeh', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 189, 'metadata' => ['icon' => 'sparkles', 'color' => '#f9a8d4']],

            ['slug' => 'badge-holo', 'name' => 'Бейдж «Голограмма»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'holo', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 239, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc', 'style' => 'holo']],
            ['slug' => 'badge-neon', 'name' => 'Бейдж «Неон»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'neon', 'rarity' => 'epic', 'price' => 3500, 'sort_order' => 240, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9', 'style' => 'neon']],
            ['slug' => 'badge-hacker', 'name' => 'Бейдж «Хакер»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'hacker', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 241, 'is_active' => false, 'metadata' => ['icon' => 'doc', 'color' => '#05d9e8', 'style' => 'glitch']],
            ['slug' => 'badge-cosmonaut', 'name' => 'Бейдж «Космонавт»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'cosmonaut', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 242, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa', 'style' => 'galaxy']],
            ['slug' => 'badge-on-fire', 'name' => 'Бейдж «В огне»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'on_fire', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 243, 'metadata' => ['icon' => 'flame', 'color' => '#f97316', 'style' => 'fire']],
            ['slug' => 'badge-major', 'name' => 'Бейдж «Мажор»', 'type' => ShopItem::TYPE_BADGE, 'effect_value' => 'major', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 244, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'style' => 'gold']],

            ['slug' => 'bundle-cyberpunk', 'name' => 'Набор «Киберпанк»', 'description' => 'Глитч, прицел, сбой системы и Матрица. Ночной город уже ждёт.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'cyberpunk', 'rarity' => 'legendary', 'price' => 22000, 'sort_order' => 8, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8', 'theme' => 'cyberpunk', 'mood' => 'Дерзко', 'items' => ['frame-glitch', 'frame-hud', 'effect-matrix', 'effect-glitch', 'badge-hacker', 'layout-synthwave']]],
            ['slug' => 'bundle-cosmos', 'name' => 'Набор «Космос»', 'description' => 'Чёрная дыра, планеты на орбите, варп-прыжок и целая галактика в профиле.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'cosmos', 'rarity' => 'legendary', 'price' => 34000, 'sort_order' => 9, 'metadata' => ['icon' => 'star', 'color' => '#818cf8', 'theme' => 'cosmos', 'mood' => 'Эпично', 'items' => ['frame-black-hole', 'frame-planets', 'effect-galaxy', 'effect-warp', 'badge-cosmonaut', 'backdrop-end']]],
            ['slug' => 'bundle-hologram', 'name' => 'Набор «Голограмма»', 'description' => 'Переливы, хром и боке. Профиль как дорогая коллекционная карточка.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'hologram', 'rarity' => 'legendary', 'price' => 19000, 'sort_order' => 10, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc', 'theme' => 'holo', 'mood' => 'Стильно', 'items' => ['frame-holo', 'frame-chrome', 'badge-holo', 'effect-bokeh']]],
            ['slug' => 'bundle-neon-night', 'name' => 'Набор «Неоновая ночь»', 'description' => 'Неон, плазма и дождь, отражающийся в лужах.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'neon_night', 'rarity' => 'epic', 'price' => 16000, 'sort_order' => 11, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9', 'theme' => 'neon', 'mood' => 'Атмосферно', 'items' => ['frame-neon', 'frame-plasma', 'effect-neon-rain', 'badge-neon']]],
            ['slug' => 'bundle-party', 'name' => 'Набор «Вечеринка»', 'description' => 'Конфетти, светлячки и огонь. Повод не нужен.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'party', 'rarity' => 'epic', 'price' => 6900, 'sort_order' => 12, 'metadata' => ['icon' => 'gift', 'color' => '#f472b6', 'theme' => 'party', 'mood' => 'Весело', 'items' => ['effect-confetti', 'effect-fireflies', 'badge-on-fire', 'effect-bokeh']]],
            ['slug' => 'bundle-luxury', 'name' => 'Набор «Мажор»', 'description' => 'Золото, хром и тронный зал. Для тех, кто может себе позволить.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'luxury', 'rarity' => 'legendary', 'price' => 26000, 'sort_order' => 13, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'theme' => 'luxury', 'mood' => 'Роскошно', 'items' => ['badge-major', 'frame-chrome', 'card-theme-gold', 'layout-royal', 'effect-sparkles']]],

            // === Третья волна: темы карточки ===
            ['slug' => 'card-theme-neon', 'name' => 'Карточка «Неон»', 'description' => 'Фиолетовая ночь, розовая рамка и бирюзовые полосы.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'neon', 'rarity' => 'epic', 'price' => 2800, 'sort_order' => 454, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9']],
            ['slug' => 'card-theme-frost', 'name' => 'Карточка «Иней»', 'description' => 'Холодная синева и ледяные полосы.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'frost', 'rarity' => 'rare', 'price' => 1400, 'sort_order' => 455, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc']],
            ['slug' => 'card-theme-sakura', 'name' => 'Карточка «Сакура»', 'description' => 'Тёмно-вишнёвая карточка с розовой рамкой.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'sakura', 'rarity' => 'rare', 'price' => 1400, 'sort_order' => 456, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'card-theme-midnight', 'name' => 'Карточка «Полночь»', 'description' => 'Глубокий индиго и лавандовые акценты.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'midnight', 'rarity' => 'epic', 'price' => 2800, 'sort_order' => 457, 'metadata' => ['icon' => 'star', 'color' => '#6366f1']],
            ['slug' => 'card-theme-blood', 'name' => 'Карточка «Кровавая луна»', 'description' => 'Почти чёрная карточка с алой рамкой.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'blood', 'rarity' => 'epic', 'price' => 3000, 'sort_order' => 458, 'metadata' => ['icon' => 'flame', 'color' => '#ef4444']],
            ['slug' => 'card-theme-toxic', 'name' => 'Карточка «Токсин»', 'description' => 'Кислотно-зелёные полосы на болотном фоне.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'toxic', 'rarity' => 'rare', 'price' => 1600, 'sort_order' => 459, 'metadata' => ['icon' => 'bolt', 'color' => '#a3e635']],
            ['slug' => 'card-theme-mono', 'name' => 'Карточка «Монохром»', 'description' => 'Строгий чёрно-белый минимализм.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'mono', 'rarity' => 'legendary', 'price' => 6500, 'sort_order' => 460, 'metadata' => ['icon' => 'frame', 'color' => '#fafafa']],
            ['slug' => 'card-theme-sunset', 'name' => 'Карточка «Закат»', 'description' => 'Тёплые оранжевые и янтарные тона.', 'type' => ShopItem::TYPE_CARD_THEME, 'effect_value' => 'sunset', 'rarity' => 'rare', 'price' => 1600, 'sort_order' => 461, 'metadata' => ['icon' => 'star', 'color' => '#fb923c']],

            // === Третья волна: фоны 3D-скина ===
            ['slug' => 'backdrop-sunset', 'name' => 'Фон «Закат»', 'description' => 'Солнце садится за холмы, небо горит розовым и оранжевым.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'sunset', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 405, 'metadata' => ['icon' => 'star', 'color' => '#fb923c']],
            ['slug' => 'backdrop-synthwave', 'name' => 'Фон «Синтвейв»', 'description' => 'Полосатое солнце и бегущая неоновая сетка до горизонта.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'synthwave', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 406, 'metadata' => ['icon' => 'bolt', 'color' => '#ec4899']],
            ['slug' => 'backdrop-cyber-city', 'name' => 'Фон «Кибергород»', 'description' => 'Небоскрёбы ночного мегаполиса и мерцающие окна.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'cyber_city', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 407, 'metadata' => ['icon' => 'bolt', 'color' => '#22d3ee']],
            ['slug' => 'backdrop-space', 'name' => 'Фон «Открытый космос»', 'description' => 'Планета с кольцами и россыпь звёзд.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'space', 'rarity' => 'legendary', 'price' => 8500, 'sort_order' => 408, 'metadata' => ['icon' => 'star', 'color' => '#a855f7']],
            ['slug' => 'backdrop-aurora-night', 'name' => 'Фон «Полярная ночь»', 'description' => 'Северное сияние переливается над снегом.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'aurora_night', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 409, 'metadata' => ['icon' => 'sparkles', 'color' => '#34d399']],
            ['slug' => 'backdrop-sakura-garden', 'name' => 'Фон «Сад сакуры»', 'description' => 'Розовые кроны и падающие лепестки.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'sakura_garden', 'rarity' => 'epic', 'price' => 3500, 'sort_order' => 410, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'backdrop-snow-peaks', 'name' => 'Фон «Снежные пики»', 'description' => 'Заснеженные горы и тихий снегопад.', 'type' => ShopItem::TYPE_SKIN_BACKDROP, 'effect_value' => 'snow_peaks', 'rarity' => 'rare', 'price' => 1800, 'sort_order' => 411, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc']],

            // === Третья волна: акценты ===
            ['slug' => 'accent-lime', 'name' => 'Лаймовый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#84cc16', 'rarity' => 'common', 'price' => 300, 'sort_order' => 205, 'metadata' => ['icon' => 'target', 'color' => '#84cc16']],
            ['slug' => 'accent-sky', 'name' => 'Небесный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#38bdf8', 'rarity' => 'common', 'price' => 300, 'sort_order' => 206, 'metadata' => ['icon' => 'target', 'color' => '#38bdf8']],
            ['slug' => 'accent-indigo', 'name' => 'Индиго-акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#6366f1', 'rarity' => 'common', 'price' => 300, 'sort_order' => 207, 'metadata' => ['icon' => 'target', 'color' => '#6366f1']],
            ['slug' => 'accent-peach', 'name' => 'Персиковый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#fdba74', 'rarity' => 'common', 'price' => 300, 'sort_order' => 208, 'metadata' => ['icon' => 'target', 'color' => '#fdba74']],
            ['slug' => 'accent-lavender', 'name' => 'Лавандовый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#c4b5fd', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 209, 'metadata' => ['icon' => 'target', 'color' => '#c4b5fd']],
            ['slug' => 'accent-mint', 'name' => 'Мятный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#5eead4', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 210, 'metadata' => ['icon' => 'target', 'color' => '#5eead4']],
            ['slug' => 'accent-cherry', 'name' => 'Вишнёвый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#be123c', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 211, 'metadata' => ['icon' => 'target', 'color' => '#be123c']],
            ['slug' => 'accent-neon-yellow', 'name' => 'Кислотный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#fcee0a', 'rarity' => 'epic', 'price' => 1200, 'sort_order' => 212, 'metadata' => ['icon' => 'target', 'color' => '#fcee0a']],
            ['slug' => 'accent-hot-pink', 'name' => 'Неоново-розовый акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#ff2a6d', 'rarity' => 'epic', 'price' => 1200, 'sort_order' => 213, 'metadata' => ['icon' => 'target', 'color' => '#ff2a6d']],
            ['slug' => 'accent-white', 'name' => 'Белоснежный акцент', 'type' => ShopItem::TYPE_ACCENT_COLOR, 'effect_value' => '#f8fafc', 'rarity' => 'epic', 'price' => 1200, 'sort_order' => 214, 'metadata' => ['icon' => 'target', 'color' => '#f8fafc']],

            // === Третья волна: цвета подсветки клана ===
            ['slug' => 'clan-highlight-color-midnight', 'name' => 'Подсветка: полночная', 'description' => 'Глубокий индиго. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'midnight', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 316, 'metadata' => ['icon' => 'star', 'color' => '#6366f1', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-sunset', 'name' => 'Подсветка: закатная', 'description' => 'Тёплый оранжевый. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'sunset', 'rarity' => 'common', 'price' => 400, 'sort_order' => 317, 'metadata' => ['icon' => 'star', 'color' => '#fb923c', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-frost', 'name' => 'Подсветка: морозная', 'description' => 'Холодный небесно-голубой. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'frost', 'rarity' => 'common', 'price' => 400, 'sort_order' => 318, 'metadata' => ['icon' => 'star', 'color' => '#7dd3fc', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-mint', 'name' => 'Подсветка: мятная', 'description' => 'Свежий мятный. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'mint', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 319, 'metadata' => ['icon' => 'star', 'color' => '#5eead4', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-blood', 'name' => 'Подсветка: кровавая', 'description' => 'Тёмно-алая. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'blood', 'rarity' => 'epic', 'price' => 1100, 'sort_order' => 3191, 'metadata' => ['icon' => 'flame', 'color' => '#b91c1c', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-lavender', 'name' => 'Подсветка: лавандовая', 'description' => 'Нежная лаванда. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'lavender', 'rarity' => 'rare', 'price' => 700, 'sort_order' => 3192, 'metadata' => ['icon' => 'star', 'color' => '#c4b5fd', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-chrome', 'name' => 'Подсветка: хромовая', 'description' => 'Холодный металл. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'chrome', 'rarity' => 'epic', 'price' => 1100, 'sort_order' => 3193, 'metadata' => ['icon' => 'star', 'color' => '#e2e8f0', 'kind' => 'color']],
            ['slug' => 'clan-highlight-color-toxic', 'name' => 'Подсветка: токсичная', 'description' => 'Кислотно-зелёная. Нужна активная подсветка.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'toxic', 'rarity' => 'epic', 'price' => 1100, 'sort_order' => 3194, 'metadata' => ['icon' => 'bolt', 'color' => '#a3e635', 'kind' => 'color']],

            // === Третья волна: эффекты подсветки клана ===
            ['slug' => 'clan-highlight-effect-neon', 'name' => 'Эффект: неон', 'description' => 'Карточка клана светится неоновой трубкой и иногда моргает.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'neon', 'rarity' => 'epic', 'price' => 2000, 'sort_order' => 327, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-holo', 'name' => 'Эффект: голограмма', 'description' => 'Радужная рамка переливается, по карточке бегут блики.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'holo', 'rarity' => 'legendary', 'price' => 4500, 'sort_order' => 328, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-storm', 'name' => 'Эффект: гроза', 'description' => 'Дождь по карточке и вспышки молний.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'storm', 'rarity' => 'epic', 'price' => 2400, 'sort_order' => 329, 'metadata' => ['icon' => 'bolt', 'color' => '#a5b4fc', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-matrix', 'name' => 'Эффект: Матрица', 'description' => 'Строки кода стекают по карточке клана.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'matrix', 'rarity' => 'epic', 'price' => 2400, 'sort_order' => 330, 'metadata' => ['icon' => 'doc', 'color' => '#22c55e', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-galaxy', 'name' => 'Эффект: галактика', 'description' => 'Звёзды и туманность на фоне карточки.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'galaxy', 'rarity' => 'legendary', 'price' => 4000, 'sort_order' => 331, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-lava', 'name' => 'Эффект: лава', 'description' => 'Раскалённое дно карточки дышит жаром.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'lava', 'rarity' => 'epic', 'price' => 2200, 'sort_order' => 332, 'metadata' => ['icon' => 'flame', 'color' => '#f97316', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-glitch', 'name' => 'Эффект: глитч', 'description' => 'Красно-бирюзовые помехи и дрожь карточки.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'glitch', 'rarity' => 'epic', 'price' => 2400, 'sort_order' => 333, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8', 'kind' => 'effect']],
            ['slug' => 'clan-highlight-effect-shine', 'name' => 'Эффект: блик', 'description' => 'Белый блик пробегает по карточке клана.', 'type' => ShopItem::TYPE_CLAN_HIGHLIGHT_STYLE, 'effect_value' => 'shine', 'rarity' => 'rare', 'price' => 1300, 'sort_order' => 334, 'metadata' => ['icon' => 'sparkles', 'color' => '#f8fafc', 'kind' => 'effect']],

            // === Третья волна: макеты профиля ===
            ['slug' => 'layout-cyberpunk', 'name' => 'Макет «Киберпанк»', 'description' => 'Жёлтый неон, срезанные углы и RGB-сдвиг в заголовках. Колонка слева.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'cyberpunk', 'rarity' => 'legendary', 'price' => 9500, 'sort_order' => 481, 'metadata' => ['icon' => 'bolt', 'color' => '#fcee0a']],
            ['slug' => 'layout-glass', 'name' => 'Макет «Стекло»', 'description' => 'Матовые стеклянные панели на плывущих цветных пятнах.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'glass', 'rarity' => 'legendary', 'price' => 9500, 'sort_order' => 482, 'metadata' => ['icon' => 'sparkles', 'color' => '#f0abfc']],
            ['slug' => 'layout-noir', 'name' => 'Макет «Нуар»', 'description' => 'Чёрно-белый журнал: засечки, зерно плёнки и строгие линии.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'noir', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 483, 'metadata' => ['icon' => 'doc', 'color' => '#fafafa']],
            ['slug' => 'layout-galaxy', 'name' => 'Макет «Галактика»', 'description' => 'Туманности, мерцающие звёзды и градиентные заголовки.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'galaxy', 'rarity' => 'epic', 'price' => 7000, 'sort_order' => 484, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa']],
            ['slug' => 'layout-abyss', 'name' => 'Макет «Бездна»', 'description' => 'Глубина океана, блики света сверху и мягкое голубое свечение.', 'type' => ShopItem::TYPE_PROFILE_LAYOUT, 'effect_value' => 'abyss', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 485, 'metadata' => ['icon' => 'snow', 'color' => '#38bdf8']],

            /*
             * === Четвёртая волна: видно всем ===
             * Эффект ника — везде, где показан ник; появление профиля и след
             * курсора — у гостей профиля.
             */
            ['slug' => 'name-rainbow', 'name' => 'Ник «Радуга»', 'description' => 'Ник переливается всеми цветами — в рейтинге, на форуме, в чате и кланах.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'rainbow', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 500, 'metadata' => ['icon' => 'sparkles', 'color' => '#f472b6']],
            ['slug' => 'name-fire', 'name' => 'Ник «Пламя»', 'description' => 'Ник горит: огненный перелив и жар вокруг букв.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'fire', 'rarity' => 'epic', 'price' => 5500, 'sort_order' => 501, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'name-neon', 'name' => 'Ник «Неон»', 'description' => 'Ник светится неоновой вывеской и иногда моргает.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'neon', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 502, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9']],
            ['slug' => 'name-glitch', 'name' => 'Ник «Глитч»', 'description' => 'Ник рассыпается на красный и бирюзовый и дёргается.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'glitch', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 503, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8']],
            ['slug' => 'name-gold', 'name' => 'Ник «Золото»', 'description' => 'Золотой ник, по которому пробегает блик.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'gold', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 504, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],
            ['slug' => 'name-ice', 'name' => 'Ник «Лёд»', 'description' => 'Ледяной ник с морозным блеском.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'ice', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 505, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc']],
            ['slug' => 'name-galaxy', 'name' => 'Ник «Галактика»', 'description' => 'Внутри букв плывёт туманность со звёздами.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'galaxy', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 506, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa']],
            ['slug' => 'name-toxic', 'name' => 'Ник «Токсин»', 'description' => 'Кислотно-зелёный ник с ядовитым свечением.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'toxic', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 507, 'metadata' => ['icon' => 'bolt', 'color' => '#a3e635']],
            ['slug' => 'name-chrome', 'name' => 'Ник «Хром»', 'description' => 'Металлический ник с бегущим отражением.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'chrome', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 508, 'metadata' => ['icon' => 'frame', 'color' => '#e2e8f0']],
            ['slug' => 'name-wave', 'name' => 'Ник «Волна»', 'description' => 'Буквы ника по очереди подпрыгивают волной.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'wave', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 509, 'metadata' => ['icon' => 'sparkles', 'color' => '#22d3ee']],
            ['slug' => 'name-sakura', 'name' => 'Ник «Сакура»', 'description' => 'Нежно-розовый ник с мягким сиянием.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'sakura', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 510, 'is_active' => false, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'name-shadow', 'name' => 'Ник «Тень»', 'description' => 'Ник окутан тёмной дымкой, которая расходится и сгущается.', 'type' => ShopItem::TYPE_NAME_EFFECT, 'effect_value' => 'shadow', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 511, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#6b21a8']],

            ['slug' => 'entrance-lightning', 'name' => 'Появление «Удар молнии»', 'description' => 'Гость открывает профиль — в него бьёт молния, экран вспыхивает.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'lightning', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 520, 'metadata' => ['icon' => 'bolt', 'color' => '#a5b4fc']],
            ['slug' => 'entrance-portal', 'name' => 'Появление «Портал»', 'description' => 'Профиль выходит из закрученного фиолетового портала.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'portal', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 521, 'metadata' => ['icon' => 'sparkles', 'color' => '#a855f7']],
            ['slug' => 'entrance-glitch', 'name' => 'Появление «Взлом»', 'description' => 'Профиль собирается из помех, как будто систему взломали.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'glitch', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 522, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8']],
            ['slug' => 'entrance-confetti', 'name' => 'Появление «Хлопушка»', 'description' => 'Профиль открывается с залпом конфетти.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'confetti', 'rarity' => 'rare', 'price' => 3500, 'sort_order' => 523, 'metadata' => ['icon' => 'gift', 'color' => '#f472b6']],
            ['slug' => 'entrance-smoke', 'name' => 'Появление «Из тумана»', 'description' => 'Густой туман рассеивается, и из него проступает профиль.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'smoke', 'rarity' => 'rare', 'price' => 3500, 'sort_order' => 524, 'metadata' => ['icon' => 'sparkles', 'color' => '#a8a29e']],
            ['slug' => 'entrance-royal', 'name' => 'Появление «Коронация»', 'description' => 'Золотые лучи, корона с небес и торжественная вспышка.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'royal', 'rarity' => 'legendary', 'price' => 11000, 'sort_order' => 525, 'is_active' => false, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],
            ['slug' => 'entrance-blackhole', 'name' => 'Появление «Горизонт событий»', 'description' => 'Всё затягивает в чёрную дыру — и профиль вырывается из неё.', 'type' => ShopItem::TYPE_PROFILE_ENTRANCE, 'effect_value' => 'blackhole', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 526, 'metadata' => ['icon' => 'star', 'color' => '#f97316']],

            ['slug' => 'trail-sparkles', 'name' => 'След «Искры»', 'description' => 'Гости профиля водят мышью — за курсором сыплются золотые искры.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'sparkles', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 540, 'metadata' => ['icon' => 'sparkles', 'color' => '#fde047']],
            ['slug' => 'trail-hearts', 'name' => 'След «Сердечки»', 'description' => 'За курсором тянутся сердечки.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'hearts', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 541, 'metadata' => ['icon' => 'heart', 'color' => '#fb7185']],
            ['slug' => 'trail-fire', 'name' => 'След «Огонь»', 'description' => 'Курсор оставляет за собой языки пламени.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'fire', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 542, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'trail-rainbow', 'name' => 'След «Радуга»', 'description' => 'Радужная лента тянется за курсором.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'rainbow', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 543, 'metadata' => ['icon' => 'sparkles', 'color' => '#a855f7']],
            ['slug' => 'trail-stars', 'name' => 'След «Звездопад»', 'description' => 'Звёздочки разлетаются от курсора и гаснут.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'stars', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 544, 'metadata' => ['icon' => 'star', 'color' => '#c4b5fd']],
            ['slug' => 'trail-bubbles', 'name' => 'След «Пузыри»', 'description' => 'Мыльные пузыри всплывают за курсором.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'bubbles', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 545, 'metadata' => ['icon' => 'sparkles', 'color' => '#bae6fd']],
            ['slug' => 'trail-matrix', 'name' => 'След «Код»', 'description' => 'За курсором осыпаются зелёные символы.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'matrix', 'rarity' => 'epic', 'price' => 5500, 'sort_order' => 546, 'is_active' => false, 'metadata' => ['icon' => 'doc', 'color' => '#22c55e']],
            ['slug' => 'trail-snow', 'name' => 'След «Снежинки»', 'description' => 'Снежинки кружатся и тают за курсором.', 'type' => ShopItem::TYPE_CURSOR_TRAIL, 'effect_value' => 'snow', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 547, 'metadata' => ['icon' => 'snow', 'color' => '#e0f2fe']],

            ['slug' => 'bundle-legend', 'name' => 'Набор «Легенда»', 'description' => 'Золотой ник, коронация при входе, звездопад за курсором и голограмма. Максимум понтов.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'legend', 'rarity' => 'legendary', 'price' => 32000, 'sort_order' => 0, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'theme' => 'legend', 'mood' => 'Легендарно', 'items' => ['name-gold', 'entrance-royal', 'trail-stars', 'frame-holo', 'badge-major']]],
            ['slug' => 'bundle-chaos', 'name' => 'Набор «Хаос»', 'description' => 'Глитч в нике, взлом при входе, код за курсором и тёмная дымка.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'chaos', 'rarity' => 'legendary', 'price' => 21000, 'sort_order' => 14, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8', 'theme' => 'cyberpunk', 'mood' => 'Дерзко', 'items' => ['name-glitch', 'name-shadow', 'entrance-glitch', 'trail-matrix', 'frame-glitch']]],
            ['slug' => 'bundle-elements', 'name' => 'Набор «Стихии»', 'description' => 'Огненный ник, удар молнии при входе, пламя за курсором и адское пламя на аватаре.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'elements', 'rarity' => 'legendary', 'price' => 25000, 'sort_order' => 15, 'metadata' => ['icon' => 'flame', 'color' => '#f97316', 'theme' => 'elements', 'mood' => 'Мощно', 'items' => ['name-fire', 'entrance-lightning', 'trail-fire', 'frame-hellfire', 'effect-inferno']]],
            ['slug' => 'bundle-sweet', 'name' => 'Набор «Сладкая жизнь»', 'description' => 'Розовый ник, хлопушка при входе, сердечки за курсором и лепестки сакуры.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'sweet', 'rarity' => 'epic', 'price' => 14000, 'sort_order' => 16, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'theme' => 'sweet', 'mood' => 'Мило', 'items' => ['name-sakura', 'entrance-confetti', 'trail-hearts', 'effect-sakura-petals', 'frame-sakura']]],
            ['slug' => 'bundle-frost', 'name' => 'Набор «Ледяное сердце»', 'description' => 'Ледяной ник, снежинки за курсором, снежные пики и иней на карточке.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'frost', 'rarity' => 'epic', 'price' => 13000, 'sort_order' => 17, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc', 'theme' => 'frost', 'mood' => 'Холодно', 'items' => ['name-ice', 'trail-snow', 'card-theme-frost', 'backdrop-snow-peaks', 'layout-frost']]],

            /*
             * === Пятая волна: питомцы, сообщения в чате, титулы ===
             */
            ['slug' => 'pet-cat', 'name' => 'Питомец «Котик»', 'description' => 'Рыжий котик гуляет по шапке профиля, спит и мурчит, если навести мышь.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'cat', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 560, 'metadata' => ['icon' => 'heart', 'color' => '#fb923c', 'sprite' => 'cat']],
            ['slug' => 'pet-dog', 'name' => 'Питомец «Пёсик»', 'description' => 'Верный пёс бегает по профилю и радуется гостям.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'dog', 'rarity' => 'epic', 'price' => 6000, 'sort_order' => 561, 'metadata' => ['icon' => 'heart', 'color' => '#a16207', 'sprite' => 'dog']],
            ['slug' => 'pet-fox', 'name' => 'Питомец «Лисёнок»', 'description' => 'Хитрый лисёнок с пушистым хвостом.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'fox', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 562, 'metadata' => ['icon' => 'heart', 'color' => '#f97316', 'sprite' => 'fox']],
            ['slug' => 'pet-axolotl', 'name' => 'Питомец «Аксолотль»', 'description' => 'Улыбчивый аксолотль ползает по шапке и булькает.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'axolotl', 'rarity' => 'epic', 'price' => 6500, 'sort_order' => 563, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'sprite' => 'axolotl']],
            ['slug' => 'pet-slime', 'name' => 'Питомец «Слизень»', 'description' => 'Прыгучий слизень. Липкий, но очень милый.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'slime', 'rarity' => 'rare', 'price' => 3500, 'sort_order' => 564, 'metadata' => ['icon' => 'heart', 'color' => '#86efac', 'sprite' => 'slime']],
            ['slug' => 'pet-ghost', 'name' => 'Питомец «Призрак»', 'description' => 'Маленький привидение летает над профилем и пугает гостей. Почти.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'ghost', 'rarity' => 'epic', 'price' => 7000, 'sort_order' => 565, 'metadata' => ['icon' => 'star', 'color' => '#e2e8f0', 'sprite' => 'ghost']],
            ['slug' => 'pet-dragon', 'name' => 'Питомец «Дракончик»', 'description' => 'Фиолетовый дракончик пыхает огнём и охраняет твоё золото.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'dragon', 'rarity' => 'legendary', 'price' => 12000, 'sort_order' => 566, 'metadata' => ['icon' => 'flame', 'color' => '#8b5cf6', 'sprite' => 'dragon']],
            ['slug' => 'pet-robot', 'name' => 'Питомец «Робот»', 'description' => 'Маленький робот с мигающей лампочкой разговаривает двоичным кодом.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'robot', 'rarity' => 'epic', 'price' => 7000, 'sort_order' => 567, 'metadata' => ['icon' => 'bolt', 'color' => '#94a3b8', 'sprite' => 'robot']],
            ['slug' => 'pet-penguin', 'name' => 'Питомец «Пингвин»', 'description' => 'Пингвин шлёпает по профилю и постоянно мёрзнет.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'penguin', 'rarity' => 'rare', 'price' => 4000, 'sort_order' => 568, 'metadata' => ['icon' => 'snow', 'color' => '#1e293b', 'sprite' => 'penguin']],
            ['slug' => 'pet-bee', 'name' => 'Питомец «Пчёлка»', 'description' => 'Пчёлка летает над профилем и ищет цветочки.', 'type' => ShopItem::TYPE_PROFILE_PET, 'effect_value' => 'bee', 'rarity' => 'rare', 'price' => 4000, 'sort_order' => 569, 'metadata' => ['icon' => 'heart', 'color' => '#facc15', 'sprite' => 'bee']],

            ['slug' => 'bubble-neon', 'name' => 'Сообщения «Неон»', 'description' => 'Твои сообщения в чате светятся неоновой рамкой — собеседник это видит.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'neon', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 580, 'metadata' => ['icon' => 'bolt', 'color' => '#e879f9']],
            ['slug' => 'bubble-gold', 'name' => 'Сообщения «Золото»', 'description' => 'Золотые сообщения с бегущим бликом.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'gold', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 581, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],
            ['slug' => 'bubble-holo', 'name' => 'Сообщения «Голограмма»', 'description' => 'Радужная голографическая рамка вокруг сообщений.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'holo', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 582, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc']],
            ['slug' => 'bubble-sakura', 'name' => 'Сообщения «Сакура»', 'description' => 'Нежно-розовые сообщения с цветочком.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'sakura', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 583, 'metadata' => ['icon' => 'leaf', 'color' => '#f9a8d4']],
            ['slug' => 'bubble-glitch', 'name' => 'Сообщения «Глитч»', 'description' => 'Сообщения с RGB-сдвигом и строками развёртки.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'glitch', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 584, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8']],
            ['slug' => 'bubble-galaxy', 'name' => 'Сообщения «Галактика»', 'description' => 'Звёздный фон и туманность внутри сообщений.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'galaxy', 'rarity' => 'epic', 'price' => 5500, 'sort_order' => 585, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa']],
            ['slug' => 'bubble-fire', 'name' => 'Сообщения «Пламя»', 'description' => 'Сообщения пышут жаром снизу.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'fire', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 586, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'bubble-ice', 'name' => 'Сообщения «Лёд»', 'description' => 'Морозное стекло с инеем по краю.', 'type' => ShopItem::TYPE_CHAT_BUBBLE, 'effect_value' => 'ice', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 587, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc']],

            ['slug' => 'title-nether-lord', 'name' => 'Титул «Повелитель Незера»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'nether_lord', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 600, 'metadata' => ['icon' => 'flame', 'color' => '#f97316']],
            ['slug' => 'title-night-hunter', 'name' => 'Титул «Ночной охотник»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'night_hunter', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 601, 'is_active' => false, 'metadata' => ['icon' => 'star', 'color' => '#7e22ce']],
            ['slug' => 'title-server-legend', 'name' => 'Титул «Легенда сервера»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'server_legend', 'rarity' => 'legendary', 'price' => 12000, 'sort_order' => 602, 'metadata' => ['icon' => 'crown', 'color' => '#facc15']],
            ['slug' => 'title-pvp-god', 'name' => 'Титул «Бог PvP»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'pvp_god', 'rarity' => 'legendary', 'price' => 10000, 'sort_order' => 603, 'metadata' => ['icon' => 'sword', 'color' => '#c084fc']],
            ['slug' => 'title-bridge-master', 'name' => 'Титул «Мастер бриджа»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'bridge_master', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 604, 'metadata' => ['icon' => 'box', 'color' => '#e879f9']],
            ['slug' => 'title-clan-general', 'name' => 'Титул «Генерал клана»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'clan_general', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 605, 'metadata' => ['icon' => 'shield', 'color' => '#e2e8f0']],
            ['slug' => 'title-sweet-bun', 'name' => 'Титул «Сладкая булочка»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'sweet_bun', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 606, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4']],
            ['slug' => 'title-chaos-agent', 'name' => 'Титул «Агент хаоса»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'chaos_agent', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 607, 'metadata' => ['icon' => 'bolt', 'color' => '#05d9e8']],
            ['slug' => 'title-star-child', 'name' => 'Титул «Дитя звёзд»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'star_child', 'rarity' => 'epic', 'price' => 5000, 'sort_order' => 608, 'metadata' => ['icon' => 'star', 'color' => '#a78bfa']],
            ['slug' => 'title-ice-heart', 'name' => 'Титул «Ледяное сердце»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'ice_heart', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 609, 'metadata' => ['icon' => 'snow', 'color' => '#7dd3fc']],
            ['slug' => 'title-toxic', 'name' => 'Титул «Токсик»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'toxic', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 610, 'metadata' => ['icon' => 'bolt', 'color' => '#a3e635']],
            ['slug' => 'title-eternal-noob', 'name' => 'Титул «Вечный нуб»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'eternal_noob', 'rarity' => 'common', 'price' => 500, 'sort_order' => 611, 'metadata' => ['icon' => 'heart', 'color' => '#94a3b8']],
            ['slug' => 'title-bread', 'name' => 'Титул «Ушёл за хлебом»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'bread', 'rarity' => 'common', 'price' => 500, 'sort_order' => 612, 'metadata' => ['icon' => 'gift', 'color' => '#d6b98c']],
            ['slug' => 'title-creeper-friend', 'name' => 'Титул «Друг криперов»', 'type' => ShopItem::TYPE_PROFILE_TITLE, 'effect_value' => 'creeper_friend', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 613, 'metadata' => ['icon' => 'bolt', 'color' => '#4ade80']],

            // Питомцы теперь только из сундука — набор из питомцев снят с продажи
            ['slug' => 'bundle-best-friends', 'name' => 'Набор «Лучшие друзья»', 'description' => 'Котик, пёсик и лисёнок — по питомцу на каждое настроение.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'best_friends', 'rarity' => 'epic', 'price' => 13900, 'sort_order' => 18, 'is_active' => false, 'metadata' => ['icon' => 'heart', 'color' => '#fb923c', 'theme' => 'cat', 'mood' => 'Мило', 'items' => ['pet-cat', 'pet-dog', 'pet-fox', 'title-sweet-bun']]],
            ['slug' => 'bundle-dragon', 'name' => 'Набор «Сердце дракона»', 'description' => 'Огненные сообщения, огненный ник и след и титул Повелителя Незера.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'dragon', 'rarity' => 'legendary', 'price' => 13000, 'sort_order' => 19, 'metadata' => ['icon' => 'flame', 'color' => '#8b5cf6', 'theme' => 'nether', 'mood' => 'Эпично', 'items' => ['bubble-fire', 'title-nether-lord', 'trail-fire', 'name-fire']]],
            ['slug' => 'bundle-phantom', 'name' => 'Набор «Призрачная ночь»', 'description' => 'Ник-тень, титул Ночного охотника, звёздные сообщения и появление в дыму.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'phantom', 'rarity' => 'legendary', 'price' => 14000, 'sort_order' => 20, 'metadata' => ['icon' => 'star', 'color' => '#7e22ce', 'theme' => 'cosmos', 'mood' => 'Мистично', 'items' => ['name-shadow', 'title-night-hunter', 'bubble-galaxy', 'entrance-smoke']]],
            ['slug' => 'bundle-idol', 'name' => 'Набор «Сетевой кумир»', 'description' => 'Голограмма в чате, радужный ник и титул Бога PvP. Все смотрят на тебя.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'idol', 'rarity' => 'legendary', 'price' => 18000, 'sort_order' => 21, 'metadata' => ['icon' => 'sparkles', 'color' => '#c084fc', 'theme' => 'holo', 'mood' => 'Звёздно', 'items' => ['bubble-holo', 'name-rainbow', 'title-pvp-god', 'trail-rainbow']]],

            /*
             * === Шестая волна: значки у ника и стикеры в чате ===
             * Значок — пиксельная иконка рядом с ником везде на сайте
             * (metadata.sprite, metadata.anim). Стикерпак — коллекция для
             * чата, metadata.stickers — ключи стикеров (frontend/data/shop/stickers.js).
             */
            ['slug' => 'icon-crown', 'name' => 'Значок «Корона»', 'description' => 'Золотая корона рядом с ником — в рейтинге, на форуме, в чате.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'crown', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 620, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'sprite' => 'crown', 'anim' => 'glow']],
            ['slug' => 'icon-flame', 'name' => 'Значок «Огонь»', 'description' => 'Ник в огне — язычок пламени рядом.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'flame', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 621, 'metadata' => ['icon' => 'flame', 'color' => '#f97316', 'sprite' => 'flame', 'anim' => 'bounce']],
            ['slug' => 'icon-diamond', 'name' => 'Значок «Алмаз»', 'description' => 'Крутящийся алмаз у ника.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'diamond', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 622, 'metadata' => ['icon' => 'sparkles', 'color' => '#67e8f9', 'sprite' => 'diamond', 'anim' => 'spin']],
            ['slug' => 'icon-heart', 'name' => 'Значок «Сердце»', 'description' => 'Пиксельное сердечко бьётся у ника.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'heart', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 623, 'metadata' => ['icon' => 'heart', 'color' => '#ef4444', 'sprite' => 'heart', 'anim' => 'pulse']],
            ['slug' => 'icon-skull', 'name' => 'Значок «Череп»', 'description' => 'Череп со светящимися глазами — для тех, кого боятся.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'skull', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 624, 'metadata' => ['icon' => 'flame', 'color' => '#ef4444', 'sprite' => 'skull', 'anim' => 'glow']],
            ['slug' => 'icon-creeper', 'name' => 'Значок «Криппер»', 'description' => 'Криппер покачивается у ника. Ссс…', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'creeper', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 625, 'metadata' => ['icon' => 'bolt', 'color' => '#4ade80', 'sprite' => 'creeper', 'anim' => 'wiggle']],
            ['slug' => 'icon-bee', 'name' => 'Значок «Пчёлка»', 'description' => 'Пчёлка прыгает рядом с ником.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'bee', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 626, 'metadata' => ['icon' => 'heart', 'color' => '#facc15', 'sprite' => 'bee', 'anim' => 'bounce']],
            ['slug' => 'icon-cat', 'name' => 'Значок «Котик»', 'description' => 'Котик у ника. Просто котик.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'cat', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 627, 'metadata' => ['icon' => 'heart', 'color' => '#fb923c', 'sprite' => 'cat', 'anim' => 'wiggle']],
            ['slug' => 'icon-totem', 'name' => 'Значок «Тотем»', 'description' => 'Тотем бессмертия сияет у ника.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'totem', 'rarity' => 'legendary', 'price' => 8000, 'sort_order' => 628, 'is_active' => false, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'sprite' => 'totem', 'anim' => 'glow']],
            ['slug' => 'icon-tnt', 'name' => 'Значок «TNT»', 'description' => 'Динамит у ника время от времени шипит и мигает.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'tnt', 'rarity' => 'rare', 'price' => 2500, 'sort_order' => 629, 'metadata' => ['icon' => 'bolt', 'color' => '#ef4444', 'sprite' => 'tnt', 'anim' => 'shake']],
            ['slug' => 'icon-warden', 'name' => 'Значок «Варден»', 'description' => 'Сенсоры вардена мерцают у ника.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'warden', 'rarity' => 'epic', 'price' => 4500, 'sort_order' => 630, 'metadata' => ['icon' => 'sparkles', 'color' => '#2dd4bf', 'sprite' => 'warden', 'anim' => 'pulse']],
            ['slug' => 'icon-ghost', 'name' => 'Значок «Призрак»', 'description' => 'Маленький призрак парит у ника.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'ghost', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 631, 'metadata' => ['icon' => 'star', 'color' => '#e2e8f0', 'sprite' => 'ghost', 'anim' => 'bounce']],
            ['slug' => 'icon-dragon', 'name' => 'Значок «Дракон»', 'description' => 'Дракончик у ника — знак силы.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'dragon', 'rarity' => 'legendary', 'price' => 9000, 'sort_order' => 632, 'is_active' => false, 'metadata' => ['icon' => 'flame', 'color' => '#8b5cf6', 'sprite' => 'dragon', 'anim' => 'glow']],
            ['slug' => 'icon-axolotl', 'name' => 'Значок «Аксолотль»', 'description' => 'Улыбчивый аксолотль у ника.', 'type' => ShopItem::TYPE_NAME_ICON, 'effect_value' => 'axolotl', 'rarity' => 'rare', 'price' => 2000, 'sort_order' => 633, 'metadata' => ['icon' => 'heart', 'color' => '#f9a8d4', 'sprite' => 'axolotl', 'anim' => 'bounce']],

            ['slug' => 'stickers-mobs', 'name' => 'Стикеры «Мобы»', 'description' => 'Шесть анимированных стикеров для чата: криппер, пчела, аксолотль, слизень, призрак, дракон.', 'type' => ShopItem::TYPE_STICKER_PACK, 'effect_value' => 'mobs', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 640, 'metadata' => ['icon' => 'gift', 'color' => '#4ade80', 'sprite' => 'creeper', 'stickers' => ['creeper', 'bee', 'axolotl', 'slime', 'ghost', 'dragon']]],
            ['slug' => 'stickers-mood', 'name' => 'Стикеры «Настроение»', 'description' => 'Люблю, я умер, огонь, король, выжил, БУМ — на любой случай в чате.', 'type' => ShopItem::TYPE_STICKER_PACK, 'effect_value' => 'mood', 'rarity' => 'epic', 'price' => 4000, 'sort_order' => 641, 'metadata' => ['icon' => 'gift', 'color' => '#ef4444', 'sprite' => 'skull', 'stickers' => ['love', 'dead', 'fire', 'king', 'survived', 'boom']]],
            ['slug' => 'stickers-pets', 'name' => 'Стикеры «Питомцы»', 'description' => 'Мяу, гав, фыр и компания — котик, пёсик, лисёнок, пингвин, робот и варден.', 'type' => ShopItem::TYPE_STICKER_PACK, 'effect_value' => 'pets', 'rarity' => 'rare', 'price' => 3000, 'sort_order' => 642, 'metadata' => ['icon' => 'gift', 'color' => '#fb923c', 'sprite' => 'cat', 'stickers' => ['cat', 'dog', 'fox', 'penguin', 'robot', 'warden']]],

            ['slug' => 'bundle-chatterbox', 'name' => 'Набор «Болтун»', 'description' => 'Все три стикерпака и неоновые сообщения — чтобы в чате тебя было слышно.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'chatterbox', 'rarity' => 'epic', 'price' => 10900, 'sort_order' => 22, 'metadata' => ['icon' => 'gift', 'color' => '#e879f9', 'theme' => 'neon', 'mood' => 'Громко', 'items' => ['stickers-mobs', 'stickers-mood', 'stickers-pets', 'bubble-neon']]],
            ['slug' => 'bundle-immortal', 'name' => 'Набор «Бессмертный»', 'description' => 'Тотем у ника, дракон у ника, тотем бессмертия в профиле и титул Легенды сервера.', 'type' => ShopItem::TYPE_BUNDLE, 'effect_value' => 'immortal', 'rarity' => 'legendary', 'price' => 30000, 'sort_order' => 23, 'metadata' => ['icon' => 'crown', 'color' => '#facc15', 'theme' => 'legend', 'mood' => 'Бессмертно', 'items' => ['icon-totem', 'icon-dragon', 'effect-totem', 'title-server-legend', 'badge-totem']]],

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

        // Только добавляем недостающие товары: цены и видимость, изменённые
        // в админке, повторный запуск на проде не перетирает
        foreach ($items as $item) {
            ShopItem::firstOrCreate(
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
