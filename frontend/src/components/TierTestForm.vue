<script setup>
import { ref } from 'vue'
import { tierTestsApi } from '@/services/tierTests.js'

const emit = defineEmits(['created'])

const open = ref(false)
const loading = ref(false)
const mode = ref('pvp')
const scheduledAt = ref('')
const notes = ref('')
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''

  try {
    await tierTestsApi.create({
      mode: mode.value,
      scheduled_at: scheduledAt.value || null,
      notes: notes.value || null,
    })

    open.value = false
    mode.value = 'pvp'
    scheduledAt.value = ''
    notes.value = ''
    emit('created')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <button class="btn-open" @click="open = true">
    + Записаться на тир-тест
  </button>

  <div v-if="open" class="modal-bg" @click.self="open = false">
    <div class="modal">
      <h2>Запись на тир-тест</h2>

      <div v-if="error" class="error">{{ error }}</div>

      <label class="field">
        <span>Режим</span>
        <select v-model="mode">
          <option value="pvp">PvP (p-ранг)</option>
          <option value="bedwars">BedWars (b-ранг)</option>
        </select>
      </label>

      <label class="field">
        <span>Желаемое время (опционально)</span>
        <input v-model="scheduledAt" type="datetime-local" />
      </label>

      <label class="field">
        <span>Комментарий</span>
        <textarea v-model="notes" rows="3" placeholder="Что хочешь показать?" />
      </label>

      <div class="modal-actions">
        <button class="btn-cancel" @click="open = false">Отмена</button>
        <button class="btn-submit" :disabled="loading" @click="submit">
          {{ loading ? 'Отправка...' : 'Записаться' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-open {
  min-height: 40px;
  padding: 0 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
  transition: all 0.2s;
}

.btn-open:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
}

.modal {
  width: 100%;
  max-width: 480px;
  padding: 28px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
}

.modal h2 {
  margin: 0 0 20px;
  font-size: 20px;
  font-weight: 800;
}

.field {
  display: block;
  margin-bottom: 16px;
}

.field span {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  padding: 11px 13px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  outline: none;
  font: inherit;
  resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

.error {
  margin-bottom: 14px;
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
}

.btn-cancel {
  min-height: 38px;
  padding: 0 16px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

.btn-submit {
  min-height: 38px;
  padding: 0 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>