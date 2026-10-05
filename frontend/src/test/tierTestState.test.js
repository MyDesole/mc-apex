/**
 * Общее состояние тир-тестов: список заявок, активная заявка и тир.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const list = vi.fn()

vi.mock('@/services/tiers/tierTests.js', () => ({
  tierTestsApi: {
    list: (...args) => list(...args),
  },
}))

const {
  activeTierTest,
  myTierTests,
  testerTierTests,
  clearActiveTierTest,
  refreshActiveTierTest,
  refreshTierTests,
  addCreatedTierTest,
  tierForScore,
} = await import('@/composables/tiers/tierTestState.js')

describe('Активная заявка', () => {
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

describe('Список заявок', () => {
  beforeEach(() => {
    myTierTests.value = []
    testerTierTests.value = []
    activeTierTest.value = null
    list.mockReset()
  })

  it('загрузка наполняет список и активную заявку', async () => {
    list.mockResolvedValue({
      my_tests: [{ id: 1, status: 'pending' }],
      as_tester: [{ id: 2 }],
    })

    await refreshTierTests()

    expect(myTierTests.value).toHaveLength(1)
    expect(testerTierTests.value).toHaveLength(1)
    expect(activeTierTest.value?.id).toBe(1)
  })

  it('созданная заявка появляется сразу, без загрузки', () => {
    myTierTests.value = [{ id: 5, status: 'completed' }]

    addCreatedTierTest({ id: 9, status: 'pending' })

    expect(myTierTests.value[0].id).toBe(9)
    expect(myTierTests.value).toHaveLength(2)
    expect(activeTierTest.value?.id).toBe(9)
  })

  it('созданная заявка без данных ничего не ломает', () => {
    myTierTests.value = []

    addCreatedTierTest(null)

    expect(myTierTests.value).toHaveLength(0)
  })

  it('ошибка сети оставляет прежний список', async () => {
    myTierTests.value = [{ id: 3, status: 'pending' }]

    list.mockRejectedValue(new Error('сеть'))

    await refreshTierTests()

    expect(myTierTests.value).toHaveLength(1)
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
