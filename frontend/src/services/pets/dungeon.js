import { api } from '@/services/core/api.js'

/**
 * Подземелья и экипировка питомцев.
 *
 * Поход — не потоковое соединение: каждый зал открывается отдельным
 * запросом, поэтому поход продолжается с любого устройства.
 */
// Справочник не меняется между ходами: грузим один раз за открытие сайта
let referenceCache = null

function loadReference() {
    referenceCache ??= api.get('/dungeons/reference').catch((e) => {
        referenceCache = null
        throw e
    })

    return referenceCache
}

export const dungeonApi = {
    /**
     * Сводка: подземелья, отряд, активный поход, экипировка.
     *
     * Сервер отдаёт отдельно изменчивое (поход, отряд) и справочник;
     * здесь они склеиваются в тот же объект, что был раньше.
     */
    async overview() {
        const [live, ref] = await Promise.all([api.get('/dungeons'), loadReference()])
        const available = Object.fromEntries(live.dungeons.map(d => [d.key, d.available]))
        const abilities = ref.reference?.abilities ?? {}

        return {
            ...live,
            dungeons: ref.dungeons.map(d => ({ ...d, available: available[d.key] ?? false })),
            features: ref.features,
            reference: ref.reference,
            pets: live.pets.map(p => ({ ...p, ability: abilities[p.element] ?? null })),
        }
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
    /** Склад материалов для крафта. */
    craftIndex() {
        return api.get('/dungeons/craft')
    },

    /** Скрафтить предмет из трёх материалов. */
    craft(material) {
        return api.post('/dungeons/craft', { material })
    },

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
