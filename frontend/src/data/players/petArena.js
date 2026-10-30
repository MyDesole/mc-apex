/**
 * Стихии арены питомцев — как в PetArenaService.php.
 * Огонь бьёт природу, природа — воду, вода — огонь, тень — зверя.
 */
export const ELEMENTS = {
    fire: { name: 'Огонь', color: '#f97316', skill: 'Пламя: урон ×1,7, может поджечь' },
    water: { name: 'Вода', color: '#38bdf8', skill: 'Волна: урон, лечение, смывает статусы' },
    nature: { name: 'Природа', color: '#4ade80', skill: 'Шипы: возврат урона' },
    shadow: { name: 'Тень', color: '#a78bfa', skill: 'Тень: сквозь защиту, пугает' },
    beast: { name: 'Зверь', color: '#fbbf24', skill: 'Рывок: шанс крита, крит ранит' },
}

/** Арены боя: цвета сцены. Названия и особенности приходят с сервера. */
export const ARENA_THEMES = {
    colosseum: { sky: '#3b2f1d', ground: '#a8865a', glow: 'rgba(250, 204, 21, 0.18)' },
    volcano: { sky: '#3a0d07', ground: '#7c2d12', glow: 'rgba(249, 115, 22, 0.28)' },
    ocean: { sky: '#071a33', ground: '#0e4a6e', glow: 'rgba(56, 189, 248, 0.24)' },
    forest: { sky: '#0b2414', ground: '#2f5d27', glow: 'rgba(74, 222, 128, 0.2)' },
    crypt: { sky: '#120b1f', ground: '#2e2440', glow: 'rgba(167, 139, 250, 0.24)' },
    savanna: { sky: '#3a2a0c', ground: '#b08a3e', glow: 'rgba(251, 191, 36, 0.22)' },
}

/** Статусы: цвет значка. */
export const STATUS_COLORS = {
    burn: '#f97316',
    bleed: '#ef4444',
    fear: '#a78bfa',
    wet: '#38bdf8',
    stun: '#facc15',
}

/** Опыт, с которого начинается уровень: 0, 5, 15, 30… */
export function levelXp(level) {
    return (5 * level * (level - 1)) / 2
}

export const PET_NAMES = {
    cat: 'Котик', dog: 'Пёсик', fox: 'Лисёнок', axolotl: 'Аксолотль', slime: 'Слизень',
    ghost: 'Призрак', dragon: 'Дракончик', robot: 'Робот', penguin: 'Пингвин', bee: 'Пчёлка',
    crow: 'Ворона', owl: 'Совёнок', frog: 'Лягушонок', bunny: 'Зайчик', panda: 'Панда', shroom: 'Грибочек',
    octopus: 'Осьминожка', capybara: 'Капибара', jelly: 'Медуза', phoenix: 'Феникс', unicorn: 'Единорог',
}
