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

/** Цвета тиров для акцентов. */
export const TIER_ACCENTS = {
  'S+': '#fbbf24',
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

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
}

/** Акцент по тиру игрока. */
export function accent(player) {
  return TIER_ACCENTS[player?.tier] || '#7c3aed'
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
export function avatarLetter(player) {
  return player?.username?.trim()?.charAt(0)?.toUpperCase() || '?'
}

/**
 * CSS-переменные для рамки аватара.
 *
 * У rainbow, legendary и season1 рамка градиентная, у остальных —
 * сплошной цвет.
 */
export function avatarFrame(player) {
  const frame = player?.avatar_frame

  if (!frame || frame === 'default') {
    return {}
  }

  if (frame === 'rainbow') {
    return {
      '--frame-color': '#7c3aed',
      '--frame-gradient':
          'linear-gradient(135deg, #ef4444, #facc15, #22c55e, #06b6d4, #7c3aed)',
    }
  }

  if (frame === 'legendary') {
    return {
      '--frame-color': '#facc15',
      '--frame-gradient':
          'linear-gradient(135deg, #facc15, #f97316, #ef4444)',
    }
  }

  if (frame === 'season1') {
    return {
      '--frame-color': '#06b6d4',
      '--frame-gradient':
          'linear-gradient(135deg, #7c3aed, #06b6d4)',
    }
  }

  return {
    '--frame-color': AVATAR_FRAMES[frame] || accent(player),
  }
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
