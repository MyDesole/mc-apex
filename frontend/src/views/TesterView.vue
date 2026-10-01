<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { testerApi } from '@/services/tester.js'
import TesterTierTestModal from '@/components/tester/TesterTierTestModal.vue'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()

const role = computed(() => auth.user?.role)
const isTester = computed(() => ['tester', 'admin'].includes(role.value))

const tests = ref([])
const stats = ref(null)
const loading = ref(true)
const error = ref('')

const tab = ref('free')
const modeFilter = ref('')

const showModal = ref(false)
const selectedTest = ref(null)

const statusLabels = {
  pending: 'Ожидает',
  in_progress: 'В работе',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

const statusIcons = {
  pending: 'target',
  in_progress: 'bolt',
  completed: 'check',
  cancelled: 'close',
}

const filteredTitle = computed(() => {
  if (tab.value === 'mine') return 'Мои заявки'
  if (tab.value === 'completed') return 'Проведённые тесты'
  return 'Свободные заявки'
})

const priorityCount = computed(() => {
  return tests.value.filter((test) => test.is_priority).length
})

async function load() {
  loading.value = true
  error.value = ''

  try {
    const params = {}

    if (tab.value === 'free') {
      params.free = '1'
    }

    if (tab.value === 'mine') {
      params.mine = '1'
      params.status = 'in_progress'
    }

    if (tab.value === 'completed') {
      params.status = 'completed'
      params.mine = '1'
    }

    if (modeFilter.value) {
      params.mode = modeFilter.value
    }

    const [testsRes, statsRes] = await Promise.all([
      testerApi.tierTests(params),
      testerApi.stats(),
    ])

    tests.value = testsRes.data ?? []
    stats.value = statsRes
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить заявки.'
    tests.value = []
  } finally {
    loading.value = false
  }
}

watch([tab, modeFilter], load)

function openTest(test) {
  selectedTest.value = test
  showModal.value = true
}

function onUpdated() {
  showModal.value = false
  selectedTest.value = null
  load()
}

function modeLabel(mode) {
  return mode === 'pvp' ? 'PvP' : 'BedWars'
}

function isMine(test) {
  return test.status === 'in_progress' && test.claimed_by === auth.user?.id
}

function buttonLabel(test) {
  if (isMine(test)) return 'Провести'
  if (test.status === 'completed') return 'Просмотр'
  return 'Открыть'
}

function initials(username) {
  return username?.charAt(0)?.toUpperCase() || '?'
}

function priorityLabel(test) {
  if (!test.is_priority) return ''

  if (test.priority_position) {
    return `#${test.priority_position} в очереди`
  }

  return 'Приоритетная заявка'
}

onMounted(() => {
  if (!isTester.value) {
    loading.value = false
    return
  }

  load()
})
</script>

<template>
  <!-- ACCESS DENIED -->
  <div v-if="!isTester" class="denied">
    <div class="denied__glow"></div>

    <div class="denied__icon">
      <AppIcon icon="shield" :size="38" />
    </div>

    <span class="eyebrow">APEX / ACCESS CONTROL</span>

    <h1 class="denied__code">403</h1>

    <h2 class="denied__title">Доступ ограничен</h2>

    <p class="denied__text">
      У вас нет доступа к панели тестера.
    </p>

    <RouterLink to="/" class="btn btn--secondary">
      <AppIcon icon="close" :size="16" />
      На главную
    </RouterLink>
  </div>

  <!-- TESTER PAGE -->
  <main v-else class="tester-page">

    <!-- ================= HEADER ================= -->

    <header class="tester-head">
      <div class="tester-head__content">
        <span class="eyebrow">
          APEX / TESTER CONTROL
        </span>

        <div class="tester-head__title-row">
          <div>
            <h1 class="tester-head__title">
              Панель тестера
            </h1>

            <p class="tester-head__subtitle">
              Проводите тир-тесты и помогайте игрокам расти.
            </p>
          </div>

          <div
              class="role-badge"
              :class="`role-badge--${role}`"
          >
            <span class="role-badge__dot"></span>

            <span>
              {{ role === 'admin' ? 'Администратор' : 'Тестер' }}
            </span>
          </div>
        </div>
      </div>
    </header>

    <!-- ================= ERROR ================= -->

    <div v-if="error" class="alert alert--error">
      <div class="alert__icon">
        <AppIcon icon="close" :size="17" />
      </div>

      <div class="alert__content">
        <strong>Ошибка загрузки</strong>
        <span>{{ error }}</span>
      </div>
    </div>

    <!-- ================= STATS ================= -->

    <section v-if="stats" class="stats">
      <div class="stats__grid">

        <article class="stat-card stat-card--purple">
          <div class="stat-card__top">
            <div class="stat-card__icon">
              <AppIcon icon="target" :size="19" />
            </div>

            <span class="stat-card__eyebrow">
              QUEUE
            </span>
          </div>

          <div class="stat-card__value">
            {{ stats.pending_total }}
          </div>

          <div class="stat-card__label">
            Свободных заявок
          </div>
        </article>

        <article class="stat-card stat-card--blue">
          <div class="stat-card__top">
            <div class="stat-card__icon">
              <AppIcon icon="bolt" :size="19" />
            </div>

            <span class="stat-card__eyebrow">
              ACTIVE
            </span>
          </div>

          <div class="stat-card__value">
            {{ stats.in_progress }}
          </div>

          <div class="stat-card__label">
            В работе у меня
          </div>
        </article>

        <article class="stat-card stat-card--green">
          <div class="stat-card__top">
            <div class="stat-card__icon">
              <AppIcon icon="check" :size="19" />
            </div>

            <span class="stat-card__eyebrow">
              TODAY
            </span>
          </div>

          <div class="stat-card__value">
            {{ stats.today }}
          </div>

          <div class="stat-card__label">
            Проведено сегодня
          </div>
        </article>

        <article class="stat-card stat-card--gold">
          <div class="stat-card__top">
            <div class="stat-card__icon">
              <AppIcon icon="trophy" :size="19" />
            </div>

            <span class="stat-card__eyebrow">
              TOTAL
            </span>
          </div>

          <div class="stat-card__value">
            {{ stats.total_completed }}
          </div>

          <div class="stat-card__label">
            Всего проведено
          </div>
        </article>

      </div>
    </section>

    <!-- ================= TOOLBAR ================= -->

    <section class="toolbar">

      <div class="toolbar__left">
        <span class="eyebrow eyebrow--small">
          TEST QUEUE
        </span>

        <h2 class="toolbar__title">
          {{ filteredTitle }}

          <span v-if="priorityCount" class="priority-counter">
            <AppIcon icon="bolt" :size="12" />
            {{ priorityCount }} priority
          </span>
        </h2>
      </div>

      <nav class="tabs">
        <button
            type="button"
            class="tabs__item"
            :class="{ 'tabs__item--active': tab === 'free' }"
            @click="tab = 'free'"
        >
          <AppIcon icon="target" :size="15" />
          <span>Свободные</span>
        </button>

        <button
            type="button"
            class="tabs__item"
            :class="{ 'tabs__item--active': tab === 'mine' }"
            @click="tab = 'mine'"
        >
          <AppIcon icon="bolt" :size="15" />
          <span>Мои в работе</span>
        </button>

        <button
            type="button"
            class="tabs__item"
            :class="{ 'tabs__item--active': tab === 'completed' }"
            @click="tab = 'completed'"
        >
          <AppIcon icon="check" :size="15" />
          <span>Проведённые</span>
        </button>
      </nav>

      <div class="mode-filter">
        <span class="mode-filter__icon">
          <AppIcon icon="chart" :size="15" />
        </span>

        <select v-model="modeFilter">
          <option value="">Все режимы</option>
          <option value="pvp">PvP</option>
          <option value="bedwars">BedWars</option>
        </select>
      </div>

    </section>

    <!-- ================= LOADING ================= -->

    <section v-if="loading" class="state-card">
      <div class="state-card__loader">
        <span></span>
        <span></span>
        <span></span>
      </div>

      <strong>Загрузка очереди</strong>

      <p>
        Получаем актуальные заявки на тир-тесты...
      </p>
    </section>

    <!-- ================= EMPTY ================= -->

    <section v-else-if="!tests.length" class="state-card state-card--empty">
      <div class="state-card__icon">
        <AppIcon icon="target" :size="30" />
      </div>

      <span class="eyebrow eyebrow--small">
        QUEUE EMPTY
      </span>

      <strong>Заявок нет</strong>

      <p>
        В выбранной категории сейчас нет доступных тир-тестов.
      </p>
    </section>

    <!-- ================= LIST ================= -->

    <section v-else class="tests">

      <div class="tests__head">
        <span>
          {{ tests.length }}
          {{ tests.length === 1 ? 'заявка' : 'заявок' }}
        </span>

        <span class="tests__head-line"></span>

        <span class="tests__head-status">
          LIVE QUEUE
        </span>
      </div>

      <div class="tests__list">

        <article
            v-for="test in tests"
            :key="test.id"
            class="test-card"
            :class="[
            `test-card--${test.status}`,
            {
              'test-card--priority': test.is_priority,
              'test-card--mine': isMine(test),
            },
          ]"
        >

          <!-- Priority glow -->
          <div
              v-if="test.is_priority"
              class="test-card__priority-glow"
          ></div>

          <!-- Left accent -->
          <div class="test-card__accent"></div>

          <!-- Avatar -->
          <div
              class="test-avatar"
              :class="{
              'test-avatar--priority': test.is_priority
            }"
          >
            <img
                v-if="test.user?.avatar_url"
                :src="test.user.avatar_url"
                :alt="test.user?.username || 'Player'"
                class="test-avatar__img"
            />

            <template v-else>
              {{ initials(test.user?.username) }}
            </template>

            <span
                v-if="test.is_priority"
                class="test-avatar__priority"
            >
              <AppIcon icon="bolt" :size="10" />
            </span>
          </div>

          <!-- Main info -->
          <div class="test-card__main">

            <div class="test-card__name-row">

              <div class="test-card__name">
                <span
                    v-if="test.user?.clan_member?.clan"
                    class="clan"
                >
                  [{{ test.user.clan_member.clan.tag }}]
                </span>

                {{ test.user?.username || 'Неизвестный игрок' }}
              </div>

              <!-- PRIORITY -->
              <div
                  v-if="test.is_priority"
                  class="priority-badge"
              >
                <span class="priority-badge__icon">
                  <AppIcon icon="bolt" :size="11" />
                </span>

                <span class="priority-badge__text">
                  PRIORITY
                </span>

                <span
                    v-if="test.priority_position"
                    class="priority-badge__position"
                >
                  #{{ test.priority_position }}
                </span>
              </div>

            </div>

            <div class="test-card__meta">

              <span
                  class="mode"
                  :class="`mode--${test.mode}`"
              >
                <span class="mode__dot"></span>

                {{ modeLabel(test.mode) }}
              </span>

              <span class="meta-item">
                <span class="meta-item__label">ТИР</span>
                {{ test.user?.tier ?? '—' }}
              </span>

              <span class="meta-item">
                <span class="meta-item__label">SCORE</span>
                {{ test.user?.tier_score ?? 0 }}%
              </span>

              <template v-if="test.contact_value">
                <span class="meta-separator">•</span>

                <span class="meta-contact">
                  <b>
                    {{ test.contact_type === 'discord' ? 'Discord' : 'Telegram' }}
                  </b>

                  {{ test.contact_value }}
                </span>
              </template>

              <template v-if="test.preferred_time">
                <span class="meta-separator">•</span>

                <span class="meta-time">
                  {{ test.preferred_time }}
                </span>
              </template>

            </div>

          </div>

          <!-- Priority info -->
          <div
              v-if="test.is_priority"
              class="priority-info"
          >
            <span class="priority-info__label">
              PRIORITY
            </span>

            <span class="priority-info__text">
              {{ priorityLabel(test) }}
            </span>
          </div>

          <!-- Status -->
          <div class="test-card__status">

            <span
                class="status"
                :class="`status--${test.status}`"
            >
              <AppIcon
                  :icon="statusIcons[test.status]"
                  :size="12"
              />

              {{ statusLabels[test.status] || test.status }}
            </span>

            <div
                v-if="test.status === 'completed' && test.result_tier"
                class="result"
            >
              <strong class="result__tier">
                {{ test.result_tier }}
              </strong>

              <span class="result__score">
                {{ test.result_score }}%
              </span>
            </div>

          </div>

          <!-- Action -->
          <button
              type="button"
              class="btn-open"
              :class="{
              'btn-open--priority': test.is_priority
            }"
              @click="openTest(test)"
          >
            <span>
              {{ buttonLabel(test) }}
            </span>

            <AppIcon icon="bolt" :size="14" />
          </button>

        </article>

      </div>
    </section>

    <!-- ================= MODAL ================= -->

    <TesterTierTestModal
        v-if="showModal && selectedTest"
        :tier-test="selectedTest"
        @close="showModal = false"
        @updated="onUpdated"
    />

  </main>
