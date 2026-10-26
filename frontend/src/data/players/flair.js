/**
 * Пятая волна косметики: питомцы профиля и титулы под ником.
 * Ключи совпадают с effect_value в ShopItemSeeder.
 */

/**
 * Питомцы: спрайт из pixelSprites.js, реплики при наведении,
 * fly — летает, а не ходит по нижнему краю шапки.
 */
export const PROFILE_PETS = {
    cat: { name: 'Котик', sprite: 'cat', phrases: ['Мяу!', 'Мурр…', 'Покорми меня', '*игнорирует тебя*', 'Это моё место'] },
    dog: { name: 'Пёсик', sprite: 'dog', phrases: ['Гав!', 'Кто хороший мальчик?', 'Пойдём гулять!', '*виляет хвостом*'] },
    fox: { name: 'Лисёнок', sprite: 'fox', phrases: ['Что говорит лиса?', 'Фыр!', 'Хвост пушистый!', '*хитро смотрит*'] },
    axolotl: { name: 'Аксолотль', sprite: 'axolotl', phrases: ['Блуп', ':3', 'Я улыбаюсь всегда', 'Буль-буль'] },
    slime: { name: 'Слизень', sprite: 'slime', phrases: ['*бульк*', 'Обнимашки?', 'Я липкий', 'Прыг!'] },
    ghost: { name: 'Призрак', sprite: 'ghost', fly: true, phrases: ['Бу!', 'Я не страшный…', 'Ууууу', 'Испугался?'] },
    dragon: { name: 'Дракончик', sprite: 'dragon', fly: true, phrases: ['Ррр!', '*пых*', 'Где моё золото?', 'Я грозный!'] },
    robot: { name: 'Робот', sprite: 'robot', phrases: ['01001000 01001001', 'Загрузка… 99%', 'Бип-буп', 'Запрос принят'] },
    penguin: { name: 'Пингвин', sprite: 'penguin', phrases: ['Холодно!', '*шлёп-шлёп*', 'Рыбка?', 'Где мой айсберг?'] },
    bee: { name: 'Пчёлка', sprite: 'bee', fly: true, phrases: ['Жжж!', 'Где цветочки?', 'Мёд!', 'Не бойся, не ужалю'] },
}

export function profilePet(id) {
    return id ? PROFILE_PETS[id] ?? null : null
}

/**
 * Титулы под ником: текст и оформление (классы name-fx--* из
 * assets/nameEffects.css; plain — без эффекта).
 */
export const PROFILE_TITLES = {
    nether_lord: { text: 'Повелитель Незера', style: 'fire' },
    night_hunter: { text: 'Ночной охотник', style: 'shadow' },
    server_legend: { text: 'Легенда сервера', style: 'gold' },
    pvp_god: { text: 'Бог PvP', style: 'rainbow' },
    bridge_master: { text: 'Мастер бриджа', style: 'neon' },
    clan_general: { text: 'Генерал клана', style: 'chrome' },
    sweet_bun: { text: 'Сладкая булочка', style: 'sakura' },
    chaos_agent: { text: 'Агент хаоса', style: 'glitch' },
    star_child: { text: 'Дитя звёзд', style: 'galaxy' },
    ice_heart: { text: 'Ледяное сердце', style: 'ice' },
    toxic: { text: 'Токсик', style: 'toxic' },
    eternal_noob: { text: 'Вечный нуб', style: 'plain' },
    bread: { text: 'Ушёл за хлебом', style: 'plain' },
    creeper_friend: { text: 'Друг криперов', style: 'wave' },
}

/** Значки у ника: пиксельный спрайт и его анимация. */
export const NAME_ICONS = {
    crown: { sprite: 'crown', anim: 'glow', glow: '#facc15' },
    flame: { sprite: 'flame', anim: 'bounce', glow: '#f97316' },
    diamond: { sprite: 'diamond', anim: 'spin', glow: '#67e8f9' },
    heart: { sprite: 'heart', anim: 'pulse', glow: '#ef4444' },
    skull: { sprite: 'skull', anim: 'glow', glow: '#ef4444' },
    creeper: { sprite: 'creeper', anim: 'wiggle' },
    bee: { sprite: 'bee', anim: 'bounce' },
    cat: { sprite: 'cat', anim: 'wiggle' },
    totem: { sprite: 'totem', anim: 'glow', glow: '#facc15' },
    tnt: { sprite: 'tnt', anim: 'shake' },
    warden: { sprite: 'warden', anim: 'pulse' },
    ghost: { sprite: 'ghost', anim: 'bounce' },
    dragon: { sprite: 'dragon', anim: 'glow', glow: '#8b5cf6' },
    axolotl: { sprite: 'axolotl', anim: 'bounce' },
}

export function nameIcon(id) {
    return id ? NAME_ICONS[id] ?? null : null
}

export function profileTitle(id) {
    return id ? PROFILE_TITLES[id] ?? null : null
}
