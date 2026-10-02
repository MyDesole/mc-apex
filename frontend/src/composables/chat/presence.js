import { onMounted, onUnmounted, ref } from 'vue'
import { chatApi } from '@/services/chat/chat.js'
import { useAuthStore } from '@/stores/core/auth.js'

/**
 * Кто сейчас на сайте.
 *
 * Источников два, и они дополняют друг друга:
 *
 *   1. Presence-канал Reverb — мгновенно. Пока вкладка открыта, Reverb
 *      сообщает о входе и выходе, и зелёная точка появляется сразу.
 *
 *   2. Периодический опрос сервера — запасной вариант. Работает, если
 *      Reverb недоступен или соединение оборвалось: сервер считает
 *      онлайн по времени последней активности.
 *
 * Опрос идёт редко (раз в минуту) и только пока открыт чат.
 */

/** Как часто спрашивать сервер, если Reverb молчит. */
const FALLBACK_INTERVAL_MS = 60_000

/** Общий набор на всё приложение: страница одна, состояние одно. */
const onlineIds = ref(new Set())

let channel = null
let timer = null
let subscribers = 0

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
  } catch {
    /* Сеть недоступна — оставляем прежнее состояние, не гасим точки */
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

/** Запускает опрос как запасной источник. */
function startFallback() {
  if (timer) return

  timer = setInterval(() => {
    /*
     * Если Reverb отдаёт данные, опрос всё равно полезен: он ловит тех,
     * кто закрыл вкладку, не дождавшись события выхода.
     */
    fetchAll()
  }, FALLBACK_INTERVAL_MS)
}

/** Останавливает опрос. */
function stopFallback() {
  if (!timer) return

  clearInterval(timer)
  timer = null
}

export function usePresence() {
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
  })

  onUnmounted(() => {
    subscribers--

    if (subscribers <= 0) {
      subscribers = 0

      unsubscribeChannel()
      stopFallback()

      if (markedOnlineUserId) {
        callService('presenceOffline')

        markedOnlineUserId = null
      }
    }
  })

  return {
    onlineIds,
    isOnline: (userId) => onlineIds.value.has(Number(userId)),
    refresh: fetchAll,
  }
}
