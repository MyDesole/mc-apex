import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { chatApi } from '@/services/chat/chat.js'
import { onEchoReset } from '@/echo.js'

/**
 * Кто сейчас на сайте.
 *
 * Раньше в канал присутствия входили только те, кто открыл страницу
 * чата, поэтому собеседник, сидящий на главной, для канала не
 * существовал, и статус узнавался опросом с задержкой до минуты.
 * Теперь в канал входит всё приложение, пока игрок авторизован
 * (startSitePresence в AppLayout), — вход и выход видны мгновенно.
 *
 * Источники:
 *   1. Канал присутствия Reverb — главный. Мгновенные вход и выход.
 *   2. Сервер — запасной: для тех, у кого сокет не поднялся. Сервер
 *      считает онлайн по пульсу (раз в HEARTBEAT_MS) и по активности.
 *
 * Если игрок вышел из канала, сервер ещё какое-то время считает его
 * онлайн. Чтобы точка гасла сразу, ушедших из канала не берём из
 * серверного списка, пока он не обновится после их ухода.
 */

/** Как часто сообщать серверу, что вкладка открыта. */
const HEARTBEAT_MS = 30_000

/** Как часто обновлять полный список с сервера. */
const SERVER_REFRESH_MS = 60_000

/** Как часто спрашивать о собеседниках открытого диалога. */
const PEERS_INTERVAL_MS = 15_000

/**
 * Сколько не верить серверу о том, кто вышел из канала: столько сервер
 * в худшем случае держит отметку присутствия (PresenceService).
 */
const LEFT_SUPPRESS_MS = 90_000

/* ------------------------------ Состояние на модуль ------------------------------ */

const channelMembers = ref(new Set())
const channelReady = ref(false)

const serverOnline = ref(new Set())
const serverReady = ref(false)

/** Кто и когда вышел из канала. */
const leftAt = ref(new Map())

const onlineIds = computed(() => {
  const ids = new Set(channelMembers.value)
  const now = Date.now()

  serverOnline.value.forEach((id) => {
    const left = leftAt.value.get(id)

    if (!left || now - left > LEFT_SUPPRESS_MS) ids.add(id)
  })

  return ids
})

const presenceReady = computed(() => channelReady.value || serverReady.value)

let sessionUserId = null
let channel = null
let heartbeatTimer = null
let refreshTimer = null
let stopEchoReset = null

/* ------------------------------ Помощники ------------------------------ */

function toSet(ids) {
  return new Set((Array.isArray(ids) ? ids : []).map(Number).filter(Boolean))
}

function withLeft(mutate) {
  const next = new Map(leftAt.value)
  mutate(next)
  leftAt.value = next
}

/** Безопасный вызов сервиса: присутствие не должно ронять страницу. */
async function call(method, ...args) {
  try {
    return await chatApi[method]?.(...args)
  } catch {
    return null
  }
}

/** Полный список с сервера. */
async function fetchAll() {
  const data = await call('presence')

  if (!data) return

  serverOnline.value = toSet(data.online)
  serverReady.value = true
}

/** Пульс: сервер продлевает отметку и заодно отдаёт свежий список. */
async function heartbeat() {
  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return

  const data = await call('presenceOnline')

  if (data?.online) {
    serverOnline.value = toSet(data.online)
    serverReady.value = true
  }
}

/* ------------------------------ Канал ------------------------------ */

function joinChannel() {
  if (!window.Echo || channel) return

  try {
    channel = window.Echo.join('online')

    channel.here((members) => {
      const ids = toSet(members.map((member) => member.id))

      channelMembers.value = ids
      channelReady.value = true

      withLeft((left) => ids.forEach((id) => left.delete(id)))
    })

    channel.joining((member) => {
      const id = Number(member.id)

      channelMembers.value = new Set([...channelMembers.value, id])
      withLeft((left) => left.delete(id))
    })

    channel.leaving((member) => {
      const id = Number(member.id)
      const next = new Set(channelMembers.value)

      next.delete(id)
      channelMembers.value = next

      withLeft((left) => left.set(id, Date.now()))
    })
  } catch {
    // Канал недоступен — останется сервер
    channel = null
  }
}

