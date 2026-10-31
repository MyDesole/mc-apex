<?php

namespace App\Domains\Players\Support;

/**
 * Справочник подземелий: залы, мобы, боссы, их особенности, награды и экипировка.
 *
 * Только данные — правила боя в DungeonService.
 *
 * Устройство похода: этаж — это несколько обычных мобов, а закрывает этаж
 * босс. Со следующего этажа мобы заметно сильнее. Отряд идёт, пока не
 * победит всех боссов подземелья, не погибнет или не отступит.
 *
 * У каждого моба и босса свой sprite (пиксель-арт из pixelSprites.js) и
 * свой «особенность» — trait, который меняет правила зала: грызёт снаряжение,
 * отменяет преимущество в скорости, поджигает и так далее. особенность описан
 * в TRAITS, чтобы интерфейс мог честно объяснить, почему зал тяжёлый.
 */
final class Dungeons
{
    /**
     * Подземелья. `floors` — этажей, `per_floor` — обычных мобов на этаже
     * (босс сверху), `min_level` — минимальный уровень каждого питомца.
     */
    public const DUNGEONS = [
        'catacombs' => [
            'name' => 'Крысиные катакомбы',
            'about' => 'Мокрые коридоры под ареной. Крысы, летучие мыши и король, которого они боятся.',
            'emblem' => 'mob-rat',
            'tone' => 'moss',
            'min_level' => 1,
            'floors' => 2,
            'per_floor' => 2,
            'stage' => 1,
        ],
        'sunken' => [
            'name' => 'Затонувшая шахта',
            'about' => 'Затопленные штольни. Утопленники не любят, когда их тревожат.',
            'emblem' => 'mob-drowned',
            'tone' => 'deep',
            'min_level' => 8,
            'floors' => 3,
            'per_floor' => 2,
            'stage' => 2,
        ],
        'forge' => [
            'name' => 'Огненная кузня',
            'about' => 'Кузня под вулканом. Жарко, тесно и очень недобро.',
            'emblem' => 'mob-imp',
            'tone' => 'ember',
            'min_level' => 16,
            'floors' => 3,
            'per_floor' => 2,
            'stage' => 3,
        ],
        'crypt' => [
            'name' => 'Мёрзлый склеп',
            'about' => 'Лёд сковал старые могилы. То, что внутри, тоже замёрзло — но не умерло.',
            'emblem' => 'mob-frostskeleton',
            'tone' => 'frost',
            'min_level' => 24,
            'floors' => 4,
            'per_floor' => 2,
            'stage' => 4,
        ],
        'void' => [
            'name' => 'Пустотный разлом',
            'about' => 'Трещина между мирами. Здесь нет ни дня, ни ночи — только голод.',
            'emblem' => 'mob-voideye',
            'tone' => 'void',
            // Максимальный уровень питомца — 30, выше ставить нельзя
            'min_level' => 28,
            'floors' => 4,
            'per_floor' => 2,
            'stage' => 5,
        ],
    ];

    /**
     * особенности: ключ => название, объяснение для игрока и как это меняет бой.
     *
     * Поля эффекта:
     *   atk        — множитель урона моба
     *   our_dmg    — множитель нашего урона
     *   squad_def  — какая доля нашей защиты работает
     *   hp_mult    — множитель здоровья моба
     *   no_free    — отменяет бесплатные удары от скорости
     *   free_bonus — сколько бесплатных ударов добавить нам
     *   free_penalty — сколько бесплатных ударов у нас отнять
     *   dot        — доля урона, которая перейдёт в следующий зал
     */
    public const TRAITS = [
        'gnaw' => ['name' => 'Грызёт снаряжение', 'text' => 'Ваша защита работает на 15% слабее', 'atk' => 1.0, 'squad_def' => 0.85],
        'screech' => ['name' => 'Визг', 'text' => 'Отменяет бесплатные удары от скорости', 'no_free' => true],
        'sticky' => ['name' => 'Липнет', 'text' => 'Здоровья на 20% больше, зал затягивается', 'hp_mult' => 1.2],
        'venom' => ['name' => 'Яд', 'text' => 'Пробивает 60% вашей защиты', 'squad_def' => 0.4],
        'wall' => ['name' => 'Стена', 'text' => 'Ваш удар слабее на 15%', 'our_dmg' => 0.85],
        'gorge' => ['name' => 'Пожирает', 'text' => 'Лечится на 20% нанесённого урона', 'hp_mult' => 1.2, 'lifesteal' => 0.2],
        'bones' => ['name' => 'Кости', 'text' => 'Бьёт на 10% сильнее и пробивает треть защиты', 'atk' => 1.1, 'squad_def' => 0.7],
        'sparks' => ['name' => 'Искры', 'text' => 'Поджигает: 15% урона перейдёт в следующий зал', 'dot' => 0.15],
        'stone' => ['name' => 'Камень', 'text' => 'Ваш удар слабее на 25%, но вы успеваете на 2 удара больше', 'our_dmg' => 0.75, 'free_bonus' => 2, 'atk' => 0.8],
        'charge' => ['name' => 'Гон', 'text' => 'Бьёт на 25% сильнее', 'atk' => 1.25],
        'smoke' => ['name' => 'Дым', 'text' => 'Съедает один ваш удар и бьёт на 15% сильнее', 'free_penalty' => 1, 'atk' => 1.15],
        'pack' => ['name' => 'Стая', 'text' => 'Призывает крыс: бьёт на 35% сильнее', 'atk' => 1.35],
        'mire' => ['name' => 'Тина', 'text' => 'Ноги вязнут: бесплатных ударов нет', 'no_free' => true],
        'hammer' => ['name' => 'Молот', 'text' => 'Пробивает 60% защиты и бьёт на 10% сильнее', 'squad_def' => 0.4, 'atk' => 1.1],
        'frost' => ['name' => 'Мороз', 'text' => 'Сковывает: вы успеваете на удар меньше', 'free_penalty' => 1, 'atk' => 1.05],
        'curse' => ['name' => 'Проклятие', 'text' => 'Защита слабее на 40%, бьёт на 15% сильнее', 'squad_def' => 0.6, 'atk' => 1.15],
        'void' => ['name' => 'Пустота', 'text' => 'Ваш урон слабее на 20%, здоровья больше на 10%', 'our_dmg' => 0.8, 'hp_mult' => 1.1],
    ];

