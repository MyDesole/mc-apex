<script setup>
/**
 * Плавающий чат тестируемого с тестером.
 *
 * Появляется, как только игрок подал заявку на тир-тест, и держится на
 * экране заметной карточкой, пока заявка активна. Когда тестер пишет —
 * карточка раскрывается сама. Писать игрок сможет только после первого
 * сообщения тестера (это решает сервер, окно просто показывает причину).
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import { tierChatApi } from '@/services/tiers/tierChat.js'
import TierTestChat from '@/components/tiers/TierTestChat.vue'

const auth = useAuthStore()

const POLL_MS = 15000

const chats = ref([])
const open = ref(false)
let timer = null
let lastUnread = 0

// Здесь — только чат игрока; чаты тестера живут в панели тестера
const chat = computed(() => chats.value.find((c) => c.role === 'player') ?? null)
const unread = computed(() => chat.value?.unread ?? 0)
const waiting = computed(() => chat.value?.status === 'pending')

async function poll() {
  if (!auth.isAuthenticated || document.hidden) return

  try {
    const { chats: list } = await tierChatApi.active()

    chats.value = list ?? []
  } catch {
    // Сеть моргнула — в следующий раз
  }
}

// Тестер написал — раскрываем окно: игрок должен заметить
watch(unread, (now) => {
  if (now > lastUnread && !open.value) open.value = true
  lastUnread = now
})

watch(() => auth.isAuthenticated, (on) => {
  if (on) poll()
  else chats.value = []
})

function markRead() {
  if (chat.value) chat.value.unread = 0
  lastUnread = 0
}

onMounted(() => {
  poll()
  timer = setInterval(poll, POLL_MS)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <Teleport to="body">
    <div v-if="chat" class="tt-dock" :class="{ 'is-open': open, 'has-unread': unread > 0 }">
      <!-- Развёрнутое окно -->
      <div v-if="open" class="tt-dock__panel">
        <button class="tt-dock__close" type="button" aria-label="Свернуть" @click="open = false">
          ×
        </button>

        <TierTestChat
          v-if="!waiting"
          :test-id="chat.tier_test_id"
          @unread-cleared="markRead"
        />

        <div v-else class="tt-dock__wait">
          <div class="tt-dock__wait-title">
            Заявка ждёт тестера
          </div>
          <p>
            Как только тестер возьмёт вашу заявку, здесь откроется чат.
            Тестер напишет первым — вы получите письмо и уведомление на сайте.
          </p>
        </div>
      </div>

      <!-- Заметная карточка -->
      <button v-else class="tt-dock__card" type="button" @click="open = true">
        <span class="tt-dock__glow" aria-hidden="true" />
        <span class="tt-dock__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
        </span>
        <span class="tt-dock__text">
          <span class="tt-dock__title">Чат с тестером</span>
          <span class="tt-dock__sub">
            <template v-if="waiting">Ждём, пока тестер возьмёт заявку</template>
            <template v-else-if="unread">Новое сообщение от {{ chat.other?.username || 'тестера' }}</template>
            <template v-else>Тест в работе: {{ chat.other?.username || 'тестер' }}</template>
          </span>
        </span>
        <span v-if="unread" class="tt-dock__badge">{{ unread }}</span>
      </button>
    </div>
  </Teleport>
</template>

<style scoped>
@import "@/components/tiers/TierChatDock.css";
</style>
