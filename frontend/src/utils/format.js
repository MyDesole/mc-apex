/**
 * Форматирование дат и подписей.
 *
 * Раньше formatDate была скопирована в десяти файлах, причём каждая
 * копия использовала свои опции вывода. Здесь три варианта, которые
 * встречались чаще всего.
 */

/** Дата числом: 05.10.2026 */
export function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** Дата словами: 05 окт. 2026 */
export function formatDateLong(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

/** Дата и время: 05 окт., 18:00 */
export function formatDateTime(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** День и месяц: 05 окт. */
export function formatDayMonth(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
  })
}

/** Количество дней словами: 1 день, 2 дня, 5 дней */
export function pluralDays(n) {
  const mod10 = n % 10
  const mod100 = n % 100

  if (mod10 === 1 && mod100 !== 11) return 'день'
  if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'дня'

  return 'дней'
}
