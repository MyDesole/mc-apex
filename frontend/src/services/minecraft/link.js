import { api } from '@/services/core/api.js'

/**
 * Привязка майнкрафт-игрока к аккаунту.
 *
 * Игрок заходит на сервер, получает код в чате и вводит его здесь.
 * Пароль при привязке не участвует — он нужен только для входа в игре.
 */
export const minecraftApi = {
    /** Состояние привязки. */
    status() {
        return api.get('/players/me/minecraft/link')
    },

    /** Ввести код из игры. */
    link(code) {
        return api.post('/players/me/minecraft/link', { code })
    },

    /** Отвязать игрока. */
    unlink() {
        return api.delete('/players/me/minecraft/link')
    },
}
