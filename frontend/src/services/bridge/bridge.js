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

    /** Отметить вид и приложить видео. */
    declare(techniqueId, videoUrl) {
        return api.post('/bridge/techniques', {
            technique_id: techniqueId,
            video_url: videoUrl,
        })
    },

    /** Убрать свою заявку (подтверждённую убрать нельзя). */
    withdraw(submissionId) {
        return api.delete(`/bridge/submissions/${submissionId}`)
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

    deleteVariant(id) {
        return api.delete(`/bridge-curator/variants/${id}`)
    },
}
