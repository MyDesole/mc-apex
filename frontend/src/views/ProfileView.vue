<script setup>
import { computed, ref } from 'vue'
import PlayerCard from '@/components/PlayerCard.vue'
import TierTestHistory from '@/components/TierTestHistory.vue'
import ClanBadge from '@/components/ClanBadge.vue'
import RankBadge from '@/components/RankBadge.vue'
import ProfileCustomizeModal from '@/components/ProfileCustomizeModal.vue'
import TierTestForm from '@/components/TierTestForm.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const user = computed(() => auth.user)
const aspects = computed(() => auth.user?.aspects ?? [])
const clanMember = computed(() => auth.user?.clan_member ?? null)
const rank = computed(() => auth.rank ?? { position: null, total: 0 })

// модалка кастомизации
const showCustomize = ref(false)

function onCustomizeUpdated() {
  showCustomize.value = false
  auth.fetchMe()
}

// модалка записи на тир-тест
const showTierTestForm = ref(false)

function openTierTestForm() {
  showTierTestForm.value = true
}

function onTierTestCreated() {
  // обновляем user — теперь в tier_tests появится запись,
  // баннер «запишись» на главной исчезнет сам
  auth.fetchMe()
}
</script>

<template>
  <div class="container">
    <template v-if="user">
      <PlayerCard
          :user="user"
          editable
          @edit="showCustomize = true"
      />

      <section class="blocks">
        <div class="block-col">
          <h3 class="block-title">Клан</h3>
          <ClanBadge :clan-member="clanMember" />
        </div>

        <div class="block-col">
          <h3 class="block-title">Место в топе</h3>
          <RankBadge :position="rank.position" :total="rank.total" />
        </div>
      </section>

      <!-- === ТИР-ТЕСТЫ === -->
      <section id="tier-test" class="tier-test-section">
        <header class="tier-test-head">
          <div>
            <h3 class="tier-test-title">Тир-тесты</h3>
            <p class="tier-test-sub">Пройди тест и получи свой ранг</p>
          </div>

          <button
              class="tier-test-cta"
              type="button"
              @click="openTierTestForm"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14M5 12h14" />
            </svg>
            Записаться на тир-тест
          </button>
        </header>

        <TierTestHistory />
      </section>

      <!-- модалка кастомизации -->
      <ProfileCustomizeModal
          v-if="showCustomize"
          @close="showCustomize = false"
          @updated="onCustomizeUpdated"
      />

      <!-- модалка записи на тир-тест -->
      <TierTestForm
          v-model="showTierTestForm"
          @created="onTierTestCreated"
      />
    </template>

    <div v-else class="loading">Загрузка...</div>
  </div>
</template>

<style scoped>
.container {
  width: min(900px, calc(100% - 40px));
  margin: 40px auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.blocks {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.block-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.block-title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* === TIER TEST SECTION === */

.tier-test-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tier-test-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.tier-test-title {
  margin: 0 0 2px;
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.2px;
}

.tier-test-sub {
  margin: 0;
  color: var(--text-dim);
  font-size: 12.5px;
}

.tier-test-cta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
  transition: all 0.2s ease;
  white-space: nowrap;
}

.tier-test-cta:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35);
}

.tier-test-cta:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

/* === LOADING === */

.loading {
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
}

/* === ADAPTIVE === */

@media (max-width: 700px) {
  .blocks {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .tier-test-head {
    flex-direction: column;
    align-items: stretch;
  }

  .tier-test-cta {
    width: 100%;
    justify-content: center;
  }
}
</style>