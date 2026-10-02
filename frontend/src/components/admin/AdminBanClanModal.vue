<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

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
@import "@/components/admin/AdminBanClanModal.css";
</style>
