<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/admin.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const role = ref(props.user.role)
const loading = ref(false)

const roles = [
  { value: 'user', label: 'Пользователь', desc: 'Обычный игрок' },
  { value: 'tester', label: 'Тестер', desc: 'Проводит тир-тесты' },
  { value: 'moderator', label: 'Модератор', desc: 'Следит за контентом' },
  { value: 'admin', label: 'Администратор', desc: 'Полный доступ' },
]

async function submit() {
  loading.value = true
  try {
    await adminApi.setRole(props.user.id, role.value)
    emit('updated')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Роль для {{ user.username }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <label
            v-for="r in roles"
            :key="r.value"
            class="role-option"
            :class="{ active: role === r.value }"
        >
          <input
              v-model="role"
              type="radio"
              :value="r.value"
              name="role"
          />
          <div>
            <div class="role-label">{{ r.label }}</div>
            <div class="role-desc">{{ r.desc }}</div>
          </div>
        </label>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="loading" @click="submit">
          {{ loading ? '...' : 'Сохранить' }}
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
  gap: 8px;
}

.role-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
}

.role-option:hover {
  border-color: var(--border-hover);
}

.role-option.active {
  border-color: var(--accent);
  background: rgba(124, 58, 237, 0.05);
}

.role-option input {
  accent-color: var(--accent);
  cursor: pointer;
}

.role-label {
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 2px;
}

.role-desc {
  font-size: 12px;
  color: var(--text-dim);
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

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>