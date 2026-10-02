import { api } from '@/services/core/api.js'

export const achievementsApi = {
    list() {
        return api.get('/achievements')
    },
    ofUser(userId) {
        return api.get(`/players/${userId}/achievements`)
    },
}