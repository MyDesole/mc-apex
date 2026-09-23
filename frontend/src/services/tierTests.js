import { api } from './api.js'

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
}