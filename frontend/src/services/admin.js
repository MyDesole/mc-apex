import { api } from './api.js'

export const adminApi = {
    // Юзеры
    users(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/users${q ? '?' + q : ''}`)
    },
    user(id) {
        return api.get(`/admin/users/${id}`)
    },
    ban(id, payload) {
        return api.post(`/admin/users/${id}/ban`, payload)
    },
    unban(id) {
        return api.post(`/admin/users/${id}/unban`)
    },
    setRole(id, role) {
        return api.post(`/admin/users/${id}/role`, { role })
    },

    // Ачивки
    grantAchievement(userId, achievementId) {
        return api.post(`/admin/users/${userId}/achievements/${achievementId}`)
    },
    revokeAchievement(userId, achievementId) {
        return api.delete(`/admin/users/${userId}/achievements/${achievementId}`)
    },

    // Аспекты
    updateAspects(userId, payload) {
        return api.put(`/admin/users/${userId}/aspects`, payload)
    },
    conductTierTest(userId, payload) {
        return api.post(`/admin/users/${userId}/tier-test`, payload)
    },

    // Кланы
    banClan(id, reason) {
        return api.post(`/admin/clans/${id}/ban`, { reason })
    },
    unbanClan(id) {
        return api.post(`/admin/clans/${id}/unban`)
    },
    removeClanAvatar(id) {
        return api.post(`/admin/clans/${id}/avatar/remove`)
    },
    removeClanCover(id) {
        return api.post(`/admin/clans/${id}/cover/remove`)
    },
    destroyClan(id) {
        return api.delete(`/admin/clans/${id}`)
    },

    // Комментарии
    comments(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/comments${q ? '?' + q : ''}`)
    },
    deleteComment(id) {
        return api.delete(`/admin/comments/${id}`)
    },
    events(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/clan-events${q ? '?' + q : ''}`)
    },
    deleteEvent(id) {
        return api.delete(`/admin/clan-events/${id}`)
    },
    clanStats(clanId, payload) {
        return api.post(`/admin/clans/${clanId}/stats`, payload)
    },
    setClanStats(clanId, payload) {
        return api.put(`/admin/clans/${clanId}/stats`, payload)
    },
    clans(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/clans${q ? '?' + q : ''}`)
    },
    // Турниры
    tournaments(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/tournaments${q ? '?' + q : ''}`)
    },
    createTournament(fd) {
        return api.post('/admin/tournaments', fd)
    },
    updateTournament(id, payload) {
        return api.put(`/admin/tournaments/${id}`, payload)
    },
    destroyTournament(id) {
        return api.delete(`/admin/tournaments/${id}`)
    },
    tournamentParticipants(id) {
        return api.get(`/admin/tournaments/${id}/participants`)
    },
    approveParticipant(tid, pid) {
        return api.post(`/admin/tournaments/${tid}/participants/${pid}/approve`)
    },
    rejectParticipant(tid, pid) {
        return api.post(`/admin/tournaments/${tid}/participants/${pid}/reject`)
    },
    setSeeds(tid, seeds) {
        return api.post(`/admin/tournaments/${tid}/seeds`, { seeds })
    },
    tournamentMatches(tid) {
        return api.get(`/admin/tournaments/${tid}/matches`)
    },
    generateBracket(tid) {
        return api.post(`/admin/tournaments/${tid}/bracket/generate`)
    },
    updateMatch(tid, mid, payload) {
        return api.put(`/admin/tournaments/${tid}/matches/${mid}`, payload)
    },
    achievements(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/achievements${q ? '?' + q : ''}`)
    },
    createAchievement(payload) {
        return api.post('/admin/achievements', payload)
    },
    updateAchievement(id, payload) {
        return api.put(`/admin/achievements/${id}`, payload)
    },
    destroyAchievement(id) {
        return api.delete(`/admin/achievements/${id}`)
    },

    // Главная страница
    siteSettings() {
        return api.get('/admin/site-settings')
    },
    updateSiteSettings(payload) {
        return api.put('/admin/site-settings', payload)
    },

// Новости
    newsList(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/news${q ? '?' + q : ''}`)
    },
    createNews(payload) {
        const fd = new FormData()
        fd.append('_method', 'POST')
        for (const [k, v] of Object.entries(payload)) {
            if (v === undefined || v === null) continue
            if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
            else fd.append(k, v)
        }
        return api.post('/admin/news', fd)
    },
    updateNews(id, payload) {
        const fd = new FormData()
        fd.append('_method', 'PUT')
        for (const [k, v] of Object.entries(payload)) {
            if (v === undefined || v === null) continue
            if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
            else fd.append(k, v)
        }
        return api.post(`/admin/news/${id}`, fd)
    },
    destroyNews(id) {
        return api.delete(`/admin/news/${id}`)
    },

    // Верификация
    verifiedUsers(params = {}) {
        const q = new URLSearchParams(params).toString()
        return api.get(`/admin/users/verified${q ? '?' + q : ''}`)
    },
    verifyUser(id, reason = null) {
        return api.post(`/admin/users/${id}/verify`, { reason })
    },
    unverifyUser(id) {
        return api.post(`/admin/users/${id}/unverify`)
    },
}