</template>

<style scoped>
/* =========================================================
   APEX TESTER
========================================================= */

.tester-page {
  width: min(1180px, calc(100% - 40px));
  margin: 0 auto;
  padding: 38px 0 70px;
  color: var(--text);
}

/* =========================================================
   COMMON
========================================================= */

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  margin-bottom: 10px;

  color: var(--accent-light, #a78bfa);

  font-size: 10px;
  font-weight: 900;
  line-height: 1;

  letter-spacing: 1.8px;
  text-transform: uppercase;
}

.eyebrow::before {
  content: '';

  width: 18px;
  height: 1px;

  background: currentColor;

  opacity: 0.6;
}

.eyebrow--small {
  margin-bottom: 7px;

  font-size: 9px;
  letter-spacing: 1.5px;
}

button,
select {
  font: inherit;
}

/* =========================================================
   HEADER
========================================================= */

.tester-head {
  position: relative;

  margin-bottom: 30px;
}

.tester-head__content {
  position: relative;
}

.tester-head__title-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
}

.tester-head__title {
  margin: 0;

  font-size: clamp(28px, 4vw, 42px);
  font-weight: 900;
  line-height: 1;

  letter-spacing: -1.8px;

  background:
      linear-gradient(
          135deg,
          var(--text) 20%,
          var(--text-dim) 100%
      );

  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.tester-head__subtitle {
  margin: 10px 0 0;

  color: var(--text-dim);

  font-size: 13px;
  line-height: 1.5;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  padding: 8px 12px;

  border: 1px solid var(--border);
  border-radius: 8px;

  background: var(--bg-card);

  font-size: 9px;
  font-weight: 900;

  letter-spacing: 1px;
  text-transform: uppercase;

  white-space: nowrap;
}

.role-badge__dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: currentColor;

  box-shadow: 0 0 9px currentColor;
}

