/**
 * Стихии арены питомцев — как в PetArenaService.php.
 * Огонь бьёт природу, природа — воду, вода — огонь, тень — зверя.
 */
export const ELEMENTS = {
    fire: { name: 'Огонь', color: '#f97316', skill: 'Пламя: урон ×1,7' },
    water: { name: 'Вода', color: '#38bdf8', skill: 'Волна: урон и лечение' },
    nature: { name: 'Природа', color: '#4ade80', skill: 'Шипы: возврат урона' },
    shadow: { name: 'Тень', color: '#a78bfa', skill: 'Тень: сквозь защиту' },
    beast: { name: 'Зверь', color: '#fbbf24', skill: 'Рывок: шанс крита' },
}

/** Опыт, с которого начинается уровень: 0, 5, 15, 30… */
export function levelXp(level) {
    return (5 * level * (level - 1)) / 2
}

export const PET_NAMES = {
    cat: 'Котик', dog: 'Пёсик', fox: 'Лисёнок', axolotl: 'Аксолотль', slime: 'Слизень',
    ghost: 'Призрак', dragon: 'Дракончик', robot: 'Робот', penguin: 'Пингвин', bee: 'Пчёлка',
    owl: 'Совёнок', frog: 'Лягушонок', bunny: 'Зайчик', panda: 'Панда', shroom: 'Грибочек',
    octopus: 'Осьминожка', capybara: 'Капибара', jelly: 'Медуза', phoenix: 'Феникс', unicorn: 'Единорог',
}
