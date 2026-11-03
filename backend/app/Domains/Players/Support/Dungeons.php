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
            'min_level' => 28,
            'floors' => 4,
            'per_floor' => 2,
            'stage' => 5,
        ],
        // Подземелья для уровней выше 30: снаряжение берётся из пустотных ступеней,
        // зато растут редкость добычи, Рубли и сила врагов
        'abyss' => [
            'name' => 'Бездонные чертоги',
            'about' => 'Лестница вниз, которая не заканчивается. Чем глубже, тем тише — и тем хуже.',
            'emblem' => 'mob-voideye',
            'tone' => 'deep',
            'min_level' => 40,
            'floors' => 5,
            'per_floor' => 2,
            'stage' => 6,
        ],
        'lair' => [
            'name' => 'Логово дракона',
            'about' => 'Гора золота, в которой кто-то дышит. Огонь здесь не страшилка, а климат.',
            'emblem' => 'mob-imp',
            'tone' => 'ember',
            'min_level' => 55,
            'floors' => 5,
            'per_floor' => 2,
            'stage' => 7,
        ],
        'throne' => [
            'name' => 'Чертог владыки',
            'about' => 'Последний зал под самым дном мира. Трон занят, и хозяин не любит гостей.',
            'emblem' => 'mob-reaper',
            'tone' => 'void',
            'min_level' => 75,
            'floors' => 6,
            'per_floor' => 2,
            'stage' => 8,
        ],
        // Подземелья с допуском по редкости: слабого питомца не пустят, сколько бы он ни стоял
        'crystal' => [
            'name' => 'Хрустальные залы',
            'about' => 'Залы из живого хрусталя. Входят только эпические питомцы и выше: простым здесь делать нечего.',
            'emblem' => 'mob-abyss-5',
            'tone' => 'frost',
            'min_level' => 45,
            'min_rarity' => 'epic',
            'floors' => 3,
            'per_floor' => 2,
            'stage' => 7,
        ],
        'pantheon' => [
            'name' => 'Пантеон забытых',
            'about' => 'Храм богов, о которых никто не помнит. Пропускает только легендарных питомцев и мифических.',
            'emblem' => 'mob-throne-11',
            'tone' => 'ember',
            'min_level' => 70,
            'min_rarity' => 'legendary',
            'floors' => 4,
            'per_floor' => 2,
            'stage' => 8,
        ],
        // Цепочка допуска: Испытание → пропуск → Рублёвые копи
        'trial' => [
            'name' => 'Испытание Владыки',
            'about' => 'Предданж для питомцев 100 уровня: проверка экипировки и умения. Зачистка даёт пропуск в Рублёвые копи.',
            'emblem' => 'mob-throne-1',
            'tone' => 'void',
            'min_level' => 100,
            'min_rarity' => 'epic',
            'floors' => 4,
            'per_floor' => 2,
            'stage' => 9,
            'grants' => 'ruble_pass',
        ],
        'rubledeep' => [
            'name' => 'Рублёвые копи',
            'about' => 'Здесь вместо ApexCoin падают Рубли. Вход по пропуску, который выдаёт Испытание Владыки; один пропуск — один поход.',
            'emblem' => 'mob-lair-9',
            'tone' => 'ember',
            'min_level' => 100,
            'min_rarity' => 'epic',
            'floors' => 3,
            'per_floor' => 2,
            'stage' => 9,
            'requires' => 'ruble_pass',
            'currency' => 'rubles',
        ],
    ];

    /** Редкости по возрастанию: для допуска в подземелья. */
    public const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary', 'mythic'];

    /** Рублёвые копи: Рубли за моба и за босса, умножаются на номер этажа. */
    public const RUBLE_MOB = 1;
    public const RUBLE_BOSS = 6;

    public static function rarityRank(?string $rarity): int
    {
        $index = array_search($rarity ?? 'common', self::RARITY_ORDER, true);

        return $index === false ? 0 : (int) $index;
    }

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
        'abyss' => [
            ['name' => 'Глубинный сторож', 'element' => 'water', 'sprite' => 'mob-abyss-1', 'trait' => 'wall', 'hp' => 1500, 'atk' => 126, 'def' => 66],
            ['name' => 'Тихий жнец', 'element' => 'shadow', 'sprite' => 'mob-abyss-2', 'trait' => 'hammer', 'hp' => 1250, 'atk' => 156, 'def' => 46],
            ['name' => 'Ползущая тьма', 'element' => 'shadow', 'sprite' => 'mob-abyss-3', 'trait' => 'gorge', 'hp' => 1400, 'atk' => 134, 'def' => 54],
            ['name' => 'Эхо-двойник', 'element' => 'water', 'sprite' => 'mob-abyss-4', 'trait' => 'frost', 'hp' => 1300, 'atk' => 142, 'def' => 50],
            ['name' => 'Кристальный паразит', 'element' => 'nature', 'sprite' => 'mob-abyss-5', 'trait' => 'stone', 'hp' => 1600, 'atk' => 118, 'def' => 70],
            ['name' => 'Глазастый рой', 'element' => 'shadow', 'sprite' => 'mob-abyss-6', 'trait' => 'void', 'hp' => 1180, 'atk' => 160, 'def' => 44],
            ['name' => 'Бесформенный', 'element' => 'shadow', 'sprite' => 'mob-abyss-7', 'trait' => 'curse', 'hp' => 1280, 'atk' => 150, 'def' => 48],
            ['name' => 'Костяной пожиратель', 'element' => 'beast', 'sprite' => 'mob-abyss-8', 'trait' => 'gorge', 'hp' => 1450, 'atk' => 130, 'def' => 58],
            ['name' => 'Слепой стражник', 'element' => 'shadow', 'sprite' => 'mob-abyss-9', 'trait' => 'bones', 'hp' => 1320, 'atk' => 144, 'def' => 52],
            ['name' => 'Глубинная пиявка', 'element' => 'water', 'sprite' => 'mob-abyss-10', 'trait' => 'sticky', 'hp' => 1560, 'atk' => 122, 'def' => 62],
            ['name' => 'Эхо-крыло', 'element' => 'shadow', 'sprite' => 'mob-abyss-11', 'trait' => 'screech', 'hp' => 1150, 'atk' => 164, 'def' => 42],
            ['name' => 'Тёмный скат', 'element' => 'water', 'sprite' => 'mob-abyss-12', 'trait' => 'smoke', 'hp' => 1260, 'atk' => 148, 'def' => 49],
        ],
        'lair' => [
            ['name' => 'Драконий выводок', 'element' => 'fire', 'sprite' => 'mob-lair-1', 'trait' => 'sparks', 'hp' => 1900, 'atk' => 190, 'def' => 80],
            ['name' => 'Угольный страж сокровищ', 'element' => 'fire', 'sprite' => 'mob-lair-2', 'trait' => 'wall', 'hp' => 2300, 'atk' => 160, 'def' => 104],
            ['name' => 'Пепельный змей', 'element' => 'fire', 'sprite' => 'mob-lair-3', 'trait' => 'gorge', 'hp' => 2050, 'atk' => 176, 'def' => 90],
            ['name' => 'Золотой голем', 'element' => 'nature', 'sprite' => 'mob-lair-4', 'trait' => 'stone', 'hp' => 2400, 'atk' => 154, 'def' => 110],
            ['name' => 'Огненная гарпия', 'element' => 'fire', 'sprite' => 'mob-lair-5', 'trait' => 'screech', 'hp' => 1700, 'atk' => 208, 'def' => 70],
            ['name' => 'Кочегар логова', 'element' => 'shadow', 'sprite' => 'mob-lair-6', 'trait' => 'bones', 'hp' => 1850, 'atk' => 196, 'def' => 78],
            ['name' => 'Лавовый пёс', 'element' => 'beast', 'sprite' => 'mob-lair-7', 'trait' => 'charge', 'hp' => 1800, 'atk' => 204, 'def' => 74],
            ['name' => 'Жрец пламени', 'element' => 'fire', 'sprite' => 'mob-lair-8', 'trait' => 'smoke', 'hp' => 1750, 'atk' => 212, 'def' => 72],
            ['name' => 'Золотая крыса', 'element' => 'beast', 'sprite' => 'mob-lair-9', 'trait' => 'gnaw', 'hp' => 1650, 'atk' => 200, 'def' => 76],
            ['name' => 'Драконий культист', 'element' => 'shadow', 'sprite' => 'mob-lair-10', 'trait' => 'curse', 'hp' => 1800, 'atk' => 192, 'def' => 80],
            ['name' => 'Лавовый слизень', 'element' => 'fire', 'sprite' => 'mob-lair-11', 'trait' => 'sticky', 'hp' => 2200, 'atk' => 164, 'def' => 98],
            ['name' => 'Чешуйчатый воин', 'element' => 'nature', 'sprite' => 'mob-lair-12', 'trait' => 'hammer', 'hp' => 2100, 'atk' => 180, 'def' => 92],
        ],
        'throne' => [
            ['name' => 'Страж трона', 'element' => 'shadow', 'sprite' => 'mob-throne-1', 'trait' => 'wall', 'hp' => 3300, 'atk' => 250, 'def' => 140],
            ['name' => 'Палач владыки', 'element' => 'shadow', 'sprite' => 'mob-throne-2', 'trait' => 'hammer', 'hp' => 2800, 'atk' => 320, 'def' => 104],
            ['name' => 'Придворный шёпот', 'element' => 'shadow', 'sprite' => 'mob-throne-3', 'trait' => 'curse', 'hp' => 2700, 'atk' => 330, 'def' => 98],
            ['name' => 'Безликий советник', 'element' => 'shadow', 'sprite' => 'mob-throne-4', 'trait' => 'void', 'hp' => 3000, 'atk' => 290, 'def' => 116],
            ['name' => 'Осколочный рыцарь', 'element' => 'nature', 'sprite' => 'mob-throne-5', 'trait' => 'stone', 'hp' => 3500, 'atk' => 240, 'def' => 150],
            ['name' => 'Тень-пожиратель', 'element' => 'shadow', 'sprite' => 'mob-throne-6', 'trait' => 'gorge', 'hp' => 3100, 'atk' => 280, 'def' => 120],
            ['name' => 'Ледяной палач', 'element' => 'water', 'sprite' => 'mob-throne-7', 'trait' => 'frost', 'hp' => 2900, 'atk' => 300, 'def' => 110],
            ['name' => 'Бес-казначей', 'element' => 'fire', 'sprite' => 'mob-throne-8', 'trait' => 'sparks', 'hp' => 2750, 'atk' => 318, 'def' => 100],
            ['name' => 'Тронный пёс', 'element' => 'beast', 'sprite' => 'mob-throne-9', 'trait' => 'charge', 'hp' => 2850, 'atk' => 306, 'def' => 108],
            ['name' => 'Мёртвый хранитель', 'element' => 'shadow', 'sprite' => 'mob-throne-10', 'trait' => 'bones', 'hp' => 3200, 'atk' => 270, 'def' => 128],
            ['name' => 'Эхо владыки', 'element' => 'shadow', 'sprite' => 'mob-throne-11', 'trait' => 'void', 'hp' => 2650, 'atk' => 336, 'def' => 96],
            ['name' => 'Осколок трона', 'element' => 'nature', 'sprite' => 'mob-throne-12', 'trait' => 'wall', 'hp' => 3600, 'atk' => 236, 'def' => 156],
        ],
        'crystal' => [
            ['name' => 'Хрустальный страж', 'element' => 'water', 'sprite' => 'mob-abyss-5', 'trait' => 'stone', 'hp' => 1900, 'atk' => 172, 'def' => 88],
            ['name' => 'Осколочный паук', 'element' => 'shadow', 'sprite' => 'mob-abyss-6', 'trait' => 'venom', 'hp' => 1700, 'atk' => 196, 'def' => 76],
            ['name' => 'Ледяная гарпия', 'element' => 'water', 'sprite' => 'mob-abyss-11', 'trait' => 'screech', 'hp' => 1650, 'atk' => 204, 'def' => 72],
            ['name' => 'Призма-страж', 'element' => 'nature', 'sprite' => 'mob-throne-12', 'trait' => 'wall', 'hp' => 2300, 'atk' => 160, 'def' => 108],
            ['name' => 'Звенящий скат', 'element' => 'water', 'sprite' => 'mob-abyss-12', 'trait' => 'frost', 'hp' => 1800, 'atk' => 188, 'def' => 82],
            ['name' => 'Кристальный пёс', 'element' => 'beast', 'sprite' => 'mob-throne-9', 'trait' => 'charge', 'hp' => 1950, 'atk' => 192, 'def' => 84],
            ['name' => 'Зеркальный двойник', 'element' => 'shadow', 'sprite' => 'mob-abyss-4', 'trait' => 'curse', 'hp' => 2000, 'atk' => 180, 'def' => 90],
            ['name' => 'Грань-жнец', 'element' => 'shadow', 'sprite' => 'mob-abyss-2', 'trait' => 'hammer', 'hp' => 1750, 'atk' => 210, 'def' => 74],
            ['name' => 'Светящийся слизень', 'element' => 'nature', 'sprite' => 'mob-lair-11', 'trait' => 'sticky', 'hp' => 2400, 'atk' => 156, 'def' => 104],
            ['name' => 'Хрустальная тень', 'element' => 'shadow', 'sprite' => 'mob-abyss-3', 'trait' => 'gorge', 'hp' => 1850, 'atk' => 186, 'def' => 80],
            ['name' => 'Стеклянный рыцарь', 'element' => 'water', 'sprite' => 'mob-throne-5', 'trait' => 'bones', 'hp' => 2100, 'atk' => 176, 'def' => 96],
            ['name' => 'Разбитый глаз', 'element' => 'shadow', 'sprite' => 'mob-abyss-6', 'trait' => 'void', 'hp' => 1780, 'atk' => 200, 'def' => 78],
        ],
        'pantheon' => [
            ['name' => 'Жрец забытого бога', 'element' => 'light', 'sprite' => 'mob-lair-8', 'trait' => 'smoke', 'hp' => 2900, 'atk' => 290, 'def' => 118],
            ['name' => 'Каменный идол', 'element' => 'nature', 'sprite' => 'mob-throne-12', 'trait' => 'stone', 'hp' => 3600, 'atk' => 250, 'def' => 150],
            ['name' => 'Золотая жрица', 'element' => 'light', 'sprite' => 'mob-lair-5', 'trait' => 'screech', 'hp' => 2800, 'atk' => 310, 'def' => 112],
            ['name' => 'Страж алтаря', 'element' => 'shadow', 'sprite' => 'mob-throne-1', 'trait' => 'wall', 'hp' => 3400, 'atk' => 262, 'def' => 140],
            ['name' => 'Призрачный певчий', 'element' => 'shadow', 'sprite' => 'mob-throne-3', 'trait' => 'curse', 'hp' => 2700, 'atk' => 320, 'def' => 108],
            ['name' => 'Бронзовый бык', 'element' => 'beast', 'sprite' => 'mob-lair-7', 'trait' => 'charge', 'hp' => 3300, 'atk' => 280, 'def' => 128],
            ['name' => 'Оракул без глаз', 'element' => 'shadow', 'sprite' => 'mob-throne-4', 'trait' => 'void', 'hp' => 3000, 'atk' => 300, 'def' => 120],
            ['name' => 'Пепельный жрец', 'element' => 'fire', 'sprite' => 'mob-lair-6', 'trait' => 'sparks', 'hp' => 3100, 'atk' => 290, 'def' => 124],
            ['name' => 'Мраморный лев', 'element' => 'beast', 'sprite' => 'mob-lair-3', 'trait' => 'pack', 'hp' => 3350, 'atk' => 270, 'def' => 134],
            ['name' => 'Тень бога', 'element' => 'shadow', 'sprite' => 'mob-throne-6', 'trait' => 'gorge', 'hp' => 3150, 'atk' => 296, 'def' => 122],
            ['name' => 'Храмовый палач', 'element' => 'shadow', 'sprite' => 'mob-throne-2', 'trait' => 'hammer', 'hp' => 2950, 'atk' => 318, 'def' => 116],
            ['name' => 'Ожившая статуя', 'element' => 'nature', 'sprite' => 'mob-lair-4', 'trait' => 'stone', 'hp' => 3500, 'atk' => 258, 'def' => 146],
        ],
        'trial' => [
            ['name' => 'Чёрный страж', 'element' => 'shadow', 'sprite' => 'mob-throne-1', 'trait' => 'wall', 'hp' => 5200, 'atk' => 400, 'def' => 210],
            ['name' => 'Клинок Владыки', 'element' => 'shadow', 'sprite' => 'mob-throne-2', 'trait' => 'hammer', 'hp' => 4400, 'atk' => 450, 'def' => 176],
            ['name' => 'Шёпот испытания', 'element' => 'shadow', 'sprite' => 'mob-throne-3', 'trait' => 'curse', 'hp' => 4300, 'atk' => 440, 'def' => 170],
            ['name' => 'Безликий судья', 'element' => 'shadow', 'sprite' => 'mob-throne-4', 'trait' => 'void', 'hp' => 4700, 'atk' => 420, 'def' => 190],
            ['name' => 'Осколочный титан', 'element' => 'nature', 'sprite' => 'mob-throne-5', 'trait' => 'stone', 'hp' => 5600, 'atk' => 380, 'def' => 224],
            ['name' => 'Пожиратель надежд', 'element' => 'shadow', 'sprite' => 'mob-throne-6', 'trait' => 'gorge', 'hp' => 4900, 'atk' => 430, 'def' => 184],
            ['name' => 'Ледяной экзекутор', 'element' => 'water', 'sprite' => 'mob-throne-7', 'trait' => 'frost', 'hp' => 4600, 'atk' => 436, 'def' => 180],
            ['name' => 'Казначей испытаний', 'element' => 'fire', 'sprite' => 'mob-throne-8', 'trait' => 'sparks', 'hp' => 4500, 'atk' => 446, 'def' => 174],
            ['name' => 'Цепной пёс', 'element' => 'beast', 'sprite' => 'mob-throne-9', 'trait' => 'charge', 'hp' => 4650, 'atk' => 440, 'def' => 178],
            ['name' => 'Мёртвый архивариус', 'element' => 'shadow', 'sprite' => 'mob-throne-10', 'trait' => 'bones', 'hp' => 5100, 'atk' => 410, 'def' => 200],
            ['name' => 'Эхо Владыки', 'element' => 'shadow', 'sprite' => 'mob-throne-11', 'trait' => 'void', 'hp' => 4800, 'atk' => 448, 'def' => 182],
            ['name' => 'Каменная плита', 'element' => 'nature', 'sprite' => 'mob-throne-12', 'trait' => 'wall', 'hp' => 5800, 'atk' => 372, 'def' => 230],
        ],
        'rubledeep' => [
            ['name' => 'Золотая гончая', 'element' => 'beast', 'sprite' => 'mob-lair-7', 'trait' => 'charge', 'hp' => 5000, 'atk' => 430, 'def' => 196],
            ['name' => 'Монетный жук', 'element' => 'nature', 'sprite' => 'mob-lair-9', 'trait' => 'gnaw', 'hp' => 4700, 'atk' => 450, 'def' => 184],
            ['name' => 'Страж сейфа', 'element' => 'shadow', 'sprite' => 'mob-lair-2', 'trait' => 'wall', 'hp' => 5900, 'atk' => 400, 'def' => 228],
            ['name' => 'Жрец прибыли', 'element' => 'fire', 'sprite' => 'mob-lair-8', 'trait' => 'smoke', 'hp' => 4800, 'atk' => 462, 'def' => 186],
            ['name' => 'Слиток-голем', 'element' => 'nature', 'sprite' => 'mob-lair-4', 'trait' => 'stone', 'hp' => 6000, 'atk' => 392, 'def' => 232],
            ['name' => 'Банковский паук', 'element' => 'shadow', 'sprite' => 'mob-lair-3', 'trait' => 'venom', 'hp' => 4850, 'atk' => 458, 'def' => 188],
            ['name' => 'Налоговый призрак', 'element' => 'shadow', 'sprite' => 'mob-throne-3', 'trait' => 'curse', 'hp' => 4600, 'atk' => 470, 'def' => 180],
            ['name' => 'Кассир-бес', 'element' => 'fire', 'sprite' => 'mob-throne-8', 'trait' => 'sparks', 'hp' => 4750, 'atk' => 466, 'def' => 184],
            ['name' => 'Хранитель ключей', 'element' => 'shadow', 'sprite' => 'mob-throne-10', 'trait' => 'bones', 'hp' => 5300, 'atk' => 420, 'def' => 208],
            ['name' => 'Золотой слизень', 'element' => 'fire', 'sprite' => 'mob-lair-11', 'trait' => 'sticky', 'hp' => 5700, 'atk' => 405, 'def' => 222],
            ['name' => 'Драконий счетовод', 'element' => 'fire', 'sprite' => 'mob-lair-10', 'trait' => 'curse', 'hp' => 4900, 'atk' => 456, 'def' => 190],
            ['name' => 'Чешуйчатый охранник', 'element' => 'nature', 'sprite' => 'mob-lair-12', 'trait' => 'hammer', 'hp' => 5500, 'atk' => 412, 'def' => 216],
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
        'abyss' => [
            ['name' => 'Привратник бездны', 'element' => 'shadow', 'sprite' => 'boss-gatekeeper', 'trait' => 'void', 'hp' => 5200, 'atk' => 250, 'def' => 104],
            ['name' => 'Глотатель света', 'element' => 'shadow', 'sprite' => 'boss-lightdevourer', 'trait' => 'curse', 'hp' => 5800, 'atk' => 268, 'def' => 112],
            ['name' => 'Хозяин тишины', 'element' => 'water', 'sprite' => 'boss-silence', 'trait' => 'mire', 'hp' => 6400, 'atk' => 284, 'def' => 120],
            ['name' => 'Костяной левиафан', 'element' => 'beast', 'sprite' => 'boss-leviathan', 'trait' => 'pack', 'hp' => 7000, 'atk' => 300, 'def' => 128],
            ['name' => 'Дно', 'element' => 'shadow', 'sprite' => 'boss-bottom', 'trait' => 'void', 'hp' => 7800, 'atk' => 330, 'def' => 138],
        ],
        'lair' => [
            ['name' => 'Дракончик-страж', 'element' => 'fire', 'sprite' => 'boss-wyrmling', 'trait' => 'hammer', 'hp' => 8400, 'atk' => 340, 'def' => 140],
            ['name' => 'Хранитель золота', 'element' => 'nature', 'sprite' => 'boss-goldkeeper', 'trait' => 'pack', 'hp' => 9200, 'atk' => 360, 'def' => 150],
            ['name' => 'Огненная мать', 'element' => 'fire', 'sprite' => 'boss-firemother', 'trait' => 'hammer', 'hp' => 10000, 'atk' => 384, 'def' => 160],
            ['name' => 'Лавовый змей', 'element' => 'fire', 'sprite' => 'boss-lavaserpent', 'trait' => 'curse', 'hp' => 11000, 'atk' => 410, 'def' => 170],
            ['name' => 'Древний дракон', 'element' => 'fire', 'sprite' => 'boss-elderdragon', 'trait' => 'hammer', 'hp' => 12400, 'atk' => 450, 'def' => 184],
        ],
        'throne' => [
            ['name' => 'Первый рыцарь', 'element' => 'shadow', 'sprite' => 'boss-firstknight', 'trait' => 'frost', 'hp' => 13500, 'atk' => 470, 'def' => 190],
            ['name' => 'Хранитель печати', 'element' => 'shadow', 'sprite' => 'boss-sealkeeper', 'trait' => 'void', 'hp' => 14800, 'atk' => 500, 'def' => 200],
            ['name' => 'Королева теней', 'element' => 'water', 'sprite' => 'boss-shadowqueen', 'trait' => 'curse', 'hp' => 16200, 'atk' => 530, 'def' => 212],
            ['name' => 'Палач мира', 'element' => 'beast', 'sprite' => 'boss-worldhangman', 'trait' => 'hammer', 'hp' => 17800, 'atk' => 560, 'def' => 224],
            ['name' => 'Тот, кто ждёт у трона', 'element' => 'shadow', 'sprite' => 'boss-throneguard', 'trait' => 'void', 'hp' => 19600, 'atk' => 600, 'def' => 238],
            ['name' => 'Владыка', 'element' => 'shadow', 'sprite' => 'boss-sovereign', 'trait' => 'void', 'hp' => 22000, 'atk' => 650, 'def' => 255],
        ],
        'crystal' => [
            ['name' => 'Хрустальный голем', 'element' => 'water', 'sprite' => 'boss-crystalgolem', 'trait' => 'stone', 'hp' => 9000, 'atk' => 360, 'def' => 150],
            ['name' => 'Призма', 'element' => 'light', 'sprite' => 'boss-prism', 'trait' => 'curse', 'hp' => 10500, 'atk' => 390, 'def' => 160],
            ['name' => 'Жеода-пожирательница', 'element' => 'nature', 'sprite' => 'boss-geode', 'trait' => 'gorge', 'hp' => 12500, 'atk' => 420, 'def' => 175],
        ],
        'pantheon' => [
            ['name' => 'Судья солнца', 'element' => 'light', 'sprite' => 'boss-sunjudge', 'trait' => 'hammer', 'hp' => 15000, 'atk' => 520, 'def' => 210],
            ['name' => 'Ткачиха луны', 'element' => 'shadow', 'sprite' => 'boss-moonweaver', 'trait' => 'curse', 'hp' => 17000, 'atk' => 550, 'def' => 220],
            ['name' => 'Отец бурь', 'element' => 'water', 'sprite' => 'boss-stormfather', 'trait' => 'sparks', 'hp' => 19500, 'atk' => 590, 'def' => 232],
            ['name' => 'Пряха судьбы', 'element' => 'shadow', 'sprite' => 'boss-fate', 'trait' => 'void', 'hp' => 22000, 'atk' => 640, 'def' => 245],
        ],
        'trial' => [
            ['name' => 'Авангард Владыки', 'element' => 'shadow', 'sprite' => 'boss-vanguard', 'trait' => 'hammer', 'hp' => 28000, 'atk' => 760, 'def' => 270],
            ['name' => 'Зовущий кости', 'element' => 'shadow', 'sprite' => 'boss-bonecaller', 'trait' => 'bones', 'hp' => 31000, 'atk' => 800, 'def' => 285],
            ['name' => 'Два клинка', 'element' => 'shadow', 'sprite' => 'boss-twinblades', 'trait' => 'pack', 'hp' => 34000, 'atk' => 860, 'def' => 300],
            ['name' => 'Король испытания', 'element' => 'light', 'sprite' => 'boss-trialking', 'trait' => 'void', 'hp' => 40000, 'atk' => 940, 'def' => 330],
        ],
        'rubledeep' => [
            ['name' => 'Монетный змей', 'element' => 'fire', 'sprite' => 'boss-coinwyrm', 'trait' => 'gorge', 'hp' => 32000, 'atk' => 820, 'def' => 290],
            ['name' => 'Демон хранилища', 'element' => 'fire', 'sprite' => 'boss-vaultdemon', 'trait' => 'hammer', 'hp' => 37000, 'atk' => 900, 'def' => 310],
            ['name' => 'Казначей бездны', 'element' => 'shadow', 'sprite' => 'boss-treasurer', 'trait' => 'curse', 'hp' => 44000, 'atk' => 1000, 'def' => 340],
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
        'gear-fang' => ['slot' => 'weapon', 'name' => 'Крысиный клык', 'sprite' => 'gi-dagger-bone', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 1, 'rarity' => 'common'],
        'gear-boneblade' => ['slot' => 'weapon', 'name' => 'Обглоданная кость', 'sprite' => 'gi-sword-bone', 'atk' => 3, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 1, 'rarity' => 'common'],
        'gear-pick' => ['slot' => 'weapon', 'name' => 'Шахтёрская кирка', 'sprite' => 'gi-axe-bronze', 'atk' => 9, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare'],
        'gear-cutlass' => ['slot' => 'weapon', 'name' => 'Ржавый тесак', 'sprite' => 'gi-sword-iron', 'atk' => 8, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare'],
        'gear-hammer' => ['slot' => 'weapon', 'name' => 'Кузнечный молот', 'sprite' => 'gi-hammer-fire', 'atk' => 16, 'def' => 2, 'hp' => 0, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic'],
        'gear-blade' => ['slot' => 'weapon', 'name' => 'Раскалённый клинок', 'sprite' => 'gi-sword-fire', 'atk' => 18, 'def' => 0, 'hp' => 6, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic'],
        'gear-iceblade' => ['slot' => 'weapon', 'name' => 'Ледяной клинок', 'sprite' => 'gi-sword-ice', 'atk' => 30, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 4, 'rarity' => 'epic'],
        'gear-bonehammer' => ['slot' => 'weapon', 'name' => 'Костяной молот', 'sprite' => 'gi-hammer-bone', 'atk' => 34, 'def' => 4, 'hp' => 0, 'spd' => -2, 'stage' => 4, 'rarity' => 'epic'],
        'gear-voidblade' => ['slot' => 'weapon', 'name' => 'Клинок бездны', 'sprite' => 'gi-sword-void', 'atk' => 50, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 5, 'rarity' => 'legendary'],
        'gear-reaper' => ['slot' => 'weapon', 'name' => 'Коса жнеца', 'sprite' => 'gi-staff-shadow', 'atk' => 58, 'def' => 0, 'hp' => 40, 'spd' => -3, 'stage' => 5, 'rarity' => 'legendary'],

        // Доспех — защита и здоровье: «лёгкий» даёт скорость, «кованый» — запас
        'gear-hide' => ['slot' => 'armor', 'name' => 'Крысиная шкура', 'sprite' => 'gi-vest-iron', 'atk' => 0, 'def' => 3, 'hp' => 12, 'spd' => 0, 'stage' => 1, 'rarity' => 'common'],
        'gear-rags' => ['slot' => 'armor', 'name' => 'Рваная тряпка', 'sprite' => 'gi-cloak-bone', 'atk' => 0, 'def' => 2, 'hp' => 8, 'spd' => 1, 'stage' => 1, 'rarity' => 'common'],
        'gear-chain' => ['slot' => 'armor', 'name' => 'Ржавая кольчуга', 'sprite' => 'gi-chest-iron', 'atk' => 0, 'def' => 7, 'hp' => 26, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare'],
        'gear-padded' => ['slot' => 'armor', 'name' => 'Мокрый ватник', 'sprite' => 'gi-vest-sea', 'atk' => 0, 'def' => 5, 'hp' => 22, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare'],
        'gear-plate' => ['slot' => 'armor', 'name' => 'Лавовый панцирь', 'sprite' => 'gi-chest-fire', 'atk' => 0, 'def' => 13, 'hp' => 46, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic'],
        'gear-ashcloak' => ['slot' => 'armor', 'name' => 'Пепельный плащ', 'sprite' => 'gi-cloak-fire', 'atk' => 0, 'def' => 9, 'hp' => 34, 'spd' => 1, 'stage' => 3, 'rarity' => 'epic'],
        'gear-frostplate' => ['slot' => 'armor', 'name' => 'Мёрзлый доспех', 'sprite' => 'gi-chest-steel', 'atk' => 0, 'def' => 24, 'hp' => 90, 'spd' => -1, 'stage' => 4, 'rarity' => 'epic'],
        'gear-frostcloak' => ['slot' => 'armor', 'name' => 'Плащ инея', 'sprite' => 'gi-cloak-ice', 'atk' => 0, 'def' => 18, 'hp' => 70, 'spd' => 2, 'stage' => 4, 'rarity' => 'epic'],
        'gear-voidarmor' => ['slot' => 'armor', 'name' => 'Пустотная бронь', 'sprite' => 'gi-chest-shadow', 'atk' => 0, 'def' => 40, 'hp' => 160, 'spd' => -2, 'stage' => 5, 'rarity' => 'legendary'],
        'gear-shadowcloak' => ['slot' => 'armor', 'name' => 'Плащ тени', 'sprite' => 'gi-cloak-shadow', 'atk' => 0, 'def' => 30, 'hp' => 120, 'spd' => 4, 'stage' => 5, 'rarity' => 'legendary'],

        // Талисман — скорость и всё понемногу: «быстрый» или «живучий»
        'gear-bone' => ['slot' => 'trinket', 'name' => 'Крысиная косточка', 'sprite' => 'gi-amulet-bone', 'atk' => 1, 'def' => 0, 'hp' => 6, 'spd' => 1, 'stage' => 1, 'rarity' => 'common'],
        'gear-candle' => ['slot' => 'trinket', 'name' => 'Свечной огарок', 'sprite' => 'gi-vial-bronze', 'atk' => 0, 'def' => 0, 'hp' => 5, 'spd' => 2, 'stage' => 1, 'rarity' => 'common'],
        'gear-pearl' => ['slot' => 'trinket', 'name' => 'Жемчужина глубин', 'sprite' => 'gi-orb-sea', 'atk' => 2, 'def' => 2, 'hp' => 14, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare'],
        'gear-compass' => ['slot' => 'trinket', 'name' => 'Медный компас', 'sprite' => 'gi-ring-bronze', 'atk' => 0, 'def' => 0, 'hp' => 8, 'spd' => 3, 'stage' => 2, 'rarity' => 'rare'],
        'gear-ember' => ['slot' => 'trinket', 'name' => 'Тлеющий уголь', 'sprite' => 'gi-orb-fire', 'atk' => 4, 'def' => 3, 'hp' => 22, 'spd' => 3, 'stage' => 3, 'rarity' => 'epic'],
        'gear-fireeye' => ['slot' => 'trinket', 'name' => 'Огненный глаз', 'sprite' => 'gi-eye-fire', 'atk' => 6, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic'],
        'gear-iceshard' => ['slot' => 'trinket', 'name' => 'Осколок льда', 'sprite' => 'gi-star-ice', 'atk' => 8, 'def' => 6, 'hp' => 44, 'spd' => 5, 'stage' => 4, 'rarity' => 'legendary'],
        'gear-stormeye' => ['slot' => 'trinket', 'name' => 'Глаз вьюги', 'sprite' => 'gi-eye-ice', 'atk' => 10, 'def' => 0, 'hp' => 20, 'spd' => 6, 'stage' => 4, 'rarity' => 'legendary'],
        'gear-voideye' => ['slot' => 'trinket', 'name' => 'Око пустоты', 'sprite' => 'gi-eye-void', 'atk' => 14, 'def' => 10, 'hp' => 80, 'spd' => 8, 'stage' => 5, 'rarity' => 'legendary'],
        'gear-shard' => ['slot' => 'trinket', 'name' => 'Кристалл разлома', 'sprite' => 'gi-star-crystal', 'atk' => 18, 'def' => 0, 'hp' => 50, 'spd' => 10, 'stage' => 5, 'rarity' => 'legendary'],

        // Ступень 6 — Бездонные чертоги
        'gear-abyssblade' => ['slot' => 'weapon', 'name' => 'Клинок глубин', 'sprite' => 'gi-sword-sea', 'atk' => 64, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 6, 'rarity' => 'legendary'],
        'gear-silentscythe' => ['slot' => 'weapon', 'name' => 'Коса тишины', 'sprite' => 'gi-staff-sea', 'atk' => 74, 'def' => 0, 'hp' => 50, 'spd' => -3, 'stage' => 6, 'rarity' => 'legendary'],
        'gear-abyssplate' => ['slot' => 'armor', 'name' => 'Доспех глубин', 'sprite' => 'gi-chest-sea', 'atk' => 0, 'def' => 50, 'hp' => 200, 'spd' => -2, 'stage' => 6, 'rarity' => 'legendary'],
        'gear-echocloak' => ['slot' => 'armor', 'name' => 'Плащ эха', 'sprite' => 'gi-cloak-sea', 'atk' => 0, 'def' => 38, 'hp' => 150, 'spd' => 5, 'stage' => 6, 'rarity' => 'legendary'],
        'gear-bottomlantern' => ['slot' => 'trinket', 'name' => 'Фонарь дна', 'sprite' => 'gi-vial-sea', 'atk' => 18, 'def' => 12, 'hp' => 100, 'spd' => 10, 'stage' => 6, 'rarity' => 'legendary'],
        'gear-blackpearl' => ['slot' => 'trinket', 'name' => 'Чёрная жемчужина', 'sprite' => 'gi-orb-shadow', 'atk' => 23, 'def' => 0, 'hp' => 64, 'spd' => 12, 'stage' => 6, 'rarity' => 'legendary'],

        // Ступень 7 — Логово дракона
        'gear-dragonfang' => ['slot' => 'weapon', 'name' => 'Клык дракона', 'sprite' => 'gi-dagger-fire', 'atk' => 80, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 7, 'rarity' => 'legendary'],
        'gear-embermaul' => ['slot' => 'weapon', 'name' => 'Молот углей', 'sprite' => 'gi-hammer-gold', 'atk' => 92, 'def' => 0, 'hp' => 60, 'spd' => -4, 'stage' => 7, 'rarity' => 'legendary'],
        'gear-scalemail' => ['slot' => 'armor', 'name' => 'Чешуйчатая броня', 'sprite' => 'gi-chest-fire', 'atk' => 0, 'def' => 62, 'hp' => 250, 'spd' => -3, 'stage' => 7, 'rarity' => 'legendary'],
        'gear-flamecloak' => ['slot' => 'armor', 'name' => 'Плащ пламени', 'sprite' => 'gi-cloak-fire', 'atk' => 0, 'def' => 46, 'hp' => 190, 'spd' => 6, 'stage' => 7, 'rarity' => 'legendary'],
        'gear-goldheart' => ['slot' => 'trinket', 'name' => 'Золотое сердце', 'sprite' => 'gi-amulet-gold', 'atk' => 22, 'def' => 15, 'hp' => 125, 'spd' => 12, 'stage' => 7, 'rarity' => 'legendary'],
        'gear-dragoneye' => ['slot' => 'trinket', 'name' => 'Око дракона', 'sprite' => 'gi-eye-gold', 'atk' => 28, 'def' => 0, 'hp' => 80, 'spd' => 14, 'stage' => 7, 'rarity' => 'legendary'],

        // Ступень 8 — Чертог владыки
        'gear-sovereignblade' => ['slot' => 'weapon', 'name' => 'Меч владыки', 'sprite' => 'gi-greatsword-bone', 'atk' => 98, 'def' => 0, 'hp' => 0, 'spd' => 3, 'stage' => 8, 'rarity' => 'legendary'],
        'gear-executioner' => ['slot' => 'weapon', 'name' => 'Секира палача', 'sprite' => 'gi-axe-shadow', 'atk' => 112, 'def' => 0, 'hp' => 80, 'spd' => -5, 'stage' => 8, 'rarity' => 'legendary'],
        'gear-thronearmor' => ['slot' => 'armor', 'name' => 'Тронный доспех', 'sprite' => 'gi-chest-bone', 'atk' => 0, 'def' => 76, 'hp' => 310, 'spd' => -4, 'stage' => 8, 'rarity' => 'legendary'],
        'gear-shadowmantle' => ['slot' => 'armor', 'name' => 'Мантия теней', 'sprite' => 'gi-cloak-void', 'atk' => 0, 'def' => 56, 'hp' => 235, 'spd' => 8, 'stage' => 8, 'rarity' => 'legendary'],
        'gear-voidcrown' => ['slot' => 'trinket', 'name' => 'Корона пустоты', 'sprite' => 'gi-crown-void', 'atk' => 27, 'def' => 18, 'hp' => 155, 'spd' => 15, 'stage' => 8, 'rarity' => 'legendary'],
        'gear-sealstone' => ['slot' => 'trinket', 'name' => 'Камень печати', 'sprite' => 'gi-orb-bone', 'atk' => 34, 'def' => 0, 'hp' => 100, 'spd' => 17, 'stage' => 8, 'rarity' => 'legendary'],

        // Ступень 1: новые находки
        'gear-x1-axe-iron' => ['slot' => 'weapon', 'name' => 'Ржавый топорик', 'sprite' => 'gi-axe-iron', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['vamp', 8], 'set' => 'grave'],
        'gear-x1-mace-bone' => ['slot' => 'weapon', 'name' => 'Костяная булава', 'sprite' => 'gi-mace-bone', 'atk' => 4, 'def' => 0, 'hp' => 1, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['thorns', 10]],
        'gear-x1-spear-bone' => ['slot' => 'weapon', 'name' => 'Копьё из бедренной кости', 'sprite' => 'gi-spear-bone', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['pierce', 18]],
        'gear-x1-vest-bone' => ['slot' => 'armor', 'name' => 'Жилет из рёбер', 'sprite' => 'gi-vest-bone', 'atk' => 0, 'def' => 2, 'hp' => 8, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['regen', 2], 'set' => 'grave'],
        'gear-x1-helm-iron' => ['slot' => 'armor', 'name' => 'Дырявый шлем', 'sprite' => 'gi-helm-iron', 'atk' => 0, 'def' => 2, 'hp' => 11, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['phoenix', 20]],
        'gear-x1-shield-bronze' => ['slot' => 'armor', 'name' => 'Крышка от бочки', 'sprite' => 'gi-shield-bronze', 'atk' => 0, 'def' => 4, 'hp' => 10, 'spd' => -1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['thorns', 10]],
        'gear-x1-ring-bronze' => ['slot' => 'trinket', 'name' => 'Медное кольцо', 'sprite' => 'gi-ring-bronze', 'atk' => 1, 'def' => 0, 'hp' => 5, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['crit', 7], 'set' => 'grave'],
        'gear-x1-vial-blood' => ['slot' => 'trinket', 'name' => 'Склянка крови', 'sprite' => 'gi-vial-blood', 'atk' => 0, 'def' => 0, 'hp' => 10, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['regen', 2]],
        'gear-x1-amulet-bone' => ['slot' => 'trinket', 'name' => 'Зуб могильщика', 'sprite' => 'gi-amulet-bone', 'atk' => 0, 'def' => 0, 'hp' => 7, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['bulwark', 6]],

        // Ступень 2: новые находки
        'gear-x2-spear-sea' => ['slot' => 'weapon', 'name' => 'Гарпун утопленника', 'sprite' => 'gi-spear-sea', 'atk' => 9, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['pierce', 20], 'set' => 'drowned'],
        'gear-x2-axe-bronze' => ['slot' => 'weapon', 'name' => 'Кирка-топор', 'sprite' => 'gi-axe-bronze', 'atk' => 10, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['vamp', 9]],
        'gear-x2-dagger-sea' => ['slot' => 'weapon', 'name' => 'Рыбацкий нож', 'sprite' => 'gi-dagger-sea', 'atk' => 8, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['opener', 38]],
        'gear-x2-chest-bronze' => ['slot' => 'armor', 'name' => 'Кираса водолаза', 'sprite' => 'gi-chest-bronze', 'atk' => 0, 'def' => 7, 'hp' => 26, 'spd' => -1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['bulwark', 7], 'set' => 'drowned'],
        'gear-x2-helm-sea' => ['slot' => 'armor', 'name' => 'Шлем водолаза', 'sprite' => 'gi-helm-sea', 'atk' => 0, 'def' => 6, 'hp' => 23, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['phoenix', 22]],
        'gear-x2-shield-iron' => ['slot' => 'armor', 'name' => 'Люк затонувшего корабля', 'sprite' => 'gi-shield-iron', 'atk' => 0, 'def' => 8, 'hp' => 22, 'spd' => -1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['thorns', 11]],
        'gear-x2-amulet-sea' => ['slot' => 'trinket', 'name' => 'Ракушка шторма', 'sprite' => 'gi-amulet-sea', 'atk' => 1, 'def' => 3, 'hp' => 15, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['bulwark', 7], 'set' => 'drowned'],
        'gear-x2-orb-sea' => ['slot' => 'trinket', 'name' => 'Пузырь глубин', 'sprite' => 'gi-orb-sea', 'atk' => 3, 'def' => 0, 'hp' => 8, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['opener', 38]],
        'gear-x2-ring-iron' => ['slot' => 'trinket', 'name' => 'Якорное кольцо', 'sprite' => 'gi-ring-iron', 'atk' => 3, 'def' => 0, 'hp' => 11, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['crit', 8]],

        // Ступень 3: новые находки
        'gear-x3-axe-fire' => ['slot' => 'weapon', 'name' => 'Топор кузнеца', 'sprite' => 'gi-axe-fire', 'atk' => 19, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['vamp', 10], 'set' => 'forge'],
        'gear-x3-mace-fire' => ['slot' => 'weapon', 'name' => 'Раскалённая булава', 'sprite' => 'gi-mace-fire', 'atk' => 18, 'def' => 0, 'hp' => 5, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['thorns', 12]],
        'gear-x3-greatsword-steel' => ['slot' => 'weapon', 'name' => 'Двуручный меч мастера', 'sprite' => 'gi-greatsword-steel', 'atk' => 20, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['execute', 30]],
        'gear-x3-chest-steel' => ['slot' => 'armor', 'name' => 'Кираса горнового', 'sprite' => 'gi-chest-steel', 'atk' => 0, 'def' => 13, 'hp' => 46, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['bulwark', 7], 'set' => 'forge'],
        'gear-x3-helm-fire' => ['slot' => 'armor', 'name' => 'Шлем плавильщика', 'sprite' => 'gi-helm-fire', 'atk' => 0, 'def' => 10, 'hp' => 41, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['phoenix', 24]],
        'gear-x3-shield-fire' => ['slot' => 'armor', 'name' => 'Щит из слитка', 'sprite' => 'gi-shield-fire', 'atk' => 0, 'def' => 16, 'hp' => 39, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['thorns', 12]],
        'gear-x3-ring-fire' => ['slot' => 'trinket', 'name' => 'Кольцо жара', 'sprite' => 'gi-ring-fire', 'atk' => 6, 'def' => 0, 'hp' => 18, 'spd' => 3, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['crit', 8], 'set' => 'forge'],
        'gear-x3-vial-fire' => ['slot' => 'trinket', 'name' => 'Фиал лавы', 'sprite' => 'gi-vial-fire', 'atk' => 2, 'def' => 0, 'hp' => 35, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['regen', 2]],
        'gear-x3-book-fire' => ['slot' => 'trinket', 'name' => 'Книга закалки', 'sprite' => 'gi-book-fire', 'atk' => 6, 'def' => 2, 'hp' => 18, 'spd' => 3, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['opener', 42]],

        // Ступень 4: новые находки
        'gear-x4-spear-ice' => ['slot' => 'weapon', 'name' => 'Ледяное копьё', 'sprite' => 'gi-spear-ice', 'atk' => 31, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['pierce', 23], 'set' => 'frost'],
        'gear-x4-staff-ice' => ['slot' => 'weapon', 'name' => 'Посох вьюги', 'sprite' => 'gi-staff-ice', 'atk' => 26, 'def' => 0, 'hp' => 19, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['regen', 3]],
        'gear-x4-axe-ice' => ['slot' => 'weapon', 'name' => 'Секира ледника', 'sprite' => 'gi-axe-ice', 'atk' => 36, 'def' => 0, 'hp' => 0, 'spd' => -2, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['vamp', 10]],
        'gear-x4-chest-ice' => ['slot' => 'armor', 'name' => 'Кираса инея', 'sprite' => 'gi-chest-ice', 'atk' => 0, 'def' => 24, 'hp' => 90, 'spd' => -1, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['bulwark', 8], 'set' => 'frost'],
        'gear-x4-helm-ice' => ['slot' => 'armor', 'name' => 'Шлем снежного короля', 'sprite' => 'gi-helm-ice', 'atk' => 0, 'def' => 19, 'hp' => 81, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['phoenix', 26]],
        'gear-x4-shield-ice' => ['slot' => 'armor', 'name' => 'Щит ледяной глыбы', 'sprite' => 'gi-shield-ice', 'atk' => 0, 'def' => 29, 'hp' => 76, 'spd' => -1, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['thorns', 13]],
        'gear-x4-amulet-ice' => ['slot' => 'trinket', 'name' => 'Снежинка', 'sprite' => 'gi-amulet-ice', 'atk' => 4, 'def' => 9, 'hp' => 48, 'spd' => 4, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['bulwark', 8], 'set' => 'frost'],
        'gear-x4-crown-ice' => ['slot' => 'trinket', 'name' => 'Венец стужи', 'sprite' => 'gi-crown-ice', 'atk' => 7, 'def' => 8, 'hp' => 57, 'spd' => 5, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['pierce', 23]],
        'gear-x4-orb-ice' => ['slot' => 'trinket', 'name' => 'Шар метели', 'sprite' => 'gi-orb-ice', 'atk' => 14, 'def' => 0, 'hp' => 26, 'spd' => 4, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['opener', 46]],

        // Ступень 5: новые находки
        'gear-x5-dagger-void' => ['slot' => 'weapon', 'name' => 'Кинжал пустоты', 'sprite' => 'gi-dagger-void', 'atk' => 48, 'def' => 0, 'hp' => 0, 'spd' => 2, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['opener', 49], 'set' => 'void'],
        'gear-x5-staff-void' => ['slot' => 'weapon', 'name' => 'Посох бездны', 'sprite' => 'gi-staff-void', 'atk' => 44, 'def' => 0, 'hp' => 32, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-x5-greatsword-shadow' => ['slot' => 'weapon', 'name' => 'Меч тени', 'sprite' => 'gi-greatsword-shadow', 'atk' => 64, 'def' => 0, 'hp' => 0, 'spd' => -2, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['execute', 35]],
        'gear-x5-chest-void' => ['slot' => 'armor', 'name' => 'Нагрудник пустоты', 'sprite' => 'gi-chest-void', 'atk' => 0, 'def' => 40, 'hp' => 160, 'spd' => -1, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['bulwark', 8], 'set' => 'void'],
        'gear-x5-helm-shadow' => ['slot' => 'armor', 'name' => 'Шлем безликого', 'sprite' => 'gi-helm-shadow', 'atk' => 0, 'def' => 32, 'hp' => 144, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['phoenix', 28]],
        'gear-x5-cloak-void' => ['slot' => 'armor', 'name' => 'Мантия звёздной тьмы', 'sprite' => 'gi-cloak-void', 'atk' => 0, 'def' => 29, 'hp' => 120, 'spd' => 2, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['dodge', 10]],
        'gear-x5-eye-void' => ['slot' => 'trinket', 'name' => 'Всевидящее око', 'sprite' => 'gi-eye-void', 'atk' => 22, 'def' => 0, 'hp' => 40, 'spd' => 11, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['execute', 35], 'set' => 'void'],
        'gear-x5-book-void' => ['slot' => 'trinket', 'name' => 'Гримуар тишины', 'sprite' => 'gi-book-void', 'atk' => 21, 'def' => 6, 'hp' => 64, 'spd' => 7, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['opener', 49]],
        'gear-x5-star-void' => ['slot' => 'trinket', 'name' => 'Падшая звезда', 'sprite' => 'gi-star-void', 'atk' => 16, 'def' => 10, 'hp' => 80, 'spd' => 10, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['phoenix', 28]],

        // Ступень 6: новые находки
        'gear-x6-spear-sea' => ['slot' => 'weapon', 'name' => 'Трезубец левиафана', 'sprite' => 'gi-spear-sea', 'atk' => 66, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['pierce', 27], 'set' => 'leviathan'],
        'gear-x6-hammer-sea' => ['slot' => 'weapon', 'name' => 'Молот прилива', 'sprite' => 'gi-hammer-sea', 'atk' => 79, 'def' => 7, 'hp' => 0, 'spd' => -3, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['pierce', 27]],
        'gear-x6-wand-sea' => ['slot' => 'weapon', 'name' => 'Жезл глубинной воды', 'sprite' => 'gi-wand-sea', 'atk' => 54, 'def' => 0, 'hp' => 28, 'spd' => 3, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['vamp', 12]],
        'gear-x6-chest-sea' => ['slot' => 'armor', 'name' => 'Панцирь глубин', 'sprite' => 'gi-chest-sea', 'atk' => 0, 'def' => 50, 'hp' => 200, 'spd' => -2, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['bulwark', 9], 'set' => 'leviathan'],
        'gear-x6-shield-sea' => ['slot' => 'armor', 'name' => 'Щит-раковина', 'sprite' => 'gi-shield-sea', 'atk' => 0, 'def' => 60, 'hp' => 170, 'spd' => -2, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['thorns', 15]],
        'gear-x6-helm-sea' => ['slot' => 'armor', 'name' => 'Шлем бездны', 'sprite' => 'gi-helm-sea', 'atk' => 0, 'def' => 40, 'hp' => 180, 'spd' => 0, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['phoenix', 30]],
        'gear-x6-orb-sea' => ['slot' => 'trinket', 'name' => 'Сердце океана', 'sprite' => 'gi-orb-sea', 'atk' => 32, 'def' => 0, 'hp' => 60, 'spd' => 8, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['opener', 52], 'set' => 'leviathan'],
        'gear-x6-eye-sea' => ['slot' => 'trinket', 'name' => 'Око левиафана', 'sprite' => 'gi-eye-sea', 'atk' => 28, 'def' => 0, 'hp' => 50, 'spd' => 14, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['execute', 38]],
        'gear-x6-crown-sea' => ['slot' => 'trinket', 'name' => 'Корона дна', 'sprite' => 'gi-crown-sea', 'atk' => 16, 'def' => 16, 'hp' => 130, 'spd' => 10, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['pierce', 27]],

        // Ступень 7: новые находки
        'gear-x7-greatsword-fire' => ['slot' => 'weapon', 'name' => 'Меч драконоборца', 'sprite' => 'gi-greatsword-fire', 'atk' => 101, 'def' => 0, 'hp' => 0, 'spd' => -3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['execute', 40], 'set' => 'dragon'],
        'gear-x7-spear-gold' => ['slot' => 'weapon', 'name' => 'Копьё охотника на драконов', 'sprite' => 'gi-spear-gold', 'atk' => 83, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['pierce', 29]],
        'gear-x7-staff-crystal' => ['slot' => 'weapon', 'name' => 'Посох кристального жреца', 'sprite' => 'gi-staff-crystal', 'atk' => 71, 'def' => 0, 'hp' => 52, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-x7-chest-gold' => ['slot' => 'armor', 'name' => 'Золотая чешуя', 'sprite' => 'gi-chest-gold', 'atk' => 0, 'def' => 62, 'hp' => 250, 'spd' => -2, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['bulwark', 10], 'set' => 'dragon'],
        'gear-x7-helm-fire' => ['slot' => 'armor', 'name' => 'Рогатый шлем дракона', 'sprite' => 'gi-helm-fire', 'atk' => 0, 'def' => 50, 'hp' => 225, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['phoenix', 32]],
        'gear-x7-shield-crystal' => ['slot' => 'armor', 'name' => 'Призматический щит', 'sprite' => 'gi-shield-crystal', 'atk' => 0, 'def' => 74, 'hp' => 212, 'spd' => -2, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['thorns', 16]],
        'gear-x7-crown-gold' => ['slot' => 'trinket', 'name' => 'Корона сокровищницы', 'sprite' => 'gi-crown-gold', 'atk' => 20, 'def' => 20, 'hp' => 162, 'spd' => 12, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['pierce', 29], 'set' => 'dragon'],
        'gear-x7-star-crystal' => ['slot' => 'trinket', 'name' => 'Звезда призмы', 'sprite' => 'gi-star-crystal', 'atk' => 25, 'def' => 15, 'hp' => 125, 'spd' => 14, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['phoenix', 32]],
        'gear-x7-vial-fire' => ['slot' => 'trinket', 'name' => 'Дыхание дракона', 'sprite' => 'gi-vial-fire', 'atk' => 10, 'def' => 0, 'hp' => 200, 'spd' => 10, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['regen', 3]],

        // Ступень 8: новые находки
        'gear-x8-axe-blood' => ['slot' => 'weapon', 'name' => 'Топор палача', 'sprite' => 'gi-axe-blood', 'atk' => 118, 'def' => 0, 'hp' => 0, 'spd' => -4, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['vamp', 14], 'set' => 'headsman'],
        'gear-x8-mace-sun' => ['slot' => 'weapon', 'name' => 'Булава судьи', 'sprite' => 'gi-mace-sun', 'atk' => 113, 'def' => 0, 'hp' => 32, 'spd' => -2, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['thorns', 17]],
        'gear-x8-wand-sun' => ['slot' => 'weapon', 'name' => 'Жезл богов', 'sprite' => 'gi-wand-sun', 'atk' => 82, 'def' => 0, 'hp' => 42, 'spd' => 4, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-x8-chest-blood' => ['slot' => 'armor', 'name' => 'Багряный доспех', 'sprite' => 'gi-chest-blood', 'atk' => 0, 'def' => 76, 'hp' => 310, 'spd' => -2, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['bulwark', 10], 'set' => 'headsman'],
        'gear-x8-helm-bone' => ['slot' => 'armor', 'name' => 'Череп-шлем', 'sprite' => 'gi-helm-bone', 'atk' => 0, 'def' => 61, 'hp' => 279, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['phoenix', 34]],
        'gear-x8-shield-sun' => ['slot' => 'armor', 'name' => 'Солнечная эгида', 'sprite' => 'gi-shield-sun', 'atk' => 0, 'def' => 91, 'hp' => 264, 'spd' => -2, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['thorns', 17]],
        'gear-x8-crown-sun' => ['slot' => 'trinket', 'name' => 'Венец пантеона', 'sprite' => 'gi-crown-sun', 'atk' => 24, 'def' => 23, 'hp' => 202, 'spd' => 15, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['pierce', 31], 'set' => 'headsman'],
        'gear-x8-book-bone' => ['slot' => 'trinket', 'name' => 'Книга павших', 'sprite' => 'gi-book-bone', 'atk' => 39, 'def' => 11, 'hp' => 124, 'spd' => 14, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['opener', 60]],
        'gear-x8-eye-sun' => ['slot' => 'trinket', 'name' => 'Око правосудия', 'sprite' => 'gi-eye-sun', 'atk' => 42, 'def' => 0, 'hp' => 78, 'spd' => 21, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['execute', 43]],

        // Ступень 9: новые находки
        'gear-x9-greatsword-gold' => ['slot' => 'weapon', 'name' => 'Меч испытания', 'sprite' => 'gi-greatsword-gold', 'atk' => 148, 'def' => 0, 'hp' => 0, 'spd' => -4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['execute', 45], 'set' => 'champion'],
        'gear-x9-axe-blood' => ['slot' => 'weapon', 'name' => 'Топор безжалостного', 'sprite' => 'gi-axe-blood', 'atk' => 140, 'def' => 0, 'hp' => 0, 'spd' => -4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-x9-dagger-shadow' => ['slot' => 'weapon', 'name' => 'Клинок последнего шанса', 'sprite' => 'gi-dagger-shadow', 'atk' => 110, 'def' => 0, 'hp' => 0, 'spd' => 4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['opener', 63]],
        'gear-x9-spear-sun' => ['slot' => 'weapon', 'name' => 'Копьё чемпиона', 'sprite' => 'gi-spear-sun', 'atk' => 120, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['pierce', 32]],
        'gear-x9-hammer-void' => ['slot' => 'weapon', 'name' => 'Молот конца света', 'sprite' => 'gi-hammer-void', 'atk' => 144, 'def' => 12, 'hp' => 0, 'spd' => -4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['pierce', 32]],
        'gear-x9-chest-gold' => ['slot' => 'armor', 'name' => 'Доспех чемпиона', 'sprite' => 'gi-chest-gold', 'atk' => 0, 'def' => 92, 'hp' => 370, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11], 'set' => 'champion'],
        'gear-x9-cloak-shadow' => ['slot' => 'armor', 'name' => 'Плащ безмолвного', 'sprite' => 'gi-cloak-shadow', 'atk' => 0, 'def' => 66, 'hp' => 278, 'spd' => 4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['dodge', 13]],
        'gear-x9-helm-blood' => ['slot' => 'armor', 'name' => 'Шлем непокорного', 'sprite' => 'gi-helm-blood', 'atk' => 0, 'def' => 74, 'hp' => 333, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['phoenix', 36]],
        'gear-x9-shield-sun' => ['slot' => 'armor', 'name' => 'Щит несгибаемых', 'sprite' => 'gi-shield-sun', 'atk' => 0, 'def' => 110, 'hp' => 314, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['thorns', 18]],
        'gear-x9-vest-void' => ['slot' => 'armor', 'name' => 'Жилет теней', 'sprite' => 'gi-vest-void', 'atk' => 0, 'def' => 57, 'hp' => 259, 'spd' => 4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['regen', 4]],
        'gear-x9-ring-gold' => ['slot' => 'trinket', 'name' => 'Кольцо победителя', 'sprite' => 'gi-ring-gold', 'atk' => 47, 'def' => 0, 'hp' => 148, 'spd' => 18, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['crit', 13], 'set' => 'champion'],
        'gear-x9-amulet-sun' => ['slot' => 'trinket', 'name' => 'Амулет бессмертных', 'sprite' => 'gi-amulet-sun', 'atk' => 18, 'def' => 33, 'hp' => 204, 'spd' => 13, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11]],
        'gear-x9-orb-void' => ['slot' => 'trinket', 'name' => 'Сфера судьбы', 'sprite' => 'gi-orb-void', 'atk' => 58, 'def' => 0, 'hp' => 111, 'spd' => 14, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['opener', 63]],
        'gear-x9-crown-blood' => ['slot' => 'trinket', 'name' => 'Венец павшего короля', 'sprite' => 'gi-crown-blood', 'atk' => 29, 'def' => 29, 'hp' => 240, 'spd' => 18, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['pierce', 32]],
        'gear-x9-book-shadow' => ['slot' => 'trinket', 'name' => 'Том последних слов', 'sprite' => 'gi-book-shadow', 'atk' => 47, 'def' => 13, 'hp' => 148, 'spd' => 16, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['opener', 63]],

        // Ступень 1: ещё находки с особыми свойствами
        'gear-y1-dagger-iron' => ['slot' => 'weapon', 'name' => 'Заточка узника', 'sprite' => 'gi-dagger-iron', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['opener', 35]],
        'gear-y1-sword-bone' => ['slot' => 'weapon', 'name' => 'Меч-ребро', 'sprite' => 'gi-sword-bone', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['crit', 7]],
        'gear-y1-hammer-iron' => ['slot' => 'weapon', 'name' => 'Кувалда стражника', 'sprite' => 'gi-hammer-iron', 'atk' => 5, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['pierce', 18]],
        'gear-y1-chest-iron' => ['slot' => 'armor', 'name' => 'Кираса караульного', 'sprite' => 'gi-chest-iron', 'atk' => 0, 'def' => 3, 'hp' => 12, 'spd' => -1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['bulwark', 6]],
        'gear-y1-cloak-bone' => ['slot' => 'armor', 'name' => 'Саван', 'sprite' => 'gi-cloak-bone', 'atk' => 0, 'def' => 2, 'hp' => 9, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['dodge', 7]],
        'gear-y1-helm-bone' => ['slot' => 'armor', 'name' => 'Череп на голове', 'sprite' => 'gi-helm-bone', 'atk' => 0, 'def' => 2, 'hp' => 11, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['phoenix', 20]],
        'gear-y1-star-bone' => ['slot' => 'trinket', 'name' => 'Мёртвая звезда', 'sprite' => 'gi-star-bone', 'atk' => 1, 'def' => 0, 'hp' => 6, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['phoenix', 20]],
        'gear-y1-orb-bone' => ['slot' => 'trinket', 'name' => 'Глазное яблоко', 'sprite' => 'gi-orb-bone', 'atk' => 2, 'def' => 0, 'hp' => 4, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['opener', 35]],
        'gear-y1-crown-bronze' => ['slot' => 'trinket', 'name' => 'Медный венец', 'sprite' => 'gi-crown-bronze', 'atk' => 1, 'def' => 0, 'hp' => 8, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['pierce', 18]],

        // Ступень 2: ещё находки с особыми свойствами
        'gear-y2-sword-sea' => ['slot' => 'weapon', 'name' => 'Сабля контрабандиста', 'sprite' => 'gi-sword-sea', 'atk' => 9, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['crit', 8]],
        'gear-y2-mace-bronze' => ['slot' => 'weapon', 'name' => 'Якорь-булава', 'sprite' => 'gi-mace-bronze', 'atk' => 10, 'def' => 0, 'hp' => 3, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['thorns', 11]],
        'gear-y2-staff-sea' => ['slot' => 'weapon', 'name' => 'Посох морской ведьмы', 'sprite' => 'gi-staff-sea', 'atk' => 7, 'def' => 0, 'hp' => 5, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['regen', 2]],
        'gear-y2-cloak-sea' => ['slot' => 'armor', 'name' => 'Плащ из водорослей', 'sprite' => 'gi-cloak-sea', 'atk' => 0, 'def' => 5, 'hp' => 20, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['dodge', 8]],
        'gear-y2-vest-bronze' => ['slot' => 'armor', 'name' => 'Тельняшка', 'sprite' => 'gi-vest-bronze', 'atk' => 0, 'def' => 4, 'hp' => 18, 'spd' => 1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['regen', 2]],
        'gear-y2-chest-sea' => ['slot' => 'armor', 'name' => 'Чешуйчатый панцирь', 'sprite' => 'gi-chest-sea', 'atk' => 0, 'def' => 7, 'hp' => 26, 'spd' => -1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['bulwark', 7]],
        'gear-y2-eye-sea' => ['slot' => 'trinket', 'name' => 'Глаз кракена', 'sprite' => 'gi-eye-sea', 'atk' => 3, 'def' => 0, 'hp' => 7, 'spd' => 3, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['execute', 28]],
        'gear-y2-crown-bronze' => ['slot' => 'trinket', 'name' => 'Корона капитана', 'sprite' => 'gi-crown-bronze', 'atk' => 2, 'def' => 3, 'hp' => 18, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['pierce', 20]],
        'gear-y2-book-sea' => ['slot' => 'trinket', 'name' => 'Судовой журнал', 'sprite' => 'gi-book-sea', 'atk' => 3, 'def' => 1, 'hp' => 11, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['opener', 38]],

        // Ступень 3: ещё находки с особыми свойствами
        'gear-y3-dagger-fire' => ['slot' => 'weapon', 'name' => 'Кинжал из шлака', 'sprite' => 'gi-dagger-fire', 'atk' => 15, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['opener', 42]],
        'gear-y3-hammer-fire' => ['slot' => 'weapon', 'name' => 'Молот горна', 'sprite' => 'gi-hammer-fire', 'atk' => 20, 'def' => 2, 'hp' => 0, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['pierce', 22]],
        'gear-y3-spear-fire' => ['slot' => 'weapon', 'name' => 'Огненная пика', 'sprite' => 'gi-spear-fire', 'atk' => 16, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['pierce', 22]],
        'gear-y3-cloak-fire' => ['slot' => 'armor', 'name' => 'Тлеющий плащ', 'sprite' => 'gi-cloak-fire', 'atk' => 0, 'def' => 9, 'hp' => 34, 'spd' => 1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['dodge', 8]],
        'gear-y3-vest-fire' => ['slot' => 'armor', 'name' => 'Фартук кузнеца', 'sprite' => 'gi-vest-fire', 'atk' => 0, 'def' => 8, 'hp' => 32, 'spd' => 1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['regen', 2]],
        'gear-y3-chest-fire' => ['slot' => 'armor', 'name' => 'Плавильная броня', 'sprite' => 'gi-chest-fire', 'atk' => 0, 'def' => 13, 'hp' => 46, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['bulwark', 7]],
        'gear-y3-orb-fire' => ['slot' => 'trinket', 'name' => 'Сердце горна', 'sprite' => 'gi-orb-fire', 'atk' => 8, 'def' => 0, 'hp' => 13, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['opener', 42]],
        'gear-y3-amulet-fire' => ['slot' => 'trinket', 'name' => 'Искра наковальни', 'sprite' => 'gi-amulet-fire', 'atk' => 2, 'def' => 4, 'hp' => 24, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['bulwark', 7]],
        'gear-y3-star-fire' => ['slot' => 'trinket', 'name' => 'Падающая искра', 'sprite' => 'gi-star-fire', 'atk' => 5, 'def' => 3, 'hp' => 22, 'spd' => 4, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['phoenix', 24]],

        // Ступень 4: ещё находки с особыми свойствами
        'gear-y4-sword-ice' => ['slot' => 'weapon', 'name' => 'Меч стужи', 'sprite' => 'gi-sword-ice', 'atk' => 32, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['crit', 9]],
        'gear-y4-hammer-ice' => ['slot' => 'weapon', 'name' => 'Молот мороза', 'sprite' => 'gi-hammer-ice', 'atk' => 37, 'def' => 3, 'hp' => 0, 'spd' => -2, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['pierce', 23]],
        'gear-y4-wand-ice' => ['slot' => 'weapon', 'name' => 'Ледяной жезл', 'sprite' => 'gi-wand-ice', 'atk' => 25, 'def' => 0, 'hp' => 13, 'spd' => 2, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['vamp', 10]],
        'gear-y4-cloak-ice' => ['slot' => 'armor', 'name' => 'Плащ метели', 'sprite' => 'gi-cloak-ice', 'atk' => 0, 'def' => 17, 'hp' => 68, 'spd' => 2, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['dodge', 9]],
        'gear-y4-vest-ice' => ['slot' => 'armor', 'name' => 'Шкура ледяного волка', 'sprite' => 'gi-vest-ice', 'atk' => 0, 'def' => 15, 'hp' => 63, 'spd' => 2, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['regen', 3]],
        'gear-y4-chest-steel' => ['slot' => 'armor', 'name' => 'Стальной панцирь севера', 'sprite' => 'gi-chest-steel', 'atk' => 0, 'def' => 24, 'hp' => 90, 'spd' => -1, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['bulwark', 8]],
        'gear-y4-ring-ice' => ['slot' => 'trinket', 'name' => 'Кольцо вечной мерзлоты', 'sprite' => 'gi-ring-ice', 'atk' => 12, 'def' => 0, 'hp' => 35, 'spd' => 5, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['crit', 9]],
        'gear-y4-eye-ice' => ['slot' => 'trinket', 'name' => 'Око йети', 'sprite' => 'gi-eye-ice', 'atk' => 13, 'def' => 0, 'hp' => 22, 'spd' => 7, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['execute', 32]],
        'gear-y4-book-ice' => ['slot' => 'trinket', 'name' => 'Ледяная скрижаль', 'sprite' => 'gi-book-ice', 'atk' => 12, 'def' => 4, 'hp' => 35, 'spd' => 4, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['opener', 46]],

        // Ступень 5: ещё находки с особыми свойствами
        'gear-y5-sword-void' => ['slot' => 'weapon', 'name' => 'Клинок разлома', 'sprite' => 'gi-sword-void', 'atk' => 54, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['crit', 10]],
        'gear-y5-axe-void' => ['slot' => 'weapon', 'name' => 'Секира пустоты', 'sprite' => 'gi-axe-void', 'atk' => 60, 'def' => 0, 'hp' => 0, 'spd' => -2, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['vamp', 11]],
        'gear-y5-spear-shadow' => ['slot' => 'weapon', 'name' => 'Копьё из тьмы', 'sprite' => 'gi-spear-shadow', 'atk' => 52, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['pierce', 25]],
        'gear-y5-shield-void' => ['slot' => 'armor', 'name' => 'Щит бездны', 'sprite' => 'gi-shield-void', 'atk' => 0, 'def' => 48, 'hp' => 136, 'spd' => -1, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['thorns', 14]],
        'gear-y5-vest-shadow' => ['slot' => 'armor', 'name' => 'Куртка бродяги пустоты', 'sprite' => 'gi-vest-shadow', 'atk' => 0, 'def' => 25, 'hp' => 112, 'spd' => 2, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-y5-chest-shadow' => ['slot' => 'armor', 'name' => 'Нагрудник тени', 'sprite' => 'gi-chest-shadow', 'atk' => 0, 'def' => 40, 'hp' => 160, 'spd' => -1, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['bulwark', 8]],
        'gear-y5-ring-void' => ['slot' => 'trinket', 'name' => 'Кольцо ничто', 'sprite' => 'gi-ring-void', 'atk' => 21, 'def' => 0, 'hp' => 64, 'spd' => 8, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['crit', 10]],
        'gear-y5-crown-void' => ['slot' => 'trinket', 'name' => 'Венец тишины', 'sprite' => 'gi-crown-void', 'atk' => 13, 'def' => 13, 'hp' => 104, 'spd' => 8, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['pierce', 25]],
        'gear-y5-orb-void' => ['slot' => 'trinket', 'name' => 'Шар небытия', 'sprite' => 'gi-orb-void', 'atk' => 26, 'def' => 0, 'hp' => 48, 'spd' => 6, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['opener', 49]],

        // Ступень 6: ещё находки с особыми свойствами
        'gear-y6-sword-sea' => ['slot' => 'weapon', 'name' => 'Клинок течения', 'sprite' => 'gi-sword-sea', 'atk' => 69, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['crit', 10]],
        'gear-y6-axe-sea' => ['slot' => 'weapon', 'name' => 'Секира шторма', 'sprite' => 'gi-axe-sea', 'atk' => 77, 'def' => 0, 'hp' => 0, 'spd' => -3, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['vamp', 12]],
        'gear-y6-dagger-sea' => ['slot' => 'weapon', 'name' => 'Игла глубин', 'sprite' => 'gi-dagger-sea', 'atk' => 61, 'def' => 0, 'hp' => 0, 'spd' => 3, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['opener', 52]],
        'gear-y6-cloak-sea' => ['slot' => 'armor', 'name' => 'Плащ глубоководья', 'sprite' => 'gi-cloak-sea', 'atk' => 0, 'def' => 36, 'hp' => 150, 'spd' => 3, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['dodge', 10]],
        'gear-y6-vest-sea' => ['slot' => 'armor', 'name' => 'Жилет ныряльщика', 'sprite' => 'gi-vest-sea', 'atk' => 0, 'def' => 31, 'hp' => 140, 'spd' => 3, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-y6-chest-shadow' => ['slot' => 'armor', 'name' => 'Броня тёмной воды', 'sprite' => 'gi-chest-shadow', 'atk' => 0, 'def' => 50, 'hp' => 200, 'spd' => -2, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['bulwark', 9]],
        'gear-y6-ring-sea' => ['slot' => 'trinket', 'name' => 'Кольцо жемчуга', 'sprite' => 'gi-ring-sea', 'atk' => 26, 'def' => 0, 'hp' => 80, 'spd' => 10, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['crit', 10]],
        'gear-y6-amulet-sea' => ['slot' => 'trinket', 'name' => 'Амулет прилива', 'sprite' => 'gi-amulet-sea', 'atk' => 10, 'def' => 18, 'hp' => 110, 'spd' => 7, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['bulwark', 9]],
        'gear-y6-star-sea' => ['slot' => 'trinket', 'name' => 'Морская звезда', 'sprite' => 'gi-star-sea', 'atk' => 20, 'def' => 12, 'hp' => 100, 'spd' => 12, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['phoenix', 30]],

        // Ступень 7: ещё находки с особыми свойствами
        'gear-y7-axe-fire' => ['slot' => 'weapon', 'name' => 'Топор огнедышащего', 'sprite' => 'gi-axe-fire', 'atk' => 96, 'def' => 0, 'hp' => 0, 'spd' => -3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['vamp', 13]],
        'gear-y7-hammer-gold' => ['slot' => 'weapon', 'name' => 'Молот сокровищницы', 'sprite' => 'gi-hammer-gold', 'atk' => 99, 'def' => 9, 'hp' => 0, 'spd' => -3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['pierce', 29]],
        'gear-y7-wand-crystal' => ['slot' => 'weapon', 'name' => 'Кристальный жезл', 'sprite' => 'gi-wand-crystal', 'atk' => 67, 'def' => 0, 'hp' => 34, 'spd' => 3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['vamp', 13]],
        'gear-y7-cloak-fire' => ['slot' => 'armor', 'name' => 'Крыло дракона', 'sprite' => 'gi-cloak-fire', 'atk' => 0, 'def' => 45, 'hp' => 188, 'spd' => 3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['dodge', 11]],
        'gear-y7-vest-gold' => ['slot' => 'armor', 'name' => 'Куртка из золотой чешуи', 'sprite' => 'gi-vest-gold', 'atk' => 0, 'def' => 38, 'hp' => 175, 'spd' => 3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-y7-chest-crystal' => ['slot' => 'armor', 'name' => 'Кираса из кристалла', 'sprite' => 'gi-chest-crystal', 'atk' => 0, 'def' => 62, 'hp' => 250, 'spd' => -2, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['bulwark', 10]],
        'gear-y7-ring-gold' => ['slot' => 'trinket', 'name' => 'Кольцо дракона', 'sprite' => 'gi-ring-gold', 'atk' => 32, 'def' => 0, 'hp' => 100, 'spd' => 12, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['crit', 11]],
        'gear-y7-amulet-gold' => ['slot' => 'trinket', 'name' => 'Амулет клада', 'sprite' => 'gi-amulet-gold', 'atk' => 12, 'def' => 22, 'hp' => 138, 'spd' => 8, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['bulwark', 10]],
        'gear-y7-orb-crystal' => ['slot' => 'trinket', 'name' => 'Призма-шар', 'sprite' => 'gi-orb-crystal', 'atk' => 40, 'def' => 0, 'hp' => 75, 'spd' => 10, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['opener', 56]],

        // Ступень 8: ещё находки с особыми свойствами
        'gear-y8-sword-sun' => ['slot' => 'weapon', 'name' => 'Меч света', 'sprite' => 'gi-sword-sun', 'atk' => 105, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['crit', 12]],
        'gear-y8-greatsword-blood' => ['slot' => 'weapon', 'name' => 'Меч приговора', 'sprite' => 'gi-greatsword-blood', 'atk' => 124, 'def' => 0, 'hp' => 0, 'spd' => -4, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['execute', 43]],
        'gear-y8-dagger-blood' => ['slot' => 'weapon', 'name' => 'Кинжал палача', 'sprite' => 'gi-dagger-blood', 'atk' => 92, 'def' => 0, 'hp' => 0, 'spd' => 4, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['opener', 60]],
        'gear-y8-cloak-sun' => ['slot' => 'armor', 'name' => 'Плащ жреца солнца', 'sprite' => 'gi-cloak-sun', 'atk' => 0, 'def' => 55, 'hp' => 232, 'spd' => 4, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['dodge', 12]],
        'gear-y8-vest-blood' => ['slot' => 'armor', 'name' => 'Кожа палача', 'sprite' => 'gi-vest-blood', 'atk' => 0, 'def' => 47, 'hp' => 217, 'spd' => 4, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-y8-shield-blood' => ['slot' => 'armor', 'name' => 'Щит с гербом владыки', 'sprite' => 'gi-shield-blood', 'atk' => 0, 'def' => 91, 'hp' => 264, 'spd' => -2, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['thorns', 17]],
        'gear-y8-ring-sun' => ['slot' => 'trinket', 'name' => 'Кольцо судьи', 'sprite' => 'gi-ring-sun', 'atk' => 39, 'def' => 0, 'hp' => 124, 'spd' => 15, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['crit', 12]],
        'gear-y8-amulet-blood' => ['slot' => 'trinket', 'name' => 'Кровавый медальон', 'sprite' => 'gi-amulet-blood', 'atk' => 15, 'def' => 27, 'hp' => 170, 'spd' => 10, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['bulwark', 10]],
        'gear-y8-orb-sun' => ['slot' => 'trinket', 'name' => 'Сфера пантеона', 'sprite' => 'gi-orb-sun', 'atk' => 48, 'def' => 0, 'hp' => 93, 'spd' => 12, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['opener', 60]],

        // Ступень 9: ещё находки с особыми свойствами
        'gear-y9-sword-gold' => ['slot' => 'weapon', 'name' => 'Золотой клинок', 'sprite' => 'gi-sword-gold', 'atk' => 125, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['crit', 13]],
        'gear-y9-mace-blood' => ['slot' => 'weapon', 'name' => 'Булава непобеждённого', 'sprite' => 'gi-mace-blood', 'atk' => 135, 'def' => 0, 'hp' => 38, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['thorns', 18]],
        'gear-y9-staff-sun' => ['slot' => 'weapon', 'name' => 'Посох последнего суда', 'sprite' => 'gi-staff-sun', 'atk' => 102, 'def' => 0, 'hp' => 75, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['regen', 4]],
        'gear-y9-wand-shadow' => ['slot' => 'weapon', 'name' => 'Жезл забвения', 'sprite' => 'gi-wand-shadow', 'atk' => 98, 'def' => 0, 'hp' => 50, 'spd' => 4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-y9-axe-sun' => ['slot' => 'weapon', 'name' => 'Секира рассвета', 'sprite' => 'gi-axe-sun', 'atk' => 140, 'def' => 0, 'hp' => 0, 'spd' => -4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-y9-shield-gold' => ['slot' => 'armor', 'name' => 'Золотая эгида', 'sprite' => 'gi-shield-gold', 'atk' => 0, 'def' => 110, 'hp' => 314, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['thorns', 18]],
        'gear-y9-chest-shadow' => ['slot' => 'armor', 'name' => 'Доспех из тьмы', 'sprite' => 'gi-chest-shadow', 'atk' => 0, 'def' => 92, 'hp' => 370, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11]],
        'gear-y9-cloak-blood' => ['slot' => 'armor', 'name' => 'Багряный плащ', 'sprite' => 'gi-cloak-blood', 'atk' => 0, 'def' => 66, 'hp' => 278, 'spd' => 4, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['dodge', 13]],
        'gear-y9-helm-gold' => ['slot' => 'armor', 'name' => 'Корона-шлем', 'sprite' => 'gi-helm-gold', 'atk' => 0, 'def' => 74, 'hp' => 333, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['phoenix', 36]],
        'gear-y9-chest-void' => ['slot' => 'armor', 'name' => 'Нагрудник конца', 'sprite' => 'gi-chest-void', 'atk' => 0, 'def' => 92, 'hp' => 370, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11]],
        'gear-y9-star-gold' => ['slot' => 'trinket', 'name' => 'Звезда чемпиона', 'sprite' => 'gi-star-gold', 'atk' => 36, 'def' => 22, 'hp' => 185, 'spd' => 22, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['phoenix', 36]],
        'gear-y9-eye-blood' => ['slot' => 'trinket', 'name' => 'Око мстителя', 'sprite' => 'gi-eye-blood', 'atk' => 50, 'def' => 0, 'hp' => 92, 'spd' => 25, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['execute', 45]],
        'gear-y9-vial-sun' => ['slot' => 'trinket', 'name' => 'Эликсир возрождения', 'sprite' => 'gi-vial-sun', 'atk' => 14, 'def' => 0, 'hp' => 296, 'spd' => 14, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['regen', 4]],
        'gear-y9-ring-blood' => ['slot' => 'trinket', 'name' => 'Кольцо клятвы', 'sprite' => 'gi-ring-blood', 'atk' => 47, 'def' => 0, 'hp' => 148, 'spd' => 18, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['crit', 13]],
        'gear-y9-amulet-shadow' => ['slot' => 'trinket', 'name' => 'Амулет безмолвия', 'sprite' => 'gi-amulet-shadow', 'atk' => 18, 'def' => 33, 'hp' => 204, 'spd' => 13, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11]],

        // Ступень 1: находки третьей волны
        'gear-z1-trident-iron' => ['slot' => 'weapon', 'name' => 'Вилы могильщика', 'sprite' => 'gi-trident-iron', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['stun', 8]],
        'gear-z1-trident-bone' => ['slot' => 'weapon', 'name' => 'Костяной трезубец', 'sprite' => 'gi-trident-bone', 'atk' => 4, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['stun', 8]],
        'gear-z1-hammer-bone' => ['slot' => 'weapon', 'name' => 'Берцовая дубина', 'sprite' => 'gi-hammer-bone', 'atk' => 5, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['pierce', 18]],
        'gear-z1-boots-bone' => ['slot' => 'armor', 'name' => 'Костяные сапоги', 'sprite' => 'gi-boots-bone', 'atk' => 0, 'def' => 2, 'hp' => 7, 'spd' => 2, 'stage' => 1, 'rarity' => 'common', 'perk' => ['dodge', 7]],
        'gear-z1-gauntlets-iron' => ['slot' => 'armor', 'name' => 'Ржавые перчатки', 'sprite' => 'gi-gauntlets-iron', 'atk' => 0, 'def' => 2, 'hp' => 7, 'spd' => 0, 'stage' => 1, 'rarity' => 'common', 'perk' => ['rage', 12]],
        'gear-z1-boots-iron' => ['slot' => 'armor', 'name' => 'Стоптанные башмаки', 'sprite' => 'gi-boots-iron', 'atk' => 0, 'def' => 2, 'hp' => 7, 'spd' => 2, 'stage' => 1, 'rarity' => 'common', 'perk' => ['dodge', 7]],
        'gear-z1-skull-bone' => ['slot' => 'trinket', 'name' => 'Череп крысиного короля', 'sprite' => 'gi-skull-bone', 'atk' => 2, 'def' => 0, 'hp' => 4, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['vamp', 8]],
        'gear-z1-lantern-bronze' => ['slot' => 'trinket', 'name' => 'Тусклый фонарь', 'sprite' => 'gi-lantern-bronze', 'atk' => 0, 'def' => 0, 'hp' => 9, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['regen', 2]],
        'gear-z1-medal-bronze' => ['slot' => 'trinket', 'name' => 'Значок стражника', 'sprite' => 'gi-medal-bronze', 'atk' => 1, 'def' => 0, 'hp' => 6, 'spd' => 1, 'stage' => 1, 'rarity' => 'common', 'perk' => ['bulwark', 6]],

        // Ступень 2: находки третьей волны
        'gear-z2-trident-sea' => ['slot' => 'weapon', 'name' => 'Трезубец рыбака', 'sprite' => 'gi-trident-sea', 'atk' => 9, 'def' => 0, 'hp' => 1, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['stun', 9]],
        'gear-z2-trident-bronze' => ['slot' => 'weapon', 'name' => 'Багор', 'sprite' => 'gi-trident-bronze', 'atk' => 9, 'def' => 0, 'hp' => 1, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['stun', 9]],
        'gear-z2-hammer-sea' => ['slot' => 'weapon', 'name' => 'Мокрая кувалда', 'sprite' => 'gi-hammer-sea', 'atk' => 10, 'def' => 1, 'hp' => 0, 'spd' => -1, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['pierce', 20]],
        'gear-z2-boots-sea' => ['slot' => 'armor', 'name' => 'Ласты водолаза', 'sprite' => 'gi-boots-sea', 'atk' => 0, 'def' => 4, 'hp' => 16, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['dodge', 8]],
        'gear-z2-gauntlets-bronze' => ['slot' => 'armor', 'name' => 'Перчатки шахтёра', 'sprite' => 'gi-gauntlets-bronze', 'atk' => 0, 'def' => 5, 'hp' => 16, 'spd' => 0, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['rage', 13]],
        'gear-z2-boots-bronze' => ['slot' => 'armor', 'name' => 'Сапоги шахтёра', 'sprite' => 'gi-boots-bronze', 'atk' => 0, 'def' => 4, 'hp' => 16, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['dodge', 8]],
        'gear-z2-skull-sea' => ['slot' => 'trinket', 'name' => 'Череп утопленника', 'sprite' => 'gi-skull-sea', 'atk' => 3, 'def' => 1, 'hp' => 10, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['vamp', 9]],
        'gear-z2-lantern-sea' => ['slot' => 'trinket', 'name' => 'Подводный фонарь', 'sprite' => 'gi-lantern-sea', 'atk' => 1, 'def' => 1, 'hp' => 21, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['regen', 2]],
        'gear-z2-medal-sea' => ['slot' => 'trinket', 'name' => 'Медаль за спасение', 'sprite' => 'gi-medal-sea', 'atk' => 2, 'def' => 2, 'hp' => 14, 'spd' => 2, 'stage' => 2, 'rarity' => 'rare', 'perk' => ['bulwark', 7]],

        // Ступень 3: находки третьей волны
        'gear-z3-trident-fire' => ['slot' => 'weapon', 'name' => 'Вилы кочегара', 'sprite' => 'gi-trident-fire', 'atk' => 17, 'def' => 0, 'hp' => 2, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['stun', 10]],
        'gear-z3-trident-steel' => ['slot' => 'weapon', 'name' => 'Стальная вилка', 'sprite' => 'gi-trident-steel', 'atk' => 17, 'def' => 0, 'hp' => 2, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['stun', 10]],
        'gear-z3-axe-steel' => ['slot' => 'weapon', 'name' => 'Секира кузнеца', 'sprite' => 'gi-axe-steel', 'atk' => 19, 'def' => 0, 'hp' => 0, 'spd' => -1, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['vamp', 10]],
        'gear-z3-boots-fire' => ['slot' => 'armor', 'name' => 'Сапоги кочегара', 'sprite' => 'gi-boots-fire', 'atk' => 0, 'def' => 7, 'hp' => 28, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['dodge', 8]],
        'gear-z3-gauntlets-fire' => ['slot' => 'armor', 'name' => 'Перчатки горна', 'sprite' => 'gi-gauntlets-fire', 'atk' => 0, 'def' => 10, 'hp' => 28, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['rage', 14]],
        'gear-z3-gauntlets-steel' => ['slot' => 'armor', 'name' => 'Рукавицы мастера', 'sprite' => 'gi-gauntlets-steel', 'atk' => 0, 'def' => 10, 'hp' => 28, 'spd' => 0, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['rage', 14]],
        'gear-z3-skull-fire' => ['slot' => 'trinket', 'name' => 'Пылающий череп', 'sprite' => 'gi-skull-fire', 'atk' => 8, 'def' => 1, 'hp' => 15, 'spd' => 2, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['vamp', 10]],
        'gear-z3-lantern-fire' => ['slot' => 'trinket', 'name' => 'Фонарь огненной шахты', 'sprite' => 'gi-lantern-fire', 'atk' => 2, 'def' => 2, 'hp' => 33, 'spd' => 3, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['regen', 2]],
        'gear-z3-medal-fire' => ['slot' => 'trinket', 'name' => 'Медаль мастера', 'sprite' => 'gi-medal-fire', 'atk' => 5, 'def' => 4, 'hp' => 22, 'spd' => 3, 'stage' => 3, 'rarity' => 'epic', 'perk' => ['bulwark', 7]],

        // Ступень 4: находки третьей волны
        'gear-z4-trident-ice' => ['slot' => 'weapon', 'name' => 'Ледяной трезубец', 'sprite' => 'gi-trident-ice', 'atk' => 33, 'def' => 0, 'hp' => 3, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['stun', 10]],
        'gear-z4-mace-ice' => ['slot' => 'weapon', 'name' => 'Булава мороза', 'sprite' => 'gi-mace-ice', 'atk' => 35, 'def' => 0, 'hp' => 10, 'spd' => -1, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['thorns', 13]],
        'gear-z4-spear-steel' => ['slot' => 'weapon', 'name' => 'Копьё севера', 'sprite' => 'gi-spear-steel', 'atk' => 31, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['pierce', 23]],
        'gear-z4-boots-ice' => ['slot' => 'armor', 'name' => 'Сапоги изо льда', 'sprite' => 'gi-boots-ice', 'atk' => 0, 'def' => 13, 'hp' => 54, 'spd' => 3, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['dodge', 9]],
        'gear-z4-gauntlets-ice' => ['slot' => 'armor', 'name' => 'Перчатки стужи', 'sprite' => 'gi-gauntlets-ice', 'atk' => 0, 'def' => 18, 'hp' => 54, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['rage', 16]],
        'gear-z4-helm-steel' => ['slot' => 'armor', 'name' => 'Шлем стража льдов', 'sprite' => 'gi-helm-steel', 'atk' => 0, 'def' => 19, 'hp' => 81, 'spd' => 0, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['phoenix', 26]],
        'gear-z4-skull-ice' => ['slot' => 'trinket', 'name' => 'Замёрзший череп', 'sprite' => 'gi-skull-ice', 'atk' => 14, 'def' => 2, 'hp' => 31, 'spd' => 4, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['vamp', 10]],
        'gear-z4-lantern-ice' => ['slot' => 'trinket', 'name' => 'Фонарь вечной зимы', 'sprite' => 'gi-lantern-ice', 'atk' => 4, 'def' => 3, 'hp' => 66, 'spd' => 5, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['regen', 3]],
        'gear-z4-medal-ice' => ['slot' => 'trinket', 'name' => 'Ледяная медаль', 'sprite' => 'gi-medal-ice', 'atk' => 9, 'def' => 7, 'hp' => 44, 'spd' => 4, 'stage' => 4, 'rarity' => 'epic', 'perk' => ['bulwark', 8]],

        // Ступень 5: находки третьей волны
        'gear-z5-trident-void' => ['slot' => 'weapon', 'name' => 'Трезубец пустоты', 'sprite' => 'gi-trident-void', 'atk' => 55, 'def' => 0, 'hp' => 5, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['stun', 11]],
        'gear-z5-mace-void' => ['slot' => 'weapon', 'name' => 'Булава небытия', 'sprite' => 'gi-mace-void', 'atk' => 58, 'def' => 0, 'hp' => 16, 'spd' => -1, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['thorns', 14]],
        'gear-z5-hammer-shadow' => ['slot' => 'weapon', 'name' => 'Молот тени', 'sprite' => 'gi-hammer-shadow', 'atk' => 62, 'def' => 5, 'hp' => 0, 'spd' => -2, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['pierce', 25]],
        'gear-z5-boots-void' => ['slot' => 'armor', 'name' => 'Сапоги идущего в пустоту', 'sprite' => 'gi-boots-void', 'atk' => 0, 'def' => 22, 'hp' => 96, 'spd' => 3, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['dodge', 10]],
        'gear-z5-gauntlets-shadow' => ['slot' => 'armor', 'name' => 'Перчатки безликого', 'sprite' => 'gi-gauntlets-shadow', 'atk' => 0, 'def' => 30, 'hp' => 96, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['rage', 17]],
        'gear-z5-helm-void' => ['slot' => 'armor', 'name' => 'Шлем пустоты', 'sprite' => 'gi-helm-void', 'atk' => 0, 'def' => 32, 'hp' => 144, 'spd' => 0, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['phoenix', 28]],
        'gear-z5-skull-void' => ['slot' => 'trinket', 'name' => 'Череп бездны', 'sprite' => 'gi-skull-void', 'atk' => 24, 'def' => 3, 'hp' => 56, 'spd' => 6, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['vamp', 11]],
        'gear-z5-lantern-void' => ['slot' => 'trinket', 'name' => 'Фонарь пустоты', 'sprite' => 'gi-lantern-void', 'atk' => 8, 'def' => 5, 'hp' => 120, 'spd' => 8, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-z5-medal-shadow' => ['slot' => 'trinket', 'name' => 'Медаль забвения', 'sprite' => 'gi-medal-shadow', 'atk' => 16, 'def' => 12, 'hp' => 80, 'spd' => 7, 'stage' => 5, 'rarity' => 'legendary', 'perk' => ['bulwark', 8]],

        // Ступень 6: находки третьей волны
        'gear-z6-trident-sea' => ['slot' => 'weapon', 'name' => 'Трезубец глубин', 'sprite' => 'gi-trident-sea', 'atk' => 70, 'def' => 0, 'hp' => 7, 'spd' => 0, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['stun', 12]],
        'gear-z6-mace-sea' => ['slot' => 'weapon', 'name' => 'Булава прилива', 'sprite' => 'gi-mace-sea', 'atk' => 75, 'def' => 0, 'hp' => 21, 'spd' => -1, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['thorns', 15]],
        'gear-z6-staff-shadow' => ['slot' => 'weapon', 'name' => 'Посох тёмных вод', 'sprite' => 'gi-staff-shadow', 'atk' => 57, 'def' => 0, 'hp' => 41, 'spd' => 0, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-z6-boots-sea' => ['slot' => 'armor', 'name' => 'Сапоги морского дна', 'sprite' => 'gi-boots-sea', 'atk' => 0, 'def' => 28, 'hp' => 120, 'spd' => 4, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['dodge', 10]],
        'gear-z6-gauntlets-sea' => ['slot' => 'armor', 'name' => 'Перчатки рыбака глубин', 'sprite' => 'gi-gauntlets-sea', 'atk' => 0, 'def' => 38, 'hp' => 120, 'spd' => 0, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['rage', 18]],
        'gear-z6-shield-shadow' => ['slot' => 'armor', 'name' => 'Щит тёмной воды', 'sprite' => 'gi-shield-shadow', 'atk' => 0, 'def' => 60, 'hp' => 170, 'spd' => -2, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['thorns', 15]],
        'gear-z6-skull-sea' => ['slot' => 'trinket', 'name' => 'Череп левиафана', 'sprite' => 'gi-skull-sea', 'atk' => 30, 'def' => 4, 'hp' => 70, 'spd' => 8, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['vamp', 12]],
        'gear-z6-lantern-shadow' => ['slot' => 'trinket', 'name' => 'Фонарь на дне', 'sprite' => 'gi-lantern-shadow', 'atk' => 10, 'def' => 6, 'hp' => 150, 'spd' => 10, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-z6-medal-sea' => ['slot' => 'trinket', 'name' => 'Медаль глубин', 'sprite' => 'gi-medal-sea', 'atk' => 20, 'def' => 14, 'hp' => 100, 'spd' => 9, 'stage' => 6, 'rarity' => 'legendary', 'perk' => ['bulwark', 9]],

        // Ступень 7: находки третьей волны
        'gear-z7-trident-fire' => ['slot' => 'weapon', 'name' => 'Вилы дракона', 'sprite' => 'gi-trident-fire', 'atk' => 88, 'def' => 0, 'hp' => 9, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['stun', 13]],
        'gear-z7-trident-gold' => ['slot' => 'weapon', 'name' => 'Золотой трезубец', 'sprite' => 'gi-trident-gold', 'atk' => 88, 'def' => 0, 'hp' => 9, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['stun', 13]],
        'gear-z7-axe-gold' => ['slot' => 'weapon', 'name' => 'Секира сокровищ', 'sprite' => 'gi-axe-gold', 'atk' => 96, 'def' => 0, 'hp' => 0, 'spd' => -3, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['vamp', 13]],
        'gear-z7-boots-fire' => ['slot' => 'armor', 'name' => 'Сапоги из чешуи', 'sprite' => 'gi-boots-fire', 'atk' => 0, 'def' => 34, 'hp' => 150, 'spd' => 4, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['dodge', 11]],
        'gear-z7-gauntlets-gold' => ['slot' => 'armor', 'name' => 'Золотые рукавицы', 'sprite' => 'gi-gauntlets-gold', 'atk' => 0, 'def' => 46, 'hp' => 150, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['rage', 19]],
        'gear-z7-helm-crystal' => ['slot' => 'armor', 'name' => 'Кристальный шлем', 'sprite' => 'gi-helm-crystal', 'atk' => 0, 'def' => 50, 'hp' => 225, 'spd' => 0, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['phoenix', 32]],
        'gear-z7-skull-fire' => ['slot' => 'trinket', 'name' => 'Череп дракона', 'sprite' => 'gi-skull-fire', 'atk' => 38, 'def' => 4, 'hp' => 88, 'spd' => 10, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['vamp', 13]],
        'gear-z7-lantern-gold' => ['slot' => 'trinket', 'name' => 'Фонарь клада', 'sprite' => 'gi-lantern-gold', 'atk' => 12, 'def' => 8, 'hp' => 188, 'spd' => 12, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-z7-medal-gold' => ['slot' => 'trinket', 'name' => 'Золотая медаль', 'sprite' => 'gi-medal-gold', 'atk' => 25, 'def' => 18, 'hp' => 125, 'spd' => 11, 'stage' => 7, 'rarity' => 'legendary', 'perk' => ['bulwark', 10]],

        // Ступень 8: находки третьей волны
        'gear-z8-trident-blood' => ['slot' => 'weapon', 'name' => 'Кровавый трезубец', 'sprite' => 'gi-trident-blood', 'atk' => 107, 'def' => 0, 'hp' => 10, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['stun', 14]],
        'gear-z8-trident-sun' => ['slot' => 'weapon', 'name' => 'Солнечный трезубец', 'sprite' => 'gi-trident-sun', 'atk' => 107, 'def' => 0, 'hp' => 10, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['stun', 14]],
        'gear-z8-staff-blood' => ['slot' => 'weapon', 'name' => 'Посох палача', 'sprite' => 'gi-staff-blood', 'atk' => 86, 'def' => 0, 'hp' => 63, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-z8-boots-blood' => ['slot' => 'armor', 'name' => 'Сапоги палача', 'sprite' => 'gi-boots-blood', 'atk' => 0, 'def' => 42, 'hp' => 186, 'spd' => 5, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['dodge', 12]],
        'gear-z8-gauntlets-sun' => ['slot' => 'armor', 'name' => 'Перчатки жреца', 'sprite' => 'gi-gauntlets-sun', 'atk' => 0, 'def' => 57, 'hp' => 186, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['rage', 20]],
        'gear-z8-helm-sun' => ['slot' => 'armor', 'name' => 'Шлем света', 'sprite' => 'gi-helm-sun', 'atk' => 0, 'def' => 61, 'hp' => 279, 'spd' => 0, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['phoenix', 34]],
        'gear-z8-skull-blood' => ['slot' => 'trinket', 'name' => 'Череп казнённого', 'sprite' => 'gi-skull-blood', 'atk' => 45, 'def' => 5, 'hp' => 108, 'spd' => 12, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-z8-lantern-sun' => ['slot' => 'trinket', 'name' => 'Фонарь пантеона', 'sprite' => 'gi-lantern-sun', 'atk' => 15, 'def' => 9, 'hp' => 232, 'spd' => 15, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['regen', 3]],
        'gear-z8-medal-blood' => ['slot' => 'trinket', 'name' => 'Знак палача', 'sprite' => 'gi-medal-blood', 'atk' => 30, 'def' => 22, 'hp' => 155, 'spd' => 14, 'stage' => 8, 'rarity' => 'legendary', 'perk' => ['bulwark', 10]],

        // Ступень 9: находки третьей волны
        'gear-z9-trident-gold' => ['slot' => 'weapon', 'name' => 'Трезубец чемпиона', 'sprite' => 'gi-trident-gold', 'atk' => 128, 'def' => 0, 'hp' => 12, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['stun', 14]],
        'gear-z9-trident-void' => ['slot' => 'weapon', 'name' => 'Трезубец конца', 'sprite' => 'gi-trident-void', 'atk' => 128, 'def' => 0, 'hp' => 12, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['stun', 14]],
        'gear-z9-staff-void' => ['slot' => 'weapon', 'name' => 'Посох пустоты чемпиона', 'sprite' => 'gi-staff-void', 'atk' => 102, 'def' => 0, 'hp' => 75, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['regen', 4]],
        'gear-z9-mace-gold' => ['slot' => 'weapon', 'name' => 'Булава победителя', 'sprite' => 'gi-mace-gold', 'atk' => 135, 'def' => 0, 'hp' => 38, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['thorns', 18]],
        'gear-z9-spear-blood' => ['slot' => 'weapon', 'name' => 'Копьё мстителя', 'sprite' => 'gi-spear-blood', 'atk' => 120, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['pierce', 32]],
        'gear-z9-boots-gold' => ['slot' => 'armor', 'name' => 'Сапоги чемпиона', 'sprite' => 'gi-boots-gold', 'atk' => 0, 'def' => 51, 'hp' => 222, 'spd' => 5, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['dodge', 13]],
        'gear-z9-gauntlets-gold' => ['slot' => 'armor', 'name' => 'Золотые перчатки победителя', 'sprite' => 'gi-gauntlets-gold', 'atk' => 0, 'def' => 69, 'hp' => 222, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['rage', 22]],
        'gear-z9-gauntlets-blood' => ['slot' => 'armor', 'name' => 'Перчатки мстителя', 'sprite' => 'gi-gauntlets-blood', 'atk' => 0, 'def' => 69, 'hp' => 222, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['rage', 22]],
        'gear-z9-helm-void' => ['slot' => 'armor', 'name' => 'Шлем конца', 'sprite' => 'gi-helm-void', 'atk' => 0, 'def' => 74, 'hp' => 333, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['phoenix', 36]],
        'gear-z9-shield-shadow' => ['slot' => 'armor', 'name' => 'Щит безмолвия', 'sprite' => 'gi-shield-shadow', 'atk' => 0, 'def' => 110, 'hp' => 314, 'spd' => -2, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['thorns', 18]],
        'gear-z9-skull-gold' => ['slot' => 'trinket', 'name' => 'Череп чемпиона', 'sprite' => 'gi-skull-gold', 'atk' => 54, 'def' => 7, 'hp' => 130, 'spd' => 14, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-z9-lantern-sun' => ['slot' => 'trinket', 'name' => 'Фонарь рассвета', 'sprite' => 'gi-lantern-sun', 'atk' => 18, 'def' => 11, 'hp' => 278, 'spd' => 18, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['regen', 4]],
        'gear-z9-medal-gold' => ['slot' => 'trinket', 'name' => 'Медаль чемпиона', 'sprite' => 'gi-medal-gold', 'atk' => 36, 'def' => 26, 'hp' => 185, 'spd' => 16, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11]],
        'gear-z9-skull-shadow' => ['slot' => 'trinket', 'name' => 'Череп забытого', 'sprite' => 'gi-skull-shadow', 'atk' => 54, 'def' => 7, 'hp' => 130, 'spd' => 14, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['vamp', 14]],
        'gear-z9-medal-void' => ['slot' => 'trinket', 'name' => 'Медаль конца', 'sprite' => 'gi-medal-void', 'atk' => 36, 'def' => 26, 'hp' => 185, 'spd' => 16, 'stage' => 9, 'rarity' => 'legendary', 'perk' => ['bulwark', 11]],

        // Реликвии: падают только с боссов (шанс RELIC_CHANCE), два свойства сразу
        'gear-r1-dagger-bone' => ['slot' => 'weapon', 'name' => 'Зуб первого мертвеца', 'sprite' => 'gi-dagger-bone', 'atk' => 5, 'def' => 0, 'hp' => 0, 'spd' => 1, 'stage' => 1, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['vamp', 10], 'perk2' => ['thorns', 13]],
        'gear-r2-trident-sea' => ['slot' => 'weapon', 'name' => 'Трезубец Утонувшего Короля', 'sprite' => 'gi-trident-sea', 'atk' => 11, 'def' => 0, 'hp' => 1, 'spd' => 0, 'stage' => 2, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['pierce', 26], 'perk2' => ['vamp', 11]],
        'gear-r3-hammer-fire' => ['slot' => 'weapon', 'name' => 'Молот Первой Наковальни', 'sprite' => 'gi-hammer-fire', 'atk' => 24, 'def' => 2, 'hp' => 0, 'spd' => -1, 'stage' => 3, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['stun', 12], 'perk2' => ['execute', 39]],
        'gear-r4-crown-ice' => ['slot' => 'trinket', 'name' => 'Корона Вечной Зимы', 'sprite' => 'gi-crown-ice', 'atk' => 8, 'def' => 10, 'hp' => 68, 'spd' => 5, 'stage' => 4, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['bulwark', 10], 'perk2' => ['regen', 3]],
        'gear-r5-eye-void' => ['slot' => 'trinket', 'name' => 'Око Вечной Тьмы', 'sprite' => 'gi-eye-void', 'atk' => 26, 'def' => 0, 'hp' => 48, 'spd' => 11, 'stage' => 5, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['crit', 13], 'perk2' => ['opener', 64]],
        'gear-r6-lantern-sea' => ['slot' => 'trinket', 'name' => 'Фонарь Последнего Корабля', 'sprite' => 'gi-lantern-sea', 'atk' => 12, 'def' => 7, 'hp' => 180, 'spd' => 10, 'stage' => 6, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['dodge', 14], 'perk2' => ['regen', 4]],
        'gear-r7-boots-gold' => ['slot' => 'armor', 'name' => 'Сапоги Хранителя Клада', 'sprite' => 'gi-boots-gold', 'atk' => 0, 'def' => 41, 'hp' => 180, 'spd' => 4, 'stage' => 7, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['dodge', 15], 'perk2' => ['rage', 25]],
        'gear-r7-book-crystal' => ['slot' => 'trinket', 'name' => 'Книга Преломлений', 'sprite' => 'gi-book-crystal', 'atk' => 38, 'def' => 11, 'hp' => 120, 'spd' => 11, 'stage' => 7, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['crit', 15], 'perk2' => ['stun', 17]],
        'gear-r8-greatsword-sun' => ['slot' => 'weapon', 'name' => 'Клинок Пантеона', 'sprite' => 'gi-greatsword-sun', 'atk' => 149, 'def' => 0, 'hp' => 0, 'spd' => -4, 'stage' => 8, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['execute', 55], 'perk2' => ['vamp', 18]],
        'gear-r8-skull-blood' => ['slot' => 'trinket', 'name' => 'Череп Последнего Палача', 'sprite' => 'gi-skull-blood', 'atk' => 54, 'def' => 6, 'hp' => 130, 'spd' => 12, 'stage' => 8, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['phoenix', 44], 'perk2' => ['thorns', 22]],
        'gear-r9-crown-gold' => ['slot' => 'trinket', 'name' => 'Корона Чемпиона', 'sprite' => 'gi-crown-gold', 'atk' => 35, 'def' => 35, 'hp' => 288, 'spd' => 18, 'stage' => 9, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['phoenix', 47], 'perk2' => ['crit', 16]],
        'gear-r9-gauntlets-void' => ['slot' => 'armor', 'name' => 'Перчатки Конца Времён', 'sprite' => 'gi-gauntlets-void', 'atk' => 0, 'def' => 83, 'hp' => 266, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['stun', 19], 'perk2' => ['pierce', 42]],
        'gear-r9-medal-sun' => ['slot' => 'trinket', 'name' => 'Медаль Бессмертного', 'sprite' => 'gi-medal-sun', 'atk' => 43, 'def' => 31, 'hp' => 222, 'spd' => 16, 'stage' => 9, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['bulwark', 14], 'perk2' => ['phoenix', 47]],
        'gear-r9-sword-shadow' => ['slot' => 'weapon', 'name' => 'Клинок Забытого Героя', 'sprite' => 'gi-sword-shadow', 'atk' => 150, 'def' => 0, 'hp' => 0, 'spd' => 0, 'stage' => 9, 'rarity' => 'legendary', 'relic' => true, 'perk' => ['crit', 16], 'perk2' => ['vamp', 19]],
    ];

    /** Шанс (в %), что с босса вместо обычной вещи выпадет реликвия его ступени. */
    public const RELIC_CHANCE = 6;

    /** Особые свойства вещей: действуют в бою подземелья. {v} — значение предмета. */
    public const PERKS = [
        'vamp' => ['name' => 'Вампиризм', 'text' => 'Лечит на {v}% нанесённого урона'],
        'crit' => ['name' => 'Меткость', 'text' => '+{v}% к шансу крита'],
        'pierce' => ['name' => 'Пробитие', 'text' => 'Игнорирует {v}% защиты врага'],
        'execute' => ['name' => 'Добивание', 'text' => '+{v}% урона по врагу ниже 30% здоровья'],
        'opener' => ['name' => 'Первый удар', 'text' => '+{v}% урона в первом раунде боя'],
        'regen' => ['name' => 'Регенерация', 'text' => 'Лечит {v}% здоровья в конце раунда'],
        'thorns' => ['name' => 'Шипы', 'text' => 'Возвращает врагу {v}% полученного урона'],
        'bulwark' => ['name' => 'Бастион', 'text' => 'Снижает получаемый урон на {v}%'],
        'dodge' => ['name' => 'Уклонение', 'text' => '{v}% шанс избежать удара'],
        'phoenix' => ['name' => 'Феникс', 'text' => 'Раз за бой воскресает с {v}% здоровья'],
        'stun' => ['name' => 'Оглушение', 'text' => '{v}% шанс оглушить врага ударом'],
        'rage' => ['name' => 'Ярость', 'text' => '+{v} ярости за каждый удар'],
    ];

    /** Бонус комплекта: надеты 2 вещи — все характеристики вещей выше, 3 вещи — ещё и свойство. */
    public const SET_STATS = [2 => 8, 3 => 20];

    /** Комплекты: название, вещи по слотам и свойство за три вещи. */
    public const SETS = [
        'grave' => ['name' => 'Могильщик', 'pieces' => ['gear-x1-axe-iron', 'gear-x1-vest-bone', 'gear-x1-ring-bronze'], 'perk' => ['thorns', 15]],
        'drowned' => ['name' => 'Утопленник', 'pieces' => ['gear-x2-spear-sea', 'gear-x2-chest-bronze', 'gear-x2-amulet-sea'], 'perk' => ['dodge', 10]],
        'forge' => ['name' => 'Горновой', 'pieces' => ['gear-x3-axe-fire', 'gear-x3-chest-steel', 'gear-x3-ring-fire'], 'perk' => ['pierce', 25]],
        'frost' => ['name' => 'Ледяной король', 'pieces' => ['gear-x4-spear-ice', 'gear-x4-chest-ice', 'gear-x4-amulet-ice'], 'perk' => ['bulwark', 12]],
        'void' => ['name' => 'Пустотник', 'pieces' => ['gear-x5-dagger-void', 'gear-x5-chest-void', 'gear-x5-eye-void'], 'perk' => ['opener', 60]],
        'leviathan' => ['name' => 'Левиафан', 'pieces' => ['gear-x6-spear-sea', 'gear-x6-chest-sea', 'gear-x6-orb-sea'], 'perk' => ['regen', 5]],
        'dragon' => ['name' => 'Драконоборец', 'pieces' => ['gear-x7-greatsword-fire', 'gear-x7-chest-gold', 'gear-x7-crown-gold'], 'perk' => ['execute', 40]],
        'headsman' => ['name' => 'Палач', 'pieces' => ['gear-x8-axe-blood', 'gear-x8-chest-blood', 'gear-x8-crown-sun'], 'perk' => ['vamp', 14]],
        'champion' => ['name' => 'Чемпион', 'pieces' => ['gear-x9-greatsword-gold', 'gear-x9-chest-gold', 'gear-x9-ring-gold'], 'perk' => ['phoenix', 40]],
    ];

    /** Материалы для крафта: по одному на тир подземелья. */
    public const MATERIALS = [
        'claw' => ['name' => 'Коготь', 'stage' => 1, 'sprite' => 'mat-claw'],
        'scale' => ['name' => 'Чешуя', 'stage' => 2, 'sprite' => 'mat-scale'],
        'magma' => ['name' => 'Магма', 'stage' => 3, 'sprite' => 'mat-magma'],
        'frost' => ['name' => 'Иней', 'stage' => 4, 'sprite' => 'mat-frost'],
        'void' => ['name' => 'Пустота', 'stage' => 5, 'sprite' => 'mat-void'],
        'abyss' => ['name' => 'Осколок бездны', 'stage' => 6, 'sprite' => 'mat-abyss'],
        'dragon' => ['name' => 'Золото дракона', 'stage' => 7, 'sprite' => 'mat-dragon'],
        'crown' => ['name' => 'Осколок короны', 'stage' => 8, 'sprite' => 'mat-crown'],

        // Ядра боссов: падают только с боссов своего подземелья и нужны для редких рецептов
        'core_catacombs' => ['name' => 'Крысиная корона', 'stage' => 1, 'core' => true, 'dungeon' => 'catacombs', 'sprite' => 'mat-core-catacombs'],
        'core_sunken' => ['name' => 'Жемчуг старейшины', 'stage' => 2, 'core' => true, 'dungeon' => 'sunken', 'sprite' => 'mat-core-sunken'],
        'core_forge' => ['name' => 'Сердце горна', 'stage' => 3, 'core' => true, 'dungeon' => 'forge', 'sprite' => 'mat-core-forge'],
        'core_crypt' => ['name' => 'Ледяное сердце', 'stage' => 4, 'core' => true, 'dungeon' => 'crypt', 'sprite' => 'mat-core-crypt'],
        'core_void' => ['name' => 'Око разлома', 'stage' => 5, 'core' => true, 'dungeon' => 'void', 'sprite' => 'mat-core-void'],
        'core_abyss' => ['name' => 'Слеза бездны', 'stage' => 6, 'core' => true, 'dungeon' => 'abyss', 'sprite' => 'mat-core-abyss'],
        'core_lair' => ['name' => 'Зуб древнего дракона', 'stage' => 7, 'core' => true, 'dungeon' => 'lair', 'sprite' => 'mat-core-lair'],
        'sovereign' => ['name' => 'Сердце владыки', 'stage' => 9, 'sprite' => 'mat-sovereign'],
        // Пропуск в Рублёвые копи: привязан к аккаунту, не продаётся и не крафтится
        'ruble_pass' => ['name' => 'Пропуск в Рублёвые копи', 'stage' => 9, 'bound' => true, 'sprite' => 'mat-pass'],
        'core_crystal' => ['name' => 'Грань-сердце', 'stage' => 7, 'core' => true, 'dungeon' => 'crystal', 'sprite' => 'mat-core-crystal'],
        'core_pantheon' => ['name' => 'Лик забытого бога', 'stage' => 8, 'core' => true, 'dungeon' => 'pantheon', 'sprite' => 'mat-core-pantheon'],
        'core_trial' => ['name' => 'Знак испытания', 'stage' => 9, 'core' => true, 'dungeon' => 'trial', 'sprite' => 'mat-core-trial'],
        'core_rubledeep' => ['name' => 'Золотой слиток', 'stage' => 9, 'core' => true, 'dungeon' => 'rubledeep', 'sprite' => 'mat-core-rubledeep'],
        'core_throne' => ['name' => 'Печать владыки', 'stage' => 8, 'core' => true, 'dungeon' => 'throne', 'sprite' => 'mat-core-throne'],
    ];

    /** Ядро босса этого подземелья. */
    public static function coreFor(string $dungeon): ?string
    {
        return 'core_' . $dungeon;
    }

    /** Материал, который падает в подземелье этой ступени. */
    public static function materialForStage(int $stage): ?string
    {
        foreach (self::MATERIALS as $key => $material) {
            if (! empty($material['core']) || ! empty($material['bound'])) {
                continue;
            }

            if ($material['stage'] === $stage) {
                return $key;
            }
        }

        return null;
    }

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
        6 => ['common' => 5, 'rare' => 30, 'epic' => 42, 'legendary' => 23],
        7 => ['common' => 3, 'rare' => 25, 'epic' => 44, 'legendary' => 28],
        8 => ['rare' => 20, 'epic' => 45, 'legendary' => 35],
        9 => ['epic' => 40, 'legendary' => 60],
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
    public const EXTRA_RUN_BASE = 2500;
    public const EXTRA_RUN_STEP = 1200;

    /** Отдых между этажами: доля от максимума здоровья отряда. */
    public const REST_SHARE = 0.15;

    /** Монеты за моба: база × этаж. */
    public const COINS_PER_MOB = [1 => 30, 2 => 70, 3 => 130, 4 => 200, 5 => 300, 6 => 380, 7 => 470, 8 => 560, 9 => 650];

    /** Монеты за босса: база × номер босса. */
    public const COINS_PER_BOSS = [1 => 200, 2 => 450, 3 => 800, 4 => 1200, 5 => 1800, 6 => 2300, 7 => 2900, 8 => 3500, 9 => 4200];

    /**
     * Штраф за поражение по ступени. Отступление бесплатно — за него
     * ничего не списываем, теряется только попытка.
     */
    public const WIPE_PENALTY = [1 => 80, 2 => 200, 3 => 450, 4 => 800, 5 => 1400, 6 => 1800, 7 => 2300, 8 => 2900, 9 => 3600];

    /**
     * Опыт питомцам: за моба и за босса. Дальше его умножает уровень
     * питомца и режет суточный предел (PetService::dailyXpCap).
     */
    public const XP_PER_MOB = 3;
    public const XP_PER_BOSS = 12;

    /**
     * Шансы добычи в процентах. `gear` — шанс предмета экипировки,
     * `boss_gear` — шанс с босса (босс даёт всегда), `key` — ключ кейса.
     */
    public const DROPS = [
        1 => ['gear' => 10, 'key' => 2, 'key_slug' => 'seeker', 'key_pet' => 0.1, 'key_pet_slug' => 'royal'],
        2 => ['gear' => 15, 'key' => 6, 'key_slug' => 'seeker', 'key_pet' => 0.3, 'key_pet_slug' => 'royal'],
        3 => ['gear' => 20, 'key' => 12, 'key_slug' => 'seeker', 'key_pet' => 0.7, 'key_pet_slug' => 'royal'],
        4 => ['gear' => 25, 'key' => 18, 'key_slug' => 'seeker', 'key_pet' => 1.2, 'key_pet_slug' => 'royal'],
        5 => ['gear' => 30, 'key' => 22, 'key_slug' => 'seeker', 'key_pet' => 1.8, 'key_pet_slug' => 'royal'],
        6 => ['gear' => 32, 'key' => 25, 'key_slug' => 'seeker', 'key_pet' => 2, 'key_pet_slug' => 'royal'],
        7 => ['gear' => 34, 'key' => 28, 'key_slug' => 'seeker', 'key_pet' => 2.2, 'key_pet_slug' => 'royal'],
        8 => ['gear' => 36, 'key' => 30, 'key_slug' => 'seeker', 'key_pet' => 2.5, 'key_pet_slug' => 'royal'],
        9 => ['gear' => 38, 'key' => 32, 'key_slug' => 'seeker', 'key_pet' => 3, 'key_pet_slug' => 'royal'],
    ];

    /** Шанс ядра с босса, в процентах. */
    public const CORE_CHANCE = 35;

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
            fn (array $gear) => empty($gear['relic']) && $gear['stage'] >= $from && $gear['stage'] <= $stage,
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
        if ($boss && random_int(1, 100) <= self::RELIC_CHANCE) {
            $relics = array_keys(array_filter(self::GEAR, fn (array $gear) => ! empty($gear['relic']) && $gear['stage'] === $stage));

            if ($relics) {
                return $relics[array_rand($relics)];
            }
        }

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

    /** Сколько вещей каждого комплекта надето: ключ комплекта => число. */
    public static function setCounts(array $slugs): array
    {
        $counts = [];

        foreach ($slugs as $slug) {
            $set = self::GEAR[$slug]['set'] ?? null;

            if ($set) {
                $counts[$set] = ($counts[$set] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** Прибавка характеристик вещей от комплектов, в процентах. */
    public static function setStatPercent(array $slugs): int
    {
        $percent = 0;

        foreach (self::setCounts($slugs) as $count) {
            $percent += self::SET_STATS[min(3, $count)] ?? 0;
        }

        return $percent;
    }

    /**
     * Свойства надетых вещей: ключ свойства => суммарное значение.
     * Комплект из трёх вещей добавляет своё свойство.
     *
     * @param  array<int, string>  $slugs
     * @return array<string, int>
     */
    public static function perksFor(array $slugs): array
    {
        $perks = [];

        foreach ($slugs as $slug) {
            foreach (['perk', 'perk2'] as $field) {
                if ($perk = self::GEAR[$slug][$field] ?? null) {
                    $perks[$perk[0]] = ($perks[$perk[0]] ?? 0) + $perk[1];
                }
            }
        }

        foreach (self::setCounts($slugs) as $set => $count) {
            if ($count >= 3 && isset(self::SETS[$set])) {
                [$key, $value] = self::SETS[$set]['perk'];
                $perks[$key] = ($perks[$key] ?? 0) + $value;
            }
        }

        return $perks;
    }

    /** Текст свойства для карточки предмета. */
    public static function perkText(string $key, int $value): string
    {
        return str_replace('{v}', (string) $value, self::PERKS[$key]['text'] ?? '');
    }
}
