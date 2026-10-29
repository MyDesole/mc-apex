<script setup>
/**
 * «Поделиться в чате»: открывает сообщения, выбранный диалог получает
 * ссылку в поле ввода, а в ленте она станет живой карточкой.
 */
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  // Путь страницы сайта: /players/5, /clans/2, /auction?lot=7
  path: { type: String, required: true },
  label: { type: String, default: '' },
})

const router = useRouter()
const auth = useAuthStore()

function share() {
  router.push({ path: '/messages', query: { share: props.path } })
}
</script>

<template>
  <button
      v-if="auth.isAuthenticated"
      type="button"
      class="share-chat"
      title="Поделиться в сообщениях"
      aria-label="Поделиться в сообщениях"
      @click.stop="share"
  >
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M15 17l5-5-5-5" />
      <path d="M4 18v-2a4 4 0 0 1 4-4h12" />
    </svg>
    <span v-if="label">{{ label }}</span>
  </button>
</template>

<style scoped>
.share-chat {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 34px;
  height: 34px;
  padding: 0 9px;

  font: inherit;
  font-size: 13px;
  font-weight: 700;
  color: inherit;

  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 6px;

  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.share-chat:hover {
  background: rgba(167, 139, 250, 0.16);
  border-color: rgba(167, 139, 250, 0.5);
}
</style>
