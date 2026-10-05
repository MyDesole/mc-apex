<script setup>
/**
 * История подтверждений видов бриджа.
 *
 * Устроена как история тир-тестов: видно, что заявил, когда, чем закончилось
 * и с какой оценкой. Неподтверждённую заявку можно забрать.
 */
import { computed, onMounted, ref } from 'vue'
import { bridgeApi } from '@/services/bridge/bridge.js'
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'

const loading = ref(true)
const error = ref('')
const rows = ref([])
const busyId = ref(null)

// Только то, что игрок уже заявлял: пустые виды в истории не нужны
const submissions = computed(() =>
    rows.value
        .filter(row => row.submission)
        .map(row => ({ technique: row.technique, submission: row.submission })),
)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await bridgeApi.techniques()

    rows.value = data.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить историю'
  } finally {
    loading.value = false
  }
}

function statusLabel(submission) {
  if (submission.is_confirmed) return 'подтверждён'
  if (submission.status === 'rejected') return 'отклонён'

  return 'на проверке'
}

function statusClass(submission) {
  if (submission.is_confirmed) return 'ok'
  if (submission.status === 'rejected') return 'no'

  return 'wait'
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** Забрать заявку, пока тестер её не подтвердил. */
async function withdraw(row) {
  const ok = await confirmDialog(
      `Убрать заявку на «${row.technique.label}»?`,
      { danger: true, confirmText: 'Убрать' },
  )

  if (!ok) return

  busyId.value = row.submission.id

  try {
    await bridgeApi.withdraw(row.submission.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось убрать заявку')
  } finally {
    busyId.value = null
  }
}

onMounted(load)

defineExpose({ load })
</script>

<template>
  <div class="bridge-history">
    <div v-if="error" class="bridge-history__error">{{ error }}</div>

    <div v-if="loading" class="bridge-history__state">
      Загрузка истории...
    </div>

    <div v-else-if="!submissions.length" class="bridge-history__state">
      Заявок пока не было
    </div>

    <ul v-else class="bridge-history__list">
      <li
          v-for="row in submissions"
          :key="row.submission.id"
          class="bridge-history__item"
          :class="`bridge-history__item--${statusClass(row.submission)}`"
      >
        <div class="bridge-history__main">
          <span class="bridge-history__name">{{ row.technique.label }}</span>

          <span class="bridge-history__date">
            {{ formatDate(row.submission.updated_at) }}
          </span>
        </div>

        <div class="bridge-history__result">
          <span
              v-if="row.submission.is_confirmed"
              class="bridge-history__score"
          >
            {{ row.submission.score }}/10 · {{ row.submission.total }}/300
          </span>

          <span v-else class="bridge-history__status">
            {{ statusLabel(row.submission) }}
          </span>
        </div>

        <div class="bridge-history__actions">
          <a
              v-if="row.submission.has_video"
              :href="row.submission.video_url"
              target="_blank"
              rel="noopener"
              class="bridge-history__link"
          >
            видео
          </a>

          <button
              v-if="!row.submission.is_confirmed && row.submission.status !== 'rejected'"
              type="button"
              class="bridge-history__remove"
              :disabled="busyId === row.submission.id"
              @click="withdraw(row)"
          >
            убрать
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
@import "@/components/bridge/BridgeTechniqueHistory.css";
</style>
