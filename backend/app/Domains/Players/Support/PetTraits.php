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
            'sunny' => ['Солнечный', 550],
            'retro' => ['Ретро', 450],
            'sakura' => ['Сакуровый', 400],
            'ghostly' => ['Призрачный', 180],
            'abyss' => ['Бездонный', 90],
            'glitch' => ['Глитч', 35],
            'aurora' => ['Северный', 25],
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
            'desert' => ['Пустыня', 600],
            'snowfall' => ['Снегопад', 400],
            'circuit' => ['Микросхема', 250],
            'rainbow' => ['Радуга', 90],
            'eclipse' => ['Затмение', 60],
            'rift' => ['Разлом', 40],
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
            'sunglasses' => ['Солнечные очки', 500],
            'bowtie' => ['Бабочка', 600],
            'bandana' => ['Бандана', 500],
            'chef' => ['Колпак повара', 300],
            'antenna' => ['Антенна', 300],
            'wreath' => ['Венок', 250],
            'santa' => ['Шапка Деда Мороза', 200],
            'viking' => ['Шлем викинга', 120],
            'star' => ['Звезда', 60],
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

        // Общая редкость — по произведению шансов черт: чем меньше, тем реже
        $result['rarity'] = self::rarityTier(self::comboChance(array_map(fn ($k) => $result[$k]['id'], [
            'model' => 'model', 'backdrop' => 'backdrop', 'accessory' => 'accessory',
        ])));

        return $result;
    }

    /**
     * Какая доля всех выпадений приходится на редкость (сверху вниз).
     * Пороги считаются из самих весов черт, поэтому новые варианты не
     * ломают распределение: раньше пороги были числами, и после новых
     * черт мифическим стал каждый четвёртый питомец, а обычных — ноль.
     */
    public const RARITY_SHARES = [
        'mythic' => 0.005,
        'legendary' => 0.025,
        'epic' => 0.10,
        'rare' => 0.27,
        // остальное — common
    ];

    /** Вероятность сочетания черт (доля, не проценты). */
    public static function comboChance(array $ids): float
    {
        $p = 1.0;
        foreach (self::TRAITS as $trait => $variants) {
            $total = array_sum(array_column($variants, 1));
            $p *= ($variants[$ids[$trait] ?? ''][1] ?? $variants[array_key_first($variants)][1]) / $total;
        }

        return $p;
    }

    /** Ранг экземпляра: где его сочетание среди всех по редкости. */
    private static function rarityTier(float $chance): string
    {
        foreach (self::rarityThresholds() as $tier => $limit) {
            if ($chance <= $limit) {
                return $tier;
            }
        }

        return 'common';
    }

    /**
     * Пороги вероятности сочетания для каждой редкости. Перебираем все
     * сочетания от самых редких: пока набранная доля выпадений не дошла
     * до границы редкости — сочетание этой редкости.
     *
     * @return array<string, float>
     */
    public static function rarityThresholds(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        // Пороги меняются только вместе с весами — ключ кэша от них
        $key = 'pet-rarity-thresholds:' . md5(json_encode([self::TRAITS, self::RARITY_SHARES]));

        return $cache = \Illuminate\Support\Facades\Cache::rememberForever($key, fn () => self::computeThresholds());
    }

    /** @return array<string, float> */
    private static function computeThresholds(): array
    {
        $chances = [[]];
        foreach (self::TRAITS as $variants) {
            $total = array_sum(array_column($variants, 1));
            $next = [];
            foreach ($chances as $partial) {
                foreach ($variants as $variant) {
                    $next[] = [...$partial, $variant[1] / $total];
                }
            }
            $chances = $next;
        }

        $combos = array_map(fn ($parts) => array_product($parts), $chances);
        sort($combos);

        $cache = [];
        $tiers = self::RARITY_SHARES;
        // Перебор от самых редких сочетаний к частым
        $edge = 0.0;
        $sum = 0.0;
        $tier = array_key_first($tiers);

        foreach ($combos as $p) {
            while ($tier !== null && $sum + $p > $edge + $tiers[$tier] + 1e-12) {
                // Граница пройдена: дальше — следующая редкость
                $cache[$tier] = $cache[$tier] ?? 0.0;
                $edge += $tiers[$tier];
                $keys = array_keys($tiers);
                $pos = array_search($tier, $keys, true);
                $tier = $keys[$pos + 1] ?? null;
            }

            if ($tier === null) {
                break;
            }

            $cache[$tier] = $p;
            $sum += $p;
        }

        return $cache;
    }
}
