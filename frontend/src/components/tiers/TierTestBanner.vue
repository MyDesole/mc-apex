<script setup>
/**
 * Приглашение пройти тест.
 *
 * В PvP-профиле зовём на тир-тест, в бридж-профиле — подтвердить вид
 * бриджа. Банер один: он смотрит на режим профиля и на то, есть ли уже
 * подтверждённые виды, чтобы не предлагать пройденное.
 */
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import TierTestForm from '@/components/tiers/TierTestForm.vue'
import BridgeTechniqueForm from '@/components/bridge/BridgeTechniqueForm.vue'

const auth = useAuthStore()

const STORAGE_KEY = 'tier_banner_hidden'

const hiddenInSession = ref(
    typeof sessionStorage !== 'undefined' && sessionStorage.getItem(STORAGE_KEY) === '1'
)

const showTierForm = ref(false)
const showBridgeForm = ref(false)

const isBridge = computed(() => auth.user?.profile_mode === 'bridge')

const bridgeConfirmed = computed(() =>
    auth.user?.bridge?.summary?.confirmed_count ?? 0,
)

const shouldShow = computed(() => {
  if (!auth.initialized) return false
  if (!auth.isAuthenticated) return false
  if (hiddenInSession.value) return false

  // В бридже зовём подтвердить вид, а не проходить тир-тест
  if (isBridge.value) {
    return bridgeConfirmed.value === 0
  }

  const tests = auth.user?.tier_tests ?? []
  return tests.length === 0
})

function hide() {
  hiddenInSession.value = true
  sessionStorage.setItem(STORAGE_KEY, '1')
}

function openForm() {
  if (isBridge.value) {
    showBridgeForm.value = true

    return
  }

  showTierForm.value = true
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
            <template v-if="isBridge">
              <path d="M3 20h18" />
              <path d="M5 20V9l7-5 7 5v11" />
              <path d="M9 20v-6h6v6" />
            </template>

            <template v-else>
              <path d="M6 3v12" />
              <circle cx="18" cy="6" r="3" />
              <circle cx="6" cy="18" r="3" />
              <path d="M18 9a9 9 0 0 1-9 9" />
            </template>
          </svg>
        </div>

        <div class="tier-banner__text">
          <template v-if="isBridge">
            <div class="tier-banner__title">Ты ещё не подтвердил ни один вид бриджа</div>
            <div class="tier-banner__sub">Загрузи ролик, пройди проверку и получи звание бриджера</div>
          </template>

          <template v-else>
            <div class="tier-banner__title">Ты ещё не проходил тир-тест</div>
            <div class="tier-banner__sub">Узнай свой реальный уровень и получи ранг</div>
          </template>
        </div>

        <button class="tier-banner__cta" type="button" @click="openForm">
          {{ isBridge ? 'Подтвердить вид' : 'Записаться' }}
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
        v-model="showTierForm"
        @created="onCreated"
    />

    <BridgeTechniqueForm
        v-model="showBridgeForm"
        @created="onCreated"
    />
  </Teleport>
</template>

<style scoped>
@import "@/components/tiers/TierTestBanner.css";
</style>