.role-badge--tester {
  color: #22d3ee;
  border-color: rgba(34, 211, 238, 0.25);
  background: rgba(34, 211, 238, 0.05);
}

.role-badge--admin {
  color: #facc15;
  border-color: rgba(250, 204, 21, 0.25);
  background: rgba(250, 204, 21, 0.05);
}

/* =========================================================
   ERROR
========================================================= */

.alert {
  display: flex;
  align-items: center;
  gap: 12px;

  margin-bottom: 20px;
  padding: 13px 15px;

  border-radius: 10px;
}

.alert--error {
  color: #fca5a5;

  background: rgba(239, 68, 68, 0.07);
  border: 1px solid rgba(239, 68, 68, 0.22);
}

.alert__icon {
  width: 30px;
  height: 30px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  border-radius: 8px;

  background: rgba(239, 68, 68, 0.1);
}

.alert__content {
  display: flex;
  flex-direction: column;
  gap: 2px;

  font-size: 12px;
}

.alert__content strong {
  color: var(--text);
  font-size: 12px;
}

/* =========================================================
   STATS
========================================================= */

.stats {
  margin-bottom: 30px;
}

.stats__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.stat-card {
  position: relative;

  min-height: 126px;
  overflow: hidden;

  padding: 17px;

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 12px;

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);

  border-color: var(--border-hover);

  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.stat-card::after {
  content: '';

  position: absolute;

  right: -35px;
  bottom: -55px;

  width: 130px;
  height: 130px;

  border-radius: 50%;

  background: currentColor;

  opacity: 0.045;

  filter: blur(12px);

  pointer-events: none;
}

