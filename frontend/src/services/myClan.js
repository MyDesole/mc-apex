import { api } from './api.js'

export const myClanApi = {
    dashboard() {
        return api.get('/my-clan')
    },

    // Форум
    forum(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/my-clan/forum${q ? '?' + q : ''}`)
    },
    createTopic(payload) {
        return api.post('/my-clan/forum', payload)
    },
    topic(id) {
        return api.get(`/my-clan/forum/${id}`)
    },
    reply(topicId, body, parentId = null) {
        return api.post(`/my-clan/forum/${topicId}/reply`, { body, parent_id: parentId })
    },
    pinTopic(id) {
        return api.post(`/my-clan/forum/${id}/pin`)
    },
    lockTopic(id) {
        return api.post(`/my-clan/forum/${id}/lock`)
    },
    deleteTopic(id) {
        return api.delete(`/my-clan/forum/${id}`)
    },

    // Ресурсы
    resources(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/my-clan/resources${q ? '?' + q : ''}`)
    },
    uploadResource(payload) {
        const fd = new FormData()
        for (const [k, v] of Object.entries(payload)) {
            if (v === undefined || v === null) continue
            if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
            else fd.append(k, v)
        }
        return api.post('/my-clan/resources', fd)
    },
    downloadResource(id) {
        return api.post(`/my-clan/resources/${id}/download`)
    },
    deleteResource(id) {
        return api.delete(`/my-clan/resources/${id}`)
    },

    // Роли
    updateRole(userId, payload) {
        return api.post(`/my-clan/roles/${userId}`, payload)
    },
    kickMember(userId) {
        return api.delete(`/my-clan/members/${userId}`)
    },
    transferLeadership(userId) {
        return api.post(`/my-clan/transfer/${userId}`)
    },
}