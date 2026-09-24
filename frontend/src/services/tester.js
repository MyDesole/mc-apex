import { api } from './api.js'

export const testerApi = {
    tierTests(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/tester/tier-tests${q ? '?' + q : ''}`)
    },
    stats() {
        return api.get('/tester/tier-tests/stats')
    },
    show(id) {
        return api.get(`/tester/tier-tests/${id}`)
    },
    claim(id) {
        return api.post(`/tester/tier-tests/${id}/claim`)
    },
    unclaim(id) {
        return api.post(`/tester/tier-tests/${id}/unclaim`)
    },
    complete(id, payload) {
        return api.post(`/tester/tier-tests/${id}/complete`, payload)
    },
    cancel(id, reason) {
        return api.post(`/tester/tier-tests/${id}/cancel`, { reason })
    },
}