.stat-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-card__icon {
  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  border: 1px solid currentColor;
  border-radius: 8px;

  background: currentColor;

  color: inherit;

  background: color-mix(
      in srgb,
      currentColor 8%,
      transparent
  );
}

.stat-card__eyebrow {
  color: var(--text-muted);

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 1.3px;
}

.stat-card__value {
  margin-top: 14px;

  font-size: 27px;
  font-weight: 900;

  line-height: 1;

  letter-spacing: -1px;

  color: var(--text);
}

.stat-card__label {
  margin-top: 6px;

  color: var(--text-dim);

  font-size: 10px;
  font-weight: 700;

  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-card--purple {
  color: #a78bfa;
}

.stat-card--blue {
  color: #60a5fa;
}

.stat-card--green {
  color: #4ade80;
}

.stat-card--gold {
  color: #facc15;
}

/* =========================================================
   TOOLBAR
========================================================= */

.toolbar {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) auto auto;
  align-items: end;
  gap: 18px;

  margin-bottom: 18px;
  padding-bottom: 15px;

  border-bottom: 1px solid var(--border);
}

.toolbar__title {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 9px;

  margin: 0;

  font-size: 18px;
  font-weight: 850;
  letter-spacing: -0.4px;
}

.priority-counter {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 4px 7px;

  color: #facc15;

  background: rgba(250, 204, 21, 0.08);

  border: 1px solid rgba(250, 204, 21, 0.2);
  border-radius: 5px;

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 0.7px;
}

