import { api } from './api.js'

export const tournamentsApi = {
    list(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/tournaments${q ? '?' + q : ''}`)
    },
    show(id) {
        return api.get(`/tournaments/${id}`)
    },
    register(id) {
        return api.post(`/tournaments/${id}/register`)
    },
    withdraw(id) {
        return api.post(`/tournaments/${id}/withdraw`)
    },
}