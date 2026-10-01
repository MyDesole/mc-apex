import { api } from './api.js'

export const chatApi = {
    // Список диалогов
    conversations() {
        return api.get('/chat/conversations')
    },

    /**
     * История сообщений с курсорной пагинацией.
     *
     * @param {number} id        id диалога
     * @param {object} options   before_id / after_id / limit / mark_read
     */
    show(id, options = {}) {
        const params = new URLSearchParams()

        if (options.beforeId) params.set('before_id', String(options.beforeId))
        if (options.afterId) params.set('after_id', String(options.afterId))
        if (options.limit) params.set('limit', String(options.limit))
        if (options.markRead === false) params.set('mark_read', '0')

        const query = params.toString()

        return api.get(`/chat/conversations/${id}${query ? '?' + query : ''}`)
    },

    // Создать/получить диалог с юзером
    startDirect(userId) {
        return api.post('/chat/start-direct', { user_id: userId })
    },

    // Создать/получить диалог с кланом
    startClan(clanId) {
        return api.post('/chat/start-clan', { clan_id: clanId })
    },

    /**
     * Отправить сообщение. attachmentIds — уже загруженные файлы.
     */
    send(id, body, replyToId = null, attachmentIds = []) {
        return api.post(`/chat/conversations/${id}/messages`, {
            body,
            reply_to_id: replyToId,
            attachments: attachmentIds,
        })
    },

    // Редактировать своё сообщение
    updateMessage(messageId, body) {
        return api.put(`/chat/messages/${messageId}`, { body })
    },

    // Удалить своё сообщение
    deleteMessage(messageId) {
        return api.delete(`/chat/messages/${messageId}`)
    },

    forward(messageId, conversationId) {
        return api.post(`/chat/messages/${messageId}/forward`, {
            conversation_id: conversationId,
        })
    },

    /**
     * Загрузить один файл к будущему сообщению.
     */
    upload(file) {
        const data = new FormData()
        data.append('file', file)

        return api.post('/chat/attachments', data)
    },

    /**
     * Загрузить пачку файлов одним запросом.
     *
     * Сервер принимает массив files и возвращает список загруженного.
     * Так пачка уходит одним обращением вместо N последовательных.
     *
     * @param {File[]} files
     */
    uploadMany(files) {
        const data = new FormData()

        files.forEach((file) => data.append('files[]', file))

        return api.post('/chat/attachments', data)
    },

    // Пометить диалог прочитанным
    markConversationRead(id) {
        return api.post(`/chat/conversations/${id}/read`)
    },

    // Общий счётчик непрочитанных
    unreadCount() {
        return api.get('/chat/unread-count')
    },

    search(q) {
        return api.get(`/chat/search?q=${encodeURIComponent(q)}`)
    },
}
