<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const mode = ref('pvp')
const saving = ref(false)
const notes = ref('')

const form = ref({
  block_placing: 0,
  rotka: 0,
  movement: 0,
  building: 0,
  ppl: 0,
})

const labels = {
  block_placing: 'БП',
  rotka: 'Ротка',
  movement: 'Мувмент',
  building: 'Строительство',
  ppl: 'Аим',
}

const sum = () => form.value.block_placing + form.value.rotka + form.value.movement
    + form.value.building + form.value.ppl

const percent = () => sum() * 2

const tier = () => {
  const p = percent()
  if (p >= 90) return 'S'
  if (p >= 80) return 'A'
  if (p >= 70) return 'B'
  if (p >= 60) return 'C'
  if (p >= 50) return 'D'
  return 'E'
}

async function submit() {
  saving.value = true
  try {
    await adminApi.conductTierTest(props.user.id, {
      mode: mode.value,
      ...form.value,
      notes: notes.value || null,
    })
    emit('updated')
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
          <button :class="{ active: mode === 'pvp' }" @click="mode = 'pvp'">
            PvP
          </button>
          <button :class="{ active: mode === 'bedwars' }" @click="mode = 'bedwars'">
            BedWars
          </button>
        </div>

        <div class="aspects-grid">
          <label
              v-for="(label, key) in labels"
              :key="key"
              class="aspect-input"
          >
            <span class="label">{{ label }}</span>
            <input
                v-model.number="form[key]"
                type="range"
                min="0"
                max="10"
                class="slider"
            />
            <input
                v-model.number="form[key]"
                type="number"
                min="0"
                max="10"
                class="value"
            />
          </label>
        </div>

        <textarea
            v-model="notes"
            rows="2"
            placeholder="Заметки тестера..."
            class="notes"
        />

        <!-- Итог -->
        <div class="result">
          <div class="result__block">
            <span class="result__label">Балл</span>
            <span class="result__value">{{ sum() }} / 50</span>
          </div>
          <div class="result__block">
            <span class="result__label">Процент</span>
            <span class="result__value accent">{{ percent() }}%</span>
          </div>
          <div class="result__block">
            <span class="result__label">Тир</span>
            <span class="result__value tier" :class="`tier-${tier()}`">
                            {{ tier() }}
                        </span>
          </div>
        </div>
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
</style>
