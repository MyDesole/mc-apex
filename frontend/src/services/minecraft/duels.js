import { api } from '@/services/core/api.js'

/** Дуэли и онлайн с игрового сервера. */
export const duelsApi = {
  recent(params = {}) { return api.get('/minecraft/duels/recent', { params }) },
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
