/**
 * Общее состояние тир-тестов и предсказание тира.
 */
import { ref } from 'vue'

/** Активная заявка (pending или in_progress) или null. */
export const activeTierTest = ref(null)

/** Обновить активную заявку по списку заявок игрока. */
export function refreshActiveTierTest(tests) {
  activeTierTest.value = (tests ?? []).find(
      t => t.status === 'pending' || t.status === 'in_progress',
  ) ?? null
}

/** Сбросить активную заявку (после отмены). */
export function clearActiveTierTest() {
  activeTierTest.value = null
}

/**
 * Тир по сумме аспектов.
 *
 * Пороги совпадают с серверным TierTestService::tierFor().
 */
export function tierForScore(score) {
  if (score === null || score === undefined) return null

  if (score >= 71) return 'A'
  if (score >= 56) return 'B'
  if (score >= 41) return 'C'
  if (score >= 21) return 'D'

  return 'E'
}
