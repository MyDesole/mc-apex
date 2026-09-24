import { api } from './api.js'

export const playersApi = {
    // === Профиль: обновление ===
    updateProfile(payload) {
        const fd = new FormData()
        fd.append('_method', 'PUT')

        for (const [key, value] of Object.entries(payload)) {
            if (value === undefined || value === null) continue

            // массивы → favorites[0], favorites[1]...
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

    removeCardBackground() {
        return api.post('/players/me/card-background/remove')
    },

    // === Аспекты ===
    updateAspects(payload) {
        return api.put('/players/me/aspects', payload)
    },

    // === Аватар / обложка (если нужны) ===
    removeAvatar() {
        return api.post('/players/me/avatar/remove')
    },

    removeCover() {
        return api.post('/players/me/cover/remove')
    },
}