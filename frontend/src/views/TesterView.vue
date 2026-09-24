<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { testerApi } from '@/services/tester.js'
import TesterTierTestModal from '@/components/tester/TesterTierTestModal.vue'

const auth = useAuthStore()

const role = computed(() => auth.user?.role)
const isTester = computed(() => ['tester', 'admin'].includes(role.value))

const tests = ref([])
const stats = ref(null)
const loading = ref(true)
const tab = ref('free')          // free | mine | completed
const modeFilter = ref('')

const showModal = ref(false)
const selectedTest = ref(null)

async function load() {
  loading.value = true
  try {
    const params = {}
    if (tab.value === 'free') params.free = '1'
    if (tab.value === 'mine') { params.mine = '1'; params.status = 'in_progress' }
    if (tab.value === 'completed') { params.status = 'completed'; params.mine = '1' }
    if (modeFilter.value) params.mode = modeFilter.value

    const [testsRes, statsRes] = await Promise.all([
      testerApi.tierTests(params),
      testerApi.stats(),
    ])

    tests.value = testsRes.data
    stats.value = statsRes
  } finally {
    loading.value = false
  }
}

watch([tab, modeFilter], load)

function openTest(t) {
  selectedTest.value = t
  showModal.value = true
}

function onUpdated() {
  showModal.value = false
  load()
}

onMounted(() => {
  if (!isTester.value) {
    return
  }
  load()
})

const statusLabels = {
  pending: 'Ожидает',
  in_progress: 'В работе',
  completed: 'Завершён',
  cancelled: 'Отменён',
}
</script>

<template>
  <div v-if="!isTester" class="denied">
    <h1>403</h1>
    <p>У вас нет доступа к панели тестера.</p>
    <RouterLink to="/">На главную</RouterLink>
  </div>

  <div v-else class="tester-page">
    <!-- HEADER -->
    <header class="tester-head">
      <div>
        <h1>Панель тестера</h1>
        <p class="subtitle">Проводи тир-тесты и помогай игрокам расти</p>
      </div>

      <span class="role-badge" :class="`role-${role}`">
                {{ role === 'admin' ? 'Администратор' : 'Тестер' }}
            </span>
    </header>

    <!-- STATS -->
    <div v-if="stats" class="stats-grid">
      <div class="stat-card">
        <div class="stat-card__value">{{ stats.pending_total }}</div>
        <div class="stat-card__label">Свободных заявок</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__value">{{ stats.in_progress }}</div>
        <div class="stat-card__label">В работе у меня</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__value">{{ stats.today }}</div>
        <div class="stat-card__label">Проведено сегодня</div>
      </div>
      <div class="stat-card">
        <div class="stat-card__value">{{ stats.total_completed }}</div>
        <div class="stat-card__label">Всего проведено</div>
      </div>
    </div>

    <!-- TABS -->
    <nav class="tabs">
      <button :class="{ active: tab === 'free' }" @click="tab = 'free'">
        Свободные
      </button>
      <button :class="{ active: tab === 'mine' }" @click="tab = 'mine'">
        Мои в работе
      </button>
      <button :class="{ active: tab === 'completed' }" @click="tab = 'completed'">
        Проведённые
      </button>

      <select v-model="modeFilter" class="mode-select">
        <option value="">Все режимы</option>
        <option value="pvp">PvP</option>
        <option value="bedwars">BedWars</option>
      </select>
    </nav>

    <!-- LIST -->
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!tests.length" class="empty">
      Заявок нет
    </div>

    <div v-else class="list">
      <div
          v-for="t in tests"
          :key="t.id"
          class="row"
          :class="`row--${t.status}`"
      >
        <div class="row__avatar">
          <img
              v-if="t.user?.avatar_url"
              :src="t.user.avatar_url"
              class="avatar-img"
          />
          <template v-else>
            {{ t.user?.username?.charAt(0).toUpperCase() }}
          </template>
        </div>

        <div class="row__info">
          <div class="row__name">
                        <span v-if="t.user?.clan_member?.clan" class="clan-tag">
                            [{{ t.user.clan_member.clan.tag }}]
                        </span>
            {{ t.user?.username }}
          </div>
          <div class="row__meta">
    <span class="mode" :class="`mode-${t.mode}`">
        {{ t.mode === 'pvp' ? 'PvP' : 'BedWars' }}
    </span>
            <span class="tier">Тир: {{ t.user?.tier ?? '—' }}</span>
            <span>{{ t.user?.tier_score }}%</span>

            <template v-if="t.contact_value">
              <span class="sep">·</span>
              <span class="contact-inline">
            <b>{{ t.contact_type === 'discord' ? 'Discord' : 'Telegram' }}:</b>
            {{ t.contact_value }}
        </span>
            </template>

            <template v-if="t.preferred_time">
              <span class="sep">·</span>
              <span class="time-inline">
            {{ t.preferred_time }}
        </span>
            </template>
          </div>
        </div>

        <div class="row__status">
                    <span class="status" :class="`status-${t.status}`">
                        {{ statusLabels[t.status] }}
                    </span>
          <span v-if="t.status === 'completed' && t.result_tier" class="result">
                        <b class="tier-result">{{ t.result_tier }}</b>
                        <span>{{ t.result_score }}%</span>
                    </span>
        </div>

        <button class="btn-open" @click="openTest(t)">
          {{ t.status === 'in_progress' && t.claimed_by === auth.user.id ? 'Провести' :
            t.status === 'completed' ? 'Просмотр' :
                'Открыть' }}
        </button>
      </div>
    </div>

    <TesterTierTestModal
        v-if="showModal && selectedTest"
        :tier-test="selectedTest"
        @close="showModal = false"
        @updated="onUpdated"
    />
  </div>
