import { api } from '@/services/core/api.js'

/** Дуэли и онлайн с игрового сервера. */
export const duelsApi = {
  /** История и статистика игрока по режимам. */
  player(id, params = {}) {
    return api.get(`/players/${encodeURIComponent(id)}/duels`, { params })
  },

  /** Топ кланов по победам участников в дуэлях. */
  clans() {
    return api.get('/minecraft/clan-duels')
  },

  /** Игроки сайта, которые сейчас на сервере. */
  online() {
    return api.get('/minecraft/online')
  },
}
