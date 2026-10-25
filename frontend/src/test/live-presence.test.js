/**
 * Присутствие: канал на всё приложение плюс сервер как запасной источник.
 *
 * Проверяется то, что ломалось:
 *   - собеседник вне страницы чата не попадал в канал, и его статус
 *     узнавался опросом с задержкой до минуты;
 *   - вышедший игрок ещё минуту светился онлайн по данным сервера.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

const presence = vi.fn()
const presenceOnline = vi.fn()
const presenceOffline = vi.fn()

vi.mock('@/services/chat/chat.js', () => ({
  chatApi: {
    presence: (...args) => presence(...args),
    presenceOnline: (...args) => presenceOnline(...args),
    presenceOffline: (...args) => presenceOffline(...args),
  },
}))

vi.mock('@/echo.js', () => ({
  onEchoReset: () => () => {},
}))

/** Хуки жизненного цикла вне компонента: выполняем вручную. */
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

const { usePresence, startSitePresence, stopSitePresence } = await import('@/composables/chat/presence.js')

/** Обработчики канала присутствия, чтобы вызывать их из тестов. */
let handlers = {}

function fakeEcho() {
  handlers = {}

  const channel = {
    here: (fn) => { handlers.here = fn; return channel },
    joining: (fn) => { handlers.joining = fn; return channel },
    leaving: (fn) => { handlers.leaving = fn; return channel },
  }

  window.Echo = { join: vi.fn(() => channel), leave: vi.fn() }
}

async function page(peers) {
  mounted = []
  unmounted = []

  const api = peers === undefined ? usePresence() : usePresence(peers)

  for (const fn of mounted) await fn()

  await Promise.resolve()

  return api
}

async function leavePage() {
  for (const fn of unmounted) await fn()

  mounted = []
  unmounted = []
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('Присутствие', () => {
  beforeEach(() => {
    fakeEcho()

    presence.mockReset().mockResolvedValue({ online: [5] })
    presenceOnline.mockReset().mockResolvedValue({ online: [5] })
    presenceOffline.mockReset().mockResolvedValue({ ok: true })
  })

  afterEach(async () => {
    await leavePage()
    stopSitePresence({ silent: true })
  })

  it('сеанс входит в канал и сразу шлёт пульс', async () => {
    startSitePresence(1)
    await flush()

    expect(window.Echo.join).toHaveBeenCalledWith('online')
    expect(presenceOnline).toHaveBeenCalled()
  })

  it('повторный старт для того же игрока ничего не дублирует', async () => {
    startSitePresence(1)
    startSitePresence(1)
    await flush()

    expect(window.Echo.join).toHaveBeenCalledTimes(1)
  })

  it('канал даёт онлайн мгновенно, ещё до ответа сервера', async () => {
    presence.mockReturnValue(new Promise(() => {}))
    presenceOnline.mockReturnValue(new Promise(() => {}))

    startSitePresence(1)
    const api = await page()

    handlers.here([{ id: 1 }, { id: 7 }])

    expect(api.presenceReady.value).toBe(true)
    expect(api.isOnline(7)).toBe(true)

    handlers.joining({ id: 9 })

    expect(api.isOnline(9)).toBe(true)
  })

  it('ушедший из канала гаснет сразу, хотя сервер ещё считает его онлайн', async () => {
    presenceOnline.mockResolvedValue({ online: [5, 7] })

    startSitePresence(1)
    const api = await page()
    await flush()

    handlers.here([{ id: 1 }, { id: 7 }])
    expect(api.isOnline(7)).toBe(true)

    handlers.leaving({ id: 7 })

    expect(api.isOnline(7)).toBe(false)

    // Без канала (сокет не поднялся) игрок 5 всё равно виден по серверу
    expect(api.isOnline(5)).toBe(true)
  })

  it('вернувшийся в канал снова онлайн', async () => {
    startSitePresence(1)
    const api = await page()

    handlers.here([{ id: 7 }])
    handlers.leaving({ id: 7 })
    handlers.joining({ id: 7 })

    expect(api.isOnline(7)).toBe(true)
  })

  it('собеседники обновляются узким запросом и не гасят остальных', async () => {
    startSitePresence(1)
    const api = await page(ref([7, 8]))
    await flush()

    presence.mockResolvedValue({ online: [7] })

    await api.refreshPeers()

    expect(presence).toHaveBeenCalledWith([7, 8])
    expect(api.isOnline(7)).toBe(true)
    expect(api.isOnline(8)).toBe(false)
    expect(api.isOnline(5)).toBe(true)
  })

  it('уход со страницы чата не обрывает присутствие сайта', async () => {
    startSitePresence(1)
    const api = await page()

    handlers.here([{ id: 7 }])

    await leavePage()

    expect(api.isOnline(7)).toBe(true)
    expect(window.Echo.leave).not.toHaveBeenCalled()
  })

  it('выход из аккаунта покидает канал и сообщает серверу', async () => {
    startSitePresence(1)
    const api = await page()

    handlers.here([{ id: 7 }])

    stopSitePresence()

    expect(window.Echo.leave).toHaveBeenCalledWith('online')
    expect(presenceOffline).toHaveBeenCalled()
    expect(api.isOnline(7)).toBe(false)
    expect(api.presenceReady.value).toBe(false)
  })

  it('ошибка сети не роняет страницу', async () => {
    presence.mockRejectedValue(new Error('сеть'))
    presenceOnline.mockRejectedValue(new Error('сеть'))

    startSitePresence(1)
    const api = await page()
    await flush()

    expect(api.presenceReady.value).toBe(false)
    expect(api.isOnline(5)).toBe(false)
  })
})
