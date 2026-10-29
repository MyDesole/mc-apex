/**
 * Свой скин: сменилась версия — скин качается заново по новому адресу,
 * иначе кэш вкладки и браузера показал бы старый.
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import { loadSkin } from '@/utils/minecraftSkin.js'

describe('loadSkin и версия своего скина', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('версия попадает в адрес и в ключ кэша', async () => {
    const fetch = vi.fn(async () => ({ ok: false }))
    vi.stubGlobal('fetch', fetch)

    await loadSkin(777)
    await loadSkin(777)
    await loadSkin(777, 1730000000)
    await loadSkin(777, 1730000000)
    await loadSkin(777, 1730000500)

    expect(fetch.mock.calls.map(([url]) => url)).toEqual([
      '/api/players/777/skin',
      '/api/players/777/skin?v=1730000000',
      '/api/players/777/skin?v=1730000500',
    ])
  })
})
