<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const mode = ref('pvp')
const loading = ref(false)
const saving = ref(false)

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

function loadAspect() {
  const existing = (props.user.aspects || []).find(a => a.mode === mode.value)

  if (existing) {
    form.value = {
      block_placing: existing.block_placing,
      rotka: existing.rotka,
      movement: existing.movement,
      building: existing.building,
      ppl: existing.ppl,
    }
  } else {
    form.value = { block_placing: 0, rotka: 0, movement: 0, building: 0, ppl: 0 }
  }
}

async function submit() {
  saving.value = true
  try {
    await adminApi.updateAspects(props.user.id, {
      mode: mode.value,
      ...form.value,
    })
    emit('updated')
  } finally {
    saving.value = false
  }
}

function onModeChange() {
  loadAspect()
}

onMounted(loadAspect)
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
          <button
              :class="{ active: mode === 'pvp' }"
              @click="mode = 'pvp'; onModeChange()"
          >
            PvP
          </button>
          <button
              :class="{ active: mode === 'bedwars' }"
              @click="mode = 'bedwars'; onModeChange()"
          >
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
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="saving" @click="submit">
          {{ saving ? '...' : 'Сохранить' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminAspectsModal.css";
</style>
