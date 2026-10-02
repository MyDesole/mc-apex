<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { testerApi } from '@/services/tiers/tester.js'
import TesterTierTestModal from '@/components/tiers/tester/TesterTierTestModal.vue'
import AppIcon from '@/components/core/AppIcon.vue'

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

    <TabTransition v-else :active="tab" tag="section">
      <section class="tests">

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
    </TabTransition>


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
@import "@/views/tiers/TesterView.css";
</style>
