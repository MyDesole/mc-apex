<script setup>
/**
 * Бридж игрока в досье админа: исправить завышенные оценки тестера,
 * сменить статус заявки, удалить её и поменять звание бриджера.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { bridgeAdminApi, bridgeReviewApi } from '@/services/bridge/bridge.js'
import { alert, confirm } from '@/utils/dialog.js'

const props = defineProps({
  userId: { type: Number, required: true },
})

const emit = defineEmits(['changed'])

const STATUS = { declared: 'на проверке', confirmed: 'подтверждён', rejected: 'отклонён' }

const data = ref(null)
const forms = ref({})
const rankId = ref(null)
const busy = ref(false)
const selectedId = ref(null)

const selected = computed(() => data.value?.submissions.find((s) => s.id === selectedId.value) ?? null)

async function load() {
  data.value = await bridgeAdminApi.player(props.userId)
  rankId.value = data.value.rank?.id ?? null
  // Выбранную заявку сохраняем, если она ещё есть, иначе берём первую
  if (!data.value.submissions.some((s) => s.id === selectedId.value)) {
    selectedId.value = data.value.submissions[0]?.id ?? null
  }
  forms.value = Object.fromEntries(data.value.submissions.map((s) => [s.id, {
    status: s.status,
    stability: s.stability,
    speed: s.speed,
    difficulty: s.difficulty,
    score: s.score,
  }]))
}

async function run(fn) {
  busy.value = true
  try {
    await fn()
    await load()
    emit('changed')
  } catch (e) {
    await alert(e.message || 'Не удалось сохранить', { title: 'Бридж' })
  } finally {
    busy.value = false
  }
}

function save(submission) {
  const form = forms.value[submission.id]
  const toInt = (v) => (v === '' || v === null || v === undefined ? null : Number(v))

  return run(() => bridgeAdminApi.update(submission.id, {
    status: form.status,
    stability: toInt(form.stability),
    speed: toInt(form.speed),
    difficulty: toInt(form.difficulty),
    score: toInt(form.score),
  }))
}

async function remove(submission) {
  const ok = await confirm(`Удалить заявку «${submission.technique?.label}»? Игрок сможет подать вид заново.`, {
    title: 'Удаление заявки',
    danger: true,
  })
  if (ok) await run(() => bridgeAdminApi.remove(submission.id))
}

function saveRank() {
  return run(() => bridgeReviewApi.assignRank(props.userId, rankId.value))
}

onMounted(load)
watch(() => props.userId, load)
</script>

<template>
  <section v-if="data" class="dossier__section">
    <h3>Бридж</h3>

    <p class="kit-muted">
      Подтверждено видов: {{ data.summary.confirmed_count }} · сумма аспектов {{ data.summary.aspects_total }}
      <template v-if="data.display_rank?.type === 'bridge'"> · на сайте показывается звание вместо тира</template>
    </p>

    <div class="bridge-admin__rank">
      <select v-model="rankId" class="kit-select" aria-label="Звание бриджера">
        <option :value="null">Без звания</option>
        <option v-for="r in data.ranks" :key="r.id" :value="r.id">{{ r.label }}</option>
      </select>
      <button
          type="button"
          class="kit-btn kit-btn--primary"
          :disabled="busy || rankId === (data.rank?.id ?? null)"
          @click="saveRank"
      >Сохранить звание</button>
    </div>

    <p v-if="!data.submissions.length" class="kit-muted">Заявок на виды нет</p>

    <template v-if="data.submissions.length">
      <select v-model="selectedId" class="kit-select bridge-admin__picker" aria-label="Вид бриджа">
        <option v-for="s in data.submissions" :key="s.id" :value="s.id">
          {{ s.technique?.label ?? 'Вид удалён' }} — {{ STATUS[s.status] ?? s.status }} · {{ s.total }}/{{ s.max_total }}
        </option>
      </select>

      <div v-if="selected" class="bridge-admin__row">
        <small class="kit-muted">
          <template v-if="selected.reviewer">тестер {{ selected.reviewer.username }} · </template>
          всего {{ selected.total }}/{{ selected.max_total }}
        </small>

        <div class="bridge-admin__fields">
          <label>Статус
            <select v-model="forms[selected.id].status" class="kit-select">
              <option value="declared">на проверке</option>
              <option value="confirmed">подтверждён</option>
              <option value="rejected">отклонён</option>
            </select>
          </label>
          <label>Стабильность
            <input v-model="forms[selected.id].stability" class="kit-input" type="number" min="0" max="100">
          </label>
          <label>Скорость
            <input v-model="forms[selected.id].speed" class="kit-input" type="number" min="0" max="100">
          </label>
          <label>Сложность
            <input v-model="forms[selected.id].difficulty" class="kit-input" type="number" min="0" max="100">
          </label>
          <label>Владение
            <input v-model="forms[selected.id].score" class="kit-input" type="number" min="0" :max="selected.max_score">
          </label>
        </div>

        <div class="bridge-admin__actions">
          <button type="button" class="kit-btn kit-btn--primary" :disabled="busy" @click="save(selected)">Сохранить</button>
          <button type="button" class="kit-btn kit-btn--danger" :disabled="busy" @click="remove(selected)">Удалить заявку</button>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.bridge-admin__rank,
.bridge-admin__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 8px 0;
}

.bridge-admin__picker {
  width: 100%;
  margin-bottom: 8px;
}

.bridge-admin__row {
  padding: 10px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
}

.bridge-admin__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.bridge-admin__fields {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 8px;
  margin-top: 8px;
}

.bridge-admin__fields label {
  display: grid;
  gap: 4px;
  font-size: 12px;
  opacity: 0.85;
}
</style>
