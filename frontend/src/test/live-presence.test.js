/**
 * Присутствие: источник правды после первой загрузки.
 *
 * Проверяется то, что ломалось: собеседник пропадал из онлайна в
 * реальном времени, но появлялся снова при обновлении страницы. Причина
 * была в запасном признаке is_online: сервер считает онлайн по времени
 * последней активности, и после выхода игрока он ещё пару минут
 * остаётся истинным.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

const presence = vi.fn()

vi.mock('@/services/chat/chat.js', () => ({
  chatApi: {
    presence: (...args) => presence(...args),
    presenceOnline: () => Promise.resolve({}),
    presenceOffline: () => Promise.resolve({}),
  },
}))

vi.mock('@/stores/core/auth.js', () => ({
  useAuthStore: () => ({ user: { id: 1 } }),
}))

/** Хуки жизненного цикла последнего вызова. */
let mounted = []
let unmounted = []

vi.mock('vue', async (importOriginal) => {
  const actual = await importOriginal()

  return {
    ...actual,
    onMounted: (fn) => mounted.push(fn),
    onUnmounted: (fn) => unmounted.push(fn),
  }
})

const { usePresence } = await import('@/composables/chat/presence.js')

/**
 * Создаёт присутствие и выполняет отложенный onMounted: подписка на
 * собеседников ставится именно там.
 */
async function setup(peers) {
  mounted = []
  unmounted = []

  const api = peers === undefined ? usePresence() : usePresence(peers)

  for (const fn of mounted) {
    await fn()
  }

  return api
}

/** Уводит страницу с чата: состояние общее на модуль, его нужно снять. */
async function teardown() {
  for (const fn of unmounted) {
    await fn()
  }

  mounted = []
  unmounted = []
}

describe('Присутствие', () => {
  beforeEach(async () => {
    // Состояние модуля общее для тестов, поэтому снимаем прошлое
    await teardown()

    presence.mockReset()
    presence.mockResolvedValue({ online: [5] })

    window.Echo = {
      join: () => ({ here: () => {}, joining: () => {}, leaving: () => {} }),
      leave: () => {},
    }
  })

  it('до первой загрузки источник ещё не готов', async () => {
    mounted = []
    unmounted = []

    const api = usePresence()

    // Признак снимается до того, как загрузка завершится
    expect(api.presenceReady.value).toBe(false)

    await teardown()
  })

  it('после загрузки источник готов', async () => {
    const api = await setup()

    expect(api.presenceReady.value).toBe(true)
  })

  it('знает, кто онлайн, после загрузки', async () => {
    const api = await setup()

    expect(api.isOnline(5)).toBe(true)
    expect(api.isOnline(6)).toBe(false)
  })

  it('переданные игроки обновляются узким запросом', async () => {
    const api = await setup(ref([7, 8]))

    presence.mockResolvedValue({ online: [7] })

    await api.refreshPeers()

    expect(presence).toHaveBeenCalledWith([7, 8])
    expect(api.isOnline(7)).toBe(true)
    expect(api.isOnline(8)).toBe(false)
  })

  it('узкий запрос не гасит остальных', async () => {
    const api = await setup(ref([]))

    await api.refresh()

    presence.mockResolvedValue({ online: [] })

    await api.refreshPeers()

    // Игрок 5 не входил в узкий запрос, значит его статус не тронут
    expect(api.isOnline(5)).toBe(true)
  })

  it('после ухода со страницы состояние снимается', async () => {
    const api = await setup()

    expect(api.presenceReady.value).toBe(true)

    await teardown()

    expect(api.presenceReady.value).toBe(false)
    expect(api.isOnline(5)).toBe(false)
  })

  it('ошибка сети не роняет страницу', async () => {
    presence.mockRejectedValue(new Error('сеть'))

    mounted = []
    unmounted = []

    const api = usePresence()

    for (const fn of mounted) {
      await fn()
    }

    expect(api.presenceReady.value).toBe(false)

    await teardown()
  })
})
