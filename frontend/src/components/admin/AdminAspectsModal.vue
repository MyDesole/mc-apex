<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

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

async function loadAspect() {
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
.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 520px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
}

.modal-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

.body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.mode-switch {
  display: flex;
  gap: 6px;
  padding: 4px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.mode-switch button {
  flex: 1;
  padding: 9px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.mode-switch button.active {
  color: #fff;
  background: var(--accent);
}

.aspects-grid {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.aspect-input {
  display: grid;
  grid-template-columns: 110px 1fr 50px;
  align-items: center;
  gap: 12px;
}

.label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-dim);
}

.slider {
  width: 100%;
  accent-color: var(--accent);
  cursor: pointer;
}

.value {
  width: 100%;
  padding: 6px 8px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 8px;
  text-align: center;
  font-weight: 800;
  outline: none;
}

.value:focus {
  border-color: var(--accent);
}

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel,
.btn-save {
  min-height: 40px;
  padding: 0 18px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-save {
  color: #fff;
  background: var(--accent);
}
</style>