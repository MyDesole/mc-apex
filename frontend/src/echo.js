/**
 * Автоматическое восстановление соединения с Reverb.
 *
 * pusher-js в состоянии failed больше не пытается подключиться: метод
 * connect() отказывается работать, если состояние не initialized. После
 * любого обрыва — перезапуск Reverb, короткая потеря сети, сон ноутбука —
 * соединение остаётся мёртвым до перезагрузки страницы, и новые сообщения
 * не приходят.
 *
 * Поэтому следим за состоянием и при отказе пересоздаём соединение.
 * На пересоздание подписчики реагируют повторной подпиской на каналы.
 *
 * Адрес берётся из окружения: wsHost и wsPort задаются всегда, в том
 * числе при работе по https — pusher-js строит защищённый адрес из wsPort,
 * поэтому оставлять его по умолчанию нельзя (получалось wss://хост:80).
 */
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const host = import.meta.env.VITE_REVERB_HOST || 'localhost'
const port = Number(import.meta.env.VITE_REVERB_PORT || 8080)
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
const secure = scheme === 'https'

const KEY = import.meta.env.VITE_REVERB_APP_KEY

/** Общие настройки подключения. */
function options() {
  return {
    broadcaster: 'reverb',
    key: KEY,
    wsHost: host,
    // Оба порта одинаковые: иначе wss уйдёт на порт 80
    wsPort: port,
    wssPort: port,
    forceTLS: secure,
    enabledTransports: secure ? ['wss'] : ['ws'],
    authEndpoint: '/broadcasting/auth',
    // Библиотека пытается сама, но из состояния failed не выходит
    activityTimeout: 30000,
  }
}

/** Сколько раз соединение пересоздавалось: подписчики следят за этим. */
export const echoGeneration = { value: 0 }

/** Кто хочет узнать о пересоздании соединения. */
const listeners = new Set()

/** Подписаться на пересоздание соединения. */
export function onEchoReset(callback) {
  listeners.add(callback)

  return () => listeners.delete(callback)
}

/** Создаёт соединение. */
function createEcho() {
  return new Echo(options())
}

window.Echo = createEcho()

/** Пересоздаёт соединение и сообщает об этом подписчикам. */
let resetting = false

export function resetEcho() {
  if (resetting) return

  resetting = true

  try {
    const old = window.Echo

    if (old) {
      try {
        old.disconnect()
      } catch {
        /* уже отключено */
      }

      try {
        // Очищаем подписки: их восстановят обработчики ниже
        Object.keys(old.connector?.channels ?? {}).forEach((name) => old.leave(name))
      } catch {
        /* нечего очищать */
      }
    }

    window.Echo = createEcho()
    echoGeneration.value += 1

    listeners.forEach((callback) => {
      try {
        callback()
      } catch {
        /* одна ошибка не должна мешать остальным */
      }
    })
  } finally {
    resetting = false
  }
}

/* --------------------------- Сторож --------------------------- */

/** Как часто проверять состояние соединения. */
const WATCH_INTERVAL_MS = 15000

/** Сколько ждать подключения, прежде чем считать попытку неудачной. */
const STUCK_MS = 45000

let watchTimer = null
let lastOkAt = 0

/** Состояние соединения: connected, connecting, unavailable, failed. */
function connectionState() {
  return window.Echo?.connector?.pusher?.connection?.state ?? 'нет'
}

function startWatchdog() {
  if (watchTimer) return

  lastOkAt = Date.now()

  watchTimer = setInterval(() => {
    const state = connectionState()

    if (state === 'connected') {
      lastOkAt = Date.now()

      return
    }

    /*
     * failed — тупик, нужен пересоздание.
     * unavailable или долгое connecting — тоже повод: соединение не
     * поднялось, а ждать бесконечно нельзя.
     */
    const stuck = state === 'connecting' && Date.now() - lastOkAt > STUCK_MS

    if (state === 'failed' || state === 'unavailable' || stuck) {
      resetEcho()
      lastOkAt = Date.now()
    }
  }, WATCH_INTERVAL_MS)
}

startWatchdog()

// Возврат из сна или восстановление сети: сразу проверяем соединение
window.addEventListener('online', () => {
  if (connectionState() !== 'connected') resetEcho()
})

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && connectionState() !== 'connected') {
    resetEcho()
  }
})
