<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const reason = ref('')
const until = ref('')
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
    await adminApi.ban(props.user.id, {
      reason: reason.value,
      until: until.value || null,
    })
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
        <h2>Забанить {{ user.username }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <label class="field">
          <span>Причина бана</span>
          <textarea v-model="reason" rows="3" placeholder="За что баним..." />
        </label>

        <label class="field">
          <span>Забанить до (опционально)</span>
          <input v-model="until" type="datetime-local" />
        </label>

        <p class="hint">
          Если не указать дату — бан будет бессрочным.
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
@import "@/components/admin/AdminBanModal.css";
</style>
