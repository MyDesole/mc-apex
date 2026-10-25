/**
 * Разделители по дням в чате — как в Telegram.
 *
 * «Сегодня», «Вчера», «25 октября» в текущем году и «25 октября 2025»
 * для прошлых лет. Сравниваем календарные дни в часовом поясе
 * пользователя, а не разницу в часах.
 */

function dayKey(date) {
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`
}

/** Новый ли день у сообщения по сравнению с предыдущим. */
export function startsNewDay(message, previous) {
    if (!message?.created_at) return false
    if (!previous?.created_at) return true

    return dayKey(new Date(message.created_at)) !== dayKey(new Date(previous.created_at))
}

/** Подпись разделителя для даты сообщения. */
export function dayLabel(value, now = new Date()) {
    const date = new Date(value)

    if (Number.isNaN(date.getTime())) return ''

    const yesterday = new Date(now)
    yesterday.setDate(now.getDate() - 1)

    if (dayKey(date) === dayKey(now)) return 'Сегодня'
    if (dayKey(date) === dayKey(yesterday)) return 'Вчера'

    const options = { day: 'numeric', month: 'long' }

    if (date.getFullYear() !== now.getFullYear()) {
        options.year = 'numeric'
    }

    // «25 октября 2025 г.» → без «г.», как в мессенджерах
    return date.toLocaleDateString('ru-RU', options).replace(/\s?г\.$/, '')
}
