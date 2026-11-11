import { api } from '@/services/core/api.js'

/** Чат заявки на тир-тест: тестируемый ↔ тестер. */
export const tierChatApi = {
    /** Мои активные чаты и число непрочитанного. */
    active() {
        return api.get('/tier-chat')
    },
    /** Переписка; after — id последнего известного сообщения (для опроса). */
    thread(testId, after = 0) {
        return api.get(`/tier-tests/${testId}/chat`, { params: after ? { after } : {} })
    },
    send(testId, body) {
        return api.post(`/tier-tests/${testId}/chat`, { body })
    },
}
