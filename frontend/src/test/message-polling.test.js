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

  it('останавливается при размонтировании', () => {
    const conversationId = ref(1)
    const messages = ref([])

    useMessagePolling(conversationId, messages)

    expect(onMounted).toHaveBeenCalled()
    expect(onUnmounted).toHaveBeenCalled()
  })
})
