/**
 * Общее состояние тир-тестов и предсказание тира.
 */
import { beforeEach, describe, expect, it } from 'vitest'

import {
  activeTierTest,
  clearActiveTierTest,
  refreshActiveTierTest,
  tierForScore,
} from '@/composables/tiers/tierTestState.js'

describe('Состояние тир-тестов', () => {
  beforeEach(() => {
    activeTierTest.value = null
  })

  it('находит активную заявку в списке', () => {
    refreshActiveTierTest([
      { id: 1, status: 'completed' },
      { id: 2, status: 'pending' },
    ])

    expect(activeTierTest.value?.id).toBe(2)
  })

  it('in_progress тоже активная', () => {
    refreshActiveTierTest([
      { id: 1, status: 'completed' },
      { id: 3, status: 'in_progress' },
    ])

    expect(activeTierTest.value?.id).toBe(3)
  })

  it('без активных заявок — null', () => {
    refreshActiveTierTest([
      { id: 1, status: 'completed' },
      { id: 2, status: 'cancelled' },
    ])

    expect(activeTierTest.value).toBeNull()
  })

  it('сброс убирает активную заявку', () => {
    refreshActiveTierTest([{ id: 1, status: 'pending' }])

    clearActiveTierTest()

    expect(activeTierTest.value).toBeNull()
  })

  it('пустой список даёт null', () => {
    refreshActiveTierTest([])

    expect(activeTierTest.value).toBeNull()
  })
})

describe('Предсказание тира', () => {
  it('A от 71', () => {
    expect(tierForScore(71)).toBe('A')
    expect(tierForScore(90)).toBe('A')
  })

  it('B от 56', () => {
    expect(tierForScore(56)).toBe('B')
    expect(tierForScore(70)).toBe('B')
  })

  it('C от 41', () => {
    expect(tierForScore(41)).toBe('C')
    expect(tierForScore(55)).toBe('C')
  })

  it('D от 21', () => {
    expect(tierForScore(21)).toBe('D')
    expect(tierForScore(40)).toBe('D')
  })

  it('E ниже 21', () => {
    expect(tierForScore(20)).toBe('E')
    expect(tierForScore(0)).toBe('E')
  })

  it('null при отсутствии данных', () => {
    expect(tierForScore(null)).toBeNull()
    expect(tierForScore(undefined)).toBeNull()
  })
})