    /**
     * Обычные мобы: имя, sprite, особенность и характеристики.
     *
     * Каждое подземелье тянет случайного моба из своего списка, поэтому
     * заходы не повторяются.
     */
    public const MOBS = [
        'catacombs' => [
            ['name' => 'Крыса', 'element' => 'beast', 'sprite' => 'mob-rat', 'trait' => 'gnaw', 'hp' => 40, 'atk' => 8, 'def' => 2],
            ['name' => 'Летучая мышь', 'element' => 'shadow', 'sprite' => 'mob-bat', 'trait' => 'screech', 'hp' => 35, 'atk' => 10, 'def' => 1],
            ['name' => 'Пещерный слизень', 'element' => 'nature', 'sprite' => 'mob-slime', 'trait' => 'sticky', 'hp' => 55, 'atk' => 6, 'def' => 5],
            ['name' => 'Паук', 'element' => 'shadow', 'sprite' => 'mob-spider', 'trait' => 'venom', 'hp' => 45, 'atk' => 11, 'def' => 3],
            ['name' => 'Мокрица', 'element' => 'nature', 'sprite' => 'mob-spider', 'trait' => 'wall', 'hp' => 60, 'atk' => 7, 'def' => 7],
            ['name' => 'Слепой червь', 'element' => 'nature', 'sprite' => 'mob-devourer', 'trait' => 'gorge', 'hp' => 70, 'atk' => 7, 'def' => 4],
            ['name' => 'Крысиный щенок', 'element' => 'beast', 'sprite' => 'mob-rat', 'trait' => 'charge', 'hp' => 32, 'atk' => 12, 'def' => 1],
            ['name' => 'Плесневик', 'element' => 'nature', 'sprite' => 'mob-slime', 'trait' => 'sticky', 'hp' => 65, 'atk' => 5, 'def' => 6],
            ['name' => 'Костяная крыса', 'element' => 'shadow', 'sprite' => 'mob-skeleton', 'trait' => 'bones', 'hp' => 50, 'atk' => 12, 'def' => 4],
            ['name' => 'Гнилой кот', 'element' => 'beast', 'sprite' => 'mob-ashhound', 'trait' => 'sparks', 'hp' => 48, 'atk' => 13, 'def' => 3],
            ['name' => 'Пещерный краб', 'element' => 'water', 'sprite' => 'mob-guard', 'trait' => 'wall', 'hp' => 75, 'atk' => 6, 'def' => 9],
            ['name' => 'Теневой паук', 'element' => 'shadow', 'sprite' => 'mob-wraith', 'trait' => 'smoke', 'hp' => 44, 'atk' => 14, 'def' => 2],
        ],
        'sunken' => [
            ['name' => 'Утопленник', 'element' => 'water', 'sprite' => 'mob-drowned', 'trait' => 'mire', 'hp' => 120, 'atk' => 18, 'def' => 8],
            ['name' => 'Стражник шахты', 'element' => 'water', 'sprite' => 'mob-guard', 'trait' => 'wall', 'hp' => 150, 'atk' => 16, 'def' => 12],
            ['name' => 'Слизень-пожиратель', 'element' => 'nature', 'sprite' => 'mob-devourer', 'trait' => 'gorge', 'hp' => 130, 'atk' => 16, 'def' => 10],
            ['name' => 'Скелет-забойщик', 'element' => 'shadow', 'sprite' => 'mob-skeleton', 'trait' => 'bones', 'hp' => 110, 'atk' => 22, 'def' => 6],
            ['name' => 'Русалочий труп', 'element' => 'water', 'sprite' => 'mob-drowned', 'trait' => 'venom', 'hp' => 125, 'atk' => 19, 'def' => 7],
            ['name' => 'Ржавый голем', 'element' => 'nature', 'sprite' => 'mob-golem', 'trait' => 'stone', 'hp' => 190, 'atk' => 14, 'def' => 16],
            ['name' => 'Водяной скорпион', 'element' => 'beast', 'sprite' => 'mob-spider', 'trait' => 'venom', 'hp' => 105, 'atk' => 24, 'def' => 6],
            ['name' => 'Мокрая тварь', 'element' => 'nature', 'sprite' => 'mob-slime', 'trait' => 'sticky', 'hp' => 145, 'atk' => 13, 'def' => 11],
            ['name' => 'Утопший шахтёр', 'element' => 'shadow', 'sprite' => 'mob-skeleton', 'trait' => 'smoke', 'hp' => 118, 'atk' => 21, 'def' => 9],
            ['name' => 'Медуза глубин', 'element' => 'water', 'sprite' => 'mob-wraith', 'trait' => 'screech', 'hp' => 100, 'atk' => 25, 'def' => 5],
            ['name' => 'Затонувший рыцарь', 'element' => 'water', 'sprite' => 'mob-guard', 'trait' => 'hammer', 'hp' => 165, 'atk' => 20, 'def' => 14],
            ['name' => 'Пузырчатка', 'element' => 'nature', 'sprite' => 'mob-devourer', 'trait' => 'gorge', 'hp' => 135, 'atk' => 15, 'def' => 10],
        ],
        'forge' => [
            ['name' => 'Огненный бес', 'element' => 'fire', 'sprite' => 'mob-imp', 'trait' => 'sparks', 'hp' => 260, 'atk' => 34, 'def' => 16],
            ['name' => 'Лавовый голем', 'element' => 'nature', 'sprite' => 'mob-golem', 'trait' => 'stone', 'hp' => 340, 'atk' => 30, 'def' => 24],
            ['name' => 'Пепельный пёс', 'element' => 'beast', 'sprite' => 'mob-ashhound', 'trait' => 'charge', 'hp' => 280, 'atk' => 38, 'def' => 14],
            ['name' => 'Дух горна', 'element' => 'fire', 'sprite' => 'mob-forgespirit', 'trait' => 'smoke', 'hp' => 240, 'atk' => 40, 'def' => 12],
            ['name' => 'Угольный шахтёр', 'element' => 'shadow', 'sprite' => 'mob-skeleton', 'trait' => 'bones', 'hp' => 255, 'atk' => 36, 'def' => 15],
            ['name' => 'Магмовый червь', 'element' => 'fire', 'sprite' => 'mob-devourer', 'trait' => 'gorge', 'hp' => 330, 'atk' => 31, 'def' => 18],
            ['name' => 'Шлаковый зверь', 'element' => 'beast', 'sprite' => 'mob-ashhound', 'trait' => 'sparks', 'hp' => 300, 'atk' => 35, 'def' => 16],
            ['name' => 'Огневик', 'element' => 'fire', 'sprite' => 'mob-forgespirit', 'trait' => 'charge', 'hp' => 235, 'atk' => 42, 'def' => 10],
            ['name' => 'Пепельная гарпия', 'element' => 'shadow', 'sprite' => 'mob-bat', 'trait' => 'screech', 'hp' => 220, 'atk' => 44, 'def' => 9],
            ['name' => 'Раскалённый страж', 'element' => 'nature', 'sprite' => 'mob-guard', 'trait' => 'hammer', 'hp' => 360, 'atk' => 28, 'def' => 26],
            ['name' => 'Кузнечный автоматон', 'element' => 'shadow', 'sprite' => 'mob-golem', 'trait' => 'wall', 'hp' => 380, 'atk' => 27, 'def' => 28],
            ['name' => 'Тлеющий череп', 'element' => 'shadow', 'sprite' => 'mob-skeleton', 'trait' => 'bones', 'hp' => 210, 'atk' => 46, 'def' => 8],
        ],
        'crypt' => [
            ['name' => 'Мёрзлый скелет', 'element' => 'shadow', 'sprite' => 'mob-frostskeleton', 'trait' => 'frost', 'hp' => 460, 'atk' => 54, 'def' => 24],
            ['name' => 'Сосульковый слизень', 'element' => 'water', 'sprite' => 'mob-iceslime', 'trait' => 'sticky', 'hp' => 520, 'atk' => 48, 'def' => 26],
            ['name' => 'Морозный волк', 'element' => 'beast', 'sprite' => 'mob-frostwolf', 'trait' => 'charge', 'hp' => 430, 'atk' => 68, 'def' => 20],
            ['name' => 'Голодный йети', 'element' => 'beast', 'sprite' => 'mob-yeti', 'trait' => 'hammer', 'hp' => 600, 'atk' => 60, 'def' => 30],
            ['name' => 'Стылая ведьма', 'element' => 'shadow', 'sprite' => 'mob-icewitch', 'trait' => 'curse', 'hp' => 440, 'atk' => 66, 'def' => 22],
            ['name' => 'Хладный призрак', 'element' => 'shadow', 'sprite' => 'mob-wraith', 'trait' => 'smoke', 'hp' => 410, 'atk' => 70, 'def' => 18],
            ['name' => 'Ледяная крыса', 'element' => 'beast', 'sprite' => 'mob-rat', 'trait' => 'gnaw', 'hp' => 395, 'atk' => 58, 'def' => 21],
            ['name' => 'Иней-страж', 'element' => 'water', 'sprite' => 'mob-guard', 'trait' => 'wall', 'hp' => 560, 'atk' => 50, 'def' => 34],
            ['name' => 'Мёрзлый паук', 'element' => 'shadow', 'sprite' => 'mob-spider', 'trait' => 'venom', 'hp' => 420, 'atk' => 64, 'def' => 19],
            ['name' => 'Костяной ходок', 'element' => 'shadow', 'sprite' => 'mob-skeleton', 'trait' => 'bones', 'hp' => 450, 'atk' => 62, 'def' => 23],
            ['name' => 'Ледяной голем', 'element' => 'water', 'sprite' => 'mob-golem', 'trait' => 'stone', 'hp' => 590, 'atk' => 46, 'def' => 36],
            ['name' => 'Стылый пёс', 'element' => 'beast', 'sprite' => 'mob-ashhound', 'trait' => 'frost', 'hp' => 435, 'atk' => 65, 'def' => 20],
        ],
        'void' => [
            ['name' => 'Пустотный глаз', 'element' => 'shadow', 'sprite' => 'mob-voideye', 'trait' => 'void', 'hp' => 820, 'atk' => 96, 'def' => 34],
            ['name' => 'Тень-пожиратель', 'element' => 'shadow', 'sprite' => 'mob-shade', 'trait' => 'gorge', 'hp' => 900, 'atk' => 88, 'def' => 38],
            ['name' => 'Тёмный жнец', 'element' => 'shadow', 'sprite' => 'mob-reaper', 'trait' => 'hammer', 'hp' => 780, 'atk' => 108, 'def' => 32],
            ['name' => 'Осколочный голем', 'element' => 'nature', 'sprite' => 'mob-shardgolem', 'trait' => 'stone', 'hp' => 1000, 'atk' => 80, 'def' => 48],
            ['name' => 'Безликий', 'element' => 'shadow', 'sprite' => 'mob-faceless', 'trait' => 'curse', 'hp' => 760, 'atk' => 104, 'def' => 30],
            ['name' => 'Эндер-ползун', 'element' => 'nature', 'sprite' => 'mob-endcrawler', 'trait' => 'venom', 'hp' => 850, 'atk' => 92, 'def' => 36],
            ['name' => 'Шепчущий ужас', 'element' => 'shadow', 'sprite' => 'mob-wraith', 'trait' => 'smoke', 'hp' => 720, 'atk' => 110, 'def' => 28],
            ['name' => 'Разломный бес', 'element' => 'fire', 'sprite' => 'mob-imp', 'trait' => 'sparks', 'hp' => 740, 'atk' => 100, 'def' => 31],
            ['name' => 'Бездонный слизень', 'element' => 'nature', 'sprite' => 'mob-slime', 'trait' => 'sticky', 'hp' => 960, 'atk' => 84, 'def' => 44],
            ['name' => 'Кривой двойник', 'element' => 'water', 'sprite' => 'mob-drowned', 'trait' => 'frost', 'hp' => 800, 'atk' => 98, 'def' => 35],
            ['name' => 'Пожиратель снов', 'element' => 'nature', 'sprite' => 'mob-devourer', 'trait' => 'gorge', 'hp' => 920, 'atk' => 86, 'def' => 40],
            ['name' => 'Искажённый страж', 'element' => 'water', 'sprite' => 'mob-guard', 'trait' => 'wall', 'hp' => 940, 'atk' => 82, 'def' => 46],
        ],
    ];

