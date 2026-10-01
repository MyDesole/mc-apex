<script setup>
import { onMounted, ref } from 'vue'
import { tierTestsApi } from '@/services/tierTests.js'
import TierTestForm from './TierTestForm.vue'

const loading = ref(true)
const myTests = ref([])
const asTester = ref([])

async function load() {
  loading.value = true

  try {
    const data = await tierTestsApi.list()

    myTests.value = data.my_tests || []
    asTester.value = data.as_tester || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const statusLabels = {
  pending: 'Ожидает',
  in_progress: 'В процессе',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

onMounted(load)
</script>

<template>
  <section class="tier-tests">
    <div class="tier-tests__glow"></div>

    <!-- HEADER -->
    <header class="head">
      <div class="head__identity">
        <div class="head__eyebrow">
          <span></span>
          COMPETITIVE RECORD
        </div>

        <div class="head__title-row">
          <h2>Тир-тесты</h2>

          <span class="head__count">
            {{ myTests.length }}
          </span>
        </div>

        <p class="head__description">
          История квалификационных испытаний
        </p>
      </div>

      <TierTestForm @created="load" />
    </header>

    <!-- LOADING -->
    <div v-if="loading" class="state state--loading">
      <div class="state__signal">
        <span></span>
      </div>

      <div>
        <div class="state__title">
          Загрузка истории
        </div>

        <div class="state__text">
          Синхронизация результатов...
        </div>
      </div>
    </div>

    <template v-else>
      <!-- EMPTY -->
      <div
          v-if="!myTests.length"
          class="state state--empty"
      >
        <div class="state__icon">
          <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
          >
            <path
                d="M4 19V9l8-5 8 5v10"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <path
                d="M8 19v-6h8v6M12 9v2"
                stroke-linecap="round"
            />
          </svg>
        </div>

        <div class="state__title">
          История пока пуста
        </div>

        <div class="state__text">
          Ты ещё не записывался на тир-тесты
        </div>
      </div>

      <!-- LIST -->
      <div v-else class="list">
        <div
            v-for="(t, index) in myTests"
            :key="t.id"
            class="test-row"
            :class="{
            'test-row--completed': t.status === 'completed',
          }"
        >
          <div class="test-row__index">
            {{ String(index + 1).padStart(2, '0') }}
          </div>

          <div class="test-info">
            <div class="test-mode">
              <span class="test-mode__dot"></span>

              {{ t.mode === 'pvp' ? 'PvP' : 'BedWars' }}
            </div>

            <div class="test-date">
              {{ new Date(t.created_at).toLocaleDateString('ru-RU') }}
            </div>
          </div>

          <div
              class="test-status"
              :class="`status-${t.status}`"
          >
            <span class="test-status__dot"></span>
            {{ statusLabels[t.status] }}
          </div>

          <div
              v-if="t.result_tier"
              class="test-result"
          >
            <span class="tier">
              {{ t.result_tier }}
            </span>

            <span class="score">
              {{ t.result_score }}%
            </span>
          </div>

          <div
              v-else
              class="test-result test-result--empty"
          >
            —
          </div>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
/* =========================================================
   CONTAINER
========================================================= */

.tier-tests {
  --test-accent: #8b5cf6;

  position: relative;

  margin-top: 28px;
  padding: 20px;

  background:
      radial-gradient(
          circle at 0% 0%,
          rgba(139, 92, 246, 0.08),
          transparent 32%
      ),
      linear-gradient(
          135deg,
          rgba(11, 12, 25, 0.98),
          rgba(7, 8, 17, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 16px;

  overflow: hidden;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 12px 35px rgba(0, 0, 0, 0.2);
}

.tier-tests::before {
  content: '';

  position: absolute;
  left: 0;
  top: 0;

  width: 34%;
  height: 1px;

  background: linear-gradient(
      90deg,
      var(--test-accent),
      transparent
  );

  box-shadow: 0 0 12px rgba(139, 92, 246, 0.5);
}

.tier-tests__glow {
  position: absolute;

  width: 180px;
  height: 180px;

  top: -100px;
  right: -50px;

  background: rgba(139, 92, 246, 0.07);

  filter: blur(45px);
  border-radius: 50%;

  pointer-events: none;
}

/* =========================================================
   HEADER
========================================================= */

.head {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 20px;
  margin-bottom: 18px;
}

.head__identity {
  min-width: 0;
}

.head__eyebrow {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 5px;

  color: #656779;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.head__eyebrow span {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: var(--test-accent);

  box-shadow:
      0 0 7px rgba(139, 92, 246, 0.7);
}

.head__title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

h2 {
  margin: 0;

  color: #f2f2f7;

  font-size: 18px;
  font-weight: 850;
  letter-spacing: -0.3px;
}

.head__count {
  min-width: 22px;
  height: 19px;
  padding: 0 6px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  color: #a78bfa;

  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 6px;

  font-size: 9px;
  font-weight: 900;
}

.head__description {
  margin: 4px 0 0;

  color: #626475;

  font-size: 10px;
}

/* =========================================================
   LIST
========================================================= */

.list {
  position: relative;
  z-index: 1;

  display: flex;
  flex-direction: column;
  gap: 6px;
}

.test-row {
  position: relative;

  display: grid;
  grid-template-columns: 28px minmax(0, 1fr) auto auto;

  align-items: center;

  gap: 12px;

  min-height: 58px;
  padding: 9px 12px;

  background:
      linear-gradient(
          90deg,
          rgba(255, 255, 255, 0.018),
          rgba(255, 255, 255, 0.008)
      );

  border: 1px solid rgba(255, 255, 255, 0.045);
  border-radius: 10px;

  transition:
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.test-row:hover {
  transform: translateX(2px);

  background:
      linear-gradient(
          90deg,
          rgba(139, 92, 246, 0.055),
          rgba(255, 255, 255, 0.01)
      );

  border-color: rgba(139, 92, 246, 0.18);
}

.test-row--completed {
  border-left-color: rgba(139, 92, 246, 0.28);
}

.test-row__index {
  color: #414354;

  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.5px;
  text-align: center;
}

/* =========================================================
   TEST INFO
========================================================= */

.test-info {
  min-width: 0;
}

.test-mode {
  display: flex;
  align-items: center;
  gap: 6px;

  color: #e5e5eb;

  font-size: 12px;
  font-weight: 800;
}

.test-mode__dot {
  width: 5px;
  height: 5px;

  flex-shrink: 0;

  border-radius: 50%;
  background: #8b5cf6;

  box-shadow:
      0 0 7px rgba(139, 92, 246, 0.6);
}

.test-date {
  margin-top: 3px;

  color: #5f6171;

  font-size: 9px;
  font-weight: 600;
}

/* =========================================================
   STATUS
========================================================= */

.test-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 5px 8px;

  border-radius: 6px;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.4px;
  text-transform: uppercase;

  white-space: nowrap;
}

.test-status__dot {
  width: 4px;
  height: 4px;

  border-radius: 50%;

  background: currentColor;
  box-shadow: 0 0 6px currentColor;
}

.status-pending {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.13);
}

.status-in_progress {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.08);
  border: 1px solid rgba(96, 165, 250, 0.13);
}

.status-completed {
  color: #22c55e;
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.13);
}

.status-cancelled {
  color: #6b7280;
  background: rgba(107, 114, 128, 0.08);
  border: 1px solid rgba(107, 114, 128, 0.12);
}

/* =========================================================
   RESULT
========================================================= */

.test-result {
  min-width: 82px;

  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.tier {
  color: #a78bfa;

  font-size: 17px;
  font-weight: 950;
  letter-spacing: -0.3px;

  text-shadow:
      0 0 14px rgba(139, 92, 246, 0.25);
}

.score {
  color: #858798;

  font-size: 10px;
  font-weight: 800;
}

.test-result--empty {
  color: #454756;

  font-size: 13px;
}

/* =========================================================
   STATES
========================================================= */

.state {
  position: relative;
  z-index: 1;

  min-height: 100px;

  display: flex;
  align-items: center;
  justify-content: center;

  gap: 12px;

  padding: 20px;

  text-align: left;

  background:
      radial-gradient(
          circle at 50% 50%,
          rgba(139, 92, 246, 0.04),
          transparent 60%
      );

  border: 1px dashed rgba(255, 255, 255, 0.055);
  border-radius: 11px;
}

.state--empty {
  flex-direction: column;
  gap: 5px;

  text-align: center;
}

.state__signal {
  width: 30px;
  height: 30px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 8px;

  background: rgba(139, 92, 246, 0.05);
}

.state__signal span {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: #8b5cf6;

  box-shadow:
      0 0 8px rgba(139, 92, 246, 0.7);

  animation: state-pulse 1.4s ease-in-out infinite;
}

.state__icon {
  width: 42px;
  height: 42px;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 4px;

  color: #696b7c;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 10px;
}

.state__title {
  color: #b7b8c2;

  font-size: 11px;
  font-weight: 800;
}

.state__text {
  color: #5e6070;

  font-size: 9px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {
  .tier-tests {
    padding: 16px;
    border-radius: 14px;
  }

  .head {
    align-items: stretch;
    flex-direction: column;
  }

  .head :deep(button) {
    width: 100%;
  }

  .test-row {
    grid-template-columns: 24px minmax(0, 1fr) auto;

    gap: 9px;
  }

  .test-status {
    grid-column: 2;
    grid-row: 2;
    justify-self: start;
  }

  .test-result {
    grid-column: 3;
    grid-row: 1 / span 2;
    align-self: center;

    min-width: 65px;
  }

  .test-row__index {
    grid-row: 1 / span 2;
  }
}

@media (max-width: 450px) {
  .tier-tests {
    padding: 13px;
  }

  .test-row {
    padding: 9px;
  }

  .test-row__index {
    display: none;
  }

  .test-info {
    grid-column: 1;
  }

  .test-status {
    grid-column: 1;
  }

  .test-result {
    grid-column: 2;
  }

  .test-mode {
    font-size: 11px;
  }
}

/* =========================================================
   MOTION
========================================================= */

@keyframes state-pulse {
  0%,
  100% {
    opacity: 0.35;
    transform: scale(0.85);
  }

  50% {
    opacity: 1;
    transform: scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .test-row,
  .state__signal span {
    transition: none;
    animation: none;
  }

  .test-row:hover {
    transform: none;
  }
}
</style>