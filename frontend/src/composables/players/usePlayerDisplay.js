/**
 * Отображение режимов игры.
 *
 * Функции форматирования переехали в utils/format.js, но реэкспорт
 * оставлен: компоненты уже импортируют их отсюда, и ломать вызовы
 * ради переезда не нужно.
 */
export { formatDate, pluralDays } from '@/utils/format.js'

export const MODE_LABELS = {
  bedwars: 'BedWars',
  skywars: 'SkyWars',
  duels: 'Duels',
  pvp: 'PvP',
  survival: 'Survival',
  other: 'Other',
}

export const MODE_COLORS = {
  bedwars: '#8b5cf6',
  skywars: '#06b6d4',
  duels: '#f97316',
  pvp: '#ef4444',
  survival: '#22c55e',
  other: '#6b7280',
}
