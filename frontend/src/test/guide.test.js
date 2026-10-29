/**
 * Путеводитель: у каждого шага сервера есть описание, а адрес шага
 * узнаётся вместе с вкладкой (?tab=).
 */
import { describe, expect, it } from 'vitest'
import { GUIDE_STEPS, onStepRoute } from '@/data/guide/steps.js'

// Как в GuideService::STEPS
const SERVER_STEPS = ['welcome', 'rating', 'test', 'profile', 'clans', 'tournaments', 'shop', 'pet', 'pets', 'expedition', 'arena', 'auction', 'tops', 'miner']

describe('guide steps', () => {
  it('каждый шаг сервера описан', () => {
    for (const id of SERVER_STEPS) {
      expect(GUIDE_STEPS[id]?.title, id).toBeTruthy()
      expect(GUIDE_STEPS[id]?.short, id).toBeTruthy()
      if (id !== 'welcome') {
        expect(GUIDE_STEPS[id].route, id).toMatch(/^\//)
        expect(GUIDE_STEPS[id].text, id).toBeTruthy()
        expect(GUIDE_STEPS[id].target?.length, id).toBeGreaterThan(0)
      }
    }
  })

  it('у шага про тест есть вариант для бриджа', () => {
    expect(GUIDE_STEPS.test.bridgeTitle).toContain('бридж')
    expect(GUIDE_STEPS.test.bridgeText).toBeTruthy()
    expect(GUIDE_STEPS.test.bridgeTarget[0]).toContain('bridge')
    // Шаг про тир-тест подсвечивает кнопку «Записаться», а не шапку профиля
    expect(GUIDE_STEPS.test.target[0]).toContain('tests-section__button')
  })

  it('адрес шага сверяется вместе с вкладкой', () => {
    expect(onStepRoute(GUIDE_STEPS.arena, { path: '/pets', query: { tab: 'arena' } })).toBe(true)
    expect(onStepRoute(GUIDE_STEPS.arena, { path: '/pets', query: {} })).toBe(false)
    expect(onStepRoute(GUIDE_STEPS.pets, { path: '/pets', query: { tab: 'arena' } })).toBe(true)
    expect(onStepRoute(GUIDE_STEPS.rating, { path: '/miner', query: {} })).toBe(false)
    expect(onStepRoute(GUIDE_STEPS.welcome, { path: '/', query: {} })).toBe(false)
  })
})
