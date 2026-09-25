<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/services/api.js'

const route = useRoute()
const status = ref('loading') // loading | success | error
const message = ref('')

onMounted(async () => {
  try {
    // URL уже подписан Laravel, просто делаем запрос
    const url = `/email/verify/${route.params.id}/${route.params.hash}?${new URLSearchParams(route.query).toString()}`
    await fetch(`/api${url}`, { credentials: 'include' })

    status.value = 'success'
    message.value = 'Email успешно подтверждён!'
  } catch (e) {
    status.value = 'error'
    message.value = 'Не удалось подтвердить email. Ссылка недействительна.'
  }
})
</script>

<template>
  <main class="auth-page">
    <section class="auth-card" style="text-align: center;">
      <div class="auth-logo">APEX</div>

      <div v-if="status === 'loading'">
        <h1>Проверяем ссылку...</h1>
      </div>

      <div v-else-if="status === 'success'">
        <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
        <h1>{{ message }}</h1>
        <RouterLink to="/login" class="auth-submit" style="display: inline-block; margin-top: 20px;">
          Войти
        </RouterLink>
      </div>

      <div v-else>
        <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
        <h1>{{ message }}</h1>
      </div>
    </section>
  </main>
</template>