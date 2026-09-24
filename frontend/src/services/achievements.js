import { api } from './api.js'

export const achievementsApi = {
    list() {
        return api.get('/achievements')
    },
    ofUser(userId) {
        return api.get(`/players/${userId}/achievements`)
    },
}