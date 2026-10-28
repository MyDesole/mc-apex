import { api } from '@/services/core/api.js'

export const shopApi = {
    // Каталог (доступен и гостю)
    catalog() {
        return api.get('/shop')
    },

    /** Страница витрины по курсору: { items, next_cursor, …шапка на первой }. */
    catalogPage({ type = null, cursor = null, limit = 24 } = {}) {
        return api.get('/shop', { params: { limit, type: type || undefined, cursor: cursor || undefined } })
    },

    /** Страница инвентаря: вкладка, поиск и сортировка на сервере. */
    inventoryPage({ tab = 'all', q = '', sort = 'rarity', cursor = null, limit = 24 } = {}) {
        return api.get('/shop/inventory', {
            params: { limit, tab, q: q || undefined, sort, cursor: cursor || undefined },
        })
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

    /** Стикерпаки игрока для выбора в чате. */
    stickers() {
        return api.get('/shop/stickers')
    },

    /** «Рандом»: случайные предметы из своих во все слоты профиля. */
    randomize() {
        return api.post('/shop/randomize')
    },

    /** Надеть набор целиком: всё, что из него есть. */
    equipBundle(id) {
        return api.post(`/shop/${id}/equip-bundle`)
    },

    // Конкретный экземпляр питомца (их может быть несколько, гуляют до трёх)
    togglePet(inventoryId, equip = true) {
        return api.post(`/shop/pets/${inventoryId}/${equip ? 'equip' : 'unequip'}`)
    },

    /** Продать строку инвентаря магазину (купленное — 50%, выпавшее — 15%). */
    sell(inventoryId) {
        return api.post(`/shop/inventory/${inventoryId}/sell`)
    },

    /** Подарить строку инвентаря другу. */
    gift(inventoryId, userId) {
        return api.post(`/shop/inventory/${inventoryId}/gift`, { user_id: userId })
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