/* =========================================================
   TABS
========================================================= */

.tabs {
  display: flex;
  align-items: center;
  gap: 3px;
}

.tabs__item {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  min-height: 34px;

  padding: 0 11px;

  color: var(--text-dim);
  background: transparent;

  border: 1px solid transparent;
  border-radius: 7px;

  cursor: pointer;

  font-size: 10px;
  font-weight: 800;

  transition:
      color 0.2s ease,
      background 0.2s ease,
      border-color 0.2s ease;
}

.tabs__item:hover {
  color: var(--text);

  background: var(--bg-card);
  border-color: var(--border);
}

.tabs__item--active {
  color: var(--text);

  background: rgba(124, 58, 237, 0.13);
  border-color: rgba(124, 58, 237, 0.35);

  box-shadow:
      inset 0 0 18px rgba(124, 58, 237, 0.05);
}

.tabs__item--active svg {
  color: var(--accent-light, #a78bfa);
}

/* =========================================================
   MODE FILTER
========================================================= */

.mode-filter {
  display: flex;
  align-items: center;

  height: 34px;

  padding: 0 8px;

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 7px;
}

.mode-filter__icon {
  display: flex;

  color: var(--text-muted);
}

.mode-filter select {
  height: 100%;

  padding: 0 4px;

  color: var(--text);
  background: transparent;

  border: 0;
  outline: none;

  font-size: 10px;
  font-weight: 700;

  cursor: pointer;
}

.mode-filter option {
  color: var(--text);
  background: var(--bg-card);
}

/* =========================================================
   TEST LIST
========================================================= */

.tests__head {
  display: flex;
  align-items: center;
  gap: 10px;

  margin-bottom: 8px;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 800;

  letter-spacing: 1px;
  text-transform: uppercase;
}

.tests__head-line {
  width: 30px;
  height: 1px;

  background: var(--border);
}

.tests__head-status {
  margin-left: auto;

  color: #4ade80;

  font-size: 8px;
  letter-spacing: 1.2px;
}

.tests__list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

/* =========================================================
   TEST CARD
========================================================= */

.test-card {
  position: relative;

  display: flex;
  align-items: center;
  gap: 13px;

  min-height: 78px;

  padding: 10px 12px 10px 15px;

  overflow: hidden;

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 11px;

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease,
      box-shadow 0.2s ease;
}

.test-card:hover {
  transform: translateY(-2px);

  border-color: var(--border-hover);

  background:
      linear-gradient(
          90deg,
          var(--bg-card),
          rgba(124, 58, 237, 0.025)
      );

  box-shadow:
      0 10px 28px rgba(0, 0, 0, 0.16);
}

.test-card__accent {
  position: absolute;

  left: 0;
  top: 10px;
  bottom: 10px;

  width: 2px;

  border-radius: 0 3px 3px 0;

  background: var(--accent);

  opacity: 0;

  transition: opacity 0.2s ease;
}

.test-card:hover .test-card__accent {
  opacity: 1;
}

/* statuses */

.test-card--in_progress {
  border-color: rgba(96, 165, 250, 0.18);
}

.test-card--completed {
  opacity: 0.72;
}

.test-card--completed:hover {
  opacity: 1;
}

.test-card--mine {
  border-color: rgba(124, 58, 237, 0.3);

  background:
      linear-gradient(
          90deg,
          rgba(124, 58, 237, 0.06),
          var(--bg-card) 40%
      );
}

/* =========================================================
   PRIORITY CARD
========================================================= */

.test-card--priority {
  border-color: rgba(250, 204, 21, 0.28);

  background:
      linear-gradient(
          90deg,
          rgba(250, 204, 21, 0.065),
          rgba(124, 58, 237, 0.025) 45%,
          var(--bg-card) 85%
      );

  box-shadow:
      inset 3px 0 0 #facc15,
      0 5px 25px rgba(250, 204, 21, 0.045);
}

.test-card--priority:hover {
  border-color: rgba(250, 204, 21, 0.5);

  box-shadow:
      inset 3px 0 0 #facc15,
      0 12px 35px rgba(250, 204, 21, 0.09);
}

.test-card__priority-glow {
  position: absolute;

  top: -100px;
  right: 15%;

  width: 220px;
  height: 220px;

  border-radius: 50%;

  background: rgba(250, 204, 21, 0.055);

  filter: blur(30px);

  pointer-events: none;
}

/* =========================================================
   AVATAR
========================================================= */

.test-avatar {
  position: relative;

  width: 44px;
  height: 44px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  overflow: visible;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #5b21b6
      );

  border: 1px solid rgba(167, 139, 250, 0.25);
  border-radius: 10px;

  font-size: 15px;
  font-weight: 900;

  box-shadow:
      0 5px 18px rgba(124, 58, 237, 0.14);
}

