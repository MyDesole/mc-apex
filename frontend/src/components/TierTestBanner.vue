<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import TierTestForm from '@/components/TierTestForm.vue'

const auth = useAuthStore()

const STORAGE_KEY = 'tier_banner_hidden'

const hiddenInSession = ref(
    typeof sessionStorage !== 'undefined' && sessionStorage.getItem(STORAGE_KEY) === '1'
)

const showForm = ref(false)

const shouldShow = computed(() => {
  if (!auth.initialized) return false
  if (!auth.isAuthenticated) return false
  if (hiddenInSession.value) return false

  const tests = auth.user?.tier_tests ?? []
  return tests.length === 0
})

function hide() {
  hiddenInSession.value = true
  sessionStorage.setItem(STORAGE_KEY, '1')
}

function openForm() {
  showForm.value = true
}

function onCreated() {
  hide()
  auth.fetchMe()
}
</script>

<template>
  <Teleport to="body">
    <Transition name="tier-banner">
      <div v-if="shouldShow" class="tier-banner">
        <!-- Пульсирующий индикатор сверху слева -->
        <span class="tier-banner__pulse" aria-hidden="true" />

        <!-- Свечение по контуру -->
        <span class="tier-banner__glow" aria-hidden="true" />

        <div class="tier-banner__icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 3v12" />
            <circle cx="18" cy="6" r="3" />
            <circle cx="6" cy="18" r="3" />
            <path d="M18 9a9 9 0 0 1-9 9" />
          </svg>
        </div>

        <div class="tier-banner__text">
          <div class="tier-banner__title">Ты ещё не проходил тир-тест</div>
          <div class="tier-banner__sub">Узнай свой реальный уровень и получи ранг</div>
        </div>

        <button class="tier-banner__cta" type="button" @click="openForm">
          Записаться
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M13 5l7 7-7 7" />
          </svg>
        </button>

        <button class="tier-banner__close" type="button" aria-label="Скрыть" @click="hide">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>
      </div>
    </Transition>

    <TierTestForm
        v-model="showForm"
        @created="onCreated"
    />
  </Teleport>
</template>

<style scoped>
.tier-banner {
  position: fixed;
  left: 20px;
  bottom: 20px;
  z-index: 1100;
  display: flex;
  align-items: center;
  gap: 14px;
  max-width: 460px;
  padding: 16px 18px;
  background:
      linear-gradient(135deg, rgba(124, 58, 237, 0.28), rgba(6, 182, 212, 0.12)),
      rgba(18, 18, 26, 0.98);
  border: 1px solid rgba(139, 92, 246, 0.65);
  border-radius: 16px;
  box-shadow:
      0 24px 70px -15px rgba(124, 58, 237, 0.55),
      0 12px 40px -10px rgba(0, 0, 0, 0.7),
      0 0 0 1px rgba(139, 92, 246, 0.25) inset;
  backdrop-filter: blur(18px);
  animation: tierBannerIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes tierBannerIn {
  from { opacity: 0; transform: translateY(24px) scale(0.94); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Пульсирующая точка в углу */
.tier-banner__pulse {
  position: absolute;
  top: -5px;
  left: -5px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #ef4444;
  border: 3px solid #12121a;
  box-shadow: 0 0 12px rgba(239, 68, 68, 0.9);
  animation: pulseDot 1.8s infinite;
  z-index: 2;
}

@keyframes pulseDot {
  0%, 100% {
    transform: scale(1);
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.9);
  }
  50% {
    transform: scale(1.25);
    box-shadow: 0 0 20px rgba(239, 68, 68, 1);
  }
}

/* Размытое свечение вокруг баннера */
.tier-banner__glow {
  position: absolute;
  inset: -2px;
  border-radius: 18px;
  pointer-events: none;
  background: linear-gradient(135deg, rgba(124, 58, 237, 0.5), rgba(6, 182, 212, 0.3));
  filter: blur(14px);
  opacity: 0.55;
  z-index: -1;
  animation: glowPulse 3s ease-in-out infinite;
}

@keyframes glowPulse {
  0%, 100% { opacity: 0.45; }
  50% { opacity: 0.75; }
}

.tier-banner__icon {
  position: relative;
  z-index: 1;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #ffffff;
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  border: 1px solid rgba(196, 181, 253, 0.5);
  border-radius: 12px;
  box-shadow:
      0 6px 20px rgba(124, 58, 237, 0.5),
      inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.tier-banner__text {
  position: relative;
  z-index: 1;
  flex: 1;
  min-width: 0;
}

.tier-banner__title {
  font-size: 14px;
  font-weight: 800;
  color: #ffffff;
  margin-bottom: 3px;
  letter-spacing: -0.1px;
}

.tier-banner__sub {
  font-size: 12px;
  color: #b8b8c7;
  line-height: 1.45;
}

/* Главная кнопка — ярче, со свечением */
.tier-banner__cta {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-shrink: 0;
  padding: 10px 18px;
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 6px 18px rgba(124, 58, 237, 0.55);
  transition: all 0.15s;
}

.tier-banner__cta:hover {
  background: linear-gradient(135deg, #a78bfa, #8b5cf6);
  transform: translateY(-1px);
  box-shadow: 0 10px 28px rgba(124, 58, 237, 0.7);
}

.tier-banner__cta svg {
  transition: transform 0.15s;
}

.tier-banner__cta:hover svg {
  transform: translateX(2px);
}

.tier-banner__close {
  position: relative;
  z-index: 1;
  width: 26px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #8a8a97;
  background: transparent;
  border: 0;
  border-radius: 7px;
  cursor: pointer;
  transition: all 0.15s;
}

.tier-banner__close:hover {
  color: #f3f4f6;
  background: rgba(255, 255, 255, 0.08);
}

/* Transition */

.tier-banner-enter-active,
.tier-banner-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.tier-banner-enter-from,
.tier-banner-leave-to {
  opacity: 0;
  transform: translateY(24px);
}

/* Mobile */

@media (max-width: 600px) {
  .tier-banner {
    left: 12px;
    right: 12px;
    bottom: 12px;
    max-width: none;
    padding: 14px;
    gap: 12px;
  }

  .tier-banner__icon {
    width: 40px;
    height: 40px;
  }

  .tier-banner__title {
    font-size: 13px;
  }

  .tier-banner__sub {
    font-size: 11px;
  }

  .tier-banner__cta {
    padding: 9px 14px;
    font-size: 12px;
  }

  .tier-banner__cta svg {
    display: none;
  }
}
</style>