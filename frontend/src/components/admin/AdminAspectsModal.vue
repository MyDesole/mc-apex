<script setup>
/**
 * Правка аспектов игрока админом.
 *
 * Раньше окно слало старые поля (building, ppl) со шкалой 0–10 —
 * сервер их не принимал, и сохранение всегда падало; текущие значения
 * тоже не подгружались. Теперь: подписи из data/players/aspects.js,
 * шкала 0–20, значения игрока с сервера и итоговый процент.
 *
 * У эндпоинта свой исторический формат: для BedWars поля приходят
 * под именами PvP-набора (см. UpdateUserAspectsRequest) — FIELD_MAP.
 */
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import { ASPECTS, ASPECT_MAX } from '@/data/players/aspects.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

/** Колонка режима → имя поля в запросе. */
const FIELD_MAP = {
  pvp: { block_placing: 'block_placing', rotka: 'rotka', movement: 'movement', aim: 'aim', game_sense: 'game_sense' },
  bedwars: { pvp: 'block_placing', bed_play: 'rotka', teamplay: 'movement', building: 'aim', game_sense: 'game_sense' },
}

const mode = ref('pvp')
const loading = ref(true)
const saving = ref(false)
const error = ref('')

// Текущие значения обоих режимов с сервера
const current = ref({ pvp: {}, bedwars: {} })
const form = ref({})

const fields = computed(() => ASPECTS[mode.value])
const total = computed(() => fields.value.reduce((sum, f) => sum + (Number(form.value[f.key]) || 0), 0))

function fillForm() {
  const source = current.value[mode.value] || {}
  form.value = Object.fromEntries(fields.value.map((f) => [f.key, Number(source[f.key] ?? 0)]))
}

function switchMode(next) {
  mode.value = next
  fillForm()
}

async function submit() {
  saving.value = true
  error.value = ''

  try {
    const payload = { mode: mode.value }

    for (const [column, field] of Object.entries(FIELD_MAP[mode.value])) {
      payload[field] = Math.max(0, Math.min(ASPECT_MAX, Number(form.value[column]) || 0))
    }

    await adminApi.updateAspects(props.user.id, payload)
    emit('updated')
    emit('close')
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить аспекты'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const data = await adminApi.user(props.user.id)
    current.value = {
      pvp: data.user?.aspects?.pvp ?? {},
      bedwars: data.user?.aspects?.bedwars ?? {},
    }
  } catch {
    /* без текущих значений стартуем с нулей */
  } finally {
    fillForm()
    loading.value = false
  }
})
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Аспекты {{ user.username }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div class="mode-switch">
          <button :class="{ active: mode === 'pvp' }" @click="switchMode('pvp')">PvP</button>
          <button :class="{ active: mode === 'bedwars' }" @click="switchMode('bedwars')">BedWars</button>
        </div>

        <p v-if="loading" class="aspects-hint">Загружаем текущие оценки…</p>

        <div v-else class="aspects-grid">
          <label v-for="f in fields" :key="f.key" class="aspect-input">
            <span class="label">{{ f.label }}</span>
            <input v-model.number="form[f.key]" type="range" min="0" :max="ASPECT_MAX" class="slider" />
            <input v-model.number="form[f.key]" type="number" min="0" :max="ASPECT_MAX" class="value" />
          </label>
        </div>

        <p class="aspects-hint">
          Итого: <b>{{ total }}%</b> — по сумме пересчитаются тир, очки рейтинга и сила клана.
        </p>
        <p v-if="error" class="aspects-error">{{ error }}</p>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="saving || loading" @click="submit">
          {{ saving ? '...' : 'Сохранить' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminAspectsModal.css";

.aspects-hint {
  margin: 12px 0 0;
  color: var(--text-muted);
  font-size: 13px;
}

.aspects-hint b {
  color: var(--text);
}

.aspects-error {
  margin: 8px 0 0;
  color: #fca5a5;
  font-size: 13px;
}
</style>
