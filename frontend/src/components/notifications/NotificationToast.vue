<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  notification: { type: Object, default: null },
  trigger: { type: Boolean, default: false },
})

const visible = ref(false)
const current = ref(null)

let hideTimer = null

// Мета по типу уведомления: лейбл + цвет
const TYPES = {
  friend:        { label: 'Друзья',       color: '#60a5fa' },
  friend_request:{ label: 'Друзья',       color: '#60a5fa' },
  tier_test:     { label: 'Тир-тесты',    color: '#a78bfa' },
  tier_test_done:{ label: 'Тир-тесты',    color: '#22c55e' },
  clan:          { label: 'Клан',         color: '#fbbf24' },
  clan_invite:   { label: 'Клан',         color: '#fbbf24' },
  achievement:   { label: 'Достижения',   color: '#facc15' },
  system:        { label: 'Система',      color: '#9ca3af' },
}

const meta = computed(() => TYPES[current.value?.type] ?? TYPES.system)
const typeColor = computed(() => meta.value.color)
const typeLabel = computed(() => meta.value.label)

watch(
    () => props.trigger,
    (val) => {
      if (!val || !props.notification) return

      current.value = props.notification
      visible.value = true

      if (hideTimer) clearTimeout(hideTimer)
      hideTimer = setTimeout(() => {
        visible.value = false
      }, 6000)
    }
)

function close() {
  visible.value = false
  if (hideTimer) clearTimeout(hideTimer)
}
</script>

<template>
  <Teleport to="body">
    <Transition name="toast">
      <component
          v-if="visible && current"
          :is="current.url ? RouterLink : 'div'"
          :to="current.url || undefined"
          class="toast"
          :style="{ '--type-color': typeColor }"
          @click="close"
      >
        <span class="toast__bar" />

        <span class="toast__icon">
          <!-- Друзья -->
          <svg v-if="current.type?.startsWith('friend')" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>

          <!-- Тир-тесты -->
          <svg v-else-if="current.type?.startsWith('tier')" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 3v12" />
            <circle cx="18" cy="6" r="3" />
            <circle cx="6" cy="18" r="3" />
            <path d="M18 9a9 9 0 0 1-9 9" />
          </svg>

          <!-- Клан -->
          <svg v-else-if="current.type?.startsWith('clan')" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
          </svg>

          <!-- Достижения -->
          <svg v-else-if="current.type === 'achievement'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 21h8M12 17v4" />
            <path d="M7 4h10v5a5 5 0 0 1-10 0V4z" />
            <path d="M17 5h3a2 2 0 0 1 0 4h-3" />
            <path d="M7 5H4a2 2 0 0 0 0 4h3" />
          </svg>

          <!-- Система / fallback -->
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
        </span>

        <span class="toast__body">
          <span class="toast__head">
            <span class="toast__type">{{ typeLabel }}</span>
            <span v-if="current.time" class="toast__time">{{ current.time }}</span>
          </span>

          <span class="toast__title">
            {{ current.title || 'Новое уведомление' }}
          </span>

          <span v-if="current.body" class="toast__text">
            {{ current.body }}
          </span>
        </span>

        <button
            class="toast__close"
            type="button"
            aria-label="Закрыть"
            @click.stop="close"
        >
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>
      </component>
    </Transition>
  </Teleport>
</template>

<style scoped>
@import "@/components/notifications/NotificationToast.css";
</style>
