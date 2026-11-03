import { api } from '@/services/core/api.js'

/**
 * Игровой сервер: статус, консоль, питание, обновления.
 * Доступ — «Администратор сайта» и админ.
 */
export const gameServerApi = {
    status() {
        return api.get('/admin/server/status')
    },

    /** Точное число игроков (через list). */
    players() {
        return api.get('/admin/server/players')
    },

    /** Плагины на сервере. */
    plugins() {
        return api.get('/admin/server/plugins')
    },

    console(lines = 150) {
        return api.get('/admin/server/console', { params: { lines } })
    },

    /** action: start | stop | restart */
    power(action) {
        return api.post(`/admin/server/${action}`)
    },

    command(command) {
        return api.post('/admin/server/command', { command })
    },

    startCommand() {
        return api.get('/admin/server/start-command')
    },

    saveStartCommand(content) {
        return api.put('/admin/server/start-command', { content })
    },

    /** target: '' (корень) | plugins | config */
    upload(file, target = '') {
        const form = new FormData()
        form.append('file', file)

        if (target) form.append('target', target)

        return api.post('/admin/server/upload', form)
    },
}
