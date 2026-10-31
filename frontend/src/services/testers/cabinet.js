import { api } from '@/services/core/api.js'

/**
 * Кабинет тестера и управление тестерами.
 *
 * Слоты календаря — обычные CRUD-запросы: календарь не держит состояние
 * на сервере, поэтому после любого действия перечитываем сводку.
 */
export const testerCabinetApi = {
    /** Сводка кабинета: профиль, статистика, слоты, заявки в работе, деньги. */
    dashboard() {
        return api.get('/tester/dashboard')
    },

    /** Слоты за окно (по умолчанию — ближайшие две недели). */
    slots(params = {}) {
        return api.get('/tester/slots', { params })
    },

    /** Открыть слот: начало и конец в ISO. */
    openSlot(payload) {
        return api.post('/tester/slots', payload)
    },

    /** Снять слот. */
    closeSlot(slotId) {
        return api.delete(`/tester/slots/${slotId}`)
    },

    /** Сводка заработка, история и запросы на выплату. */
    payouts() {
        return api.get('/tester/payouts')
    },

    /** Запросить выплату всего доступного. */
    requestPayout() {
        return api.post('/tester/payouts')
    },

    /* ---------- Для игрока ---------- */

    /** Свободные слоты тестеров: сгруппированы по человеку. */
    openSlots(days = 14) {
        return api.get('/tester-slots', { params: { days } })
    },

    /** Записаться на слот своей заявкой. */
    book(tierTestId, slotId) {
        return api.post(`/tier-tests/${tierTestId}/book`, { slot_id: slotId })
    },

    /* ---------- Для админа ---------- */

    adminTesters() {
        return api.get('/admin/testers')
    },

    adminCalendar(params = {}) {
        return api.get('/admin/testers/calendar', { params })
    },

    adminTests(params = {}) {
        return api.get('/admin/testers/tests', { params })
    },

    adminSettings() {
        return api.get('/admin/testers/settings')
    },

    saveSettings(payload) {
        return api.put('/admin/testers/settings', payload)
    },

    adminPayouts(params = {}) {
        return api.get('/admin/testers/payouts', { params })
    },

    payPayout(payoutId, comment = '') {
        return api.post(`/admin/testers/payouts/${payoutId}/pay`, { comment })
    },

    rejectPayout(payoutId, comment = '') {
        return api.post(`/admin/testers/payouts/${payoutId}/reject`, { comment })
    },

    blockTester(userId, payload) {
        return api.post(`/admin/testers/${userId}/block`, payload)
    },

    unblockTester(userId) {
        return api.post(`/admin/testers/${userId}/unblock`)
    },
}
