import { api } from '@/services/core/api.js'

export const searchApi = {
    /**
     * Поиск по сайту для командной палитры.
     * @returns {Promise<{players: Array, clans: Array, topics: Array, news: Array}>}
     */
    query(q) {
        return api.get('/search', { params: { q } })
    },
}
