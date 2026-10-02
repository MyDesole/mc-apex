import { onMounted, onUnmounted, ref, watch, computed } from 'vue'
import { chatApi } from '@/services/chat/chat.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { echoGeneration, onEchoReset } from '@/echo.js'

/**
 * Кто сейчас на сайте.
 *
 * Источников несколько, и они дополняют друг друга:
 *
 *   1. Присутствие собеседников открытого диалога опрашивается часто.
 *      Это главный источник: presence-канал знает только тех, кто сам
 *      открыл страницу чата, а собеседник может быть где угодно на сайте.
 *
 *   2. Presence-канал Reverb — мгновенные события входа и выхода. Он даёт
 *      зелёную точку сразу, как только собеседник заходит в чат.
 *
 *   3. Полный список с сервера — редко, как общая картина.
 *
 * Сервер считает онлайн по времени последней активности, поэтому статус
 * остаётся верным даже для тех, кто не открывал чат.
 */

/** Как часто обновлять присутствие собеседников открытого диалога. */
const PEERS_INTERVAL_MS = 15_000

/** Как часто обновлять полный список. */
const FALLBACK_INTERVAL_MS = 60_000

/** Общий набор на всё приложение: страница одна, состояние одно. */
const onlineIds = ref(new Set())

/**
 * Загружено ли присутствие хотя бы раз.
 *
 * Пока нет, страница может опираться на признак из данных диалога. После
 * загрузки источник один — данные присутствия: сервер считает онлайн по
 * времени последней активности, и после выхода игрока он остаётся
 * истинным ещё пару минут.
 */
const presenceReady = ref(false)

let channel = null
let peersTimer = null
let fullTimer = null
let subscribers = 0

/** Кого сейчас показываем: собеседники открытого диалога. */
const watchedIds = ref([])

/** Кто отметил вход: нужен, чтобы снять отметку при выходе. */
let markedOnlineUserId = null

/**
 * Безопасно зовёт метод сервиса.
 *
 * Отсутствие метода не должно ломать страницу: присутствие — вещь
 * дополнительная, при сбое просто не будет отметки.
 */
function callService(method, ...args) {
  try {
    const fn = chatApi[method]

    if (typeof fn !== 'function') return

    const result = fn.apply(chatApi, args)

    if (result && typeof result.catch === 'function') {
      result.catch(() => {})
    }
  } catch {
    /* Сервис недоступен — молча продолжаем */
  }
}

/** Разбирает ответ сервера в набор идентификаторов. */
function toSet(ids) {
  return new Set((Array.isArray(ids) ? ids : []).map(Number))
}

/** Полный список с сервера. */
async function fetchAll() {
  try {
    const data = await chatApi.presence()

    onlineIds.value = toSet(data.online)
    presenceReady.value = true
  } catch {
    /* Сеть недоступна — оставляем прежнее состояние, не гасим точки */
  }
}

/**
 * Спрашивает сервер о конкретных игроках.
 *
 * Запрос узкий: сервер проверяет только переданные идентификаторы.
 */
async function fetchWatched() {
  const ids = watchedIds.value

  if (!ids.length) return

  try {
    const data = await chatApi.presence(ids)
    const fresh = toSet(data.online)

    /*
     * Обновляем только тех, кого спрашивали: остальных не трогаем,
     * чтобы точки в списке диалогов не гасли из-за узкого запроса.
     */
    const next = new Set(onlineIds.value)

    ids.forEach((id) => {
      const key = Number(id)

      if (fresh.has(key)) next.add(key)
      else next.delete(key)
    })

    onlineIds.value = next
  } catch {
    /* Сеть недоступна — оставляем прежнее состояние */
  }
}

