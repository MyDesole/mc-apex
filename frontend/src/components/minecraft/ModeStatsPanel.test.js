import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { reactive } from 'vue'
import ModeStatsPanel from './ModeStatsPanel.vue'
const api = vi.hoisted(() => ({ get: vi.fn() }))
let auth
vi.mock('@/services/core/api.js', () => ({ api }))
vi.mock('@/stores/core/auth.js', () => ({ useAuthStore: () => auth }))
vi.mock('vue-router', () => ({ RouterLink: { props: ['to'], template: '<a><slot /></a>' } }))
const snapshot = (name = 'Alice') => ({ configured: true, updated_at: '2026-11-18T19:00:00Z', stale: false, summary: { players: 2, records: 1, attempts24: 10, finishes24: 3, beds: 6, final_kills: 9, arenas: 1, online: 0 }, players: [{ place: 1, name, user_id: 4, best: 1234, attempts24: 10, finishes24: 3, wins: 3, final_kills: 9, beds: 6, games: 5 }], arenas: [{ id: 'amazon', name: 'Amazon', teams: 8, team_size: 2, players: 0, max_players: 16, state: 'waiting' }], tables: [{ key: 'current', label: 'Новая карта', records: 1 }, { key: 'classic', label: 'Архив', records: 3 }], history: [], meta: { current_page: 1, last_page: 1, total: 1 }, personal: null })
beforeEach(() => { vi.clearAllMocks(); auth = reactive({ user: null }); api.get.mockResolvedValue(snapshot()) })
describe('живые данные BW и FB', () => {
  it('показывает реальные рекорды и передаёт дистанцию с архивом', async () => {
    const w = mount(ModeStatsPanel, { props: { mode: 'fastbridge' } }); await flushPromises()
    expect(w.text()).toContain('1.234 с'); expect(w.text()).toContain('Alice'); expect(w.text()).toContain('Попыток за 24 ч')
    await w.findAll('select')[0].setValue('16'); await flushPromises(); expect(api.get).toHaveBeenLastCalledWith('/minecraft/modes/fastbridge', expect.objectContaining({ params: expect.objectContaining({ distance: '16', page: 1 }) }))
    await w.findAll('select')[1].setValue('classic'); await flushPromises(); expect(w.text()).toContain('Архив предыдущей версии'); w.unmount()
  })
  it('BedWars показывает таблицу игроков и реальные арены', async () => {
    const w = mount(ModeStatsPanel, { props: { mode: 'bedwars' } }); await flushPromises()
    expect(w.text()).toContain('Amazon'); expect(w.text()).toContain('8 × 2'); expect(w.text()).toContain('Ожидание'); expect(w.text()).toContain('Кроватей разрушено'); w.unmount()
  })
  it('не раскрывает историю другого игрока даже при ошибочном ответе', async () => {
    auth.user = { id: 5 }; api.get.mockResolvedValue({ ...snapshot(), history: [{ at: 1, outcome: 'OTHER_HISTORY_SECRET', elapsed: 1000 }] })
    const w = mount(ModeStatsPanel, { props: { mode: 'fastbridge', userId: 4 } }); await flushPromises()
    expect(w.text()).toContain('Подробная история доступна самому игроку'); expect(w.text()).not.toContain('OTHER_HISTORY_SECRET'); w.unmount()
  })
  it('поздний ответ предыдущей дистанции не подменяет текущий топ', async () => {
    let resolve; api.get.mockReturnValueOnce(new Promise(r => { resolve = r }))
    const w = mount(ModeStatsPanel, { props: { mode: 'fastbridge' } }); await flushPromises(); api.get.mockResolvedValue(snapshot('NewRecord'))
    await w.findAll('select')[0].setValue('16'); await flushPromises(); resolve(snapshot('OldRecord')); await flushPromises()
    expect(w.text()).toContain('NewRecord'); expect(w.text()).not.toContain('OldRecord'); w.unmount()
  })
})
