<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref(route.query.email ?? '')
const code = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const error = ref('')
const info = ref('')
const fieldErrors = ref({})

async function submit() {
  error.value = ''
  info.value = ''
  fieldErrors.value = {}

  try {
    const data = await auth.resetPassword(
        email.value,
        code.value,
        password.value,
        passwordConfirmation.value
    )
    info.value = data.message ?? 'Пароль обновлён.'
    setTimeout(() => router.push('/login'), 1200)
  } catch (e) {
    fieldErrors.value = e.errors || {}
    error.value = e.message || 'Не удалось сбросить пароль.'
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <div class="auth-logo">APEX</div>

      <div class="auth-heading">
        <h1>Новый пароль</h1>
        <p>Введите код из письма и новый пароль</p>
      </div>

      <form @submit.prevent="submit">
        <label class="field">
          <span>Email</span>
          <input v-model="email" type="email" autocomplete="email">
          <small v-if="fieldErrors.email" class="field-error">
            {{ fieldErrors.email[0] }}
          </small>
        </label>

        <label class="field">
          <span>Код из письма</span>
          <input
              v-model="code"
              type="text"
              inputmode="numeric"
              maxlength="6"
              autocomplete="one-time-code"
              placeholder="000000"
          >
          <small v-if="fieldErrors.code" class="field-error">
            {{ fieldErrors.code[0] }}
          </small>
        </label>

        <label class="field">
          <span>Новый пароль</span>
          <input
              v-model="password"
              type="password"
              autocomplete="new-password"
              placeholder="Минимум 8 символов"
          >
          <small v-if="fieldErrors.password" class="field-error">
            {{ fieldErrors.password[0] }}
          </small>
        </label>

        <label class="field">
          <span>Повторите пароль</span>
          <input
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
          >
        </label>

        <div v-if="error" class="auth-error">{{ error }}</div>
        <div v-if="info" class="auth-info">{{ info }}</div>

        <button class="auth-submit" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Сохраняем...' : 'Сбросить пароль' }}
        </button>
      </form>

      <div class="auth-footer">
        <RouterLink to="/forgot-password">Отправить код ещё раз</RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-info {
  margin-top: 12px;
  padding: 10px 12px;
  border: 1px solid #1f3d2b;
  background: #10231a;
  color: #6ee7a8;
  border-radius: 8px;
  font-size: 13px;
}

</style>