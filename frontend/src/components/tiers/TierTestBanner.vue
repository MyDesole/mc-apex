<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import TierTestForm from '@/components/tiers/TierTestForm.vue'

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
@import "@/components/tiers/TierTestBanner.css";
</style>
