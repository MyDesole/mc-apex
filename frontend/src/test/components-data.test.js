/**
 * Тесты компонентов с данными.
 *
 * Smoke-тест монтирует компонент с пустыми заглушками и ловит только
 * падения при создании. Здесь компоненты получают правдоподобные данные,
 * а предупреждения Vue считаются ошибкой: именно так проявляется ссылка
 * на несуществующую функцию в шаблоне — интерфейс рисует пустое место,
 * а сборка и обычные тесты проходят.
 */
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'

import { buildTestRouter } from '@/test/router.js'

/* Данные, похожие на настоящие */
const CLAN = { id: 1, name: 'Тестовый клан', tag: 'TST', banner_color: '#7c3aed' }

const WAR = {
  id: 5,
  direction: 'outgoing',
  status: 'accepted',
  challenger_clan_id: 1,
  opponent_clan_id: 2,
  challenger_score: 3,
  opponent_score: 2,
  winner_clan_id: null,
  scheduled_at: '2026-10-05T18:00:00Z',
  notes: 'Играем на нашей карте',
  participants: [
    { user_id: 1, clan_id: 1, username: 'Игрок' },
    { user_id: 2, clan_id: 2, username: 'Соперник' },
  ],
  challenger: CLAN,
  opponent: { id: 2, name: 'Враги', tag: 'ENM', banner_color: '#ef4444' },
}

const PLAYER = {
  id: 1,
  username: 'Игрок',
  tier: 'A',
  rating_score: 87,
  avatar_url: null,
  avatar_frame: 'gold',
  profile_effect: 'fire',
  role: 'player',
  clan_tag: 'TST',
}

const PLAYERS = [PLAYER, { ...PLAYER, id: 2, username: 'Второй' }, { ...PLAYER, id: 3, username: 'Третий' }]

/** Собирает текст всех предупреждений и ошибок Vue за время вызова. */
function captureWarnings() {
  const messages = []
  const originalWarn = console.warn
  const originalError = console.error

  console.warn = (...args) => messages.push(String(args[0]))
  console.error = (...args) => messages.push(String(args[0]))

  return {
    messages,
    restore() {
      console.warn = originalWarn
      console.error = originalError
    },
  }
}

/** Проверяет, что предупреждений Vue не было. */
function expectNoVueWarnings(messages, label) {
  const vueWarnings = messages.filter((m) =>
      m.includes('[Vue warn]') ||
      m.includes('Failed to resolve') ||
      m.includes('is not defined') ||
      m.includes('is not a function') ||
      m.includes('Unhandled error')
  )

  expect(
    vueWarnings,
    `${label}: предупреждения Vue — ${vueWarnings.join(' | ')}`
  ).toEqual([])
}

async function renderComponent(component, props = {}) {
  const router = buildTestRouter()
  await router.push('/')
  await router.isReady()

  return mount(component, {
    props,
    global: { plugins: [router, createPinia()] },
  })
}

describe('карточка клановой войны', () => {
  let capture

  beforeEach(() => { capture = captureWarnings() })
  afterEach(() => { capture.restore() })

  it('рисует соперников, счёт и статус', async () => {
    const ClanWarCard = (await import('@/components/clan/ClanWarCard.vue')).default

    const wrapper = await renderComponent(ClanWarCard, { war: WAR, clan: CLAN })

    const text = wrapper.text()

    expect(text).toContain('TST')
    expect(text).toContain('ENM')
    expect(text).toContain('Враги')

    expectNoVueWarnings(capture.messages, 'ClanWarCard')

    wrapper.unmount()
  })

  it('показывает счёт только у завершённой войны', async () => {
    const ClanWarCard = (await import('@/components/clan/ClanWarCard.vue')).default

    const active = await renderComponent(ClanWarCard, { war: WAR, clan: CLAN })
    expect(active.text()).not.toContain('Ничья')
    active.unmount()

    const done = await renderComponent(ClanWarCard, {
      war: { ...WAR, status: 'completed', winner_clan_id: 1 },
      clan: CLAN,
    })

    expect(done.text()).toMatch(/Победа|Завершена/)
    expectNoVueWarnings(capture.messages, 'ClanWarCard завершённая')

    done.unmount()
  })

  it('переживает ничью и отсутствие соперника', async () => {
    const ClanWarCard = (await import('@/components/clan/ClanWarCard.vue')).default

    const wrapper = await renderComponent(ClanWarCard, {
      war: {
        ...WAR,
        status: 'completed',
        challenger_score: 2,
        opponent_score: 2,
        opponent: null,
        participants: [],
      },
      clan: CLAN,
    })

    expect(wrapper.exists()).toBe(true)
    expectNoVueWarnings(capture.messages, 'ClanWarCard ничья')

    wrapper.unmount()
  })
})

describe('пьедестал рейтинга', () => {
  let capture

  beforeEach(() => { capture = captureWarnings() })
  afterEach(() => { capture.restore() })

  it('рисует три места и баллы', async () => {
    const RatingPodium = (await import('@/components/players/RatingPodium.vue')).default

    const wrapper = await renderComponent(RatingPodium, { players: PLAYERS })

    const text = wrapper.text()

    expect(text).toContain('Игрок')
    expect(text).toContain('87')

    expectNoVueWarnings(capture.messages, 'RatingPodium')

    wrapper.unmount()
  })

  it('ничего не рисует без игроков', async () => {
    const RatingPodium = (await import('@/components/players/RatingPodium.vue')).default

    const wrapper = await renderComponent(RatingPodium, { players: [] })

    expect(wrapper.text()).toBe('')
    expectNoVueWarnings(capture.messages, 'RatingPodium пустой')

    wrapper.unmount()
  })
})

describe('карточка пьедестала главной', () => {
  let capture

  beforeEach(() => { capture = captureWarnings() })
  afterEach(() => { capture.restore() })

  it('рисует игрока с тиром и баллом', async () => {
    const PodiumCard = (await import('@/components/players/PodiumCard.vue')).default

    const wrapper = await renderComponent(PodiumCard, {
      entry: PLAYER,
      position: 1,
      baseLabel: 'APEX CHAMPION',
    })

    const text = wrapper.text()

    expect(text).toContain('Игрок')
    expect(text).toContain('87')
    expect(text).toContain('APEX CHAMPION')

    expectNoVueWarnings(capture.messages, 'PodiumCard игрок')

    wrapper.unmount()
  })

  it('рисует клан с силой и победами', async () => {
    const PodiumCard = (await import('@/components/players/PodiumCard.vue')).default

    const wrapper = await renderComponent(PodiumCard, {
      entry: { ...CLAN, power: 1250, wins: 30, avatar_url: null },
      position: 1,
      baseLabel: 'APEX CLAN',
    })

    const text = wrapper.text()

    expect(text).toContain('TST')
    expect(text).toContain('1250')
    expect(text).toContain('30')

    expectNoVueWarnings(capture.messages, 'PodiumCard клан')

    wrapper.unmount()
  })
})
