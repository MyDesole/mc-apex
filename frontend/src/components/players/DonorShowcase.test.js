import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DonorShowcase from './DonorShowcase.vue'
const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn(), delete: vi.fn() }))
vi.mock('@/services/core/api.js', () => ({ api }))
vi.mock('vue-router', () => ({ RouterLink: { props: ['to'], template: '<a><slot /></a>' } }))
const own = () => ({ eligible: true, rank: 'VIP', visible: false, settings: null, options: { duels: [], achievements: [] } })
const published = title => ({ ...own(), visible: true, settings: { enabled: true, title, mode: 'duels', theme: 'aurora', duel_id: null, achievement_id: null } })
beforeEach(() => { vi.clearAllMocks(); api.get.mockResolvedValue(own()) })
describe('личная витрина', () => {
  it('владелец сохраняет настройки без подмены пользователя', async () => {
    api.put.mockResolvedValue(published('Моя тренировка'))
    const w = mount(DonorShowcase, { props: { userId: 4, isMe: true } }); await flushPromises()
    await w.get('header button').trigger('click'); await w.get('input[maxlength="48"]').setValue('Моя тренировка'); await w.get('form').trigger('submit'); await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/players/me/showcase', expect.objectContaining({ title: 'Моя тренировка', mode: 'duels' }))
    expect(w.text()).toContain('Витрина сохранена'); expect(w.text()).toContain('Моя тренировка'); w.unmount()
  })
  it('запоздавшее сохранение не показывает свои настройки на чужом профиле', async () => {
    let resolve; api.put.mockReturnValue(new Promise(r => { resolve = r }))
    const w = mount(DonorShowcase, { props: { userId: 4, isMe: true } }); await flushPromises()
    await w.get('header button').trigger('click'); await w.get('form').trigger('submit')
    api.get.mockResolvedValue({ eligible: false, visible: false, settings: null, options: null })
    await w.setProps({ userId: 5, isMe: false }); await flushPromises(); resolve(published('Личные настройки владельца')); await flushPromises()
    expect(w.find('.donor-showcase').exists()).toBe(false); expect(w.text()).not.toContain('Личные настройки владельца'); w.unmount()
  })
  it('после окончания ранга владелец может удалить сохранённую витрину', async () => {
    api.get.mockResolvedValue({ ...published('Архив'), eligible: false, visible: false }); api.delete.mockResolvedValue({ ...own(), eligible: false })
    const w = mount(DonorShowcase, { props: { userId: 4, isMe: true } }); await flushPromises()
    const reset = w.findAll('button').find(b => b.text().includes('Удалить сохранённую')); await reset.trigger('click'); await flushPromises()
    expect(api.delete).toHaveBeenCalledWith('/players/me/showcase'); expect(w.text()).not.toContain('Удалить сохранённую'); w.unmount()
  })
})
