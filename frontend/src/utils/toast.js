import { reactive } from 'vue'

/**
 * Всплывающие уведомления: короткое сообщение в углу экрана.
 *
 * Нужны там, где у страницы нет своего места под ошибку: фоновая загрузка,
 * клик по кнопке без формы. Раньше такие сбои уходили только в консоль,
 * и игрок видел пустой блок или кнопку, которая «не нажимается».
 *
 *   toast('Ссылка скопирована')
 *   notifyError(e, 'Не удалось загрузить участников.')
 */

export const toastState = reactive({ items: [] })

const LIFETIME_MS = 6000
const MAX_VISIBLE = 3

let nextId = 1

export function dismissToast(id) {
    const index = toastState.items.findIndex((item) => item.id === id)

    if (index !== -1) toastState.items.splice(index, 1)
}

/**
 * @param {string} message
 * @param {{ tone?: 'error' | 'success' | 'info' }} options
 */
export function toast(message, options = {}) {
    if (!message) return

    // Десять упавших запросов одной страницы — одно сообщение, а не десять
    if (toastState.items.some((item) => item.message === message)) return

    const item = { id: nextId++, message, tone: options.tone ?? 'info' }

    toastState.items.push(item)

    if (toastState.items.length > MAX_VISIBLE) toastState.items.shift()

    setTimeout(() => dismissToast(item.id), LIFETIME_MS)
}

/** Ответ API с ошибкой: объект, который бросает services/core/api.js. */
export function isApiError(error) {
    return Boolean(error) && typeof error === 'object' && typeof error.status === 'number' && typeof error.message === 'string'
}

/**
 * Показать ошибку человеку.
 *
 * Текст берётся из ответа API (он уже русский и с подсказкой), а для
 * сбоев в коде страницы — запасной: их сообщения игроку ничего не скажут.
 */
export function notifyError(error, fallback = 'Не получилось выполнить действие. Попробуйте ещё раз.') {
    // 401 гостю — не сбой: страница сама отправит на вход.
    // 502–504 — идёт обновление сайта, об этом говорит отдельное окно.
    if (isApiError(error) && (error.status === 401 || (error.status >= 502 && error.status <= 504))) return

    toast(isApiError(error) ? error.message : fallback, { tone: 'error' })
}
