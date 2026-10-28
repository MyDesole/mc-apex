<?php

namespace App\Domains\Players\Support;

/**
 * Уникальные питомцы — как подарки в Telegram.
 *
 * Каждый питомец при получении «чеканится»: номер экземпляра (#17
 * среди всех лисят) и три случайные черты — окрас, фон и аксессуар.
 * У каждого варианта свой шанс; редкие черты делают питомца особенным.
 *
 * Здесь же названия и шансы: клиент берёт их из ответа, а не хранит у
 * себя, так что поменять вероятности можно в одном месте.
 */
class PetTraits
{
    /** Черта => вариант => [название, вес]. Веса в долях процента. */
    public const TRAITS = [
        'model' => [
            'classic' => ['Классический', 3000],
            'shadow' => ['Теневой', 1200],
            'ash' => ['Пепельный', 1100],
            'mint' => ['Мятный', 900],
            'rose' => ['Розовый', 800],
            'ocean' => ['Океанский', 700],
            'gold' => ['Золотой', 450],
            'neon' => ['Неоновый', 400],
            'crystal' => ['Кристальный', 250],
            'rainbow' => ['Радужный', 100],
            'void' => ['Пустотный', 50],
            'galaxy' => ['Галактический', 50],
            'cherry' => ['Вишнёвый', 600],
            'lime' => ['Лаймовый', 600],
            'pastel' => ['Пастельный', 500],
            'frost' => ['Морозный', 350],
            'obsidian' => ['Обсидиановый', 300],
            'toxic' => ['Токсичный', 250],
            'lava' => ['Лавовый', 120],
            'holo' => ['Голографический', 40],
        ],
        'backdrop' => [
            'none' => ['Без фона', 3500],
            'sky' => ['Небо', 1500],
            'sunset' => ['Закат', 1200],
            'forest' => ['Лес', 1000],
            'nebula' => ['Туманность', 700],
            'ember' => ['Угли', 500],
            'aurora' => ['Сияние', 350],
            'cosmos' => ['Космос', 200],
            'prism' => ['Призма', 50],
            'ocean' => ['Океан', 700],
            'sakura' => ['Сакура', 600],
            'candy' => ['Конфета', 500],
            'night' => ['Ночь', 400],
            'matrix' => ['Матрица', 200],
            'lava' => ['Лава', 150],
        ],
        'accessory' => [
            'none' => ['Без аксессуара', 4000],
            'cap' => ['Кепка', 1200],
            'bow' => ['Бантик', 1000],
            'flower' => ['Цветок', 700],
            'glasses' => ['Очки', 700],
            'tophat' => ['Цилиндр', 500],
            'scarf' => ['Шарф', 400],
            'crown' => ['Корона', 250],
            'halo' => ['Нимб', 150],
            'horns' => ['Рожки', 80],
            'wizard' => ['Колпак мага', 20],
            'headphones' => ['Наушники', 500],
            'party' => ['Праздничный колпак', 400],
            'leaf' => ['Росток', 400],
            'pirate' => ['Пиратская повязка', 200],
            'monocle' => ['Монокль', 150],
        ],
    ];

    /**
     * Случайные черты. Если передан seed — результат всегда один и тот же
     * (для питомца без записи в инвентаре, например у админа).
     *
     * luck > 1 выравнивает веса (вес ^ 1/luck): редкие черты выпадают
     * заметно чаще — так работает «Сундук питомцев».
     */
    public static function roll(?int $seed = null, float $luck = 1.0): array
    {
        if ($seed !== null) {
            mt_srand($seed);
        }

        $traits = [];

        foreach (self::TRAITS as $trait => $variants) {
            $weights = array_map(
                fn ($variant) => max(1, (int) round($variant[1] ** (1 / max(1.0, $luck)) * 100)),
                $variants,
            );
            $pick = mt_rand(1, array_sum($weights));

            foreach ($weights as $id => $weight) {
                $pick -= $weight;

                if ($pick <= 0) {
                    $traits[$trait] = $id;
                    break;
                }
            }
        }

        if ($seed !== null) {
            mt_srand();
        }

        return $traits;
    }

    /**
     * Черты для клиента: id, название и шанс в процентах, плюс номер.
     *
     * @param  array<string, string>|null  $traits
     */
    public static function present(?array $traits, ?int $serial = null): ?array
    {
        if (! $traits) {
            return null;
        }

        $result = ['serial' => $serial];

        foreach (self::TRAITS as $trait => $variants) {
            $id = isset($variants[$traits[$trait] ?? '']) ? $traits[$trait] : array_key_first($variants);
            $total = array_sum(array_column($variants, 1));

            $result[$trait] = [
                'id' => $id,
                'name' => $variants[$id][0],
                'chance' => round($variants[$id][1] / $total * 100, 2),
            ];
        }

        // Общая редкость — произведение шансов черт: чем меньше, тем реже
        $result['rarity'] = self::rarityTier(
            $result['model']['chance'] * $result['backdrop']['chance'] * $result['accessory']['chance'] / 10000,
        );

        return $result;
    }

    /** Ранг экземпляра по общему шансу (в процентах). */
    private static function rarityTier(float $chance): string
    {
        return match (true) {
            $chance < 0.01 => 'mythic',
            $chance < 0.1 => 'legendary',
            $chance < 0.5 => 'epic',
            $chance < 2 => 'rare',
            default => 'common',
        };
    }
}
