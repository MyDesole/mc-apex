import { api } from '@/services/core/api.js'

export const activityApi = {
    /**
     * Последние события ленты, новые сверху.
     * @param {{ limit?: number, before?: number }} params
     */
    list(params = {}) {
        return api.get('/activity', { params })
    },
}
