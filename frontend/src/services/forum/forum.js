import { api } from '@/services/core/api.js'

/**
 * Форум: разделы, темы, ответы, лайки, вложения.
 */
export const forumApi = {
    // Разделы + статистика (публично)
    index() {
        return api.get('/forum')
    },

    // Список тем: категория, поиск, сортировка, пагинация
    topics({ category = '', search = '', sort = 'activity', page = 1, perPage = 20 } = {}) {
        const params = new URLSearchParams()

        if (category) params.set('category', category)
        if (search) params.set('search', search)
        if (sort) params.set('sort', sort)
        params.set('page', String(page))
        params.set('per_page', String(perPage))

        return api.get(`/forum/topics?${params.toString()}`)
    },

    // Тема с ответами
    topic(id) {
        return api.get(`/forum/topics/${id}`)
    },

    createTopic(payload) {
        return api.post('/forum/topics', payload)
    },

    updateTopic(id, payload) {
        return api.put(`/forum/topics/${id}`, payload)
    },

    deleteTopic(id) {
        return api.delete(`/forum/topics/${id}`)
    },

    reply(topicId, payload) {
        return api.post(`/forum/topics/${topicId}/reply`, payload)
    },

    updateReply(id, payload) {
        return api.put(`/forum/replies/${id}`, payload)
    },

    deleteReply(id) {
        return api.delete(`/forum/replies/${id}`)
    },

    like(type, id) {
        return api.post(`/forum/like/${type}/${id}`)
    },

    search(q) {
        return api.get(`/forum/search?q=${encodeURIComponent(q)}`)
    },

    /**
     * Загрузка вложения. Возвращает запись с id — его передаём
     * в attachments при создании темы или ответа.
     */
    upload(file) {
        const body = new FormData()
        body.append('file', file)

        return api.post('/forum/upload', body)
    },
}
