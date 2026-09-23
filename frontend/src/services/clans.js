import { api } from './api.js'

export const clansApi = {
    list(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/clans${q ? '?' + q : ''}`)
    },
    top() {
        return api.get('/clans/top')
    },
    show(id) {
        return api.get(`/clans/${id}`)
    },
    create(payload) {
        return api.post('/clans', payload)
    },
    update(id, payload) {
        return api.put(`/clans/${id}`, payload)
    },
    apply(id, message) {
        return api.post(`/clans/${id}/apply`, { message })
    },
    acceptApplication(clanId, appId) {
        return api.post(`/clans/${clanId}/applications/${appId}/accept`)
    },
    declineApplication(clanId, appId) {
        return api.post(`/clans/${clanId}/applications/${appId}/decline`)
    },
    leave(id) {
        return api.post(`/clans/${id}/leave`)
    },
    kick(clanId, userId) {
        return api.delete(`/clans/${clanId}/members/${userId}`)
    },
    events(clanId) {
        return api.get(`/clans/${clanId}/events`)
    },
    createEvent(clanId, payload) {
        return api.post(`/clans/${clanId}/events`, payload)
    },
    deleteEvent(clanId, eventId) {
        return api.delete(`/clans/${clanId}/events/${eventId}`)
    },
    challenge(clanId, payload) {
        return api.post(`/clans/${clanId}/wars`, payload)
    },
    acceptWar(warId) {
        return api.post(`/wars/${warId}/accept`)
    },
    declineWar(warId) {
        return api.post(`/wars/${warId}/decline`)
    },
    completeWar(warId, payload) {
        return api.post(`/wars/${warId}/complete`, payload)
    },
    applications(clanId) {
        return api.get(`/clans/${clanId}/applications`)
    },
}