</template>

<style scoped>
.tester-page {
  width: min(1100px, calc(100% - 40px));
  margin: 40px auto;
}

.tester-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 28px;
}

.tester-head h1 {
  margin: 0 0 6px;
  font-size: 28px;
  font-weight: 800;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

.role-badge {
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border: 1px solid;
}

.role-tester {
  color: #06b6d4;
  background: rgba(6, 182, 212, 0.1);
  border-color: rgba(6, 182, 212, 0.3);
}

.role-admin {
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border-color: rgba(250, 204, 21, 0.3);
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 28px;
}

.stat-card {
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  text-align: center;
}

.stat-card__value {
  font-size: 28px;
  font-weight: 900;
  color: var(--accent-light);
  letter-spacing: -1px;
  margin-bottom: 4px;
}

.stat-card__label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.4px;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.tabs button {
  padding: 12px 18px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s;
}

.tabs button:hover {
  color: var(--text);
}

.tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}

.mode-select {
  margin-left: auto;
  padding: 8px 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  font-size: 13px;
}

/* List */
.list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.row--in_progress {
  border-color: rgba(251, 191, 36, 0.3);
}

.row--completed {
  opacity: 0.75;
}

.row__avatar {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.row__info {
  flex: 1;
  min-width: 0;
}

.row__name {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 4px;
}

.clan-tag {
  color: var(--accent-light);
  font-weight: 800;
  margin-right: 4px;
}

.row__meta {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  color: var(--text-dim);
  font-size: 12px;
  align-items: center;
}

.mode {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
}
.contact-inline {
  color: #8895f5;
  font-size: 11px;
}

.time-inline {
  color: #fbbf24;
  font-size: 11px;
}
.mode-pvp {
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.1);
}

.mode-bedwars {
  color: #06b6d4;
  background: rgba(6, 182, 212, 0.1);
}

.sep {
  opacity: 0.4;
}

.row__status {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.status {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.status-pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.status-in_progress { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.status-completed { color: #4ade80; background: rgba(34, 197, 94, 0.1); }
.status-cancelled { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.result {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 12px;
}

.tier-result {
  font-size: 16px;
  font-weight: 900;
  color: var(--accent-light);
}

.btn-open {
  padding: 8px 16px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  flex-shrink: 0;
}

.btn-open:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.empty {
  padding: 60px;
  text-align: center;
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}

.denied {
  padding: 80px 20px;
  text-align: center;
}

.denied h1 {
  font-size: 72px;
  color: var(--danger);
  margin: 0;
}

.denied p {
  color: var(--text-dim);
  margin: 12px 0 20px;
}

.denied a {
  color: var(--accent-light);
  font-weight: 700;
}

@media (max-width: 800px) {
  .row {
    flex-wrap: wrap;
  }

  .row__status {
    width: 100%;
    align-items: flex-start;
  }

  .btn-open {
    width: 100%;
  }
}
</style>