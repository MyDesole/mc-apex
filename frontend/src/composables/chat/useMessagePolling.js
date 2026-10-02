/**
 * Запасной опрос сообщений.
 *
 * Соединение с Reverb иногда не восстанавливается, и тогда новые
 * сообщения не приходят до перезагрузки страницы. Чтобы чат работал
 * всегда, дополнительно опрашиваем сервер.
 *
 * Опрос редкий и дешёвый: один запрос раз в несколько секунд, только
 * пока открыт диалог. Если соединение живо, он ничего не меняет —
 * пришедшие по нему сообщения просто уже есть в списке.
 *
 * Забираем сообщения после самого свежего известного id, поэтому запрос
 * всегда маленький.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { chatApi } from '@/services/chat/chat.js'

/** Как часто проверять новые сообщения. */
const INTERVAL_MS = 5000

/**
 * Опрашивает сервер, пока открыт диалог.
 *
 * @param {import('vue').Ref<number|null>} conversationId  текущий диалог
 * @param {import('vue').Ref<Array>} messages  список сообщений
 * @param {{ onNew?: (messages: Array) => void }} options
 */
export function useMessagePolling(conversationId, messages, options = {}) {
  const polling = ref(false)

  let timer = null

  /** Самый свежий известный id в диалоге. */
  function newestId() {
    if (!messages.value.length) return null

    return messages.value[messages.value.length - 1].id
  }

  async function tick() {
    const id = conversationId.value

    if (!id) return

    try {
      const afterId = newestId()

      const data = await chatApi.show(id, {
        afterId,
        limit: 50,
        markRead: false,
      })

      const fresh = data.messages ?? []

      if (!fresh.length) return

      /*
       * Добавляем только то, чего ещё нет: сообщение могло прийти и по
       * websocket, и опросом.
       */
      const known = new Set(messages.value.map((m) => m.id))
      const added = fresh.filter((m) => !known.has(m.id))

      if (!added.length) return

      messages.value = [...messages.value, ...added]

      options.onNew?.(added)
    } catch {
      /* Сеть недоступна — попробуем на следующем круге */
    }
  }

  function start() {
    if (timer) return

    polling.value = true

    // Первый опрос сразу: вдруг соединение уже мертво
    tick()

    timer = setInterval(tick, INTERVAL_MS)
  }

  function stop() {
    if (!timer) return

    clearInterval(timer)
    timer = null
    polling.value = false
  }

  // Опрашиваем только когда открыт диалог
  watch(
      conversationId,
      (id) => {
        if (id) start()
        else stop()
      },
      { immediate: true },
  )

  onMounted(() => {
    if (conversationId.value) start()
  })

  onUnmounted(stop)

  return { polling, start, stop, refresh: tick }
}
