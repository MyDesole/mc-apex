/**
 * Запасной опрос сообщений.
 *
 * Он нужен, потому что соединение с Reverb может отвалиться и не
 * подняться: тогда новые сообщения не приходят до перезагрузки страницы.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

const show = vi.fn()

vi.mock('@/services/chat/chat.js', () => ({
  chatApi: {
    show: (...args) => show(...args),
  },
}))

/*
 * Хуки жизненного цикла вне компонента не работают и сыплют
 * предупреждениями. Подменяем их и проверяем сам факт вызова.
 */
const onMounted = vi.fn()
const onUnmounted = vi.fn()

vi.mock('vue', async (importOriginal) => {
  const actual = await importOriginal()

  return {
    ...actual,
    onMounted: (...args) => onMounted(...args),
    onUnmounted: (...args) => onUnmounted(...args),
  }
})

const { useMessagePolling } = await import('@/composables/chat/useMessagePolling.js')

describe('Запасной опрос сообщений', () => {
  beforeEach(() => {
    show.mockReset()
    show.mockResolvedValue({ messages: [] })
    onMounted.mockReset()
    onUnmounted.mockReset()
  })

  it('запрашивает сообщения после самого свежего', async () => {
    const conversationId = ref(7)
    const messages = ref([{ id: 100, body: 'старое' }])

    const { refresh } = useMessagePolling(conversationId, messages)

    await refresh()

    expect(show).toHaveBeenCalledWith(
        7,
        expect.objectContaining({ afterId: 100 }),
    )
  })

  it('без сообщений запрашивает с начала', async () => {
    const conversationId = ref(5)
    const messages = ref([])

    const { refresh } = useMessagePolling(conversationId, messages)

    await refresh()

    expect(show).toHaveBeenCalledWith(
        5,
        expect.objectContaining({ afterId: null }),
    )
  })

  it('добавляет новые сообщения', async () => {
    const conversationId = ref(3)
    const messages = ref([{ id: 1, body: 'первое' }])

    show.mockResolvedValue({
      messages: [{ id: 2, body: 'второе' }],
    })

    const { refresh } = useMessagePolling(conversationId, messages)

    await refresh()

    expect(messages.value).toHaveLength(2)
    expect(messages.value[1].body).toBe('второе')
  })

  it('не добавляет то, что уже пришло по websocket', async () => {
    const conversationId = ref(3)
    const messages = ref([{ id: 1, body: 'первое' }])

    // Опрос вернул уже известное сообщение и одно новое
    show.mockResolvedValue({
      messages: [
        { id: 1, body: 'первое' },
        { id: 2, body: 'второе' },
      ],
    })

    const { refresh } = useMessagePolling(conversationId, messages)

    await refresh()

    expect(messages.value).toHaveLength(2)
    expect(messages.value.filter((m) => m.id === 1)).toHaveLength(1)
  })

  it('не падает, если сервер недоступен', async () => {
    const conversationId = ref(9)
    const messages = ref([])

    show.mockRejectedValue(new Error('сеть недоступна'))

    const { refresh } = useMessagePolling(conversationId, messages)

    await expect(refresh()).resolves.toBeUndefined()
    expect(messages.value).toEqual([])
  })

  it('без открытого диалога не запрашивает', async () => {
    const conversationId = ref(null)
    const messages = ref([])

    const { refresh } = useMessagePolling(conversationId, messages)

    await refresh()

    expect(show).not.toHaveBeenCalled()
  })

  it('сообщает о новых сообщениях наружу', async () => {
    const conversationId = ref(1)
    const messages = ref([])
    const onNew = vi.fn()

    show.mockResolvedValue({ messages: [{ id: 4, body: 'новое' }] })

    const { refresh } = useMessagePolling(conversationId, messages, { onNew })

    await refresh()

    expect(onNew).toHaveBeenCalled()
    expect(onNew.mock.calls[0][0][0].id).toBe(4)
  })

  it('ответ для старого диалога не попадает в новый', async () => {
    const conversationId = ref(3)
    const messages = ref([{ id: 1, body: 'первое' }])

    let resolve
    show.mockReturnValue(new Promise((r) => { resolve = r }))

    const { refresh } = useMessagePolling(conversationId, messages)

    const pending = refresh()

    // Игрок открыл другой диалог, пока шёл запрос
    conversationId.value = 4
    messages.value = [{ id: 50, body: 'другой диалог' }]

    resolve({ messages: [{ id: 2, body: 'из старого' }] })
    await pending

    expect(messages.value.map((m) => m.id)).toEqual([50])
  })

  it('не запускает второй запрос, пока не вернулся первый', async () => {
    const conversationId = ref(3)
    const messages = ref([])

    let resolve
    show.mockReturnValue(new Promise((r) => { resolve = r }))

    const { refresh } = useMessagePolling(conversationId, messages)

    const first = refresh()
    await refresh()

    expect(show).toHaveBeenCalledTimes(1)

    resolve({ messages: [] })
    await first
  })

  it('останавливается при размонтировании', () => {
    const conversationId = ref(1)
    const messages = ref([])

    useMessagePolling(conversationId, messages)

    expect(onMounted).toHaveBeenCalled()
    expect(onUnmounted).toHaveBeenCalled()
  })
})

describe('Polling lifecycle and backlog', () => {
  it('does not request in a hidden tab', async () => {
    show.mockClear()
    const poll = useMessagePolling(ref(7), ref([]))
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' })
    try {
      await poll.refresh()
      expect(show).not.toHaveBeenCalled()
    } finally {
      Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' })
      poll.stop()
    }
  })

  it('waits for the initial history load', async () => {
    show.mockClear()
    const poll = useMessagePolling(ref(7), ref([]), { enabled: () => false })
    await poll.refresh()
    expect(show).not.toHaveBeenCalled()
    poll.stop()
  })

  it('catches up consecutive pages without skipping the middle', async () => {
    show.mockReset()
    show.mockResolvedValueOnce({ messages: [{ id: 11 }], has_more: true, newest_id: 11 })
      .mockResolvedValueOnce({ messages: [{ id: 12 }], has_more: false, newest_id: 12 })
    const messages = ref([{ id: 10 }])
    const poll = useMessagePolling(ref(7), messages)
    await poll.refresh()
    expect(messages.value.map(m => m.id)).toEqual([10, 11, 12])
    expect(show).toHaveBeenNthCalledWith(2, 7, expect.objectContaining({ afterId: 11, messagesOnly: true }))
    poll.stop()
  })

  it('ignores an old response even after switching away and back', async () => {
    let resolve
    show.mockReturnValueOnce(new Promise(r => { resolve = r }))
    const id = ref(7)
    const messages = ref([{ id: 10 }])
    const poll = useMessagePolling(id, messages)
    const pending = poll.refresh()
    id.value = 8
    id.value = 7
    messages.value = [{ id: 20 }]
    resolve({ messages: [{ id: 11 }] })
    await pending
    expect(messages.value.map(m => m.id)).toEqual([20])
    poll.stop()
  })
})
