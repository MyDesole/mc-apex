<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const role = ref(props.user.role)
const loading = ref(false)

const roles = [
  { value: 'user', label: 'Пользователь', desc: 'Обычный игрок' },
  { value: 'media', label: 'Медийка', desc: 'Ютубер или стример: участвует в топах, особый вид профиля' },
  { value: 'tester', label: 'Тестер', desc: 'Проводит тир-тесты' },
  { value: 'bridge_tester', label: 'Бридж-тестер', desc: 'Проверяет виды бриджа' },
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
@import "@/components/admin/AdminRoleModal.css";
</style>
