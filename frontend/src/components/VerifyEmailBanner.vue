<script setup>
import { ref } from 'vue'
import { authApi } from '@/services/auth.js'
import { useAuthStore } from '@/stores/auth'

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
.verify-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  margin-bottom: 20px;
  background: rgba(251, 191, 36, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.25);
  border-radius: 12px;
}

.verify-banner__icon {
  font-size: 24px;
  flex-shrink: 0;
}

.verify-banner__text {
  flex: 1;
  min-width: 0;
}

.verify-banner__title {
  font-size: 14px;
  font-weight: 800;
  color: #fbbf24;
  margin-bottom: 2px;
}

.verify-banner__sub {
  font-size: 12px;
  color: var(--text-dim);
}

.verify-banner__sub b {
  color: var(--text);
}

.verify-banner__btn {
  padding: 8px 14px;
  color: #000;
  background: #fbbf24;
  border: 0;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}

.verify-banner__btn:hover:not(:disabled) {
  background: #fcd34d;
}

.verify-banner__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>