    /**
     * Боссы по этажам: последний зал каждого этажа — свой босс.
     *
     * Один и тот же вид по подземелью, но каждый следующий — старше и злее;
     * снаружи это видно по силе и по подписи «этаж N».
     */
    public const BOSSES = [
        'catacombs' => [
            ['name' => 'Крысиный король', 'element' => 'beast', 'sprite' => 'boss-ratking', 'trait' => 'pack', 'hp' => 180, 'atk' => 18, 'def' => 8],
            ['name' => 'Король падали', 'element' => 'beast', 'sprite' => 'boss-rotking', 'trait' => 'pack', 'hp' => 240, 'atk' => 24, 'def' => 11],
        ],
        'sunken' => [
            ['name' => 'Утопленник-старейшина', 'element' => 'water', 'sprite' => 'boss-elder', 'trait' => 'mire', 'hp' => 420, 'atk' => 34, 'def' => 18],
            ['name' => 'Хозяин затопленных штолен', 'element' => 'water', 'sprite' => 'boss-deep-lord', 'trait' => 'hammer', 'hp' => 520, 'atk' => 40, 'def' => 22],
            ['name' => 'Утонувший шахтёр-гигант', 'element' => 'water', 'sprite' => 'boss-miner-giant', 'trait' => 'mire', 'hp' => 640, 'atk' => 46, 'def' => 26],
        ],
        'forge' => [
            ['name' => 'Кузнец преисподней', 'element' => 'fire', 'sprite' => 'boss-smith', 'trait' => 'hammer', 'hp' => 780, 'atk' => 56, 'def' => 28],
            ['name' => 'Мастер горна', 'element' => 'fire', 'sprite' => 'boss-forgemaster', 'trait' => 'hammer', 'hp' => 950, 'atk' => 66, 'def' => 33],
            ['name' => 'Владыка лавы', 'element' => 'fire', 'sprite' => 'boss-lavalord', 'trait' => 'hammer', 'hp' => 1150, 'atk' => 78, 'def' => 38],
        ],
        'crypt' => [
            ['name' => 'Стылый король', 'element' => 'shadow', 'sprite' => 'boss-frostking', 'trait' => 'frost', 'hp' => 1600, 'atk' => 110, 'def' => 52],
            ['name' => 'Ледяная королева', 'element' => 'water', 'sprite' => 'boss-icequeen', 'trait' => 'curse', 'hp' => 1800, 'atk' => 120, 'def' => 56],
            ['name' => 'Мёрзлый великан', 'element' => 'beast', 'sprite' => 'boss-frostgiant', 'trait' => 'hammer', 'hp' => 2100, 'atk' => 132, 'def' => 62],
            ['name' => 'Хранитель склепа', 'element' => 'shadow', 'sprite' => 'boss-cryptkeeper', 'trait' => 'void', 'hp' => 2400, 'atk' => 144, 'def' => 68],
        ],
        'void' => [
            ['name' => 'Смотритель разлома', 'element' => 'shadow', 'sprite' => 'boss-voidwarden', 'trait' => 'void', 'hp' => 3000, 'atk' => 170, 'def' => 74],
            ['name' => 'Пожиратель миров', 'element' => 'beast', 'sprite' => 'boss-worldeater', 'trait' => 'pack', 'hp' => 3400, 'atk' => 186, 'def' => 80],
            ['name' => 'Бездна говорящая', 'element' => 'shadow', 'sprite' => 'boss-abyss', 'trait' => 'curse', 'hp' => 3800, 'atk' => 200, 'def' => 86],
            ['name' => 'Тот, кто ждёт', 'element' => 'shadow', 'sprite' => 'boss-waiter', 'trait' => 'void', 'hp' => 4300, 'atk' => 220, 'def' => 94],
        ],
    ];

