<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

// 1 — email, 2 — код, 3 — ник/пароль
const step = ref(1)

const email = ref('')
const code = ref('')
const username = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const error = ref('')
const info = ref('')
const fieldErrors = ref({})

const codeSentTo = ref('')

const isBusy = computed(() => auth.loading)

function clearErrors() {
  error.value = ''
  fieldErrors.value = {}
}

async function sendCode() {
  clearErrors()
  info.value = ''

  try {
    const data = await auth.sendVerificationCode(email.value)
    codeSentTo.value = data.email ?? email.value
    info.value = data.message ?? 'Код отправлен на почту.'
    step.value = 2
  } catch (e) {
    fieldErrors.value = e.errors || {}
    error.value = e.message || 'Не удалось отправить код.'
  }
}

async function verifyCode() {
  clearErrors()
  info.value = ''

  try {
    await auth.verifyCode(email.value, code.value)
    info.value = 'Email подтверждён.'
    step.value = 3
  } catch (e) {
    fieldErrors.value = e.errors || {}
    error.value = e.message || 'Не удалось подтвердить код.'
  }
}

async function submit() {
  clearErrors()

  try {
    await auth.register(
        username.value,
        password.value,
        passwordConfirmation.value
    )

    router.push('/')
  } catch (e) {
    fieldErrors.value = e.errors || {}
    error.value = e.message || 'Не удалось создать аккаунт.'
  }
}

function changeEmail() {
  clearErrors()
  info.value = ''
  code.value = ''
  auth.resetVerification()
  step.value = 1
}

function resendCode() {
  clearErrors()
  sendCode()
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <div class="auth-logo">
        APEX
      </div>

      <!-- ШАГ 1: EMAIL -->
      <template v-if="step === 1">
        <div class="auth-heading">
          <h1>Создать аккаунт</h1>
          <p>Сначала подтвердим вашу почту</p>
        </div>

        <form @submit.prevent="sendCode">
          <label class="field">
            <span>Email</span>

            <input
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
            >

            <small
                v-if="fieldErrors.email"
                class="field-error"
            >
              {{ fieldErrors.email[0] }}
            </small>
          </label>

          <div
              v-if="error"
              class="auth-error"
          >
            {{ error }}
          </div>

          <button
              class="auth-submit"
              type="submit"
              :disabled="isBusy"
          >
            {{ isBusy ? 'Отправляем...' : 'Отправить код' }}
          </button>
        </form>
      </template>

      <!-- ШАГ 2: КОД -->
      <template v-else-if="step === 2">
        <div class="auth-heading">
          <h1>Введите код</h1>
          <p>Мы отправили код на {{ codeSentTo }}</p>
        </div>

        <form @submit.prevent="verifyCode">
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

            <small
                v-if="fieldErrors.code"
                class="field-error"
            >
              {{ fieldErrors.code[0] }}
            </small>
          </label>

          <div
              v-if="error"
              class="auth-error"
          >
            {{ error }}
          </div>

          <button
              class="auth-submit"
              type="submit"
              :disabled="isBusy"
          >
            {{ isBusy ? 'Проверяем...' : 'Подтвердить' }}
          </button>

          <div class="auth-options auth-options--stack">
            <a
                href="#"
                @click.prevent="resendCode"
            >
              Отправить код ещё раз
            </a>

            <a
                href="#"
                @click.prevent="changeEmail"
            >
              Изменить email
            </a>
          </div>
        </form>
      </template>

      <!-- ШАГ 3: НИК + ПАРОЛЬ -->
      <template v-else>
        <div class="auth-heading">
          <h1>Придумайте ник и пароль</h1>
          <p>{{ codeSentTo }} подтверждён</p>
        </div>

        <form @submit.prevent="submit">
          <label class="field">
            <span>Игровой ник</span>

            <input
                v-model="username"
                type="text"
                autocomplete="username"
                placeholder="YourNickname"
            >

            <small
                v-if="fieldErrors.username"
                class="field-error"
            >
              {{ fieldErrors.username[0] }}
            </small>
          </label>

          <label class="field">
            <span>Пароль</span>

            <input
                v-model="password"
                type="password"
                autocomplete="new-password"
                placeholder="Минимум 8 символов"
            >

            <small
                v-if="fieldErrors.password"
                class="field-error"
            >
              {{ fieldErrors.password[0] }}
            </small>
          </label>

          <label class="field">
            <span>Повторите пароль</span>

            <input
                v-model="passwordConfirmation"
                type="password"
                autocomplete="new-password"
                placeholder="Повторите пароль"
            >
          </label>

          <div
              v-if="error"
              class="auth-error"
          >
            {{ error }}
          </div>

          <button
              class="auth-submit"
              type="submit"
              :disabled="isBusy"
          >
            {{ isBusy ? 'Создаём...' : 'Создать аккаунт' }}
          </button>
        </form>
      </template>

      <div class="auth-footer">
        Уже есть аккаунт?

        <RouterLink to="/login">
          Войти
        </RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.field-error {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #f87171;
}

.auth-options--stack {
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  margin-top: 12px;
}
</style>