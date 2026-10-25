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
]

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
