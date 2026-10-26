export const AVATAR_FRAMES = [
    { id: 'default', name: 'Обычная', color: 'transparent', rarity: 'common' },
    { id: 'purple', name: 'Фиолетовая', color: '#7c3aed', rarity: 'common' },
    { id: 'cyan', name: 'Голубая', color: '#06b6d4', rarity: 'common' },
    { id: 'green', name: 'Зелёная', color: '#22c55e', rarity: 'common' },
    { id: 'gold', name: 'Золотая', color: '#facc15', rarity: 'rare' },
    { id: 'orange', name: 'Оранжевая', color: '#f97316', rarity: 'rare' },
    { id: 'pink', name: 'Розовая', color: '#ec4899', rarity: 'rare' },
    { id: 'red', name: 'Красная', color: '#ef4444', rarity: 'rare' },
    { id: 'rainbow', name: 'Радужная', gradient: 'linear-gradient(135deg, #ef4444, #facc15, #22c55e, #06b6d4, #7c3aed)', rarity: 'epic' },
    { id: 'legendary', name: 'Легендарная', gradient: 'linear-gradient(135deg, #facc15, #f97316, #ef4444)', rarity: 'legendary', glow: true },
    { id: 'season1', name: 'Сезон 1', gradient: 'linear-gradient(135deg, #7c3aed, #06b6d4)', rarity: 'epic' },

    // Рамки в стиле блоков Minecraft
    { id: 'emerald', name: 'Изумрудная', color: '#10b981', rarity: 'rare' },
    { id: 'redstone', name: 'Редстоун', color: '#dc2626', rarity: 'rare' },
    // color у градиентных рамок — базовый цвет для подсветки аватара
    { id: 'diamond', name: 'Алмазная', color: '#22d3ee', gradient: 'linear-gradient(135deg, #a5f3fc, #22d3ee, #0e7490)', rarity: 'epic' },
    { id: 'obsidian', name: 'Обсидиановая', color: '#6d28d9', gradient: 'linear-gradient(135deg, #1e1b2e, #6d28d9, #1e1b2e)', rarity: 'epic' },
    { id: 'ender', name: 'Края', color: '#14b8a6', gradient: 'linear-gradient(135deg, #14b8a6, #0f172a, #a855f7)', rarity: 'epic' },
    { id: 'netherite', name: 'Незеритовая', color: '#a8a29e', gradient: 'linear-gradient(135deg, #57534e, #a8a29e, #44403c, #78716c)', rarity: 'legendary', glow: true },

    /*
     * Милые рамки с украшениями: decor рисует AvatarFrameDecor —
     * цветочки, сердечки или звёздочки по кругу аватара.
     */
    { id: 'sakura', name: 'Сакура', color: '#f9a8d4', gradient: 'linear-gradient(135deg, #fbcfe8, #f472b6, #fce7f3)', rarity: 'epic', decor: { shape: 'flower', colors: ['#fbcfe8', '#f9a8d4', '#fdf2f8'], center: '#fbbf24' } },
    { id: 'daisy', name: 'Ромашки', color: '#fde68a', gradient: 'linear-gradient(135deg, #bbf7d0, #fef9c3, #86efac)', rarity: 'rare', decor: { shape: 'flower', colors: ['#ffffff', '#fefce8'], center: '#facc15' } },
    { id: 'hearts', name: 'Сердечки', color: '#fb7185', gradient: 'linear-gradient(135deg, #fda4af, #fb7185, #fecdd3)', rarity: 'rare', decor: { shape: 'heart', colors: ['#fb7185', '#f43f5e', '#fda4af'] } },
    { id: 'marshmallow', name: 'Зефир', color: '#c4b5fd', gradient: 'linear-gradient(135deg, #fbcfe8, #c4b5fd, #bae6fd, #bbf7d0)', rarity: 'legendary', glow: true, decor: { shape: 'star', colors: ['#fde68a', '#f9a8d4', '#bae6fd'] } },

    /*
     * Живые рамки «Майнкрафт оживает»: decor.kind выбирает анимацию в
     * AvatarFrameDecor — украшение на голове (top, ears, horns, halo),
     * спрайты на орбите (orbit), пламя по кругу, ударная волна, криппер
     * из-за угла, жабры аксолотля.
     */
    { id: 'hellfire', name: 'Адское пламя', color: '#f97316', gradient: 'linear-gradient(135deg, #7f1d1d, #f97316, #fde047, #b91c1c)', rarity: 'legendary', glow: true, decor: { kind: 'flames' } },
    { id: 'nether_horns', name: 'Рога Незера', color: '#ef4444', gradient: 'linear-gradient(135deg, #1e1b2e, #7f1d1d, #1e1b2e)', rarity: 'epic', decor: { kind: 'horns', color: '#1e1b2e', tip: '#f97316' } },
    { id: 'warden', name: 'Варден', color: '#2dd4bf', gradient: 'linear-gradient(135deg, #042f2e, #134e4a, #2dd4bf, #042f2e)', rarity: 'legendary', glow: true, decor: { kind: 'shockwave', color: '#5eead4' } },
    { id: 'creeper', name: 'Криппер', color: '#4ade80', gradient: 'linear-gradient(135deg, #14532d, #4ade80, #166534)', rarity: 'epic', decor: { kind: 'peek', sprite: 'creeper' } },
    { id: 'beehive', name: 'Улей', color: '#facc15', gradient: 'linear-gradient(135deg, #fde047, #f59e0b, #92400e, #fde047)', rarity: 'epic', decor: { kind: 'orbit', sprite: 'bee', count: 3, duration: 7 } },
    { id: 'axolotl', name: 'Аксолотль', color: '#f9a8d4', gradient: 'linear-gradient(135deg, #fbcfe8, #f472b6, #fce7f3)', rarity: 'epic', decor: { kind: 'gills', color: '#ec4899', color2: '#f9a8d4' } },
    { id: 'cat_ears', name: 'Котоушки', color: '#fb923c', gradient: 'linear-gradient(135deg, #fdba74, #fb923c, #c2410c)', rarity: 'epic', decor: { kind: 'ears', color: '#fb923c', inner: '#f9a8d4' } },
    { id: 'crown', name: 'Корона', color: '#facc15', gradient: 'linear-gradient(135deg, #fef08a, #facc15, #b45309, #fde047)', rarity: 'legendary', glow: true, decor: { kind: 'top', sprite: 'crown' } },
    { id: 'halo', name: 'Нимб', color: '#fef08a', gradient: 'linear-gradient(135deg, #ffffff, #fef9c3, #fde68a)', rarity: 'epic', glow: true, decor: { kind: 'halo', color: '#fef08a' } },
    { id: 'diamond_orbit', name: 'Алмазная орбита', color: '#22d3ee', gradient: 'linear-gradient(135deg, #a5f3fc, #22d3ee, #0e7490)', rarity: 'legendary', glow: true, decor: { kind: 'orbit', sprite: 'diamond', count: 4, duration: 9 } },

    // Премиум-рамки: голограмма, неон, космос, киберпанк
    { id: 'holo', name: 'Голограмма', color: '#c084fc', gradient: 'linear-gradient(135deg, #ff3d81, #ffb03d, #3dffa0, #3dd9ff, #6b3dff)', rarity: 'legendary', glow: true, decor: { kind: 'holo' } },
    { id: 'neon', name: 'Неон', color: '#e879f9', gradient: 'linear-gradient(135deg, #0f0a1e, #1e0b3d)', rarity: 'epic', decor: { kind: 'neon' } },
    { id: 'black_hole', name: 'Чёрная дыра', color: '#f97316', gradient: 'linear-gradient(135deg, #000, #1c0f05, #000)', rarity: 'legendary', glow: true, decor: { kind: 'blackhole' } },
    { id: 'glitch', name: 'Глитч', color: '#05d9e8', gradient: 'linear-gradient(135deg, #ff2a6d, #0b0b14, #05d9e8)', rarity: 'epic', decor: { kind: 'glitch' } },
    { id: 'hud', name: 'Кибер-прицел', color: '#22d3ee', gradient: 'linear-gradient(135deg, #082f49, #0e7490, #082f49)', rarity: 'epic', decor: { kind: 'hud' } },
    { id: 'plasma', name: 'Плазма', color: '#a855f7', gradient: 'linear-gradient(135deg, #7c3aed, #ec4899, #22d3ee)', rarity: 'legendary', glow: true, decor: { kind: 'plasma' } },
    { id: 'planets', name: 'Планетарий', color: '#3b82f6', gradient: 'linear-gradient(135deg, #020617, #1e1b4b, #020617)', rarity: 'legendary', glow: true, decor: { kind: 'planets' } },
    { id: 'chrome', name: 'Жидкий металл', color: '#e2e8f0', gradient: 'linear-gradient(135deg, #f8fafc, #64748b, #e2e8f0, #334155)', rarity: 'epic', decor: { kind: 'chrome' } },
]

