<?php

namespace App\Domains\Players\Support;

/**
 * Названия видов питомцев — как во frontend/src/data/players/flair.js.
 * Нужны, когда у экземпляра нет своего имени (арена, топ, питомцедекс).
 */
class PetNames
{
    public const SPECIES = [
        'cat' => 'Котик', 'dog' => 'Пёсик', 'fox' => 'Лисёнок', 'axolotl' => 'Аксолотль', 'slime' => 'Слизень',
        'ghost' => 'Призрак', 'dragon' => 'Дракончик', 'robot' => 'Робот', 'penguin' => 'Пингвин', 'bee' => 'Пчёлка',
        'owl' => 'Совёнок', 'frog' => 'Лягушонок', 'bunny' => 'Зайчик', 'panda' => 'Панда', 'shroom' => 'Грибочек',
        'octopus' => 'Осьминожка', 'capybara' => 'Капибара', 'jelly' => 'Медуза', 'phoenix' => 'Феникс', 'unicorn' => 'Единорог',
    ];

    public static function species(?string $key): string
    {
        return self::SPECIES[$key] ?? 'Питомец';
    }
}
