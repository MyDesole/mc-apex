import { api } from './api.js'

export const chatApi = {
    // Список диалогов
    conversations() {
        return api.get('/chat/conversations')
    },

    // История + пометка прочитанного
    show(id) {
        return api.get(`/chat/conversations/${id}`)
    },

    // Создать/получить диалог с юзером
    startDirect(userId) {
        return api.post('/chat/start-direct', { user_id: userId })
    },

    // Создать/получить диалог с кланом
    startClan(clanId) {
        return api.post('/chat/start-clan', { clan_id: clanId })
    },

    // Отправить сообщение
    send(id, body, replyToId = null) {
        return api.post(`/chat/conversations/${id}/messages`, {
            body,
            reply_to_id: replyToId,
        })
    },



    forward(messageId, conversationId) {
        return api.post(`/chat/messages/${messageId}/forward`, {
            conversation_id: conversationId,
        })
    },

    // Общий счётчик непрочитанных
    unreadCount() {
        return api.get('/chat/unread-count')
    },
    search(q) {
        return api.get(`/chat/search?q=${encodeURIComponent(q)}`)
    },
}