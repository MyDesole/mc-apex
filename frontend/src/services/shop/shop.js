import { api } from '@/services/core/api.js'

export const shopApi = {
    // Каталог (доступен и гостю)
    catalog() {
        return api.get('/shop')
    },

    item(id) {
        return api.get(`/shop/${id}`)
    },

    // Инвентарь и надевание
    inventory() {
        return api.get('/shop/inventory')
    },

    purchase(id, payload = {}) {
        return api.post(`/shop/${id}/purchase`, payload)
    },

    equip(id) {
        return api.post(`/shop/${id}/equip`)
    },

    /** «Рандом»: случайные предметы из своих во все слоты профиля. */
    randomize() {
        return api.post('/shop/randomize')
    },

    /** Надеть набор целиком: всё, что из него есть. */
    equipBundle(id) {
        return api.post(`/shop/${id}/equip-bundle`)
    },

    unequip(id) {
        return api.post(`/shop/${id}/unequip`)
    },

    // Заявки, к которым можно применить приоритет, и число зарядов
    priorityCandidates() {
        return api.get('/shop/priority-candidates')
    },

    // Применить купленный приоритет к конкретной заявке
    applyPriority(tierTestId) {
        return api.post(`/tier-tests/${tierTestId}/priority`)
    },
}
