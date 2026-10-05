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
}