/** Рамка по id: цвет или градиент. */
export function frameById(id) {
    return AVATAR_FRAMES.find((frame) => frame.id === id) ?? null
}

export const PROFILE_EFFECTS = [
    { id: null, name: 'Без эффекта', rarity: 'common' },
    { id: 'glow', name: 'Свечение', color: '#7c3aed', rarity: 'common' },
    { id: 'pulse', name: 'Пульсация', color: '#06b6d4', rarity: 'rare' },
    { id: 'gradient', name: 'Градиент', gradient: 'linear-gradient(135deg, #7c3aed, #06b6d4, #7c3aed)', rarity: 'rare' },
    { id: 'fire', name: 'Огонь', gradient: 'linear-gradient(135deg, #ef4444, #f97316, #facc15)', rarity: 'epic' },
    { id: 'ice', name: 'Лёд', gradient: 'linear-gradient(135deg, #06b6d4, #a5f3fc, #06b6d4)', rarity: 'epic' },
    { id: 'legendary', name: 'Легендарный', gradient: 'linear-gradient(135deg, #facc15, #f97316)', rarity: 'legendary', animated: true },

    /*
     * Эффекты-слои в стиле Minecraft: рисует ProfileEffectLayer.
     * particles — сколько частиц, size/duration — разброс [min, max],
     * sheet — полотно поверх (блеск, сияние, кольца).
     */
    { id: 'rain', name: 'Дождь', color: '#60a5fa', rarity: 'common', layer: { particles: 30, size: [2, 2], duration: [0.7, 1.2], streak: true } },
    { id: 'snow', name: 'Снегопад', color: '#e0f2fe', rarity: 'rare', layer: { particles: 22, size: [3, 5], duration: [6, 11] } },
    { id: 'embers', name: 'Искры Незера', color: '#f97316', color2: '#fde047', rarity: 'rare', layer: { particles: 18, size: [2, 4], duration: [3.5, 7] } },
    { id: 'redstone', name: 'Редстоун-пыль', color: '#ef4444', rarity: 'rare', layer: { particles: 20, size: [3, 4], duration: [1.6, 3.2] } },
    { id: 'ender', name: 'Частицы Края', color: '#c084fc', rarity: 'epic', layer: { particles: 20, size: [3, 5], duration: [5, 9] } },
    { id: 'xp', name: 'Опыт', color: '#a3e635', color2: '#4d7c0f', rarity: 'epic', layer: { particles: 12, size: [6, 9], duration: [4, 7] } },
    { id: 'enchant', name: 'Зачарование', color: '#a78bfa', color2: '#f0abfc', rarity: 'epic', layer: { sheet: true } },
    { id: 'aurora', name: 'Северное сияние', color: '#34d399', color2: '#22d3ee', rarity: 'legendary', layer: { sheet: true } },
    { id: 'sculk', name: 'Скалк', color: '#2dd4bf', rarity: 'legendary', layer: { sheet: true, particles: 10, size: [2, 3], duration: [3, 6] } },

    // Милые эффекты: форму частиц задаёт ProfileEffectLayer по id
    { id: 'sakura_petals', name: 'Лепестки сакуры', color: '#f9a8d4', color2: '#fce7f3', rarity: 'epic', layer: { particles: 18, size: [8, 13], duration: [7, 12] } },
    { id: 'hearts', name: 'Сердечки', color: '#fb7185', color2: '#fda4af', rarity: 'rare', layer: { particles: 12, size: [10, 16], duration: [5, 9] } },
    { id: 'sparkles', name: 'Блёстки', color: '#fde68a', color2: '#f9a8d4', rarity: 'rare', layer: { particles: 22, size: [8, 14], duration: [1.8, 3.6] } },
    { id: 'bubbles', name: 'Мыльные пузыри', color: '#bae6fd', color2: '#f9a8d4', rarity: 'common', layer: { particles: 12, size: [10, 22], duration: [6, 11] } },

    /*
     * «Майнкрафт оживает»: sprite — пиксельная картинка частиц, hero —
     * крупная картинка на полотне (криппер перед взрывом, тотем во вспышке).
     */
    { id: 'inferno', name: 'Инферно', color: '#f97316', color2: '#fde047', rarity: 'legendary', layer: { sheet: true, particles: 26, size: [2, 5], duration: [2.5, 6] } },
    { id: 'creeper_boom', name: 'Ссссс… БУМ', color: '#4ade80', color2: '#ffffff', rarity: 'epic', layer: { sheet: true, hero: 'creeper', particles: 10, size: [14, 30], duration: [6, 6] } },
    { id: 'bees', name: 'Пчёлки', color: '#facc15', rarity: 'epic', layer: { sprite: 'bee', particles: 6, size: [18, 24], duration: [8, 13] } },
    { id: 'totem', name: 'Тотем бессмертия', color: '#facc15', color2: '#22c55e', rarity: 'legendary', layer: { sheet: true, hero: 'totem', particles: 26, size: [3, 6], duration: [2.6, 4.2] } },
    { id: 'lightning', name: 'Гроза', color: '#c7d2fe', color2: '#6366f1', rarity: 'legendary', layer: { sheet: true, particles: 24, size: [1, 1], duration: [0.6, 1], streak: true } },
    { id: 'diamond_rain', name: 'Алмазный дождь', color: '#67e8f9', rarity: 'epic', layer: { sprite: 'diamond', particles: 14, size: [10, 16], duration: [4, 8] } },
    { id: 'pixel_hearts', name: 'Пиксельные сердца', color: '#ef4444', rarity: 'rare', layer: { sprite: 'heart', particles: 10, size: [10, 16], duration: [5, 9] } },
    { id: 'cat_paws', name: 'Кошачьи следы', color: '#f9a8d4', rarity: 'rare', layer: { sprite: 'paw', particles: 8, size: [12, 15], duration: [6, 6] } },

    // Премиум-эффекты: Матрица, варп, неоновый дождь, галактика, глитч, праздник
    { id: 'matrix', name: 'Матрица', color: '#22c55e', color2: '#bbf7d0', rarity: 'legendary', layer: { particles: 16, size: [12, 15], duration: [3, 7], glyphs: true } },
    { id: 'warp', name: 'Гиперпрыжок', color: '#e0e7ff', color2: '#818cf8', rarity: 'legendary', layer: { sheet: true, particles: 34, size: [2, 3], duration: [0.9, 1.8] } },
    { id: 'neon_rain', name: 'Неоновый дождь', color: '#f472b6', color2: '#22d3ee', rarity: 'epic', layer: { sheet: true, particles: 34, size: [2, 2], duration: [0.6, 1.3], streak: true } },
    { id: 'galaxy', name: 'Галактика', color: '#ffffff', color2: '#a78bfa', rarity: 'legendary', layer: { sheet: true, particles: 40, size: [1, 3], duration: [1.5, 4] } },
    { id: 'glitch', name: 'Сбой системы', color: '#ff2a6d', color2: '#05d9e8', rarity: 'epic', layer: { sheet: true } },
    { id: 'confetti', name: 'Конфетти', color: '#f472b6', color2: '#facc15', rarity: 'rare', layer: { particles: 26, size: [6, 10], duration: [3.5, 7] } },
    { id: 'fireflies', name: 'Светлячки', color: '#d9f99d', color2: '#facc15', rarity: 'epic', layer: { particles: 18, size: [3, 5], duration: [6, 12] } },
    { id: 'bokeh', name: 'Боке', color: '#f9a8d4', color2: '#93c5fd', rarity: 'rare', layer: { particles: 12, size: [24, 60], duration: [10, 18] } },
]

