import { api } from './api.js'

export const playersApi = {
    updateMe(payload) {
        const fd = new FormData()
        fd.append('_method', 'PUT')

        for (const [key, value] of Object.entries(payload)) {
            if (value === undefined || value === null) continue

            if (key === 'socials') {
                for (const [k, v] of Object.entries(value)) {
                    if (v) fd.append(`socials[${k}]`, v)
                }
            } else {
                fd.append(key, value)
            }
        }

        return api.post('/players/me', fd)
    },

    removeAvatar() {
        return api.post('/players/me/avatar/remove')
    },

    removeCover() {
        return api.post('/players/me/cover/remove')
    },
}