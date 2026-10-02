/**
 * Smoke-тесты компонентов.
 *
 * Монтируют каждый компонент с заглушками окружения и проверяют, что он
 * не падает. Это ловит ошибки, которые не видит сборка: обращение к
 * переменной до объявления, чтение свойства у undefined, вызов
 * несуществующей функции.
 *
 * Компонентам, которым нужно настоящее приложение, ставится пометка
 * «skip» с причиной — чтобы тест не был ложным.
 */
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

import { buildTestRouter } from './router.js'

const SRC = join(process.cwd(), 'src')

/* --- Заглушки сервисов: запросы в тестах не нужны и вредны --- */
const emptyArray = () => vi.fn().mockResolvedValue([])

vi.mock('@/services/api.js', () => ({
  api: {
    get: emptyArray(),
    post: vi.fn().mockResolvedValue({}),
    put: vi.fn().mockResolvedValue({}),
    delete: vi.fn().mockResolvedValue({}),
  },
  getCsrfCookie: vi.fn(),
}))

vi.mock('@/services/chat.js', () => ({
  chatApi: {
    conversations: vi.fn().mockResolvedValue({ conversations: [] }),
    show: vi.fn().mockResolvedValue({ messages: [] }),
  },
}))

const authUser = {
  id: 1,
  username: 'Тест',
  role: 'user',
  tier: 'A',
  tier_score: 50,
  apex_coins: 1000,
  clan_member: null,
  aspects: { pvp: null, bedwars: null },
}

vi.mock('@/stores/auth', () => {
  const useAuthStore = () => ({
    user: authUser,
    rank: { position: 1, total: 10 },
    isAuthenticated: true,
    isStaff: false,
    fetchMe: vi.fn().mockResolvedValue(authUser),
    logout: vi.fn(),
    hasRole: () => false,
  })

  return { useAuthStore, default: useAuthStore }
})

/** Рекурсивно собирает компоненты. */
function components(dir = join(SRC, 'components'), acc = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name)

    if (statSync(full).isDirectory()) components(full, acc)
    else if (name.endsWith('.vue')) acc.push(full)
  }

  return acc
}

// Маршруты берём из настоящего роутера: дубли разошлись бы с приложением
const makeRouter = buildTestRouter

/**
 * Пробные значения для обязательных свойств.
 * Ключ — имя свойства, значение — правдоподобные данные.
 */
const SAMPLE = {
  id: 1,
  userId: 1,
  user: { id: 1, username: 'Тест', tier: 'A', avatar_url: null },
  clan: { id: 1, name: 'Клан', tag: 'CLN', members: [], is_highlighted: false, banner_color: '#7c3aed' },
  topic: { id: 1, title: 'Тема', body: 'Текст', author: { id: 1, username: 'Тест' }, replies: [], category: { id: 1, name: 'Раздел' } },
  reply: { id: 1, body: 'Ответ', author: { id: 1, username: 'Тест' }, children: [], attachments: [] },
  message: { id: 1, body: 'Текст', user: { id: 1, username: 'Тест' }, attachments: [] },
  conversation: { id: 1, type: 'direct', users: [] },
  item: { id: 1, name: 'Предмет', type: 'badge', price: 100 },
  shopItem: { id: 1, name: 'Предмет', type: 'badge', price: 100 },
  achievement: { id: 1, name: 'Ачивка', points: 10, icon: 'star', color: '#fff' },
  notification: { id: 1, data: {} },
  match: { id: 1, round: 1, position: 1 },
  participant: { id: 1, user: { id: 1, username: 'Тест' } },
  tournament: { id: 1, name: 'Турнир', status: 'registration', type: 'solo' },
  event: { id: 1, title: 'Событие', body: 'Текст' },
  comment: { id: 1, body: 'Комментарий', user: { id: 1, username: 'Тест' } },
  resource: { id: 1, name: 'Ресурс' },
  newsItem: { id: 1, title: 'Новость', body: 'Текст' },
  tierTest: { id: 1, status: 'pending', mode: 'pvp', user: { id: 1, username: 'Тест' } },
  tierTests: [],
  achievements: [],
  recommendations: [],
  myRecommendation: null,
  members: [],
  requests: [],
  participants: [],
  matches: [],
  tournaments: [],
  clans: [],
  players: [],
  items: [],
  list: [],
  options: [],
  stats: { members: 1, applications: 0, wars_active: 0 },
  permissions: {},
  label: 'Подпись',
  title: 'Заголовок',
  body: 'Текст',
  name: 'Имя',
  text: 'Текст',
  value: 'значение',
  modelValue: [],
  max: 10,
  total: 10,
  page: 1,
  myRole: 'member',
  isLeader: false,
  canManage: false,
  active: true,
}

/**
 * Значение для обязательного свойства.
 *
 * Для массивов важен именно массив: если подставить строку, Vue выдаст
 * предупреждение о неверном типе, и тест будет шуметь.
 */
function propValue(name, type) {
  if (SAMPLE[name] !== undefined) return SAMPLE[name]

  const types = Array.isArray(type) ? type : [type]
  const isArray = types.includes(Array)

  if (isArray) return []
  if (types.includes(Number)) return 1
  if (types.includes(Boolean)) return false
  if (types.includes(Object)) return {}
  if (types.includes(Function)) return () => {}

  return 'тест'
}

/** Собирает props из объявления компонента. */
function buildProps(component) {
  const props = {}
  const declaration = component.props

  if (!declaration) return props

  if (Array.isArray(declaration)) {
    for (const name of declaration) props[name] = propValue(name)

    return props
  }

  for (const [name, cfg] of Object.entries(declaration)) {
    const isRequired = cfg?.required === true
    const known = SAMPLE[name] !== undefined

    if (!isRequired && !known) continue

    props[name] = propValue(name, cfg?.type)
  }

  return props
}

/**
 * Компоненты, которым нужно настоящее приложение.
 *
 * Причина обязательна: без неё непонятно, тест пропущен осознанно
 * или потому, что компонент сломан.
 */
const SKIP = {
  'TierHistoryChart.vue': 'Chart.js требует canvas, которого нет в jsdom',
  'HelloWorld.vue': 'демонстрационный компонент из шаблона',
  'TheWelcome.vue': 'демонстрационный компонент из шаблона',
}

const list = components()

describe('компоненты монтируются', () => {
  it('в проекте есть компоненты', () => {
    expect(list.length).toBeGreaterThan(50)
  })

  for (const file of list) {
    const name = file.split('/').pop()
    const reason = SKIP[name]

    if (reason) {
      it.skip(`${relative(SRC, file)} (пропущен: ${reason})`, () => {})
      continue
    }

    it(`${relative(SRC, file)}`, async () => {
      const module = await import(/* @vite-ignore */ file)
      const component = module.default

      expect(component, 'Компонент не экспортируется по умолчанию').toBeTruthy()

      const router = makeRouter()
      await router.push('/')
      await router.isReady()

      const wrapper = mount(component, {
        props: buildProps(component),
        global: { plugins: [router, createPinia()] },
      })

      expect(wrapper.exists()).toBe(true)

      wrapper.unmount()
    })
  }
})
