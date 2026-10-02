<script setup>
import { ref } from 'vue'
import { authApi } from '@/services/auth/auth.js'
import { useAuthStore } from '@/stores/core/auth.js'

const auth = useAuthStore()
const sending = ref(false)
const sent = ref(false)

async function resend() {
  sending.value = true
  try {
    await authApi.resendVerification()
    sent.value = true
    setTimeout(() => sent.value = false, 5000)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div v-if="auth.user && !auth.user.email_verified_at" class="verify-banner">
    <div class="verify-banner__icon">📧</div>

    <div class="verify-banner__text">
      <div class="verify-banner__title">Подтвердите email</div>
      <div class="verify-banner__sub">
        Мы отправили письмо на <b>{{ auth.user.email }}</b>.
        Проверь почту и перейди по ссылке.
      </div>
    </div>

    <button
        class="verify-banner__btn"
        :disabled="sending || sent"
        @click="resend"
    >
      {{ sending ? '...' : sent ? '✓ Отправлено' : 'Отправить снова' }}
    </button>
  </div>
</template>

<style scoped>
@import "@/components/notifications/VerifyEmailBanner.css";
</style>
