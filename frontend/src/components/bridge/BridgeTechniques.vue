<script setup>
/**
 * Техники бриджа в профиле.
 *
 * Подтверждённые виды показаны ярко с оценкой тестера, заявленные но ещё
 * не проверенные — серыми. В своём профиле можно отметить новый вид и
 * приложить видео.
 */
import { computed, onMounted, ref } from 'vue'
import { bridgeApi } from '@/services/bridge/bridge.js'
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'

const props = defineProps({
  // Подтверждённые виды из ответа профиля
  techniques: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  rank: { type: Object, default: () => ({ position: null, total: 0 }) },
  // Своя ли это страница: в чужом профиле только просмотр
  editable: { type: Boolean, default: false },
})

// Каталог с моими заявками — нужен только в своём профиле
const catalog = ref([])
const loading = ref(false)
const error = ref('')

const formOpen = ref(false)
const submitting = ref(false)
const form = ref({ technique_id: null, video_url: '' })

const confirmedCount = computed(() => props.summary?.confirmed_count ?? props.techniques.length)
const aspectsTotal = computed(() => props.summary?.aspects_total ?? 0)
const maxTotal = computed(() => Math.max(1, confirmedCount.value * 300))

/** Свои виды: подтверждённые + заявленные, в одном списке с состоянием. */
const rows = computed(() => {
  if (!props.editable) {
    return props.techniques.map(row => ({
      technique: row.technique,
      submission: row,
    }))
  }

  return catalog.value
})

/** Свободные виды — их можно заявить. */
const available = computed(() =>
    catalog.value.filter(row => !row.submission),
)

async function loadCatalog() {
  if (!props.editable) return

  loading.value = true
  error.value = ''

  try {
    const data = await bridgeApi.techniques()

    catalog.value = data.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить виды бриджа'
  } finally {
    loading.value = false
  }
}

function openForm() {
  form.value = { technique_id: available.value[0]?.technique?.id ?? null, video_url: '' }
  error.value = ''
  formOpen.value = true
}

async function submit() {
  if (!form.value.technique_id || !form.value.video_url) return

  submitting.value = true
  error.value = ''

  try {
    await bridgeApi.declare(form.value.technique_id, form.value.video_url.trim())

    formOpen.value = false
    await loadCatalog()
  } catch (e) {
    error.value = e.message || 'Не удалось отправить заявку'
  } finally {
    submitting.value = false
  }
}

async function withdraw(row) {
  const ok = await confirmDialog(
      `Убрать вид «${row.technique.label}» из профиля?`,
      { danger: true, confirmText: 'Убрать' },
  )

  if (!ok) return

  try {
    await bridgeApi.withdraw(row.submission.id)
    await loadCatalog()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось убрать вид')
  }
}

onMounted(loadCatalog)
</script>

<template>
  <section class="bridge-block">
    <header class="bridge-block__head">
      <div>
        <div class="bridge-block__eyebrow">
          BRIDGE MASTERY
        </div>

        <h3 class="bridge-block__title">
          Техники бриджа
        </h3>
      </div>

      <div class="bridge-block__summary">
        <span class="bridge-block__count">
          {{ confirmedCount }}
        </span>

        <span class="bridge-block__count-label">
          подтверждено
        </span>

        <span
            v-if="confirmedCount"
            class="bridge-block__total"
        >
          {{ aspectsTotal }} / {{ maxTotal }}
        </span>
      </div>
    </header>

    <p class="bridge-block__hint">
      Вид становится ярким, когда бридж-тестер проверит видео.
      Ролик должен быть записан на сервере, длиться около двух минут,
      без обрезки неудач, с видимым CPS-модом и Keystrokes.
    </p>

    <div v-if="error" class="bridge-block__error">{{ error }}</div>

    <div v-if="loading" class="bridge-block__state">
      Загрузка видов...
    </div>

    <div v-else-if="!rows.length" class="bridge-block__state">
      Пока нет подтверждённых видов
    </div>

    <ul v-else class="bridge-list">
      <li
          v-for="row in rows"
          :key="row.technique.id"
          class="bridge-item"
          :class="{
            'bridge-item--confirmed': row.submission?.is_confirmed,
            'bridge-item--pending': row.submission && !row.submission.is_confirmed,
          }"
      >
        <div class="bridge-item__main">
          <span class="bridge-item__label">
            {{ row.technique.label }}
          </span>

          <span
              v-if="row.technique.description"
              class="bridge-item__desc"
          >
            {{ row.technique.description }}
          </span>
        </div>

        <!-- Подтверждено: оценка тестера -->
        <div
            v-if="row.submission?.is_confirmed"
            class="bridge-item__score"
        >
          <span class="bridge-item__score-value">
            {{ row.submission.score }}/10
          </span>

          <span class="bridge-item__aspects">
            {{ row.submission.total }}/300
          </span>
        </div>

        <!-- Заявлено, ждёт проверки -->
        <div
            v-else-if="row.submission"
            class="bridge-item__pending"
        >
          <span class="bridge-item__badge">
            на проверке
          </span>

          <a
              v-if="row.submission.video_url"
              :href="row.submission.video_url"
              target="_blank"
              rel="noopener"
              class="bridge-item__link"
          >
            видео
          </a>

          <button
              v-if="editable"
              type="button"
              class="bridge-item__remove"
              @click="withdraw(row)"
          >
            убрать
          </button>
        </div>

        <!-- Свободный вид -->
        <div
            v-else
            class="bridge-item__empty"
        >
          не заявлен
        </div>
      </li>
    </ul>

    <!-- Подача нового вида -->
    <div
        v-if="editable"
        class="bridge-actions"
    >
      <button
          v-if="!formOpen"
          type="button"
          class="bridge-actions__open"
          :disabled="!available.length"
          @click="openForm"
      >
        {{ available.length ? '+ Заявить вид бриджа' : 'Все виды уже заявлены' }}
      </button>

      <div
          v-else
          class="bridge-form"
      >
        <label class="bridge-form__field">
          <span>Вид бриджа</span>

          <select v-model.number="form.technique_id">
            <option
                v-for="row in available"
                :key="row.technique.id"
                :value="row.technique.id"
            >
              {{ row.technique.label }}
            </option>
          </select>
        </label>

        <label class="bridge-form__field">
          <span>Ссылка на видео</span>

          <input
              v-model="form.video_url"
              type="url"
              placeholder="https://youtu.be/..."
              maxlength="512"
          />
        </label>

        <p class="bridge-form__note">
          Видео должно быть записано на сервере, а не в одиночном мире:
          около двух минут, без обрезки неудач, с видимым CPS-модом и
          Keystrokes. Если показываешь несколько бриджей — пришли
          отдельный ролик на каждый.
        </p>

        <div class="bridge-form__actions">
          <button
              type="button"
              class="bridge-form__cancel"
              @click="formOpen = false"
          >
            Отмена
          </button>

          <button
              type="button"
              class="bridge-form__submit"
              :disabled="submitting || !form.technique_id || !form.video_url"
              @click="submit"
          >
            {{ submitting ? 'Отправка...' : 'Отправить на проверку' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@import "@/components/bridge/BridgeTechniques.css";
</style>
