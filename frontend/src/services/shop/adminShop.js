import { api } from '@/services/core/api.js'

/**
 * Админка магазина: каталог, настройки наград, ручные начисления, леджер.
 * Все методы требуют роль moderator/admin (начисления и леджер — только admin).
 */

function withQuery(url, params = {}) {
    const query = new URLSearchParams(
        Object.fromEntries(
            Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')
        )
    ).toString()

    return query ? `${url}?${query}` : url
}

export const adminShopApi = {
    items(params = {}) {
        return api.get(withQuery('/admin/shop-items', params))
    },

    createItem(payload) {
        return api.post('/admin/shop-items', payload)
    },

    updateItem(id, payload) {
        return api.put(`/admin/shop-items/${id}`, payload)
    },

    deleteItem(id) {
        return api.delete(`/admin/shop-items/${id}`)
    },

    toggleItem(id) {
        return api.post(`/admin/shop-items/${id}/toggle`)
    },

    rewards() {
        return api.get('/admin/shop-rewards')
    },

    updateRewards(payload) {
        return api.put('/admin/shop-rewards', payload)
    },

    grantCoins(userId, payload) {
        return api.post(`/admin/users/${userId}/coins`, payload)
    },

    transactions(params = {}) {
        return api.get(withQuery('/admin/coin-transactions', params))
    },
}
