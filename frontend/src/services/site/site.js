import { api } from '@/services/core/api.js'

export const siteApi = {
    /** Подвал: соцсети, текст и адрес Minecraft-сервера. */
    info() {
        return api.get('/site')
    },

    /** Статус Minecraft-сервера: онлайн, игроки, версия, MOTD. */
    serverStatus() {
        return api.get('/site/server-status')
    },

    /** Публичная статистика: тиры, средние аспекты, тесты по месяцам. */
    stats() {
        return api.get('/stats')
    },

    /** Команда проекта. */
    team() {
        return api.get('/site/team')
    },
}
