<?php

namespace Database\Seeders;

use App\Domains\Bridge\Models\BridgeRank;
use Illuminate\Database\Seeder;

/**
 * Ступени званий бриджера.
 *
 * Выдаёт их бридж-тестер вручную — это не тир по очкам, а звание за
 * подтверждённые виды и общий уровень.
 *
 * Ключи заданы явно: «Bridge Мастер» и «Bridge Master» дают одинаковый
 * слаг, и один ранг затирал другой.
 */
class BridgeRankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['novice', 'Bridge Новичок', '#6b7280'],
            ['student', 'Bridge Ученик', '#22c55e'],
            ['fighter', 'Bridge Боец', '#06b6d4'],
            ['pro', 'Bridge Профи', '#8b5cf6'],
            ['master', 'Bridge Мастер', '#f97316'],
            ['grandmaster', 'Bridge Master', '#fbbf24'],
        ];

        foreach ($ranks as $index => [$key, $label, $color]) {
            BridgeRank::updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'color' => $color,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