.test-avatar__img {
  width: 100%;
  height: 100%;

  object-fit: cover;

  border-radius: 9px;
}

.test-avatar--priority {
  border-color: rgba(250, 204, 21, 0.5);

  box-shadow:
      0 0 0 2px rgba(250, 204, 21, 0.05),
      0 0 20px rgba(250, 204, 21, 0.15);
}

.test-avatar__priority {
  position: absolute;

  right: -5px;
  bottom: -5px;

  width: 18px;
  height: 18px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #17120a;

  background: #facc15;

  border: 2px solid var(--bg-card);
  border-radius: 50%;

  box-shadow:
      0 0 10px rgba(250, 204, 21, 0.35);
}

/* =========================================================
   MAIN INFO
========================================================= */

.test-card__main {
  flex: 1;
  min-width: 200px;

  position: relative;
  z-index: 1;
}

.test-card__name-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;

  margin-bottom: 5px;
}

.test-card__name {
  color: var(--text);

  font-size: 13px;
  font-weight: 800;
}

.clan {
  margin-right: 3px;

  color: var(--accent-light, #a78bfa);

  font-size: 11px;
  font-weight: 900;
}

/* =========================================================
   PRIORITY BADGE
========================================================= */

.priority-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  height: 19px;

  padding: 0 6px 0 4px;

  color: #facc15;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.13),
          rgba(250, 204, 21, 0.045)
      );

  border: 1px solid rgba(250, 204, 21, 0.25);
  border-radius: 5px;

  box-shadow:
      0 0 12px rgba(250, 204, 21, 0.06);
}

.priority-badge__icon {
  width: 13px;
  height: 13px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #17120a;

  background: #facc15;

  border-radius: 3px;
}

.priority-badge__text {
  font-size: 8px;
  font-weight: 1000;

  letter-spacing: 0.8px;
}

.priority-badge__position {
  margin-left: 1px;

  color: #fde68a;

  font-size: 8px;
  font-weight: 800;
}

/* =========================================================
   META
========================================================= */

.test-card__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;

  color: var(--text-muted);

  font-size: 10px;
}

.mode {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 3px 7px;

  border: 1px solid transparent;
  border-radius: 5px;

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.mode__dot {
  width: 4px;
  height: 4px;

  border-radius: 50%;

  background: currentColor;
  box-shadow: 0 0 5px currentColor;
}

.mode--pvp {
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.08);
  border-color: rgba(124, 58, 237, 0.16);
}

