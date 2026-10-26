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

    open(slug) {
        return api.post(`/shop/cases/${slug}/open`)
    },
}
