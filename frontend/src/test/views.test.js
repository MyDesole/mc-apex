/**
 * Smoke-тесты страниц (views).
 *
 * Страницы крупнее компонентов и чаще содержат ошибки времени
 * выполнения: обращение к переменной до объявления, чтение свойства
 * у undefined, несуществующий метод. Сборка такие ошибки не видит.
 */
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

import { buildTestRouter } from './router.js'

const SRC = join(process.cwd(), 'src')

vi.mock('@/services/api.js', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ data: [], items: [] }),
    post: vi.fn().mockResolvedValue({}),
    put: vi.fn().mockResolvedValue({}),
    delete: vi.fn().mockResolvedValue({}),
  },
  getCsrfCookie: vi.fn(),
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
  featured_achievements: [],
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

function views(dir = join(SRC, 'views'), acc = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name)

    if (statSync(full).isDirectory()) views(full, acc)
    else if (name.endsWith('.vue')) acc.push(full)
  }

  return acc
}

// Маршруты берём из настоящего роутера, чтобы имена не расходились
const makeRouter = buildTestRouter

const list = views()

describe('страницы монтируются', () => {
  it('в проекте есть страницы', () => {
    expect(list.length).toBeGreaterThan(20)
  })

  for (const file of list) {
    it(`${relative(SRC, file)}`, async () => {
      const module = await import(/* @vite-ignore */ file)
      const component = module.default

      expect(component, 'Страница не экспортируется по умолчанию').toBeTruthy()

      const router = makeRouter()
      await router.push('/')
      await router.isReady()

      const wrapper = mount(component, {
        global: {
          plugins: [router, createPinia()],
          stubs: {
            // Тяжёлые дочерние блоки не нужны: проверяем саму страницу
            AppLayout: { template: '<div><slot /></div>' },
          },
        },
      })

      expect(wrapper.exists()).toBe(true)

      wrapper.unmount()
    })
  }
})
