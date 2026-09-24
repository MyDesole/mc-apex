<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // === КЛАН ===
            [
                'code' => 'clan_joined',
                'name' => 'Командный игрок',
                'description' => 'Вступил в первый клан',
                'icon' => '🛡️',
                'color' => '#7c3aed',
                'rarity' => 'common',
                'points' => 10,
            ],
            [
                'code' => 'clan_leader',
                'name' => 'Лидер',
                'description' => 'Создал свой клан',
                'icon' => '👑',
                'color' => '#facc15',
                'rarity' => 'rare',
                'points' => 50,
            ],
            [
                'code' => 'clan_officer',
                'name' => 'Офицер',
                'description' => 'Получил роль офицера в клане',
                'icon' => '⚔️',
                'color' => '#60a5fa',
                'rarity' => 'common',
                'points' => 20,
            ],
            [
                'code' => 'clan_war_win',
                'name' => 'Победитель',
                'description' => 'Выиграл первую клан-войну',
                'icon' => '🏆',
                'color' => '#f97316',
                'rarity' => 'rare',
                'points' => 30,
            ],
            [
                'code' => 'clan_war_5',
                'name' => 'Ветеран войн',
                'description' => 'Выиграл 5 клан-войн',
                'icon' => '🔥',
                'color' => '#ef4444',
                'rarity' => 'epic',
                'points' => 80,
            ],

            // === ТИРЫ ===
            [
                'code' => 'tier_e',
                'name' => 'Первые шаги',
                'description' => 'Получил первый тир',
                'icon' => '🌱',
                'color' => '#6b7280',
                'rarity' => 'common',
                'points' => 5,
            ],
            [
                'code' => 'tier_d',
                'name' => 'Ученик',
                'description' => 'Достиг тира D',
                'icon' => '🗡️',
                'color' => '#22c55e',
                'rarity' => 'common',
                'points' => 10,
            ],
            [
                'code' => 'tier_c',
                'name' => 'Боец',
                'description' => 'Достиг тира C',
                'icon' => '⚡',
                'color' => '#06b6d4',
                'rarity' => 'common',
                'points' => 25,
            ],
            [
                'code' => 'tier_b',
                'name' => 'Ветеран',
                'description' => 'Достиг тира B',
                'icon' => '💎',
                'color' => '#8b5cf6',
                'rarity' => 'rare',
                'points' => 50,
            ],
            [
                'code' => 'tier_a',
                'name' => 'Элита',
                'description' => 'Достиг тира A',
                'icon' => '🌟',
                'color' => '#f97316',
                'rarity' => 'epic',
                'points' => 100,
            ],
            [
                'code' => 'tier_s',
                'name' => 'Легенда',
                'description' => 'Достиг тира S',
                'icon' => '👑',
                'color' => '#facc15',
                'rarity' => 'legendary',
                'points' => 250,
            ],

            // === ТИР-ТЕСТЫ ===
            [
                'code' => 'tier_test_first',
                'name' => 'Дебют',
                'description' => 'Прошёл первый тир-тест',
                'icon' => '🎯',
                'color' => '#7c3aed',
                'rarity' => 'common',
                'points' => 10,
            ],
            [
                'code' => 'tier_test_5',
                'name' => 'Испытуемый',
                'description' => 'Прошёл 5 тир-тестов',
                'icon' => '📋',
                'color' => '#06b6d4',
                'rarity' => 'rare',
                'points' => 40,
            ],

            // === СОЦИАЛКА ===
            [
                'code' => 'friends_5',
                'name' => 'Душа компании',
                'description' => '5 друзей на платформе',
                'icon' => '🤝',
                'color' => '#22c55e',
                'rarity' => 'common',
                'points' => 20,
            ],
            [
                'code' => 'friends_20',
                'name' => 'Социальная сеть',
                'description' => '20 друзей на платформе',
                'icon' => '🌐',
                'color' => '#06b6d4',
                'rarity' => 'rare',
                'points' => 60,
            ],

            // === ПРОЧЕЕ ===
            [
                'code' => 'profile_complete',
                'name' => 'Полный профиль',
                'description' => 'Заполнил аватар, bio, подложку и соцсети',
                'icon' => '✨',
                'color' => '#a78bfa',
                'rarity' => 'common',
                'points' => 15,
            ],
            [
                'code' => 'top_10',
                'name' => 'В десятке',
                'description' => 'Попал в топ-10 игроков',
                'icon' => '📊',
                'color' => '#f97316',
                'rarity' => 'rare',
                'points' => 50,
            ],
            [
                'code' => 'top_1',
                'name' => 'Первый',
                'description' => 'Занял 1-е место в топе',
                'icon' => '🥇',
                'color' => '#facc15',
                'rarity' => 'legendary',
                'points' => 500,
            ],
        ];

        foreach ($achievements as $a) {
            Achievement::updateOrCreate(['code' => $a['code']], $a);
        }
    }
}
