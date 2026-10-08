import { api } from '@/services/core/api.js'

/**
 * Ник в майнкрафте, заявленный на сайте.
 *
 * Ник уникален: два аккаунта не могут заявить один и тот же. Плагин на
 * сервере пускает только того, чей ник в игре совпадает с заявленным,
 * поэтому зайти под чужим ником нельзя.
 */
export const minecraftApi = {
    /** Какой ник заявлен. */
    status() {
        return api.get('/players/me/minecraft/link')
    },

    /** Заявить ник. */
    claim(nickname) {
        return api.post('/players/me/minecraft/link', { nickname })
    },

    /** Снять заявку. */
    release() {
        return api.delete('/players/me/minecraft/link')
    },
}
