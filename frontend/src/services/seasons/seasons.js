import { api } from '@/services/core/api.js'

export const seasonsApi = {
    /** Текущий сезон и прошедшие с тройкой лучших. */
    list() {
        return api.get('/seasons')
    },

    /** Места игрока по сезонам. */
    player(userId) {
        return api.get(`/players/${userId}/seasons`)
    },
}
