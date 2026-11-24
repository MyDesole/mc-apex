import { onMounted, onUnmounted, ref, watch } from 'vue'
import { chatApi } from '@/services/chat/chat.js'
import { mergeMessages } from '@/utils/chatMessages.js'

const INTERVAL_MS = 2000
const LIVE_INTERVAL_MS = 15000

function socketAlive() {
  return window.Echo?.connector?.pusher?.connection?.state === 'connected'
}

/** Incremental fallback; no background requests or full conversation reloads. */
export function useMessagePolling(conversationId, messages, options = {}) {
  const polling = ref(false)
  let timer = null
  let inFlight = false
  let lastTickAt = 0
  let generation = 0
  let disposed = false

  function enabled() {
    return !disposed && document.visibilityState !== 'hidden' && (options.enabled?.() ?? true)
  }

  function scheduledTick() {
    if (socketAlive() && Date.now() - lastTickAt < LIVE_INTERVAL_MS) return
    tick()
  }

  async function tick() {
    const id = conversationId.value
    const request = generation
    if (!id || inFlight || !enabled()) return

    lastTickAt = Date.now()
    inFlight = true
    let afterId = messages.value.at(-1)?.id ?? null

    try {
      // Drain a backlog in order, without waiting another timer interval per page.
      for (let page = 0; page < 5; page++) {
        const data = await chatApi.show(id, { afterId, limit: 50, markRead: false, messagesOnly: true })
        if (request !== generation || conversationId.value !== id || !enabled()) return

        const fresh = data.messages ?? []
        if (!fresh.length) return

        const { list, added } = mergeMessages(messages.value, fresh)
        messages.value = list
        if (added.length) options.onNew?.(added)

        const nextId = data.newest_id ?? fresh.at(-1)?.id
        if (!afterId || !data.has_more || !nextId || nextId <= afterId) return
        afterId = nextId
      }
    } catch {
      // Retry at the next interval; keep the existing history on screen.
    } finally {
      inFlight = false
    }
  }

  function start() {
    if (timer || disposed) return
    polling.value = true
    // Opening already loads history. Avoid racing it with an identical request.
    timer = setInterval(scheduledTick, INTERVAL_MS)
  }

  function stop() {
    clearInterval(timer)
    timer = null
    polling.value = false
    generation++
  }

  function onVisibility() {
    if (document.visibilityState === 'visible') tick()
  }

  watch(conversationId, (id) => {
    generation++
    lastTickAt = 0
    if (id) start()
    else stop()
  }, { immediate: true, flush: 'sync' })

  onMounted(() => {
    if (conversationId.value) start()
    document.addEventListener('visibilitychange', onVisibility)
  })

  onUnmounted(() => {
    disposed = true
    stop()
    document.removeEventListener('visibilitychange', onVisibility)
  })

  return { polling, start, stop, refresh: tick }
}
