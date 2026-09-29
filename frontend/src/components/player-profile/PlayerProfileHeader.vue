<script setup>
import { computed } from 'vue'
import UserName from '@/components/UserName.vue'
import PlayerProfileAvatar from './PlayerProfileAvatar.vue'
import PlayerProfileMeta from './PlayerProfileMeta.vue'
import { tierColor } from '@/composables/useTier'

const props = defineProps({
  user: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  coverUrl: { type: String, default: null },
  accent: { type: String, default: null },
  size: { type: String, default: 'md' }, // 'md' | 'sm'
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['edit'])

const isVerified = computed(() => props.user.is_verified ?? false)

const accentColor = computed(() =>
    props.accent
    || props.user.accent_color
    || props.user.banner_color
    || tierColor(props.user.tier)
)

const tColor = computed(() => tierColor(props.user.tier))

const effectClass = computed(() => {
  if (!props.user.profile_effect) return ''
  return `effect-${props.user.profile_effect}`
})

const headerStyle = computed(() => {
  const url = props.coverUrl
  return {
    ...(url ? {
      backgroundImage: `linear-gradient(rgba(10,10,15,0.45), rgba(10,10,15,0.85)), url(${url})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    } : {}),
    '--accent-color': accentColor.value,
  }
})

// Размер аватара в зависимости от compact/size
const avatarSize = computed(() => {
  if (props.compact) return 40
  return props.size === 'sm' ? 56 : 72
})

// Размер галочки верификации
const verifiedSize = computed(() => {
  if (props.compact) return 12
  return props.size === 'sm' ? 13 : 16
})

// Цитата для компактного режима
const quote = computed(() => props.user.quote || null)
</script>

<template>
  <header
      class="pp-header"
      :class="[
      effectClass,
      `pp-header--${size}`,
      { 'pp-header--compact': compact },
    ]"
      :style="headerStyle"
  >
    <div class="pp-header__left">
      <PlayerProfileAvatar
          :user="user"
          :accent="accentColor"
          :size="avatarSize"
      />

      <div class="pp-header__info">
        <h2 class="pp-header__name">
          <UserName :user="user" />

          <span
              v-if="isVerified"
              class="verified"
              :title="user.verified_reason || 'Подтверждённый аккаунт'"
          >
            <svg :width="verifiedSize" :height="verifiedSize" viewBox="0 0 24 24" fill="none">
              <path d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z" fill="#1da1f2" />
              <path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            </svg>
          </span>
        </h2>

        <!-- COMPACT: только цитата -->
        <p v-if="compact && quote" class="pp-header__quote">
          "{{ quote }}"
        </p>

        <!-- ОБЫЧНЫЙ: полная мета -->
        <PlayerProfileMeta v-else-if="!compact" :user="user" />
      </div>
    </div>

    <div class="pp-header__right">
      <slot name="actions">
        <!-- Обычная кнопка -->
        <button
            v-if="editable && !compact"
            class="pp-header__edit"
            type="button"
            @click="emit('edit')"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Настройки
        </button>

        <!-- Компактная иконочная кнопка -->
        <button
            v-else-if="editable && compact"
            class="pp-header__edit pp-header__edit--icon"
            type="button"
            aria-label="Настройки"
            @click="emit('edit')"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </slot>

      <slot name="tier">
        <div
            class="pp-header__tier"
            :class="{
            'pp-header__tier--sm': size === 'sm',
            'pp-header__tier--compact': compact,
          }"
            :style="{
            borderColor: tColor,
            color: tColor,
            boxShadow: `0 0 20px ${tColor}40`,
          }"
        >
          {{ user.tier }}
        </div>
      </slot>
    </div>
  </header>
</template>

<style scoped>
/* ============================================
   BASE
   ============================================ */

.pp-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 20px;
  background: #0d0d14;
  background-size: cover;
  background-position: center;
  border-radius: 12px;
  overflow: hidden;
  min-height: 110px;
}

.pp-header--sm {
  padding: 16px;
  gap: 12px;
  min-height: 90px;
}

.pp-header__left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  flex: 1;
}

.pp-header--sm .pp-header__left { gap: 12px; }

.pp-header__info {
  flex: 1;
  min-width: 0;
}

.pp-header__name {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pp-header--sm .pp-header__name { font-size: 15px; }

.verified {
  display: inline-flex;
  flex-shrink: 0;
  filter: drop-shadow(0 0 6px rgba(29, 161, 242, 0.6));
}

.pp-header__right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  flex-shrink: 0;
}

/* === EDIT BUTTON === */

.pp-header__edit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  color: #fff;
  background: rgba(124, 58, 237, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  backdrop-filter: blur(8px);
  transition: all 0.2s ease;
}

.pp-header__edit:hover {
  background: var(--accent, #7c3aed);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
}

.pp-header__edit--icon {
  padding: 6px;
  width: 28px;
  height: 28px;
  justify-content: center;
}

/* === TIER === */

.pp-header__tier {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid;
  border-radius: 12px;
  font-size: 24px;
  font-weight: 900;
  background: rgba(10, 10, 15, 0.85);
  backdrop-filter: blur(8px);
  transition: box-shadow 0.2s ease;
}

.pp-header__tier--sm {
  width: 44px;
  height: 44px;
  font-size: 19px;
  border-radius: 10px;
}

/* ============================================
   COMPACT MODE
   ============================================ */

.pp-header--compact {
  padding: 10px 14px;
  gap: 10px;
  min-height: 0;
  border-radius: 10px;
}

.pp-header--compact .pp-header__left {
  gap: 10px;
}

.pp-header--compact .pp-header__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pp-header--compact .pp-header__name {
  margin: 0;
  font-size: 14px;
  gap: 5px;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.pp-header--compact .verified {
  filter: drop-shadow(0 0 4px rgba(29, 161, 242, 0.5));
}

/* Цитата — единственное, что видно в компактном режиме */
.pp-header__quote {
  margin: 0;
  font-size: 11.5px;
  line-height: 1.35;
  font-style: italic;
  color: #b8b8c7;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

.pp-header--compact .pp-header__right {
  flex-direction: row;
  align-items: center;
  gap: 6px;
}

.pp-header--compact .pp-header__tier {
  width: 36px;
  height: 36px;
  font-size: 16px;
  border-radius: 8px;
  border-width: 1.5px;
}

/* ============================================
   PROFILE EFFECTS
   ============================================ */

.pp-header.effect-glow {
  box-shadow: inset 0 0 40px color-mix(in srgb, var(--accent-color) 20%, transparent);
}

.pp-header.effect-pulse {
  animation: profilePulse 3s infinite;
}

@keyframes profilePulse {
  0%, 100% {
    box-shadow: inset 0 0 40px color-mix(in srgb, var(--accent-color) 15%, transparent);
  }
  50% {
    box-shadow: inset 0 0 60px color-mix(in srgb, var(--accent-color) 35%, transparent);
  }
}

.pp-header.effect-gradient::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
      135deg,
      color-mix(in srgb, var(--accent-color) 15%, transparent) 0%,
      transparent 40%,
      color-mix(in srgb, var(--accent-color) 15%, transparent) 100%
  );
  pointer-events: none;
}

.pp-header.effect-fire::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(239, 68, 68, 0.3), transparent 50%);
  pointer-events: none;
  animation: fireFlicker 2s infinite;
}

@keyframes fireFlicker {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

.pp-header.effect-ice::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 0% 100%, rgba(6, 182, 212, 0.3), transparent 50%);
  pointer-events: none;
}

.pp-header.effect-legendary {
  border: 1px solid rgba(250, 204, 21, 0.4);
}

.pp-header.effect-legendary::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
      135deg,
      rgba(250, 204, 21, 0.15),
      transparent 40%,
      rgba(249, 115, 22, 0.15)
  );
  pointer-events: none;
  animation: legendaryShift 4s infinite;
  background-size: 200% 200%;
}

@keyframes legendaryShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}

/* ============================================
   MOBILE
   ============================================ */

@media (max-width: 600px) {
  .pp-header--compact {
    padding: 8px 12px;
    gap: 8px;
  }

  .pp-header--compact .pp-header__name {
    font-size: 13px;
  }

  .pp-header--compact .pp-header__quote {
    font-size: 10.5px;
  }

  .pp-header--compact .pp-header__tier {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }
}
</style>