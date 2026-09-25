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
}