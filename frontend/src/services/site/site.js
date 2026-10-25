import { api } from '@/services/core/api.js'

// Один запрос /site на загрузку страницы: его ждут подвал и баннер объявления
let infoPromise = null

export const siteApi = {
    /** Подвал: соцсети, адрес сервера, баннер и состояние «Пульта». */
    info() {
        infoPromise ??= api.get('/site').catch((e) => {
            infoPromise = null
            throw e
        })
        return infoPromise
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
