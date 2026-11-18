import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { reactive } from 'vue'
import DuelsView from './DuelsView.vue'

const mocked = vi.hoisted(() => ({ recent: vi.fn(), player: vi.fn(), online: vi.fn(), replace: vi.fn() }))
let route, auth
vi.mock('vue-router', () => ({ useRoute: () => route, useRouter: () => ({ replace: mocked.replace }), RouterLink: { props: ['to'], template: '<a><slot /></a>' } }))
vi.mock('@/stores/core/auth.js', () => ({ useAuthStore: () => auth }))
vi.mock('@/services/minecraft/duels.js', () => ({ duelsApi: mocked }))
vi.mock('@/services/site/site.js', () => ({ siteApi: { serverStatus: () => Promise.resolve({ online: true, players: 2, address: 'apexpvp.pro' }) } }))
const page = (winner = 'Alice') => ({ duels: [{ id: 1, winner, loser: 'Bob', kit: 'sumo', ranked: false, duration_seconds: 12, played_at: '2026-11-18T12:00:00Z' }], meta: { current_page: 1, last_page: 1, total: 1 } })
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r }); return { promise, resolve } }
beforeEach(() => {
  vi.clearAllMocks(); route = reactive({ params: {}, query: {} }); auth = reactive({ user: null })
  mocked.recent.mockResolvedValue(page()); mocked.player.mockResolvedValue({ ...page(), player: { id: 4, username: 'Player' } }); mocked.online.mockResolvedValue({ count: 2 })
})
describe('история игровых режимов', () => {
  it('показывает сохранённые серверные счётчики без выдуманного списка старых матчей', async () => {
    route.params.id='4'; auth.user={id:4};
    mocked.player.mockResolvedValue({duels:[],player:{id:4,username:'MyDesole'},stats:{total:{games:0,wins:0,losses:0,win_rate:0}},server_stats:{total:{games:40,wins:19,losses:21,win_rate:47.5}},meta:{current_page:1,last_page:1,total:0}})
    const w=mount(DuelsView);await flushPromises()
    expect(w.find('.mode-numbers').text()).toContain('40');expect(w.text()).toContain('Подробности прошлых матчей не были переданы');expect(w.text()).toContain('В истории сайта сохранено 0');w.unmount()
  })
  it('показывает гостю серверные матчи без запроса undefined-аккаунта', async () => {
    const w = mount(DuelsView); await flushPromises()
    expect(w.text()).toContain('Alice'); expect(mocked.player).not.toHaveBeenCalled()
    expect(w.get('.server-address code').text()).toBe('apexpvp.pro')
    await w.findAll('.mode-history__controls button')[1].trigger('click'); await flushPromises()
    expect(w.text()).toContain('Войди в аккаунт'); expect(mocked.player).not.toHaveBeenCalled(); w.unmount()
  })
  it('открывает историю указанного игрока и не подменяет её поздним ответом', async () => {
    route.params.id = '4'; const old = deferred(); mocked.player.mockReturnValueOnce(old.promise)
    const w = mount(DuelsView); await flushPromises(); route.params.id = '5'; await flushPromises()
    mocked.player.mockResolvedValue({ ...page(), player: { id: 5, username: 'NewPlayer' } })
    await w.find('.mode-refresh').trigger('click'); await flushPromises()
    old.resolve({ ...page(), player: { id: 4, username: 'OldPlayer' } }); await flushPromises()
    expect(w.text()).toContain('NewPlayer'); expect(w.text()).not.toContain('OldPlayer'); w.unmount()
  })
  it('показывает ошибку и восстанавливает историю после повтора', async () => {
    mocked.recent.mockRejectedValueOnce({ message: 'Связь прервалась' })
    const w = mount(DuelsView); await flushPromises(); expect(w.get('[role="alert"]').text()).toContain('Связь прервалась')
    await w.get('[role="alert"] button').trigger('click'); await flushPromises(); expect(w.text()).toContain('Alice'); w.unmount()
  })
  it('передаёт фильтр набора и открывает вкладку FastBridge', async () => {
    const w = mount(DuelsView); await flushPromises(); await w.get('select').setValue('boxing'); await flushPromises()
    expect(mocked.recent).toHaveBeenLastCalledWith({ page: 1, kit: 'boxing' })
    await w.findAll('.modes-tabs button')[2].trigger('click'); expect(mocked.replace).toHaveBeenCalledWith(expect.objectContaining({ query: { mode: 'fastbridge' } })); w.unmount()
  })
})
