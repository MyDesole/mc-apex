<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const error = ref('')
const fieldErrors = ref({})

async function submit() {
  error.value = ''
  fieldErrors.value = {}

  try {
    await auth.forgotPassword(email.value)
    router.push({ name: 'reset-password', query: { email: email.value } })
  } catch (e) {
    fieldErrors.value = e.errors || {}
    error.value = e.message || 'Не удалось отправить код.'
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <div class="auth-logo">APEX</div>

      <div class="auth-heading">
        <h1>Восстановление пароля</h1>
        <p>Мы отправим код на вашу почту</p>
      </div>

      <form @submit.prevent="submit">
        <label class="field">
          <span>Email</span>
          <input
              v-model="email"
              type="email"
              autocomplete="email"
              placeholder="you@example.com"
          >
          <small v-if="fieldErrors.email" class="field-error">
            {{ fieldErrors.email[0] }}
          </small>
        </label>

        <div v-if="error" class="auth-error">{{ error }}</div>

        <button class="auth-submit" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Отправляем...' : 'Отправить код' }}
        </button>
      </form>

      <div class="auth-footer">
        Вспомнили пароль?
        <RouterLink to="/login">Войти</RouterLink>
      </div>
    </section>
  </main>
</template>