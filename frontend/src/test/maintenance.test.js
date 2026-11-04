import { afterEach, describe, expect, it, vi } from 'vitest'
import { isUpdateStatus, maintenance, resetMaintenance, startMaintenance } from '@/services/core/maintenance.js'

describe('окно «идёт обновление»', () => {
  afterEach(() => {
    resetMaintenance()
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('считает обновлением только 502, 503 и 504', () => {
    expect([502, 503, 504].every(isUpdateStatus)).toBe(true)
    expect([200, 401, 404, 422, 500].some(isUpdateStatus)).toBe(false)
  })

  it('включается и ждёт, пока API снова ответит, потом перезагружает страницу', async () => {
    vi.useFakeTimers()

    const reload = vi.fn()
    vi.stubGlobal('location', { reload })

    const answers = [false, false, true]
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: answers.shift() ?? true })))

    startMaintenance()
    expect(maintenance.value).toBe(true)

    await vi.advanceTimersByTimeAsync(5000)
    await vi.advanceTimersByTimeAsync(5000)
    expect(reload).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(5000)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('не запускает опрос дважды', () => {
    vi.useFakeTimers()
    const fetchMock = vi.fn(async () => ({ ok: false }))
    vi.stubGlobal('fetch', fetchMock)

    startMaintenance()
    startMaintenance()

    vi.advanceTimersByTime(5000)

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
