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
]

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