function leaveChannel() {
  if (channel) {
    try {
      window.Echo?.leave('online')
    } catch {
      /* уже отключено */
    }
  }

  channel = null
  channelReady.value = false
  channelMembers.value = new Set()
}

function onVisibility() {
  if (document.visibilityState === 'visible') {
    heartbeat()
  }
}

/* ------------------------------ Сеанс на всё приложение ------------------------------ */

/**
 * Начать присутствие: вызывается, когда игрок авторизован.
 * Повторный вызов для того же игрока ничего не делает.
 */
export function startSitePresence(userId) {
  if (!userId) return

  if (sessionUserId === userId) return

  stopSitePresence({ silent: true })

  sessionUserId = userId

  joinChannel()
  heartbeat()
  fetchAll()

  heartbeatTimer = setInterval(heartbeat, HEARTBEAT_MS)
  refreshTimer = setInterval(fetchAll, SERVER_REFRESH_MS)

  document.addEventListener('visibilitychange', onVisibility)

  // Соединение пересоздано — канал потерян, входим заново
  stopEchoReset = onEchoReset(() => {
    channel = null
    channelReady.value = false
    joinChannel()
    fetchAll()
  })
}

/**
 * Закончить присутствие: выход из аккаунта.
 * silent — без сообщения серверу (смена пользователя, сессия уже другая).
 */
export function stopSitePresence({ silent = false } = {}) {
  if (!sessionUserId) return

  if (!silent) call('presenceOffline')

  sessionUserId = null

  clearInterval(heartbeatTimer)
  clearInterval(refreshTimer)
  heartbeatTimer = null
  refreshTimer = null

  document.removeEventListener('visibilitychange', onVisibility)
  stopEchoReset?.()
  stopEchoReset = null

  leaveChannel()

  serverOnline.value = new Set()
  serverReady.value = false
  leftAt.value = new Map()
}

/* ------------------------------ Для страниц ------------------------------ */

/**
 * Присутствие на странице.
 *
 * @param {Function|import('vue').Ref<Array<number>>} [peers]
 *        собеседники открытого диалога: о них спрашиваем сервер чаще —
 *        на случай, если у кого-то из них не поднялся сокет
 */
export function usePresence(peers = null) {
  const peerIds = peers
      ? (typeof peers === 'function' ? computed(peers) : peers)
      : null

  let peersTimer = null
  let stopWatch = null

  async function fetchPeers() {
    const ids = (peerIds?.value ?? []).map(Number).filter(Boolean)

    if (!ids.length) return

    const data = await call('presence', ids)

    if (!data) return

    const fresh = toSet(data.online)
    const next = new Set(serverOnline.value)

    // Обновляем только спрошенных: остальных узкий запрос не касается
    ids.forEach((id) => (fresh.has(id) ? next.add(id) : next.delete(id)))

    serverOnline.value = next
    serverReady.value = true
  }

  onMounted(() => {
    // Страница могла открыться раньше, чем сработал сеанс — список нужен сразу
    if (!serverReady.value) fetchAll()

    if (!peerIds) return

    stopWatch = watch(
        peerIds,
        (ids) => {
          clearInterval(peersTimer)
          peersTimer = null

          if (ids?.length) {
            fetchPeers()
            peersTimer = setInterval(fetchPeers, PEERS_INTERVAL_MS)
          }
        },
        { immediate: true },
    )
  })

  onUnmounted(() => {
    clearInterval(peersTimer)
    stopWatch?.()
  })

  return {
    onlineIds,
    presenceReady,
    isOnline: (userId) => onlineIds.value.has(Number(userId)),
    refresh: fetchAll,
    refreshPeers: fetchPeers,
  }
}