/** Подписка на presence-канал Reverb. */
function subscribeChannel() {
  if (!window.Echo || channel) return

  try {
    channel = window.Echo.join('online')

    channel.here((members) => {
      onlineIds.value = toSet(members.map((m) => m.id))
    })

    channel.joining((member) => {
      const next = new Set(onlineIds.value)

      next.add(Number(member.id))
      onlineIds.value = next

      /* Сервер тоже должен знать: он отдаёт статус другим */
      callService('presenceOnline')
    })

    channel.leaving((member) => {
      const next = new Set(onlineIds.value)

      next.delete(Number(member.id))
      onlineIds.value = next
    })
  } catch {
    /* Канал недоступен — останется опрос */
    channel = null
  }
}

/** Отписка от канала. */
function unsubscribeChannel() {
  if (!channel || !window.Echo) return

  try {
    window.Echo.leave('online')
  } catch {
    /* уже отключён */
  }

  channel = null
}

/** Запускает опрос собеседников. */
function startPeersPolling() {
  if (peersTimer) return

  peersTimer = setInterval(fetchWatched, PEERS_INTERVAL_MS)
}

/** Останавливает опрос собеседников. */
function stopPeersPolling() {
  if (!peersTimer) return

  clearInterval(peersTimer)
  peersTimer = null
}

/** Запускает общий опрос. */
function startFallback() {
  if (fullTimer) return

  fullTimer = setInterval(() => {
    /*
     * Если Reverb отдаёт данные, опрос всё равно полезен: он ловит тех,
     * кто закрыл вкладку, не дождавшись события выхода.
     */
    fetchAll()
  }, FALLBACK_INTERVAL_MS)
}

/** Останавливает общий опрос. */
function stopFallback() {
  if (!fullTimer) return

  clearInterval(fullTimer)
  fullTimer = null
}

/*
 * Пересоздание соединения: канал присутствия теряется, поэтому
 * подписываемся заново и обновляем список онлайна.
 */
onEchoReset(() => {
  // Если страница уже ушла с чата, подписываться заново не нужно
  if (subscribers <= 0) return

  channel = null
  subscribeChannel()
  fetchAll()
})

/**
 * Присутствие.
 *
 * @param {Function|import('vue').Ref<Array<number>>} [peers]
 *        за кем следить: список идентификаторов собеседников
 */
export function usePresence(peers = null) {
  /** Приводит вход к computed-ссылке. */
  const peerIds = peers
      ? (typeof peers === 'function' ? computed(peers) : peers)
      : null

  onMounted(async () => {
    /*
     * Хранилище берём внутри onMounted: к этому моменту Pinia уже
     * активна, и обращение не падает при монтировании в тестах.
     */
    const auth = useAuthStore()

    subscribers++

    if (subscribers === 1) {
      markedOnlineUserId = auth.user?.id ?? null
      subscribeChannel()
      startFallback()

      await fetchAll()

      /* Сервер отмечает вход: это запасной источник статуса */
      callService('presenceOnline')
    }

    /* Следим за собеседниками текущего диалога */
    if (peerIds) {
      watch(
          peerIds,
          (ids) => {
            watchedIds.value = (ids ?? []).map(Number).filter(Boolean)

            if (watchedIds.value.length) {
              startPeersPolling()

              // Спрашиваем сразу: статус мог устареть с прошлого круга
              fetchWatched()
            } else {
              stopPeersPolling()
            }
          },
          { immediate: true },
      )
    }
  })

  onUnmounted(() => {
    subscribers--

    if (subscribers <= 0) {
      subscribers = 0

      unsubscribeChannel()
      stopFallback()
      stopPeersPolling()

      watchedIds.value = []
      onlineIds.value = new Set()

      /*
       * Признак готовности снимаем: состояние общее на модуль, и без
       * сброса следующая страница сочтёт присутствие уже загруженным.
       */
      presenceReady.value = false

      if (markedOnlineUserId) {
        callService('presenceOffline')

        markedOnlineUserId = null
      }
    }
  })

  return {
    onlineIds,
    presenceReady,
    isOnline: (userId) => onlineIds.value.has(Number(userId)),
    refresh: fetchAll,
    refreshPeers: fetchWatched,
  }
}
