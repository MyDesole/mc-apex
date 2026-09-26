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
.toast {
  position: fixed;
  top: calc(var(--header-height, 64px) + 16px);
  right: 20px;
  z-index: 3000;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  max-width: 380px;
  padding: 14px 14px 14px 18px;
  background: #16161f;
  border: 1px solid #262634;
  border-radius: 12px;
  box-shadow:
      0 20px 50px -20px rgba(0, 0, 0, 0.8),
      0 2px 6px rgba(0, 0, 0, 0.3);
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.toast:hover {
  transform: translateY(-1px);
  border-color: #30303f;
}

/* Цветная полоса слева */
.toast__bar {
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--type-color);
  box-shadow: 0 0 8px var(--type-color);
}

/* Иконка */
.toast__icon {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
  color: var(--type-color);
  background: color-mix(in srgb, var(--type-color) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--type-color) 28%, transparent);
  border-radius: 8px;
}

/* Тело */
.toast__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.toast__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.toast__type {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--type-color);
}

.toast__time {
  font-size: 10px;
  color: #6c6c78;
  font-weight: 700;
  flex-shrink: 0;
}

.toast__title {
  font-size: 13.5px;
  font-weight: 800;
  color: #f3f4f6;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.toast__text {
  font-size: 12px;
  color: #b8b8c7;
  line-height: 1.45;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* Закрыть */
.toast__close {
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #6c6c78;
  background: transparent;
  border: 0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.toast__close:hover {
  color: #e5e7eb;
  background: rgba(255, 255, 255, 0.06);
}

/* Transition */
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Mobile */
@media (max-width: 600px) {
  .toast {
    left: 12px;
    right: 12px;
    max-width: none;
  }
}
</style>