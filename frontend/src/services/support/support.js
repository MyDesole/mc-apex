import { api } from '@/services/core/api.js'

/** Техподдержка со стороны игрока. */
export const supportApi = {
    list() {
        return api.get('/support/tickets')
    },

    open(payload) {
        return api.post('/support/tickets', payload)
    },

    show(id) {
        return api.get(`/support/tickets/${id}`)
    },

    reply(id, body) {
        return api.post(`/support/tickets/${id}/messages`, { body })
    },

    close(id) {
        return api.post(`/support/tickets/${id}/close`)
    },
}

/** Очередь поддержки для модератора и админа. */
export const adminSupportApi = {
    list(status = 'open') {
        return api.get(`/admin/support?status=${status}`)
    },

    show(id) {
        return api.get(`/admin/support/${id}`)
    },

    reply(id, body) {
        return api.post(`/admin/support/${id}/messages`, { body })
    },

    close(id) {
        return api.post(`/admin/support/${id}/close`)
    },
}
