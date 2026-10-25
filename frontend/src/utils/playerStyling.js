import { TIER_COLORS } from '@/composables/players/useTier.js'
import { frameById } from '@/data/players/profileCustomization.js'

/**
 * Общие хелперы оформления игрока.
 *
 * Раньше эти функции были скопированы в шести файлах: PlayersView,
 * RatingPodium, PlayerProfileHeader, ClanEventComments,
 * TesterTierTestModal, AdminTournamentBracket. При выносе пьедестала
 * выяснилось, что копии расходятся, поэтому сводим их в одно место.
 */

/** Подписи ролей для карточек. */
export const ROLE_LABELS = {
  admin: 'Администратор',
  tester: 'Тестер',
  moderator: 'Модератор',
  media: 'Медийка',
}

/*
 * Палитра тиров живёт в composables/players/useTier.js: там она была
 * раньше и оттуда её берут профиль и история тестов. Держим один
 * источник, а не копию.
 */
export { TIER_COLORS as TIER_ACCENTS } from '@/composables/players/useTier.js'

/** Базовые цвета рамок аватара. */
export const AVATAR_FRAMES = {
  purple: '#7c3aed',
  cyan: '#06b6d4',
  green: '#22c55e',
  gold: '#facc15',
  orange: '#f97316',
  pink: '#ec4899',
  red: '#ef4444',
  legendary: '#facc15',
  season1: '#06b6d4',
  rainbow: '#7c3aed',
  emerald: '#10b981',
  redstone: '#dc2626',
  diamond: '#22d3ee',
  obsidian: '#6d28d9',
  ender: '#14b8a6',
  netherite: '#a8a29e',
}

/** Акцент по тиру игрока. */
export function accent(player) {
  return TIER_COLORS[player?.tier] || '#7c3aed'
}

/** Рейтинговый балл. */
export function scoreOf(player) {
  return player?.rating_score ?? 0
}

/** Подпись роли: пустая для обычного игрока. */
export function roleLabel(player) {
  if (!player?.role || player.role === 'player') {
    return ''
  }

  return ROLE_LABELS[player.role] || player.role
}

/** Первая буква ника для заглушки аватара. */
export function avatarLetter(username) {
  /*
   * Принимает ник строкой: так её вызывают карточки комментариев,
   * тир-тестов и участников турнира. Если случайно передали объект,
   * берём username из него — раньше на этом молча возвращался «?».
   */
  const name = typeof username === 'string' ? username : username?.username

  return name?.trim()?.charAt(0)?.toUpperCase() || '?'
}

/**
 * CSS-переменные для рамки аватара.
 *
 * Цвета и градиенты берутся из общего списка рамок: раньше градиентные
 * рамки были вписаны сюда вручную, и новая рамка из магазина требовала
 * правки в двух местах.
 */
export function avatarFrame(player) {
  const id = player?.avatar_frame

  if (!id || id === 'default') {
    return {}
  }

  const frame = frameById(id)
  const color = frame?.color || AVATAR_FRAMES[id] || accent(player)

  return frame?.gradient
      ? { '--frame-color': color, '--frame-gradient': frame.gradient }
      : { '--frame-color': color }
}

/** CSS-переменные для эффекта профиля. */
export function profileEffectStyle(player) {
  const effect = player?.profile_effect

  if (!effect) {
    return {}
  }

  const colors = {
    legendary: '#facc15',
    fire: '#f97316',
    ice: '#06b6d4',
    glow: '#7c3aed',
    pulse: '#06b6d4',
  }

  return colors[effect] ? { '--effect-color': colors[effect] } : {}
}

/** Легендарный эффект: рамка карточки подсвечивается особым образом. */
export function isLegendary(player) {
  return player?.profile_effect === 'legendary'
}