/**
 * Настройки слоя эффекта для ProfileEffectLayer или null, если эффект
 * рисуется стилями шапки (свечение, огонь и прочие первые эффекты).
 */
export function layerEffect(id) {
    const effect = PROFILE_EFFECTS.find((e) => e.id === id && e.layer)

    return effect ? { ...effect.layer, color: effect.color, color2: effect.color2 } : null
}

export const ACCENT_COLORS = [
    '#7c3aed', '#8b5cf6', '#06b6d4', '#22c55e',
    '#f97316', '#ef4444', '#facc15', '#ec4899',
    '#14b8a6', '#eab308', '#a855f7', '#f43f5e',
]

export const FAVORITE_MODES = [
    { value: 'bedwars', label: 'BedWars', color: '#8b5cf6' },
    { value: 'skywars', label: 'SkyWars', color: '#06b6d4' },
    { value: 'duels', label: 'Duels', color: '#f97316' },
    { value: 'pvp', label: 'PvP', color: '#ef4444' },
    { value: 'survival', label: 'Survival', color: '#22c55e' },
    { value: 'other', label: 'Other', color: '#6b7280' },
]

export const RARITY_COLORS = {
    common: '#7c3aed',
    rare: '#06b6d4',
    epic: '#f97316',
    legendary: '#facc15',
}

/**
 * Цвета тиров: используются в карточках рейтинга и на пьедестале.
 */
export const tierColors = {
  S: '#facc15',
  A: '#fb923c',
  B: '#a78bfa',
  C: '#22d3ee',
  D: '#4ade80',
  E: '#94a3b8',
}
