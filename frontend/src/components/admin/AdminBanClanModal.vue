<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/admin.js'

const props = defineProps({ clan: Object })
const emit = defineEmits(['close', 'updated'])

const reason = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  if (!reason.value.trim()) {
    error.value = 'Укажи причину бана'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await adminApi.banClan(props.clan.id, reason.value)
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
          Забанить
          <span class="tag">[{{ clan.tag }}]</span>
          {{ clan.name }}
        </h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <label class="field">
          <span>Причина бана</span>
          <textarea
              v-model="reason"
              rows="3"
              placeholder="За что баним клан..."
          />
        </label>

        <p class="hint">
          Забаненный клан скрывается из списка и топа, но не удаляется.
        </p>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-danger" :disabled="loading" @click="submit">
          {{ loading ? '...' : 'Забанить' }}
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
  max-width: 480px;
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

.field span {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field textarea {
  width: 100%;
  padding: 11px 13px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.field textarea:focus {
  border-color: var(--accent);
}

.hint {
  margin: 0;
  color: var(--text-muted);
  font-size: 12px;
}

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel,
.btn-danger {
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

.btn-danger {
  color: #fff;
  background: var(--danger);
}

.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>