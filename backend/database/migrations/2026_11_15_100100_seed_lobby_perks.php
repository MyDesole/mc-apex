<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Товары лобби: LuckPerms-команды, которые плагин выполняет на сервере. */
    private function items(): array
    {
        return [
            ['lobby-fly', 'Полёт в лобби', 'Летайте по лобби сервера. Выдаётся на сервере в течение минуты после покупки.', 'rare', 1500, 'plane', '#38bdf8',
                ['lp user {player} permission set apex.lobby.fly true']],
            ['lobby-prefix-star', 'Префикс ★', 'Звезда перед ником в чате сервера.', 'common', 600, 'star', '#facc15',
                ['lp user {player} meta setprefix 100 &e★&r ']],
            ['lobby-prefix-apex', 'Префикс [APEX]', 'Тег проекта перед ником в чате сервера.', 'rare', 1200, 'tag', '#a78bfa',
                ['lp user {player} meta setprefix 100 &5[APEX]&r ']],
            ['lobby-prefix-legend', 'Префикс [Легенда]', 'Золотой префикс для тех, кто идёт до конца.', 'epic', 3000, 'crown', '#f59e0b',
                ['lp user {player} meta setprefix 100 &6[Легенда]&r ']],
        ];
    }

    public function up(): void
    {
        foreach ($this->items() as $i => [$slug, $name, $description, $rarity, $price, $icon, $color, $commands]) {
            if (DB::table('shop_items')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('shop_items')->insert([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'type' => 'lobby_perk',
                'rarity' => $rarity,
                'effect_value' => null,
                'price' => $price,
                'is_active' => true,
                'sort_order' => 900 + $i,
                'is_consumable' => false,
                'is_repeatable' => false,
                'max_quantity' => null,
                'metadata' => json_encode(['icon' => $icon, 'color' => $color, 'commands' => $commands], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('shop_items')->where('type', 'lobby_perk')->delete();
    }
};