    /**
     * Экипировка: ключ => слот, характеристики, ступень.
     *
     * `stage` — с какого подземелья начинает попадаться. Характеристики
     * попадают в metadata предмета магазина при сидировании.
     */
    public const GEAR = [
        /*
         * Оружие — атака. На каждой ступени два варианта: «точный» бьёт
         * ровнее, «тяжёлый» — сильнее, но медленнее.
         */
        'gear-fang' => ['slot' => 'weapon', 'name' => 'Крысиный клык', 'sprite' => 'gear-weapon', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 1, 'rarity' => 'common'],
        'gear-boneblade' => ['slot' => 'weapon', 'name' => 'Обглоданная кость', 'sprite' => 'gear-weapon', 'atk' => 3, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 1, 'rarity' => 'common'],
        'gear-pick' => ['slot' => 'weapon', 'name' => 'Шахтёрская кирка', 'sprite' => 'gear-weapon-2', 'atk' => 9, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare'],
        'gear-cutlass' => ['slot' => 'weapon', 'name' => 'Ржавый тесак', 'sprite' => 'gear-weapon-2', 'atk' => 8, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare'],
        'gear-hammer' => ['slot' => 'weapon', 'name' => 'Кузнечный молот', 'sprite' => 'gear-weapon-3', 'atk' => 16, 'def' => 2, 'hp' => 0, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic'],
        'gear-blade' => ['slot' => 'weapon', 'name' => 'Раскалённый клинок', 'sprite' => 'gear-weapon-3', 'atk' => 18, 'def' => 0, 'hp' => 6, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic'],
        'gear-iceblade' => ['slot' => 'weapon', 'name' => 'Ледяной клинок', 'sprite' => 'gear-weapon-4', 'atk' => 30, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 4, 'rarity' => 'epic'],
        'gear-bonehammer' => ['slot' => 'weapon', 'name' => 'Костяной молот', 'sprite' => 'gear-weapon-4', 'atk' => 34, 'def' => 4, 'hp' => 0, 'spd' => -2, 'stage' => 4, 'rarity' => 'epic'],
        'gear-voidblade' => ['slot' => 'weapon', 'name' => 'Клинок бездны', 'sprite' => 'gear-weapon-5', 'atk' => 50, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 5, 'rarity' => 'legendary'],
        'gear-reaper' => ['slot' => 'weapon', 'name' => 'Коса жнеца', 'sprite' => 'gear-weapon-5', 'atk' => 58, 'def' => 0, 'hp' => 40, 'spd' => -3, 'stage' => 5, 'rarity' => 'legendary'],

        // Доспех — защита и здоровье: «лёгкий» даёт скорость, «кованый» — запас
        'gear-hide' => ['slot' => 'armor', 'name' => 'Крысиная шкура', 'sprite' => 'gear-armor', 'atk' => 0, 'def' => 3, 'hp' => 12, 'spd' => 0, 'stage' => 1, 'rarity' => 'common'],
        'gear-rags' => ['slot' => 'armor', 'name' => 'Рваная тряпка', 'sprite' => 'gear-armor', 'atk' => 0, 'def' => 2, 'hp' => 8, 'spd' => 1, 'stage' => 1, 'rarity' => 'common'],
        'gear-chain' => ['slot' => 'armor', 'name' => 'Ржавая кольчуга', 'sprite' => 'gear-armor-2', 'atk' => 0, 'def' => 7, 'hp' => 26, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare'],
        'gear-padded' => ['slot' => 'armor', 'name' => 'Мокрый ватник', 'sprite' => 'gear-armor-2', 'atk' => 0, 'def' => 5, 'hp' => 22, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare'],
        'gear-plate' => ['slot' => 'armor', 'name' => 'Лавовый панцирь', 'sprite' => 'gear-armor-3', 'atk' => 0, 'def' => 13, 'hp' => 46, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic'],
        'gear-ashcloak' => ['slot' => 'armor', 'name' => 'Пепельный плащ', 'sprite' => 'gear-armor-3', 'atk' => 0, 'def' => 9, 'hp' => 34, 'spd' => 1, 'stage' => 3, 'rarity' => 'epic'],
        'gear-frostplate' => ['slot' => 'armor', 'name' => 'Мёрзлый доспех', 'sprite' => 'gear-armor-4', 'atk' => 0, 'def' => 24, 'hp' => 90, 'spd' => -1, 'stage' => 4, 'rarity' => 'epic'],
        'gear-frostcloak' => ['slot' => 'armor', 'name' => 'Плащ инея', 'sprite' => 'gear-armor-4', 'atk' => 0, 'def' => 18, 'hp' => 70, 'spd' => 2, 'stage' => 4, 'rarity' => 'epic'],
        'gear-voidarmor' => ['slot' => 'armor', 'name' => 'Пустотная бронь', 'sprite' => 'gear-armor-5', 'atk' => 0, 'def' => 40, 'hp' => 160, 'spd' => -2, 'stage' => 5, 'rarity' => 'legendary'],
        'gear-shadowcloak' => ['slot' => 'armor', 'name' => 'Плащ тени', 'sprite' => 'gear-armor-5', 'atk' => 0, 'def' => 30, 'hp' => 120, 'spd' => 4, 'stage' => 5, 'rarity' => 'legendary'],

        // Талисман — скорость и всё понемногу: «быстрый» или «живучий»
        'gear-bone' => ['slot' => 'trinket', 'name' => 'Крысиная косточка', 'sprite' => 'gear-trinket', 'atk' => 1, 'def' => 0, 'hp' => 6, 'spd' => 1, 'stage' => 1, 'rarity' => 'common'],
        'gear-candle' => ['slot' => 'trinket', 'name' => 'Свечной огарок', 'sprite' => 'gear-trinket', 'atk' => 0, 'def' => 0, 'hp' => 5, 'spd' => 2, 'stage' => 1, 'rarity' => 'common'],
        'gear-pearl' => ['slot' => 'trinket', 'name' => 'Жемчужина глубин', 'sprite' => 'gear-trinket-2', 'atk' => 2, 'def' => 2, 'hp' => 14, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare'],
        'gear-compass' => ['slot' => 'trinket', 'name' => 'Медный компас', 'sprite' => 'gear-trinket-2', 'atk' => 0, 'def' => 0, 'hp' => 8, 'spd' => 3, 'stage' => 2, 'rarity' => 'rare'],
        'gear-ember' => ['slot' => 'trinket', 'name' => 'Тлеющий уголь', 'sprite' => 'gear-trinket-3', 'atk' => 4, 'def' => 3, 'hp' => 22, 'spd' => 3, 'stage' => 3, 'rarity' => 'epic'],
        'gear-fireeye' => ['slot' => 'trinket', 'name' => 'Огненный глаз', 'sprite' => 'gear-trinket-3', 'atk' => 6, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic'],
        'gear-iceshard' => ['slot' => 'trinket', 'name' => 'Осколок льда', 'sprite' => 'gear-trinket-4', 'atk' => 8, 'def' => 6, 'hp' => 44, 'spd' => 5, 'stage' => 4, 'rarity' => 'legendary'],
        'gear-stormeye' => ['slot' => 'trinket', 'name' => 'Глаз вьюги', 'sprite' => 'gear-trinket-4', 'atk' => 10, 'def' => 0, 'hp' => 20, 'spd' => 6, 'stage' => 4, 'rarity' => 'legendary'],
        'gear-voideye' => ['slot' => 'trinket', 'name' => 'Око пустоты', 'sprite' => 'gear-trinket-5', 'atk' => 14, 'def' => 10, 'hp' => 80, 'spd' => 8, 'stage' => 5, 'rarity' => 'legendary'],
        'gear-shard' => ['slot' => 'trinket', 'name' => 'Кристалл разлома', 'sprite' => 'gear-trinket-5', 'atk' => 18, 'def' => 0, 'hp' => 50, 'spd' => 10, 'stage' => 5, 'rarity' => 'legendary'],
    ];

    /**
     * Редкость выпавшего предмета: множитель характеристик и подпись.
     *
     * В GEAR лежат базовые (обычные) значения, а редкость их растягивает —
     * поэтому один и тот же «Кузнечный молот» может выпасть обычным и
     * легендарным.
     */
    public const RARITY_MULT = [
        'common' => 1.0,
        'rare' => 1.35,
        'epic' => 1.8,
        'legendary' => 2.4,
    ];

    public const RARITY_NAMES = [
        'common' => 'Обычный',
        'rare' => 'Редкий',
        'epic' => 'Эпический',
        'legendary' => 'Легендарный',
    ];

    /** Шансы редкости в процентах по ступени подземелья. */
    public const DROP_RARITY = [
        1 => ['common' => 70, 'rare' => 25, 'epic' => 5],
        2 => ['common' => 45, 'rare' => 40, 'epic' => 15],
        3 => ['common' => 25, 'rare' => 45, 'epic' => 25, 'legendary' => 5],
        4 => ['common' => 15, 'rare' => 40, 'epic' => 35, 'legendary' => 10],
        5 => ['common' => 10, 'rare' => 30, 'epic' => 40, 'legendary' => 20],
    ];

    /** Слоты экипировки: на питомца надевается по одному предмету в каждый. */
    public const SLOTS = ['weapon', 'armor', 'trinket'];

    /** Подписи слотов и их значки — для интерфейса. */
    public const SLOT_NAMES = ['weapon' => 'Оружие', 'armor' => 'Доспех', 'trinket' => 'Талисман'];

    /** Сколько питомцев можно взять в отряд. */
    public const SQUAD_MIN = 2;
    public const SQUAD_MAX = 3;

    /** Сколько походов в день даётся бесплатно. */
    public const DAILY_RUNS = 3;

    /**
     * Докупка похода за ApexCoin: первый стоит BASE, каждый следующий
     * за день — на STEP дороже. Цена растёт, поэтому «докупать до
     * бесконечности» невыгодно, но можно.
     */
    public const EXTRA_RUN_BASE = 800;
    public const EXTRA_RUN_STEP = 400;

    /** Отдых между этажами: доля от максимума здоровья отряда. */
    public const REST_SHARE = 0.15;

    /** Монеты за моба: база × этаж. */
    public const COINS_PER_MOB = [1 => 60, 2 => 140, 3 => 260, 4 => 420, 5 => 640];

    /** Монеты за босса: база × номер босса. */
    public const COINS_PER_BOSS = [1 => 400, 2 => 900, 3 => 1600, 4 => 2600, 5 => 4000];

    /**
     * Штраф за поражение по ступени. Отступление бесплатно — за него
     * ничего не списываем, теряется только попытка.
     */
    public const WIPE_PENALTY = [1 => 80, 2 => 200, 3 => 450, 4 => 800, 5 => 1400];

    /** Опыт питомцам: за моба и за босса. */
    public const XP_PER_MOB = 4;
    public const XP_PER_BOSS = 20;

    /**
     * Шансы добычи в процентах. `gear` — шанс предмета экипировки,
     * `boss_gear` — шанс с босса (босс даёт всегда), `key` — ключ кейса.
     */
    public const DROPS = [
        1 => ['gear' => 14, 'key' => 4, 'key_slug' => 'seeker', 'key_pet' => 0.5, 'key_pet_slug' => 'pets'],
        2 => ['gear' => 22, 'key' => 12, 'key_slug' => 'seeker', 'key_pet' => 2, 'key_pet_slug' => 'pets'],
        3 => ['gear' => 30, 'key' => 25, 'key_slug' => 'seeker', 'key_pet' => 5, 'key_pet_slug' => 'pets'],
        4 => ['gear' => 38, 'key' => 35, 'key_slug' => 'seeker', 'key_pet' => 8, 'key_pet_slug' => 'pets'],
        5 => ['gear' => 46, 'key' => 45, 'key_slug' => 'seeker', 'key_pet' => 12, 'key_pet_slug' => 'pets'],
    ];

    /** Босс чаще даёт лучшую вещь: шанс в процентах на предмет своей ступени. */
    public const BOSS_UPGRADE_CHANCE = 25;

    /** Подземелье по ключу. */
    /** Штраф за поражение в этом подземелье. */
    public static function wipePenalty(string $key): int
    {
        $dungeon = self::dungeon($key) ?? self::DUNGEONS['catacombs'];

        return self::WIPE_PENALTY[$dungeon['stage']] ?? 0;
    }

    public static function dungeon(string $key): ?array
    {
        return self::DUNGEONS[$key] ?? null;
    }

    /** Все подземелья со служебным ключом. */
    public static function all(): array
    {
        $result = [];

        foreach (self::DUNGEONS as $key => $dungeon) {
            $result[] = [
                'key' => $key,
                ...$dungeon,
                'rooms' => self::rooms($dungeon),
                // Какие стихии встречаются: по ним интерфейс подбирает связку
                'elements' => self::elements($key),
                // Кто ждёт в конце этажей — для карточки подземелья
                'bosses' => array_map(
                    fn (array $boss) => ['name' => $boss['name'], 'sprite' => $boss['sprite']],
                    self::BOSSES[$key] ?? [],
                ),
            ];
        }

        return $result;
    }

    /**
     * Стихии, которые встречаются в подземелье.
     *
     * Нужно интерфейсу: кнопка «Подобрать отряд» ищет связку, которая
     * контрит одну из этих стихий.
     *
     * @return array<int, string>
     */
    public static function elements(string $key): array
    {
        $elements = [];

        foreach (self::MOBS[$key] ?? [] as $mob) {
            $elements[$mob['element'] ?? 'beast'] = true;
        }

        foreach (self::BOSSES[$key] ?? [] as $boss) {
            $elements[$boss['element'] ?? 'beast'] = true;
        }

        return array_keys($elements);
    }

    /** Сколько всего залов в подземелье: мобы на всех этажах плюс боссы. */
    public static function rooms(array $dungeon): int
    {
        return (int) $dungeon['floors'] * ((int) $dungeon['per_floor'] + 1);
    }

    /** Является ли зал боссом: последний зал каждого этажа. */
    public static function isBossRoom(array $dungeon, int $floor, int $encounter): bool
    {
        return $encounter === (int) $dungeon['per_floor'];
    }

    /** Случайный обычный моб подземелья. */
    public static function mob(string $dungeon): array
    {
        $mobs = self::MOBS[$dungeon] ?? self::MOBS['catacombs'];

        return $mobs[array_rand($mobs)];
    }

    /** Босс указанного этажа. */
    public static function boss(string $dungeon, int $floor): array
    {
        $bosses = self::BOSSES[$dungeon] ?? self::BOSSES['catacombs'];
        $index = max(0, min(count($bosses) - 1, $floor - 1));

        return $bosses[$index];
    }

    /** Описание особенности по ключу. */
    public static function trait(string $key): array
    {
        $trait = self::TRAITS[$key] ?? ['name' => 'Без особенностей', 'text' => ''] + [];

        return $trait;
    }

    /** Экипировка, доступная в подземелье указанной ступени. */
    /**
     * Что может выпасть на этой ступени: предметы своей ступени и одной
     * предыдущей. Иначе в разломе снова падал бы «Крысиный клык».
     */
    public static function gearForStage(int $stage): array
    {
        $from = max(1, $stage - 1);

        return array_filter(
            self::GEAR,
            fn (array $gear) => $gear['stage'] >= $from && $gear['stage'] <= $stage,
        );
    }

    /**
     * Бросить редкость для выпавшего предмета.
     *
     * С босса бросаем дважды и берём лучший результат: босс должен
     * чувствоваться как босс.
     */
    public static function rollRarity(int $stage, bool $boss = false): string
    {
        $best = 'common';

        $tries = $boss ? 2 : 1;

        for ($i = 0; $i < $tries; $i++) {
            $rolled = self::rollRarityOnce($stage);

            if (array_search($rolled, array_keys(self::RARITY_MULT), true) > array_search($best, array_keys(self::RARITY_MULT), true)) {
                $best = $rolled;
            }
        }

        return $best;
    }

    private static function rollRarityOnce(int $stage): string
    {
        $weights = self::DROP_RARITY[$stage] ?? self::DROP_RARITY[1];
        $roll = random_int(1, array_sum($weights));

        foreach ($weights as $rarity => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $rarity;
            }
        }

        return 'common';
    }

    /**
     * Характеристики предмета с учётом редкости.
     *
     * Скорость может быть отрицательной («Лавовый панцирь»), поэтому её
     * множим как есть и округляем к нулю: −1 так и остаётся −1.
     *
     * @return array{atk: int, def: int, hp: int, spd: int}
     */
    public static function scaledStats(array $gear, string $rarity): array
    {
        $mult = self::RARITY_MULT[$rarity] ?? 1.0;

        return [
            'atk' => (int) round(($gear['atk'] ?? 0) * $mult),
            'def' => (int) round(($gear['def'] ?? 0) * $mult),
            'hp' => (int) round(($gear['hp'] ?? 0) * $mult),
            'spd' => (int) round(($gear['spd'] ?? 0) * $mult),
        ];
    }

    public static function rarityName(string $rarity): string
    {
        return self::RARITY_NAMES[$rarity] ?? $rarity;
    }

    /** Случайный предмет экипировки для подземелья. */
    public static function rollGear(int $stage, bool $boss = false): string
    {
        $pool = self::gearForStage($stage);

        if ($boss && random_int(1, 100) <= self::BOSS_UPGRADE_CHANCE) {
            $best = array_filter($pool, fn (array $g) => $g['stage'] === $stage);

            if ($best) {
                $pool = $best;
            }
        }

        $keys = array_keys($pool);

        return $keys[array_rand($keys)];
    }

    public static function slotName(string $slot): string
    {
        return self::SLOT_NAMES[$slot] ?? $slot;
    }
}