.mode--bedwars {
  color: #22d3ee;
  background: rgba(6, 182, 212, 0.08);
  border-color: rgba(6, 182, 212, 0.16);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.meta-item__label {
  color: var(--text-muted);

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 0.8px;
}

.meta-separator {
  color: var(--text-muted);
  opacity: 0.4;
}

.meta-contact {
  color: #818cf8;

  white-space: nowrap;
}

.meta-contact b {
  font-weight: 800;
}

.meta-time {
  color: #fbbf24;
}

/* =========================================================
   PRIORITY INFO
========================================================= */

.priority-info {
  position: relative;
  z-index: 1;

  display: flex;
  flex-direction: column;

  min-width: 105px;

  padding-right: 8px;
}

.priority-info__label {
  color: #facc15;

  font-size: 8px;
  font-weight: 1000;

  letter-spacing: 1px;
}

.priority-info__text {
  margin-top: 3px;

  color: var(--text-muted);

  font-size: 8px;
  font-weight: 700;

  white-space: nowrap;
}

/* =========================================================
   STATUS
========================================================= */

.test-card__status {
  position: relative;
  z-index: 1;

  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;

  flex-shrink: 0;
}

.status {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 4px 8px;

  border: 1px solid transparent;
  border-radius: 5px;

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.status--pending {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.08);
  border-color: rgba(251, 191, 36, 0.14);
}

.status--in_progress {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.08);
  border-color: rgba(96, 165, 250, 0.14);
}

.status--completed {
  color: #4ade80;
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.14);
}

.status--cancelled {
  color: #6b7280;
  background: rgba(107, 114, 128, 0.08);
  border-color: rgba(107, 114, 128, 0.14);
}

.result {
  display: flex;
  align-items: baseline;
  gap: 5px;
}

.result__tier {
  color: var(--accent-light, #a78bfa);

  font-size: 15px;
  font-weight: 1000;
}

.result__score {
  color: var(--text-dim);

  font-size: 9px;
  font-weight: 700;
}

/* =========================================================
   OPEN BUTTON
========================================================= */

.btn-open {
  position: relative;
  z-index: 1;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  min-width: 92px;
  height: 34px;

  padding: 0 12px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          var(--accent),
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, 0.2);
  border-radius: 7px;

  cursor: pointer;

  font-size: 9px;
  font-weight: 900;

  transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;

  white-space: nowrap;
}

.btn-open:hover {
  transform: translateY(-1px);

  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent)
      );

  box-shadow:
      0 7px 20px rgba(124, 58, 237, 0.22);
}

.btn-open--priority {
  color: #17120a;

  background:
      linear-gradient(
          135deg,
          #fde047,
          #eab308
      );

  border-color: rgba(250, 204, 21, 0.4);

  box-shadow:
      0 5px 18px rgba(250, 204, 21, 0.1);
}

.btn-open--priority:hover {
  background:
      linear-gradient(
          135deg,
          #fef08a,
          #facc15
      );

  box-shadow:
      0 8px 24px rgba(250, 204, 21, 0.2);
}

/* =========================================================
   STATE
========================================================= */

.state-card {
  min-height: 260px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  padding: 40px 20px;

  text-align: center;

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.055),
          transparent 50%
      ),
      var(--bg-card);

  border: 1px dashed var(--border);
  border-radius: 13px;
}

.state-card strong {
  margin-top: 14px;

  color: var(--text);

  font-size: 14px;
  font-weight: 800;
}

.state-card p {
  max-width: 360px;

  margin: 7px 0 0;

  color: var(--text-dim);

  font-size: 11px;
  line-height: 1.5;
}

.state-card--empty .eyebrow {
  margin-top: 15px;
}

.state-card__icon {
  width: 58px;
  height: 58px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--accent-light, #a78bfa);

  background: rgba(124, 58, 237, 0.08);

  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 14px;

  box-shadow:
      0 0 30px rgba(124, 58, 237, 0.08);
}

.state-card__loader {
  display: flex;
  gap: 5px;
}

