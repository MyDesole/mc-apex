import { api } from '@/services/core/api.js'

/**
 * Бридж-тесты.
 *
 * Механика отличается от обычных тир-тестов: записи в очередь нет, игрок
 * отмечает вид бриджа и прикладывает видео, а проверяет бридж-тестер.
 */
export const bridgeApi = {
    /** Каталог видов вместе с подвидами и состоянием моих заявок. */
    techniques() {
        return api.get('/bridge/techniques')
    },

    /** Отметить вид: видео уже загружено частями. */
    declare(techniqueId, uploadId) {
        return api.post('/bridge/techniques', {
            technique_id: techniqueId,
            upload_id: uploadId,
        })
    },

    /** Убрать свою заявку (подтверждённую убрать нельзя). */
    withdraw(submissionId) {
        return api.delete(`/bridge/submissions/${submissionId}`)
    },

    /** Включить или выключить подвиды своей заявки. */
    setVariants(submissionId, variantIds) {
        return api.put(`/bridge/submissions/${submissionId}/variants`, {
            variants: variantIds,
        })
    },
}

/**
 * Видео бриджа: грузится частями прямо на Apex.
 *
 * Ролик на две минуты может весить сотни мегабайт, поэтому одним запросом
 * он не пройдёт.
 */
export const bridgeVideoApi = {
    /** Начать загрузку: сколько частей ждать. */
    init({ file_name, size, mime }) {
        return api.post('/bridge/uploads', { file_name, size, mime })
    },

    /** Отправить одну часть. */
    chunk(uuid, index, blob) {
        const form = new FormData()

        form.append('index', String(index))
        form.append('chunk', blob, `part-${index}`)

        return api.post(`/bridge/uploads/${uuid}/chunks`, form)
    },

    /** Собрать части в готовый файл. */
    complete(uuid) {
        return api.post(`/bridge/uploads/${uuid}/complete`)
    },
}

/** Проверка заявок: доступна только бридж-тестеру и админу. */
export const bridgeReviewApi = {
    pending() {
        return api.get('/bridge-review/submissions')
    },

    review(submissionId, payload) {
        return api.post(`/bridge-review/submissions/${submissionId}`, payload)
    },

    /** Звания для выбора. */
    ranks() {
        return api.get('/bridge-review/ranks')
    },

    /** История всех проверок: куратор видит и чужие проверки. */
    history() {
        return api.get('/bridge-review/history')
    },

    /** Бриджеры: здесь тестер выдаёт звания. */
    players() {
        return api.get('/bridge-review/players')
    },

    /** Присвоить звание (rankId = null снимает). */
    assignRank(userId, rankId) {
        return api.post(`/bridge-review/players/${userId}/rank`, { rank_id: rankId })
    },
}

/**
 * Куратор бриджа: каталог видов и подвидов.
 *
 * Доступ только у роли bridge_curator и админа.
 */
export const bridgeCuratorApi = {
    catalog() {
        return api.get('/bridge-curator/techniques')
    },

    createTechnique(payload) {
        return api.post('/bridge-curator/techniques', payload)
    },

    updateTechnique(id, payload) {
        return api.put(`/bridge-curator/techniques/${id}`, payload)
    },

    deleteTechnique(id) {
        return api.delete(`/bridge-curator/techniques/${id}`)
    },

    createVariant(techniqueId, label) {
        return api.post(`/bridge-curator/techniques/${techniqueId}/variants`, { label })
    },

    updateVariant(id, payload) {
        return api.put(`/bridge-curator/variants/${id}`, payload)
    },

    /** Пометить подвид особым. */
    setVariantSpecial(id, isSpecial) {
        return api.put(`/bridge-curator/variants/${id}`, { is_special: isSpecial })
    },

    deleteVariant(id) {
        return api.delete(`/bridge-curator/variants/${id}`)
    },
}
