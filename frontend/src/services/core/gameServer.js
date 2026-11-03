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

    /* ---------- Конфиги ---------- */

    files() {
        return api.get('/admin/server/files')
    },

    readFile(path) {
        return api.get('/admin/server/file', { params: { path } })
    },

    saveFile(path, content) {
        return api.put('/admin/server/file', { path, content })
    },

    /* ---------- Архивы и бэкапы ---------- */

    archives() {
        return api.get('/admin/server/archives')
    },

    uploadArchive(file) {
        const form = new FormData()
        form.append('file', file)

        return api.post('/admin/server/archives', form)
    },

    applyArchive(name) {
        return api.post(`/admin/server/archives/${encodeURIComponent(name)}/apply`)
    },

    deleteArchive(name) {
        return api.delete(`/admin/server/archives/${encodeURIComponent(name)}`)
    },

    restoreBackup(name) {
        return api.post(`/admin/server/backups/${encodeURIComponent(name)}/restore`)
    },

    /** CPU, TPS, ники онлайна. */
    metrics() {
        return api.get('/admin/server/metrics')
    },

    pluginsState() {
        return api.get('/admin/server/plugins-state')
    },

    /* ---------- Файловый менеджер ---------- */

    fmTree(path = '') {
        return api.get('/admin/server/fm/tree', { params: { path } })
    },

    fmRead(path) {
        return api.get('/admin/server/fm/read', { params: { path } })
    },

    fmWrite(path, content) {
        return api.put('/admin/server/fm/write', { path, content })
    },

    fmCreate(path, kind) {
        return api.post('/admin/server/fm/create', { path, kind })
    },

    fmRename(from, to) {
        return api.post('/admin/server/fm/rename', { from, to })
    },

    fmTrash(path) {
        return api.post('/admin/server/fm/trash', { path, confirm: true })
    },

    fmTrashList() {
        return api.get('/admin/server/fm/trash')
    },

    fmUpload(file, dir = '') {
        const form = new FormData()
        form.append('file', file)
        form.append('dir', dir)

        return api.post('/admin/server/fm/upload', form)
    },

    /** Ссылка на скачивание: авторизация по сессионной куке. */
    fmDownloadUrl(path) {
        return `/api/admin/server/fm/download?path=${encodeURIComponent(path)}`
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
