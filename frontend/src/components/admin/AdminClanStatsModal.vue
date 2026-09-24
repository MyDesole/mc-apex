<script setup>
import { computed, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const mode = ref('delta')        // 'delta' | 'set'
const winsDelta = ref(0)
const lossesDelta = ref(0)
const winsSet = ref(props.clan.wins)
const lossesSet = ref(props.clan.losses)
const reason = ref('')
const loading = ref(false)
const error = ref('')

const previewWins = computed(() => {
  if (mode.value === 'set') return winsSet.value
  return Math.max(0, props.clan.wins + winsDelta.value)
})

const previewLosses = computed(() => {
  if (mode.value === 'set') return lossesSet.value
  return Math.max(0, props.clan.losses + lossesDelta.value)
})

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const payload = { reason: reason.value || null }

    if (mode.value === 'set') {
      payload.wins = winsSet.value
      payload.losses = lossesSet.value
      await adminApi.setClanStats(props.clan.id, payload)
    } else {
      payload.wins = winsDelta.value
      payload.losses = lossesDelta.value
      await adminApi.clanStats(props.clan.id, payload)
    }

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>
          Статистика
          <span class="tag">[{{ clan.tag }}]</span>
          {{ clan.name }}
        </h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Текущее -->
        <div class="current">
          <div class="current__block">
            <span class="current__label">Побед сейчас</span>
            <span class="current__value win">{{ clan.wins }}</span>
          </div>
          <div class="current__block">
            <span class="current__label">Поражений сейчас</span>
            <span class="current__value loss">{{ clan.losses }}</span>
          </div>
          <div class="current__block">
            <span class="current__label">Сила</span>
            <span class="current__value power">{{ clan.power }}</span>
          </div>
        </div>

        <!-- Режим -->
        <div class="mode-switch">
          <button
              :class="{ active: mode === 'delta' }"
              @click="mode = 'delta'"
          >
            Прибавить / убавить
          </button>
          <button
              :class="{ active: mode === 'set' }"
              @click="mode = 'set'"
          >
            Установить точное
          </button>
        </div>

        <!-- Delta -->
        <div v-if="mode === 'delta'" class="grid">
          <div class="field">
            <label>Победы (дельта)</label>
            <div class="delta">
              <button
                  class="delta__btn"
                  :disabled="winsDelta <= -999"
                  @click="winsDelta--"
              >−</button>
              <input
                  v-model.number="winsDelta"
                  type="number"
                  min="-999"
                  max="999"
              />
              <button
                  class="delta__btn"
                  :disabled="winsDelta >= 999"
                  @click="winsDelta++"
              >+</button>
            </div>
          </div>

          <div class="field">
            <label>Поражения (дельта)</label>
            <div class="delta">
              <button
                  class="delta__btn"
                  :disabled="lossesDelta <= -999"
                  @click="lossesDelta--"
              >−</button>
              <input
                  v-model.number="lossesDelta"
                  type="number"
                  min="-999"
                  max="999"
              />
              <button
                  class="delta__btn"
                  :disabled="lossesDelta >= 999"
                  @click="lossesDelta++"
              >+</button>
            </div>
          </div>
        </div>

        <!-- Set -->
        <div v-else class="grid">
          <div class="field">
            <label>Победы</label>
            <input
                v-model.number="winsSet"
                type="number"
                min="0"
                max="100000"
            />
          </div>

          <div class="field">
            <label>Поражения</label>
            <input
                v-model.number="lossesSet"
                type="number"
                min="0"
                max="100000"
            />
          </div>
        </div>

        <!-- Причина -->
        <div class="field">
          <label>Причина (опционально)</label>
          <input
              v-model="reason"
              type="text"
              maxlength="255"
              placeholder="Например: правка ошибки, ручная корректировка"
          />
        </div>

        <!-- Превью -->
        <div class="preview">
          <div class="preview__title">Итог после сохранения</div>
          <div class="preview__row">
            <span class="preview__label">Победы:</span>
            <span class="preview__value">
                            {{ clan.wins }}
                            <span class="arrow">→</span>
                            <b class="win">{{ previewWins }}</b>
                        </span>
          </div>
          <div class="preview__row">
            <span class="preview__label">Поражения:</span>
            <span class="preview__value">
                            {{ clan.losses }}
                            <span class="arrow">→</span>
                            <b class="loss">{{ previewLosses }}</b>
                        </span>
          </div>
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="loading" @click="submit">
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
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
  gap: 12px;
}

.modal-head h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: baseline;
}

.tag {
  color: var(--accent-light);
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
  gap: 16px;
}

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

/* Текущее */
.current {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.current__block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.current__label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.4px;
}

.current__value {
  font-size: 18px;
  font-weight: 900;
}

.current__value.win { color: #4ade80; }
.current__value.loss { color: #f87171; }
.current__value.power { color: #a78bfa; }

/* Переключатель режима */
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
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.mode-switch button.active {
  color: #fff;
  background: var(--accent);
}

/* Поля */
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input[type='number'],
.field input[type='text'] {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
}

.field input:focus {
  border-color: var(--accent);
}

/* Delta */
.delta {
  display: flex;
  align-items: stretch;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  overflow: hidden;
}

.delta input {
  flex: 1;
  text-align: center;
  border: 0 !important;
  background: transparent !important;
  border-radius: 0 !important;
}

.delta__btn {
  width: 40px;
  background: rgba(255, 255, 255, 0.03);
  color: var(--text);
  border: 0;
  font-size: 16px;
  font-weight: 900;
  cursor: pointer;
  transition: background 0.15s;
}

.delta__btn:hover:not(:disabled) {
  background: rgba(124, 58, 237, 0.15);
  color: var(--accent-light);
}

.delta__btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

/* Превью */
.preview {
  padding: 12px 14px;
  background: rgba(124, 58, 237, 0.05);
  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 10px;
}

.preview__title {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.4px;
  margin-bottom: 8px;
}

.preview__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  margin-bottom: 4px;
}

.preview__row:last-child {
  margin-bottom: 0;
}

.preview__label {
  color: var(--text-dim);
}

.preview__value {
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
}

.arrow {
  color: var(--text-muted);
  font-size: 12px;
}

.preview__value b {
  font-weight: 900;
}

.preview__value b.win { color: #4ade80; }
.preview__value b.loss { color: #f87171; }

/* Футер */
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

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 500px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>