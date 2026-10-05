<?php

namespace Database\Seeders;

use App\Domains\Bridge\Models\BridgeTechnique;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Стартовый список видов бриджа.
 *
 * Список в базе, а не в коде: новые виды можно добавить без выкладки.
 * Здесь — распространённые техники, остальное дополняется по мере надобности.
 */
class BridgeTechniqueSeeder extends Seeder
{
    public function run(): void
    {
        $techniques = [
            ['Straight Bridge', 'Обычный прямой бридж без разгона'],
            ['Speed Bridge', 'Спидбридж: бридж с разбега и прыжка'],
            ['Ninja Bridge', 'Ниндзя-бридж: быстрый бридж с приседаниями'],
            ['Breezily Bridge', 'Бризли-бридж: без приседаний'],
            ['Moonwalk', 'Мунволк: бридж спиной вперёд'],
            ['Godbridge', 'Годбридж: максимально быстрый бридж с задержкой'],
            ['Telly Bridge', 'Телли-бридж'],
            ['Held Telly Bridge', 'Хелд телли: телли с удержанием'],
            ['Schneller Bridge', 'Шнеллер-бридж'],
            ['Andromeda Bridge', 'Андромеда: диагональный бридж'],
            ['Witchly Bridge', 'Вичли-бридж'],
            ['Eagle Bridge', 'Игл-бридж: бридж с приседом перед каждым блоком'],
        ];

        foreach ($techniques as $index => [$label, $description]) {
            BridgeTechnique::updateOrCreate(
                ['key' => Str::slug($label, '_')],
                [
                    'label' => $label,
                    'description' => $description,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