.state-card__loader span {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: var(--accent-light, #a78bfa);

  animation: loading-dot 1s infinite ease-in-out;
}

.state-card__loader span:nth-child(2) {
  animation-delay: 0.12s;
}

.state-card__loader span:nth-child(3) {
  animation-delay: 0.24s;
}

@keyframes loading-dot {
  0%,
  60%,
  100% {
    transform: translateY(0);
    opacity: 0.35;
  }

  30% {
    transform: translateY(-5px);
    opacity: 1;
  }
}

/* =========================================================
   BUTTONS
========================================================= */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  min-height: 36px;

  padding: 0 14px;

  border-radius: 8px;

  text-decoration: none;

  font-size: 11px;
  font-weight: 800;

  transition: all 0.2s ease;
}

.btn--secondary {
  color: var(--text);

  background: var(--bg-card);

  border: 1px solid var(--border);
}

.btn--secondary:hover {
  border-color: var(--border-hover);

  background: var(--bg-card-hover, var(--bg-card));

  transform: translateY(-1px);
}

/* =========================================================
   DENIED
========================================================= */

.denied {
  position: relative;

  min-height: 65vh;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  padding: 40px 20px;

  overflow: hidden;

  text-align: center;
}

.denied__glow {
  position: absolute;

  width: 300px;
  height: 300px;

  border-radius: 50%;

  background: rgba(124, 58, 237, 0.08);

  filter: blur(60px);

  pointer-events: none;
}

.denied__icon {
  position: relative;

  width: 72px;
  height: 72px;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 20px;

  color: #f87171;

  background: rgba(239, 68, 68, 0.07);

  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 18px;

  box-shadow:
      0 0 35px rgba(239, 68, 68, 0.08);
}

.denied .eyebrow {
  color: #f87171;
}

.denied__code {
  position: relative;

  margin: 0;

  font-size: clamp(72px, 12vw, 120px);
  font-weight: 1000;
  line-height: 0.9;

  letter-spacing: -7px;

  color: var(--text);
}

.denied__title {
  position: relative;

  margin: 18px 0 0;

  font-size: 20px;
  font-weight: 850;
}

.denied__text {
  position: relative;

  margin: 8px 0 22px;

  color: var(--text-dim);

  font-size: 12px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {
  .stats__grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .toolbar {
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .tabs {
    overflow-x: auto;
  }

  .mode-filter {
    width: max-content;
  }
}

@media (max-width: 800px) {
  .tester-page {
    width: min(100% - 24px, 700px);
    padding-top: 24px;
  }

  .tester-head__title-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .role-badge {
    align-self: flex-start;
  }

  .test-card {
    align-items: flex-start;
    flex-wrap: wrap;

    padding: 12px;
  }

  .test-card__main {
    min-width: calc(100% - 60px);
  }

  .priority-info {
    width: 100%;
    min-width: 0;

    padding: 8px 0 0 57px;

    flex-direction: row;
    align-items: center;
    gap: 8px;
  }

  .priority-info__text {
    margin-top: 0;
  }

  .test-card__status {
    margin-left: 57px;

    align-items: flex-start;
  }

  .btn-open {
    width: calc(100% - 57px);

    margin-left: 57px;
  }
}

@media (max-width: 560px) {
  .stats__grid {
    grid-template-columns: 1fr 1fr;
    gap: 7px;
  }

  .stat-card {
    min-height: 105px;
    padding: 13px;
  }

  .stat-card__value {
    font-size: 23px;
  }

  .stat-card__label {
    font-size: 8px;
  }

  .tabs {
    width: 100%;
  }

  .tabs__item {
    flex: 1;

    padding: 0 7px;

    justify-content: center;

    font-size: 9px;
  }

  .tabs__item span {
    display: none;
  }

  .mode-filter {
    width: 100%;
  }

  .mode-filter select {
    flex: 1;
  }

  .test-card__meta {
    gap: 6px;
  }

  .meta-contact,
  .meta-time {
    max-width: 100%;

    overflow: hidden;
    text-overflow: ellipsis;
  }

  .test-card__name {
    font-size: 12px;
  }

  .priority-badge__text {
    font-size: 7px;
  }
}

@media (max-width: 400px) {
  .tester-page {
    width: calc(100% - 18px);
  }

  .stats__grid {
    grid-template-columns: 1fr;
  }

  .tester-head__title {
    font-size: 30px;
  }

  .test-avatar {
    width: 40px;
    height: 40px;
  }

  .test-card__main {
    min-width: calc(100% - 53px);
  }

  .test-card__status,
  .btn-open,
  .priority-info {
    margin-left: 53px;
  }
}
</style>