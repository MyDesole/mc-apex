import { api } from './api.js'

/**
 * Собирает FormData из объекта с учётом типов значений.
 *
 * Разница между undefined и null принципиальна:
 *   - undefined — поле не трогаем, сервер оставит текущее значение;
 *   - null      — поле очищаем. Отправляем пустую строку: middleware
 *                 Laravel (ConvertEmptyStringsToNull) превратит её в null.
 *
 * Раньше null отбрасывался наравне с undefined, поэтому выбор
 * «Без эффекта» не доходил до сервера и эффект оставался надетым.
 * Пустой массив отправляем как [0] => '', чтобы валидация «array»
 * прошла и коллекция действительно очистилась.
 */
function buildFormData(payload) {
    const fd = new FormData()

    for (const [key, value] of Object.entries(payload)) {
        // Поле не участвует в запросе — оставляем как есть
        if (value === undefined) continue

        if (value === null) {
            fd.append(key, '')
            continue
        }

        if (value instanceof File) {
            fd.append(key, value)
            continue
        }

        if (Array.isArray(value)) {
            if (value.length === 0) {
                // Пустой массив: одно пустое значение, чтобы ключ существовал
                fd.append(`${key}[0]`, '')
            } else {
                value.forEach((v, i) => fd.append(`${key}[${i}]`, v))
            }
            continue
        }

        if (typeof value === 'boolean') {
            fd.append(key, value ? '1' : '0')
            continue
        }

        fd.append(key, value)
    }

    return fd
}

export const playersApi = {
    // === Профиль: обновление (рамки, эффекты, био и т.д.) ===
    updateProfile(payload) {
        const fd = buildFormData(payload)
        fd.append('_method', 'PUT')

        return api.post('/players/me/profile', fd)
    },

    // === Аватар / обложка (файлы) ===
    updateMe(payload) {
        const fd = buildFormData(payload)
        fd.append('_method', 'PUT')

        return api.post('/players/me', fd)
    },

    removeCardBackground() {
        return api.post('/players/me/card-background/remove')
    },

    // === Аспекты ===
    updateAspects(payload) {
        return api.put('/players/me/aspects', payload)
    },

    removeAvatar() {
        return api.post('/players/me/avatar/remove')
    },

    removeCover() {
        return api.post('/players/me/cover/remove')
    },

    saveRecommendation(userId, payload) {
        return api.post(`/players/${userId}/recommendations`, payload)
    },
    deleteRecommendation(userId) {
        return api.delete(`/players/${userId}/recommendations`)
    },
    hideRecommendation(recommendationId) {
        return api.post(`/recommendations/${recommendationId}/hide`)
    },

    // === Публичные ===
    show(idOrNick) {
        return api.get(`/players/${idOrNick}`)
    },

    top() {
        return api.get('/players/top')
    },
}
