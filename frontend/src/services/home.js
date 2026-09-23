import { api } from './api.js'

export const homeApi = {
    top() {
        return api.get('/top')
    },
}