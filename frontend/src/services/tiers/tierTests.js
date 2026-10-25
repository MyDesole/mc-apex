import { api } from '@/services/core/api.js'

export const tierTestsApi = {
    list() {
        return api.get('/tier-tests')
    },
    create(payload) {
        return api.post('/tier-tests', payload)
    },
    update(id, payload) {
        return api.put(`/tier-tests/${id}`, payload)
    },
    cancel(id, reason = null) {
        return api.post(`/tier-tests/${id}/cancel`, { reason })
    },
    appeal(id, reason) {
        return api.post(`/tier-tests/${id}/appeal`, { reason })
    },
}

/** Рассмотрение апелляций (модератор, админ). */
export const tierAppealsApi = {
    list(status = 'pending') {
        return api.get('/admin/tier-test-appeals', { params: { status } })
    },
    resolve(id, payload) {
        return api.post(`/admin/tier-test-appeals/${id}/resolve`, payload)
    },
}