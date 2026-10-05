<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// Код приглашения из ссылки ?ref=CODE
const inviteCode = ref(null)

onMounted(() => {
  const code = route.query.ref

  if (code) {
    inviteCode.value = String(code).trim().toUpperCase()
    auth.setReferralCode(inviteCode.value)
  }
})

// 1 — email, 2 — код, 3 — ник/пароль
const step = ref(1)

const email = ref('')
const code = ref('')
const username = ref('')
const password = ref('')
const passwordConfirmation = ref('')

// Кем играет новичок: сразу задаёт вид профиля
const profileMode = ref('pvp')

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
        passwordConfirmation.value,
        profileMode.value,
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

        <div v-if="inviteCode" class="invite-note">
          Тебя пригласили по коду <b>{{ inviteCode }}</b> — после регистрации получишь стартовый бонус ApexCoin.
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

          <!-- Кем играет новичок: сразу задаёт вид профиля -->
          <div class="play-style">
            <span class="play-style__title">Во что играешь?</span>

            <div class="play-style__options">
              <button
                  type="button"
                  class="play-style__option"
                  :class="{ 'play-style__option--active': profileMode === 'pvp' }"
                  @click="profileMode = 'pvp'"
              >
                <span class="play-style__name">PvP</span>
                <span class="play-style__desc">Аспекты, тиры, тир-тесты</span>
              </button>

              <button
                  type="button"
                  class="play-style__option"
                  :class="{ 'play-style__option--active': profileMode === 'bridge' }"
                  @click="profileMode = 'bridge'"
              >
                <span class="play-style__name">Бридж</span>
                <span class="play-style__desc">Виды бриджа и звание бриджера</span>
              </button>
            </div>

            <span class="play-style__hint">
              Профиль можно сменить в любой момент в настройках
            </span>
          </div>

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

.invite-note {
  margin-bottom: 16px;
  padding: 10px 14px;
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.1);
  border: 1px solid rgba(99, 102, 241, 0.35);
  border-radius: 10px;
  font-size: 13px;
  line-height: 1.5;
}

.invite-note b { color: #fff; }

.auth-options--stack {
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  margin-top: 12px;
}

/* ---------------- Стиль игры при регистрации ---------------- */

.play-style {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.play-style__title {
  color: rgba(255, 255, 255, 0.55);

  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.play-style__options {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 9px;
}

.play-style__option {
  display: flex;
  flex-direction: column;
  gap: 2px;

  padding: 11px 13px;

  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 11px;

  background: rgba(255, 255, 255, 0.03);

  text-align: left;
  cursor: pointer;

  transition: border-color 0.16s ease, background 0.16s ease;
}

.play-style__option:hover {
  border-color: rgba(139, 92, 246, 0.4);
}

.play-style__option--active {
  border-color: rgba(139, 92, 246, 0.65);

  background: rgba(139, 92, 246, 0.15);
}

.play-style__name {
  color: var(--text, #e5e5eb);

  font-size: 14px;
  font-weight: 900;
}

.play-style__desc {
  color: rgba(255, 255, 255, 0.42);

  font-size: 11px;
}

.play-style__hint {
  color: rgba(255, 255, 255, 0.32);

  font-size: 11px;
}

</style>