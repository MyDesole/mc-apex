import { api } from './api.js'

export const giftApi = {
    limits() {
        return api.get('/gifts/limits')
    },

    // Подарить монеты другу
    send(userId, amount) {
        return api.post(`/gifts/${userId}`, { amount })
    },
}
