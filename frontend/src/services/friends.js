import { api } from './api.js'

export const friendsApi = {
    list() {
        return api.get('/friends')
    },
    add(userId) {
        return api.post(`/friends/${userId}`)
    },
    accept(userId) {
        return api.post(`/friends/${userId}/accept`)
    },
    remove(userId) {
        return api.delete(`/friends/${userId}`)
    },
}