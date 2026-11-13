<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/services/core/api.js'

const route = useRoute()
const status = ref('loading') // loading | success | error
const message = ref('')

onMounted(async () => {
  const { id, hash, ...signature } = { ...route.query, ...route.params }

  // Без id и подписи подтверждать нечего: страницу открыли не из письма
  if (!id || !hash) {
    status.value = 'error'
    message.value = 'Ссылка неполная. Откройте её из письма ещё раз или запросите новое письмо в профиле.'
    return
  }

  try {
    // URL уже подписан Laravel, просто делаем запрос
    const url = `/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}?${new URLSearchParams(signature).toString()}`
    const response = await fetch(`/api${url}`, { credentials: 'include', headers: { Accept: 'application/json' } })

    // Раньше ответ не проверялся: «успешно» показывалось и на просроченную ссылку
    if (!response.ok) throw new Error('verify failed')

    status.value = 'success'
    message.value = 'Почта подтверждена!'
  } catch {
    status.value = 'error'
    message.value = 'Не удалось подтвердить почту: ссылка устарела или уже использована. Запросите новое письмо в профиле.'
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
        <h1 style="font-size: 18px; line-height: 1.45;">{{ message }}</h1>
        <RouterLink to="/" class="auth-submit" style="display: inline-block; margin-top: 20px;">
          На главную
        </RouterLink>
      </div>
    </section>
  </main>
</template>