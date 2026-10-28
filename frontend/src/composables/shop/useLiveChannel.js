import { onUnmounted, ref } from 'vue'
import { onEchoReset } from '@/echo.js'

/**
 * Подписка на публичный канал Reverb с переподпиской после пересоздания
 * соединения. live — сокет жив: пока он лежит, страница опрашивает сервер.
 *
 * @param {Record<string, Function>} handlers  событие (без точки) => обработчик
 */
export function useLiveChannel(handlers) {
  const live = ref(false)
  let current = null

  function subscribe(name) {
    leave()
    current = name
    if (!name || !window.Echo) return

    const channel = window.Echo.channel(name)
    Object.entries(handlers).forEach(([event, handler]) => channel.listen(`.${event}`, handler))
  }

  function leave() {
    if (!current) return
    try {
      window.Echo?.leave(current)
    } catch {
      /* соединения уже нет */
    }
    current = null
  }

  function readState() {
    live.value = window.Echo?.connector?.pusher?.connection?.state === 'connected'
  }

  readState()
  const stateTimer = setInterval(readState, 3000)
  const stopReset = onEchoReset(() => subscribe(current))

  onUnmounted(() => {
    leave()
    clearInterval(stateTimer)
    stopReset()
  })

  return { live, subscribe, leave }
}
