import { api } from './api.js'

export const newsApi = {
    list(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/news${q ? '?' + q : ''}`)
    },
    show(id) {
        return api.get(`/news/${id}`)
    },
}