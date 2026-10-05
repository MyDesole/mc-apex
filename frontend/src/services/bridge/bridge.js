import { api } from '@/services/core/api.js'

/**
 * Бридж-тесты.
 *
 * Механика отличается от обычных тир-тестов: записи в очередь нет, игрок
 * отмечает вид бриджа и прикладывает видео, а проверяет бридж-тестер.
 */
export const bridgeApi = {
    /** Каталог видов вместе с состоянием моих заявок. */
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
}
