<?php

/*
|--------------------------------------------------------------------------
| APEX — экономика ApexCoin
|--------------------------------------------------------------------------
| Все числа правятся из админки (раздел «Магазин → Награды»), значения здесь —
| только дефолт, который используется, пока в shop_settings нет своей записи.
| Ключи ниже перечислены в App\Domains\Wallet\Services\RewardService::SETTINGS.
*/

return [

    // Валюта
    'currency' => [
        'code' => 'APEX',
        'name' => 'ApexCoin',
        'symbol' => '₳',
    ],

    // Награда за пройденный тир-тест по итоговому тиру
    'coins' => [
        'tier_test' => [
            'enabled' => true,
            'per_tier' => [
                'E' => 60,
                'D' => 90,
                'C' => 140,
                'B' => 220,
                'A' => 350,
                'S' => 600,
                'S+' => 1000,
            ],
            // Доплата за сам факт заявки (идемпотентно: раз в жизни)
            'first_test_bonus' => 150,
        ],

        // Награда за ачивку: reward = base + points * per_point (если у ачивки нет coin_reward)
        'achievement' => [
            'enabled' => true,
            'base' => 25,
            'per_point' => 2,
        ],

        // Ежедневный вход
        'daily_bonus' => [
            'enabled' => true,
            'amount' => 40,
        ],

        // Пригласительные ссылки
        'referral' => [
            // Сколько получает пригласивший за нового игрока
            'amount' => 200,
            // Приветственный бонус самому новичку
            'welcome_bonus' => 50,
        ],

        // Подарки друзьям
        'gift' => [
            'enabled' => true,
            'min' => 10,
            'max' => 100000,
            'daily_limit' => 5000,
            'fee_percent' => 5,
        ],

        // Способы начисления, которые можно включать/выключать без правки кода.
        // Ключ — значение поля source в coin_transactions.
        'sources' => [
            'tier_test' => true,
            'achievement' => true,
            'daily_bonus' => true,
            'admin' => true,
            'tournament' => false,
            'clan_war' => false,
            'referral' => true,
        ],
    ],

    // Приоритет тир-теста
    'priority' => [
        // Стартовый вес приоритетной заявки. Чем выше, тем раньше в очереди.
        'default_weight' => 100,
        // Сколько дней действует буст, если заявку так и не взяли в работу (null — бессрочно)
        'expires_after_days' => null,
    ],

    // Лимиты магазина
    'shop' => [
        'max_equipped_badges' => 3,
    ],
];
