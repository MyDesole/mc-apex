import { api } from '@/services/core/api.js'

export const homeApi = {
    top() {
        return api.get('/top')
    },
    index() {
        return api.get('/home')
    },
}