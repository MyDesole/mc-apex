<script setup>
/**
 * Панель бридж-тестера: проверка заявок на виды бриджа.
 *
 * Доступна только роли bridge_tester и админу — остальные игроки этот
 * раздел не видят. Тестер смотрит видео, ставит оценки и подтверждает
 * вид либо отклоняет его с причиной.
 */
import { computed, onMounted, ref } from 'vue'
import { bridgeReviewApi } from '@/services/bridge/bridge.js'
import { alert as alertDialog } from '@/utils/dialog.js'

const loading = ref(true)
const error = ref('')
const rows = ref([])

const active = ref(null)
const processing = ref(false)
const form = ref(empty())

function empty() {
  return {
    stability: 0,
    speed: 0,
    difficulty: 0,
    score: 0,
    notes: '',
  }
}

const total = computed(() =>
    (Number(form.value.stability) || 0)
    + (Number(form.value.speed) || 0)
    + (Number(form.value.difficulty) || 0),
)

const tierForTotal = computed(() => {
  const value = total.value

  if (value >= 213) return 'A'
  if (value >= 168) return 'B'
  if (value >= 123) return 'C'
  if (value >= 63) return 'D'

  return 'E'
})

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await bridgeReviewApi.pending()

    rows.value = data.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить заявки'
  } finally {
    loading.value = false
  }
}

function open(row) {
  active.value = row
  form.value = empty()
  error.value = ''
}

function close() {
  active.value = null
}

async function confirm() {
  processing.value = true
  error.value = ''

  try {
    await bridgeReviewApi.review(active.value.id, {
      confirm: true,
      stability: Number(form.value.stability) || 0,
      speed: Number(form.value.speed) || 0,
      difficulty: Number(form.value.difficulty) || 0,
      score: Number(form.value.score) || 0,
      notes: form.value.notes || null,
    })

    close()
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось подтвердить'
  } finally {
    processing.value = false
  }
}

async function reject() {
  if (!form.value.notes.trim()) {
    error.value = 'Укажи причину отказа — игрок должен понять, что не так'

    return
  }

  processing.value = true
  error.value = ''

  try {
    await bridgeReviewApi.review(active.value.id, {
      confirm: false,
      notes: form.value.notes,
    })

    close()
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось отклонить'
  } finally {
    processing.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="bridge-review">
    <header class="bridge-review__head">
      <div>
        <div class="bridge-review__eyebrow">
          BRIDGE REVIEW
        </div>

        <h1>Проверка бриджа</h1>

        <p>
          Заявки на виды бриджа. Вид становится подтверждённым только после
          твоей проверки.
        </p>
      </div>

      <div class="bridge-review__count">
        <span>{{ rows.length }}</span>
        в очереди
      </div>
    </header>

    <div v-if="error" class="bridge-review__error">{{ error }}</div>

    <div v-if="loading" class="bridge-review__state">
      Загрузка заявок...
    </div>

    <div v-else-if="!rows.length" class="bridge-review__state">
      Заявок на проверку нет
    </div>

    <div v-else class="bridge-review__list">
      <article
          v-for="row in rows"
          :key="row.id"
          class="request"
      >
        <div class="request__player">
          <div class="request__avatar">
            <img
                v-if="row.user?.avatar_url"
                :src="row.user.avatar_url"
                :alt="row.user.username"
            />

            <span v-else>
              {{ (row.user?.username || 'И')[0].toUpperCase() }}
            </span>
          </div>

          <div>
            <div class="request__name">
              {{ row.user?.username }}
            </div>

            <div class="request__meta">
              текущий тир: <b>{{ row.user?.tier ?? '—' }}</b>
              · {{ row.user?.tier_score ?? 0 }}%
            </div>
          </div>
        </div>

        <div class="request__technique">
          <span class="request__label">Вид</span>
          {{ row.technique?.label }}
        </div>

        <a
            v-if="row.video_url"
            :href="row.video_url"
            target="_blank"
            rel="noopener"
            class="request__video"
        >
          Смотреть видео
        </a>

        <button
            type="button"
            class="request__open"
            @click="open(row)"
        >
          Проверить
        </button>
      </article>
    </div>

    <!-- Проверка заявки -->
    <Teleport to="body">
      <div
          v-if="active"
          class="review-modal-bg"
          @click.self="close"
      >
        <div class="review-modal">
          <header class="review-modal__head">
            <div>
              <h2>{{ active.technique?.label }}</h2>
              <span>{{ active.user?.username }}</span>
            </div>

            <button type="button" class="review-modal__close" @click="close">✕</button>
          </header>

          <div class="review-modal__body">
            <div v-if="error" class="bridge-review__error">{{ error }}</div>

            <a
                v-if="active.video_url"
                :href="active.video_url"
                target="_blank"
                rel="noopener"
                class="review-modal__video"
            >
              Открыть видео в новой вкладке
            </a>

            <p class="review-modal__note">
              Проверь, что ролик записан на сервере, длится около двух минут,
              неудачи не обрезаны, виден CPS-мод и Keystrokes. При подозрении
              на читы, монтаж или чужой клип — отклони и вызови на проверку.
            </p>

            <div class="review-grid">
              <label class="review-field">
                <span>Стабильность <i>0–100</i></span>
                <input v-model.number="form.stability" type="number" min="0" max="100" />
              </label>

              <label class="review-field">
                <span>Скорость <i>0–100</i></span>
                <input v-model.number="form.speed" type="number" min="0" max="100" />
              </label>

              <label class="review-field">
                <span>Сложность <i>0–100</i></span>
                <input v-model.number="form.difficulty" type="number" min="0" max="100" />
              </label>

              <label class="review-field">
                <span>Владение видом <i>0–10</i></span>
                <input v-model.number="form.score" type="number" min="0" max="10" />
              </label>
            </div>

            <div class="review-result">
              <div>
                <span class="review-result__label">Общая статистика</span>
                <span class="review-result__value">{{ total }} / 300</span>
              </div>

              <div>
                <span class="review-result__label">Тир</span>
                <span class="review-result__value review-result__value--tier">
                  {{ tierForTotal }}
                </span>
              </div>
            </div>

            <label class="review-field review-field--wide">
              <span>Комментарий <i>обязателен при отказе</i></span>
              <textarea
                  v-model="form.notes"
                  rows="3"
                  maxlength="1000"
                  placeholder="Что видно на видео, замечания"
              />
            </label>
          </div>

          <footer class="review-modal__foot">
            <button
                type="button"
                class="review-modal__reject"
                :disabled="processing"
                @click="reject"
            >
              Отклонить
            </button>

            <button
                type="button"
                class="review-modal__confirm"
                :disabled="processing"
                @click="confirm"
            >
              {{ processing ? '...' : 'Подтвердить вид' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
@import "@/views/bridge/BridgeReviewView.css";
</style>
