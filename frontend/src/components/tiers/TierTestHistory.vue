<script setup>
import { computed, onMounted, ref } from 'vue'
import { tierTestsApi } from '@/services/tiers/tierTests.js'
import TierTestForm from '@/components/tiers/TierTestForm.vue'
import { userLink } from '@/utils/links.js'
import { confirm } from '@/utils/dialog.js'
import { refreshActiveTierTest } from '@/composables/tiers/tierTestState.js'

const loading = ref(true)
const myTests = ref([])
const asTester = ref([])

async function load() {
  loading.value = true

  try {
    const data = await tierTestsApi.list()

    myTests.value = data.my_tests || []
    asTester.value = data.as_tester || []

    // Кнопка записи в профиле должна узнать об активной заявке сразу
    refreshActiveTierTest(myTests.value)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

/*
 * Активная заявка: ожидает тестера или уже в работе. Пока она есть,
 * вторую создавать нельзя — сервер такую попытку отклонит.
 */
const activeTest = computed(() =>
    myTests.value.find(
        t => t.status === 'pending' || t.status === 'in_progress',
    ) ?? null
)

/** Отмена собственной заявки, пока она ждёт тестера. */
const cancellingId = ref(null)
const cancelError = ref('')

async function cancelTest(test) {
  if (cancellingId.value) return

  // Отмена безвозвратна — спрашиваем подтверждение
  const ok = await confirm(
      'Отменить заявку на тир-тест? Дождаться тестера будет нельзя.',
      { danger: true, confirmText: 'Отменить' },
  )

  if (!ok) return

  cancellingId.value = test.id
  cancelError.value = ''

  try {
    await tierTestsApi.cancel(test.id)

    await load()
  } catch (e) {
    cancelError.value = e.message || 'Не удалось отменить заявку'
  } finally {
    cancellingId.value = null
  }
}

const statusLabels = {
  pending: 'Ожидает',
  in_progress: 'В процессе',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

onMounted(load)
</script>

<template>
  <section class="tier-tests">
    <div class="tier-tests__glow"></div>

    <!-- HEADER -->
    <header class="head">
      <div class="head__identity">
        <div class="head__eyebrow">
          <span></span>
          COMPETITIVE RECORD
        </div>

        <div class="head__title-row">
          <h2>Тир-тесты</h2>

          <span class="head__count">
            {{ myTests.length }}
          </span>
        </div>

        <p class="head__description">
          История квалификационных испытаний
        </p>
      </div>

      <TierTestForm
          :active-test="activeTest"
          @created="load"
      />
    </header>

    <!-- LOADING -->
    <div v-if="loading" class="state state--loading">
      <div class="state__signal">
        <span></span>
      </div>

      <div>
        <div class="state__title">
          Загрузка истории
        </div>

        <div class="state__text">
          Синхронизация результатов...
        </div>
      </div>
    </div>

    <template v-else>
      <!-- EMPTY -->
      <div
          v-if="!myTests.length"
          class="state state--empty"
      >
        <div class="state__icon">
          <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
          >
            <path
                d="M4 19V9l8-5 8 5v10"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <path
                d="M8 19v-6h8v6M12 9v2"
                stroke-linecap="round"
            />
          </svg>
        </div>

        <div class="state__title">
          История пока пуста
        </div>

        <div class="state__text">
          Ты ещё не записывался на тир-тесты
        </div>
      </div>

      <div
          v-if="cancelError"
          class="cancel-error"
      >
        {{ cancelError }}
      </div>

      <!-- LIST -->
      <div v-else class="list">
        <div
            v-for="(t, index) in myTests"
            :key="t.id"
            class="test-row"
            :class="{
            'test-row--completed': t.status === 'completed',
          }"
        >
          <div class="test-row__index">
            {{ String(index + 1).padStart(2, '0') }}
          </div>

          <div class="test-info">
            <div class="test-mode">
              <span class="test-mode__dot"></span>

              {{ t.mode === 'pvp' ? 'PvP' : 'BedWars' }}
            </div>

            <div class="test-date">
              {{ new Date(t.created_at).toLocaleDateString('ru-RU') }}
            </div>
          </div>

          <div
              class="test-status"
              :class="`status-${t.status}`"
          >
            <span class="test-status__dot"></span>
            {{ statusLabels[t.status] }}
          </div>

          <!--
            Кто провёл тест. Раньше в истории было только время и
            результат, и понять, какой тестер ставил оценку, было нельзя.
          -->
          <div class="test-tester">
            <template v-if="t.tester">
              <span class="test-tester__label">Тестер</span>

              <RouterLink
                  :to="userLink(t.tester)"
                  class="test-tester__person"
              >
                <span class="test-tester__avatar">
                  <img
                      v-if="t.tester.avatar_url"
                      :src="t.tester.avatar_url"
                      :alt="t.tester.username"
                  />

                  <span v-else class="test-tester__avatar-fallback">
                    {{ (t.tester.username || 'Т')[0].toUpperCase() }}
                  </span>
                </span>

                <span class="test-tester__name">
                  {{ t.tester.username }}
                </span>
              </RouterLink>
            </template>

            <span
                v-else
                class="test-tester__label"
            >
              —
            </span>
          </div>

          <div
              v-if="t.result_tier"
              class="test-result"
          >
            <span class="tier">
              {{ t.result_tier }}
            </span>

            <span class="score">
              {{ t.result_score }}%
            </span>
          </div>

          <div
              v-else
              class="test-result test-result--empty"
          >
            —
          </div>

          <button
              v-if="t.status === 'pending'"
              type="button"
              class="test-cancel"
              :disabled="cancellingId === t.id"
              @click="cancelTest(t)"
          >
            {{ cancellingId === t.id ? 'Отмена…' : 'Отменить' }}
          </button>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
@import "@/components/tiers/TierTestHistory.css";
</style>
