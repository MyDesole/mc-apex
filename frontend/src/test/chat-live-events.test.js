/**
 * Живые события чата в сокете: прочтение и «печатает».
 *
 * Эти события нужны, чтобы галочка прочтения вставала сразу, а надпись о
 * наборе текста появлялась без обновления страницы.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

vi.mock('@/stores/core/auth.js', () => ({
  useAuthStore: () => ({ user: { id: 1 } }),
}))

vi.mock('@/echo.js', () => ({
  echoGeneration: { value: 0 },
}))

const handlers = {}

const channel = {
  listen: (event, handler) => {
    handlers[event] = handler

    return channel
  },
}

window.Echo = {
  private: () => channel,
  leave: () => {},
}

const { useRealtimeMessages } = await import('@/composables/chat/useRealtimeMessages.js')

/** Даёт watcher'ам отработать. */
async function flush() {
  await nextTick()
  await nextTick()
}

describe('Живые события чата', () => {
  beforeEach(async () => {
    Object.keys(handlers).forEach((key) => delete handlers[key])

    await flush()
  })

  it('слушает события о прочтении и печати', async () => {
    const api = useRealtimeMessages()

    await flush()

    expect(handlers['.messages.read']).toBeTypeOf('function')
    expect(handlers['.user.typing']).toBeTypeOf('function')

    expect(api.latestRead).toBeDefined()
    expect(api.typingUsers).toBeDefined()
  })

  it('запоминает, кто прочитал', async () => {
    const api = useRealtimeMessages()

    await flush()

    handlers['.messages.read']({
      conversation_id: 5,
      reader: { id: 2, username: 'Друг' },
      read_at: '2026-10-02T12:00:00+00:00',
    })

    expect(api.latestRead.value.conversation_id).toBe(5)
    expect(api.latestRead.value.reader.id).toBe(2)
  })

  it('отмечает, кто печатает', async () => {
    const api = useRealtimeMessages()

    await flush()

    handlers['.user.typing']({
      conversation_id: 7,
      user: { id: 3, username: 'Друг' },
    })

    expect(api.typingUsers.value[7]).toBeDefined()
    expect(api.typingUsers.value[7].username).toBe('Друг')
  })

  it('надпись о печати гаснет сама', async () => {
    vi.useFakeTimers()

    const api = useRealtimeMessages()

    await flush()

    handlers['.user.typing']({
      conversation_id: 9,
      user: { id: 4, username: 'Друг' },
    })

    expect(api.typingUsers.value[9]).toBeDefined()

    // Событие о конце печати не приходит, поэтому надпись гаснет по времени
    vi.advanceTimersByTime(7000)

    expect(api.typingUsers.value[9]).toBeUndefined()

    vi.useRealTimers()
  })

  it('событие без пользователя не создаёт запись', async () => {
    const api = useRealtimeMessages()

    await flush()

    // Диалог 99 в этом тесте не встречается, поэтому проверяем именно его
    handlers['.user.typing']({ conversation_id: 99 })
    handlers['.user.typing']({ user: { id: 2 } })
    handlers['.user.typing'](null)

    expect(api.typingUsers.value[99]).toBeUndefined()
  })
})
