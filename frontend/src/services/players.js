import { api } from './api.js'

export const playersApi = {
    // === Профиль: обновление (рамки, эффекты, био и т.д.) ===
    updateProfile(payload) {
        const fd = new FormData()
        fd.append('_method', 'PUT')

        for (const [key, value] of Object.entries(payload)) {
            if (value === undefined || value === null) continue

            if (Array.isArray(value)) {
                value.forEach((v, i) => {
                    fd.append(`${key}[${i}]`, v)
                })
            } else if (value instanceof File) {
                fd.append(key, value)
            } else if (typeof value === 'boolean') {
                fd.append(key, value ? '1' : '0')
            } else {
                fd.append(key, value)
            }
        }

        return api.post('/players/me/profile', fd)
    },

    // === Аватар / обложка (файлы) ===
    updateMe(payload) {
        const fd = new FormData()
        fd.append('_method', 'PUT')   // ← спуф метода

        for (const [key, value] of Object.entries(payload)) {
            if (value === undefined || value === null) continue
            if (value instanceof File) fd.append(key, value)
            else fd.append(key, value)
        }

        return api.post('/players/me', fd)   // ← именно POST, не PUT
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

    /**
     * Рейтинг с курсорной пагинацией.
     *
     * @param {object} options mode / cursor / limit / search / tier / clanId
     */
    ranking({ mode = 'overall', cursor = null, limit = 30, search = '', tier = '', clanId = null } = {}) {
        const params = new URLSearchParams()

        params.set('mode', mode)
        params.set('limit', String(limit))

        if (cursor?.score !== undefined) params.set('cursor_score', String(cursor.score))
        if (cursor?.id) params.set('cursor_id', String(cursor.id))
        if (cursor?.offset) params.set('offset', String(cursor.offset))
        if (search) params.set('search', search)
        if (tier) params.set('tier', tier)
        if (clanId) params.set('clan_id', String(clanId))

        return api.get(`/ranking?${params.toString()}`)
    },

    // Постраничный список игроков (обычная пагинация Laravel)
    list(params = {}) {
        const query = new URLSearchParams(params).toString()

        return api.get(`/players${query ? '?' + query : ''}`)
    },
}
