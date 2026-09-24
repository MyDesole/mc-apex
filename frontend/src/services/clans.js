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
        const fd = new FormData()
        fd.append('_method', 'PUT')

        for (const [key, value] of Object.entries(payload)) {
            if (value === undefined || value === null) continue

            if (key === 'socials') {
                for (const [k, v] of Object.entries(value)) {
                    if (v) fd.append(`socials[${k}]`, v)
                }
            } else if (key === 'is_highlighted' || key === 'is_open') {
                fd.append(key, value ? '1' : '0')
            } else {
                fd.append(key, value)
            }
        }

        return api.post(`/clans/${id}`, fd)
    },

    removeCover(id) {
        return api.post(`/clans/${id}/cover/remove`)
    },
    eventComments(clanId, eventId) {
        return api.get(`/clans/${clanId}/events/${eventId}/comments`)
    },
    createEventComment(clanId, eventId, body, parentId = null) {
        return api.post(`/clans/${clanId}/events/${eventId}/comments`, {
            body,
            parent_id: parentId,
        })
    },
    deleteEventComment(clanId, eventId, commentId) {
        return api.delete(`/clans/${clanId}/events/${eventId}/comments/${commentId}`)
    },
    removeAvatar(id) {
        return api.post(`/clans/${id}/avatar/remove`)
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


    war(warId) {
        return api.get(`/wars/${warId}`)
    },
    joinWar(warId) {
        return api.post(`/wars/${warId}/join`)
    },
    leaveWar(warId) {
        return api.post(`/wars/${warId}/leave`)
    },


}