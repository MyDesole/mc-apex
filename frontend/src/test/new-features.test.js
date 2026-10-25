/**
 * Командная палитра, живая лента и данные аспектов.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import { buildTestRouter } from '@/test/router.js'
import { playerAspects } from '@/data/players/aspects.js'
import { useCommandPalette } from '@/composables/core/useCommandPalette.js'

/* ------------------------------ Аспекты ------------------------------ */

describe('playerAspects', () => {
  it('берёт PvP, если он пройден', () => {
    const result = playerAspects({
      aspects: {
        pvp: { block_placing: 15, rotka: 11, movement: 14, aim: 12, game_sense: 11 },
        bedwars: { pvp: 10, game_sense: 10, bed_play: 10, teamplay: 10, building: 10 },
      },
    })

    expect(result.mode).toBe('pvp')
    expect(result.values.map((aspect) => aspect.value)).toEqual([15, 11, 14, 12, 11])
  })

  it('переходит на BedWars, если в PvP одни нули', () => {
    const result = playerAspects({
      aspects: {
        pvp: { block_placing: 0, rotka: 0, movement: 0, aim: 0, game_sense: 0 },
        bedwars: { pvp: 12, game_sense: 0, bed_play: 0, teamplay: 0, building: 0 },
      },
    })

    expect(result.mode).toBe('bedwars')
  })

  it('без пройденных тестов возвращает null', () => {
    expect(playerAspects({ aspects: { pvp: null, bedwars: null } })).toBeNull()
    expect(playerAspects(null)).toBeNull()
  })
})

/* ------------------------------ Командная палитра ------------------------------ */

vi.mock('@/services/search/search.js', () => ({
  searchApi: {
    query: vi.fn(() => Promise.resolve({
      players: [{ id: 7, username: 'desole', tier: 'A', tier_score: 78, clan_tag: 'FG', clan_color: '#7c3aed' }],
      clans: [{ id: 3, name: 'Funny Group', tag: 'FG', power: 850, members_count: 4 }],
      topics: [],
      news: [],
    })),
  },
}))

describe('CommandPalette', () => {
  let router
  let wrapper

  beforeEach(async () => {
    vi.useFakeTimers()
    setActivePinia(createPinia())

    router = buildTestRouter()
    router.push('/')
    await router.isReady()

    const CommandPalette = (await import('@/components/core/CommandPalette.vue')).default

    wrapper = mount(CommandPalette, {
      attachTo: document.body,
      global: { plugins: [router] },
    })
  })

  afterEach(() => {
    wrapper.unmount()
    vi.useRealTimers()

    useCommandPalette().close()
  })

  function pressCtrlK() {
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))
  }

  it('открывается по Ctrl+K и показывает разделы сайта', async () => {
    pressCtrlK()
    await flushPromises()

    const text = document.body.textContent

    expect(document.querySelector('.palette')).not.toBeNull()
    expect(text).toContain('Разделы')
    expect(text).toContain('Форум')
  })

  it('ищет и переходит по Enter к выбранному результату', async () => {
    pressCtrlK()
    await flushPromises()

    const input = document.querySelector('.palette__input')

    input.value = 'des'
    input.dispatchEvent(new Event('input'))

    await vi.advanceTimersByTimeAsync(200)
    await flushPromises()

    const labels = [...document.querySelectorAll('.palette-item__label')].map((el) => el.textContent.trim())

    expect(labels[0]).toContain('desole')
    expect(labels.some((label) => label.includes('Funny Group'))).toBe(true)

    // Вниз — на клан, Enter — переход
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }))
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }))
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/clan/Funny_Group')
    expect(document.querySelector('.palette')).toBeNull()

    // Выбор запомнился в «Недавнем»
    expect(JSON.parse(localStorage.getItem('apex:palette-recent'))[0].label).toBe('Funny Group')
  })

  it('не перехватывает «/» внутри поля ввода', async () => {
    const field = document.createElement('input')
    document.body.appendChild(field)

    field.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }))
    await flushPromises()

    expect(document.querySelector('.palette')).toBeNull()

    field.remove()
  })
})

/* ------------------------------ Живая лента ------------------------------ */

const ACTIVITIES = [
  {
    id: 4,
    type: 'tier.assigned',
    actor: { id: 1, username: 'desole', clan_tag: 'FG', clan_color: '#7c3aed' },
    payload: { tier: 'A', score: 78.4, mode: 'pvp' },
    created_at: new Date().toISOString(),
  },
  {
    id: 3,
    type: 'clan_war.finished',
    actor: null,
    payload: {
      challenger: { id: 1, name: 'Alpha', tag: 'ALP', color: '#7c3aed' },
      opponent: { id: 2, name: 'Bravo', tag: 'BRV', color: '#ef4444' },
      challenger_score: 3,
      opponent_score: 1,
      winner_clan_id: 1,
    },
    created_at: new Date(Date.now() - 3 * 3600 * 1000).toISOString(),
  },
  {
    id: 2,
    type: 'achievement.earned',
    actor: { id: 2, username: 'aaaaaa' },
    payload: { achievement: { name: 'Дебют', icon: '★', color: '#facc15' } },
    created_at: new Date(Date.now() - 2 * 86400 * 1000).toISOString(),
  },
]

vi.mock('@/services/activity/activity.js', () => ({
  activityApi: {
    list: vi.fn(() => Promise.resolve({ data: ACTIVITIES })),
  },
}))

describe('LiveFeed', () => {
  let listener

  beforeEach(() => {
    window.Echo.channel = vi.fn(() => ({
      listen: (event, callback) => {
        listener = callback
      },
    }))
  })

  async function mountFeed() {
    const LiveFeed = (await import('@/components/activity/LiveFeed.vue')).default
    const router = buildTestRouter()

    const wrapper = mount(LiveFeed, { global: { plugins: [router] } })
    await flushPromises()

    return wrapper
  }

  it('рисует события разных типов с относительным временем', async () => {
    const wrapper = await mountFeed()
    const text = wrapper.text()

    expect(text).toContain('desole получил тир A в PvP')
    expect(text).toContain('[ALP] Alpha')
    expect(text).toContain('3 : 1')
    expect(text).toContain('открыл ачивку «Дебют»')
    expect(text).toContain('только что')
    expect(text).toContain('3 ч назад')
    expect(text).toContain('2 дн назад')
  })

  it('добавляет событие из сокета наверх и не дублирует его', async () => {
    const wrapper = await mountFeed()

    expect(window.Echo.channel).toHaveBeenCalledWith('activity')

    const fresh = {
      id: 9,
      type: 'minecraft.linked',
      actor: { id: 5, username: 'newbie' },
      payload: { nickname: 'Notch' },
      created_at: new Date().toISOString(),
    }

    listener({ activity: fresh })
    listener({ activity: fresh })
    await flushPromises()

    const items = wrapper.findAll('.feed-item')

    expect(items).toHaveLength(ACTIVITIES.length + 1)
    expect(items[0].text()).toContain('привязал Minecraft-аккаунт Notch')
    expect(items[0].classes()).toContain('feed-item--fresh')
  })
})
