<script setup>
/**
 * Админ проводит тир-тест вручную: оценки режима, заметка — и тест
 * сразу записывается завершённым (TierTestService::conductManually).
 *
 * Раньше окно слало старые поля (building, ppl) со шкалой 0–10 и
 * считало тир по своим порогам — сервер такой запрос отклонял.
 * Теперь поля и подписи из data/players/aspects.js, шкала 0–20,
 * пороги как в TierTestService::TIER_THRESHOLDS.
 */
import { computed, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import { ASPECTS, ASPECT_MAX } from '@/data/players/aspects.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const THRESHOLDS = [['A', 71], ['B', 56], ['C', 41], ['D', 21]]

const mode = ref('pvp')
const saving = ref(false)
const notes = ref('')
const error = ref('')
const form = ref(emptyForm('pvp'))

const fields = computed(() => ASPECTS[mode.value])
const percent = computed(() => fields.value.reduce((sum, f) => sum + (Number(form.value[f.key]) || 0), 0))
const tier = computed(() => THRESHOLDS.find(([, min]) => percent.value >= min)?.[0] ?? 'E')

function emptyForm(m) {
  return Object.fromEntries(ASPECTS[m].map((f) => [f.key, 0]))
}

function switchMode(next) {
  mode.value = next
  form.value = emptyForm(next)
}

async function submit() {
  saving.value = true
  error.value = ''

  try {
    const aspects = Object.fromEntries(
        fields.value.map((f) => [f.key, Math.max(0, Math.min(ASPECT_MAX, Number(form.value[f.key]) || 0))]),
    )

    await adminApi.conductTierTest(props.user.id, {
      mode: mode.value,
      aspects,
      notes: notes.value.trim() || null,
    })

    emit('updated')
    emit('close')
  } catch (e) {
    error.value = e.message || 'Не удалось провести тест'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Тир-тест для {{ user.username }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div class="mode-switch">
          <button :class="{ active: mode === 'pvp' }" @click="switchMode('pvp')">PvP</button>
          <button :class="{ active: mode === 'bedwars' }" @click="switchMode('bedwars')">BedWars</button>
        </div>

        <div class="aspects-grid">
          <label v-for="f in fields" :key="f.key" class="aspect-input">
            <span class="label">{{ f.label }}</span>
            <input v-model.number="form[f.key]" type="range" min="0" :max="ASPECT_MAX" class="slider" />
            <input v-model.number="form[f.key]" type="number" min="0" :max="ASPECT_MAX" class="value" />
          </label>
        </div>

        <textarea v-model="notes" rows="2" placeholder="Заметки тестера..." class="notes" />

        <!-- Итог -->
        <div class="result">
          <div class="result__block">
            <span class="result__label">Процент</span>
            <span class="result__value accent">{{ percent }}%</span>
          </div>
          <div class="result__block">
            <span class="result__label">Тир</span>
            <span class="result__value tier" :class="`tier-${tier}`">{{ tier }}</span>
          </div>
        </div>

        <p v-if="error" class="test-error">{{ error }}</p>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="saving" @click="submit">
          {{ saving ? '...' : 'Завершить тест' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminTierTestModal.css";

.test-error {
  margin: 10px 0 0;
  color: #fca5a5;
  font-size: 13px;
}
</style>
