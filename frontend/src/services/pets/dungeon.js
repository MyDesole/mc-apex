import { api } from '@/services/core/api.js'

/**
 * Подземелья и экипировка питомцев.
 *
 * Поход — не потоковое соединение: каждый зал открывается отдельным
 * запросом, поэтому поход продолжается с любого устройства.
 */
export const dungeonApi = {
    /** Сводка: подземелья, отряд, активный поход, экипировка. */
    overview() {
        return api.get('/dungeons')
    },

    /** Купить ещё один поход на сегодня: цена растёт с каждой покупкой. */
    buyRun() {
        return api.post('/dungeons/buy-run')
    },

    /** Начать поход: ключ подземелья и 2–3 питомца. */
    start(dungeon, squad) {
        return api.post('/dungeons/start', { dungeon, squad })
    },

    /** Пройти следующий зал. */
    advance(runId) {
        return api.post(`/dungeons/${runId}/advance`)
    },

    /**
     * Раунд боя: приёмы каждому живому питомцу.
     *
     * moves — объект «id питомца» → attack | skill | defend | ultimate.
     */
    move(runId, moves) {
        return api.post(`/dungeons/${runId}/move`, { moves })
    },

    /** Отступить, сохранив добычу. */
    retreat(runId) {
        return api.post(`/dungeons/${runId}/retreat`)
    },

    /** Вся экипировка игрока и на кого надета. */
    gear() {
        return api.get('/dungeons/gear')
    },

    /** Надеть предмет на питомца. */
    equip(petId, gearId) {
        return api.post(`/dungeons/gear/${petId}/equip`, { gear: gearId })
    },

    /** Снять предмет со слота. */
    unequip(petId, slot) {
        return api.post(`/dungeons/gear/${petId}/unequip/${slot}`)
    },
}
