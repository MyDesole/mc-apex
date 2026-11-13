import { isUpdateStatus, startMaintenance } from '@/services/core/maintenance.js'

import { NETWORK_ERROR, humanError } from '@/services/core/errors.js'

const API_URL = '/api'

function getCookie(name) {
    const match = document.cookie.match(
        new RegExp('(^|;\\s*)' + name + '=([^;]*)')
    )
    return match ? decodeURIComponent(match[2]) : null
}

/**
 * Собирает query-строку из объекта параметров.
 *
 * Вложенные объекты (курсор рейтинга) разворачиваются в скобочную
 * нотацию: { cursor: { score: 5 } } -> cursor[score]=5 — Laravel
 * читает её как массив.
 */
function buildQuery(params) {
    if (!params) return ''

    const parts = []

    const append = (key, value) => {
        if (value === undefined || value === null || value === '') return

        if (Array.isArray(value)) {
            value.forEach((item, index) => append(`${key}[${index}]`, item))
            return
        }

        if (typeof value === 'object') {
            Object.entries(value).forEach(([innerKey, innerValue]) => {
                append(`${key}[${innerKey}]`, innerValue)
            })
            return
        }

        parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    }

    Object.entries(params).forEach(([key, value]) => append(key, value))

    return parts.length ? `?${parts.join('&')}` : ''
}

async function request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase()
    const isFormData = options.body instanceof FormData

    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    }

    // Content-Type только для НЕ-FormData
    if (options.body && !isFormData) {
        headers['Content-Type'] = 'application/json'
    }

    // CSRF для небезопасных методов
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        const token = getCookie('XSRF-TOKEN')
        if (token) headers['X-XSRF-TOKEN'] = token
    }

    // Параметры запроса: раньше второй аргумент api.get() молча терялся,
    // поэтому mode, cursor и фильтры до сервера не доходили
    const { params, ...fetchOptions } = options

    let response

    try {
        response = await fetch(`${API_URL}${url}${buildQuery(params)}`, {
            credentials: 'include',
            ...fetchOptions,
            headers,
        })
    } catch (e) {
        // Отменённый запрос — не ошибка сети: пусть вызывающий сам решит
        if (e?.name === 'AbortError') throw e

        // fetch бросает «Failed to fetch» — игроку это ни о чём не говорит
        throw { status: 0, message: NETWORK_ERROR, errors: {}, data: {} }
    }

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
        // Сайт обновляется: показываем окно ожидания вместо россыпи ошибок
        if (isUpdateStatus(response.status)) startMaintenance()

        throw {
            status: response.status,
            message: humanError(response.status, data),
            errors: data.errors || {},
            // Весь ответ: в нём бывают подсказки (например, сколько не хватает монет)
            data,
        }
    }

    return data
}

export async function getCsrfCookie() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
}

export const api = {
    /**
     * @param {string} url
     * @param {{ params?: object }} options
     */
    get(url, options = {}) {
        return request(url, { ...options, method: 'GET' })
    },

    post(url, body = {}, options = {}) {
        const isFormData = body instanceof FormData

        return request(url, {
            method: 'POST',
            body: isFormData ? body : JSON.stringify(body),
            ...options,
        })
    },

    put(url, body = {}, options = {}) {
        const isFormData = body instanceof FormData

        return request(url, {
            method: 'PUT',
            body: isFormData ? body : JSON.stringify(body),
            ...options,
        })
    },

    delete(url, options = {}) {
        return request(url, {
            method: 'DELETE',
            ...options,
        })
    },
}