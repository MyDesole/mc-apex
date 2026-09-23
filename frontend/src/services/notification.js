import { api } from './api.js'

export const notificationsApi = {
    list() {
        return api.get('/notifications')
    },
    markAsRead(id) {
        return api.post(`/notifications/${id}/read`)
    },
    markAllAsRead() {
        return api.post('/notifications/read-all')
    },
}