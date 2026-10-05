/**
 * Тир-тесты: общее состояние страницы.
 *
 * Один источник на список заявок и на активную заявку. Раньше список жил
 * внутри компонента истории, а кнопка записи — в профиле: запись через
 * профиль не обновляла список, и заявка появлялась только после
 * перезагрузки страницы.
 */
import { ref } from 'vue'
import { tierTestsApi } from '@/services/tiers/tierTests.js'

/** Активная заявка (pending или in_progress) или null. */
export const activeTierTest = ref(null)

/** Мои заявки и заявки, где я тестер. */
export const myTierTests = ref([])
export const testerTierTests = ref([])

/** Идёт ли загрузка списка. */
export const tierTestsLoading = ref(false)

/** Обновить активную заявку по списку заявок игрока. */
export function refreshActiveTierTest(tests) {
  activeTierTest.value = (tests ?? []).find(
      t => t.status === 'pending' || t.status === 'in_progress',
  ) ?? null
}

/**
 * Загружает список заявок с сервера.
 *
 * Вызывается при монтировании истории и после каждого изменения —
 * записи или отмены.
 */
export async function refreshTierTests() {
  tierTestsLoading.value = true

  try {
    const data = await tierTestsApi.list()

    myTierTests.value = data.my_tests || []
    testerTierTests.value = data.as_tester || []

    refreshActiveTierTest(myTierTests.value)
  } catch {
    /* Сеть недоступна: оставляем прежний список */
  } finally {
    tierTestsLoading.value = false
  }
}

/**
 * Добавляет только что созданную заявку.
 *
 * Ставим её первой сразу, не дожидаясь ответа списка: так заявка видна
 * в ту же секунду, а refreshTierTests() следом уточнит данные.
 */
export function addCreatedTierTest(created) {
  if (!created) return

  myTierTests.value = [created, ...myTierTests.value]
  activeTierTest.value = created
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
