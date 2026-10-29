import { api } from '@/services/core/api.js'

export const casesApi = {
    /** Витрина: кейсы, шансы, бесплатный сундук, гарантия, история. */
    list() {
        return api.get('/cases')
    },

    /** Ждёт ли игрока бесплатный сундук — для меню сайта. */
    status() {
        return api.get('/cases/status')
    },

    /** Открыть сундук; платный — можно пачкой (3, 5, 10). */
    open(slug, count = 1) {
        return api.post(`/shop/cases/${slug}/open`, count > 1 ? { count } : {})
    },
}
