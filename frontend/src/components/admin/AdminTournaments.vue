<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/admin.js'
import AdminTournamentForm from './AdminTournamentForm.vue'
import AdminTournamentParticipants from './AdminTournamentParticipants.vue'
import AdminTournamentBracket from './AdminTournamentBracket.vue'

const tournaments = ref([])
const loading = ref(true)
const statusFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

const showForm = ref(false)
const editingTournament = ref(null)

const showParticipants = ref(false)
const participantsTournament = ref(null)

const showBracket = ref(false)
const bracketTournament = ref(null)

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (statusFilter.value) params.status = statusFilter.value

    const data = await adminApi.tournaments(params)
    tournaments.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } finally {
    loading.value = false
  }
}

watch(statusFilter, () => { page.value = 1; load() })
watch(page, load)

const statusLabels = {
  draft: 'Черновик',
  registration: 'Регистрация',
  ongoing: 'Идёт',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

function openCreate() {
  editingTournament.value = null
  showForm.value = true
}

function openEdit(t) {
  editingTournament.value = t
  showForm.value = true
}

function openParticipants(t) {
  participantsTournament.value = t
  showParticipants.value = true
}

function openBracket(t) {
  bracketTournament.value = t
  showBracket.value = true
}

async function destroy(t) {
  if (!confirm(`Удалить турнир «${t.name}»?`)) return
  if (!confirm('Точно уверен? Все матчи и участники будут удалены.')) return
  await adminApi.destroyTournament(t.id)
  await load()
}

function onUpdated() {
  showForm.value = false
  showParticipants.value = false
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="head">
      <div class="filters">
        <select v-model="statusFilter" class="select">
          <option value="">Все статусы</option>
          <option value="draft">Черновики</option>
          <option value="registration">Регистрация</option>
          <option value="ongoing">Идут</option>
          <option value="completed">Завершены</option>
          <option value="cancelled">Отменены</option>
        </select>

        <span class="stats">Найдено: <b>{{ total }}</b></span>
      </div>

      <button class="btn-create" @click="openCreate">
        + Создать турнир
      </button>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!tournaments.length" class="empty">Турниров нет</div>

    <div v-else class="list">
      <div
          v-for="t in tournaments"
          :key="t.id"
          class="row"
          :class="`row--${t.status}`"
      >
        <div class="row__main">
          <div class="row__badges">
                        <span class="badge badge--type">
                            {{ t.type === 'solo' ? '1 vs 1' : 'Клан vs Клан' }}
                        </span>
            <span class="badge" :class="`badge--${t.status}`">
                            {{ statusLabels[t.status] }}
                        </span>
            <span class="badge badge--format">
                            {{ t.format === 'single_elim' ? 'SE' : t.format === 'double_elim' ? 'DE' : 'RR' }}
                        </span>
          </div>

          <RouterLink :to="`/tournaments/${t.id}`" class="row__name">
            {{ t.name }}
          </RouterLink>

          <div class="row__meta">
            <span>👥 {{ t.participants_count ?? 0 }} / {{ t.max_participants }}</span>
            <span v-if="Number(t.prize_pool) > 0">
                            💰 {{ Number(t.prize_pool).toLocaleString('ru-RU') }} {{ t.prize_currency }}
                        </span>
            <span v-if="t.min_tier || t.max_tier">
                            🎯 {{ t.min_tier || 'E' }}–{{ t.max_tier || 'S' }}
                        </span>
          </div>
        </div>

        <div class="row__actions">
          <button class="btn-action" @click="openParticipants(t)">
            👥 Участники
          </button>
          <button class="btn-action" @click="openBracket(t)">
            🏆 Сетка
          </button>
          <button class="btn-action" @click="openEdit(t)">
            ⚙️ Изменить
          </button>
          <button class="btn-action danger" @click="destroy(t)">
            🗑
          </button>
        </div>
      </div>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="page <= 1" @click="page--">← Назад</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="page++">Вперёд →</button>
    </div>

    <AdminTournamentForm
        v-if="showForm"
        :tournament="editingTournament"
        @close="showForm = false"
        @updated="onUpdated"
    />

    <AdminTournamentParticipants
        v-if="showParticipants && participantsTournament"
        :tournament="participantsTournament"
        @close="showParticipants = false"
        @updated="onUpdated"
    />

    <AdminTournamentBracket
        v-if="showBracket && bracketTournament"
        :tournament="bracketTournament"
        @close="showBracket = false"
        @updated="onUpdated"
    />
  </div>
</template>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.filters {
  display: flex;
  align-items: center;
  gap: 12px;
}

.select {
  min-height: 40px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
}

.stats {
  color: var(--text-dim);
  font-size: 13px;
}

.stats b {
  color: var(--text);
}

.btn-create {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.btn-create:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.row__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.row__badges {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border: 1px solid;
}

.badge--type {
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.1);
  border-color: rgba(124, 58, 237, 0.3);
}

.badge--format {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.04);
  border-color: var(--border);
}

.badge--draft { color: var(--text-dim); background: rgba(255, 255, 255, 0.04); border-color: var(--border); }
.badge--registration { color: #4ade80; background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.3); }
.badge--ongoing { color: #fbbf24; background: rgba(251, 191, 36, 0.1); border-color: rgba(251, 191, 36, 0.3); }
.badge--completed { color: #60a5fa; background: rgba(96, 165, 250, 0.1); border-color: rgba(96, 165, 250, 0.3); }
.badge--cancelled { color: #f87171; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }

.row__name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
}

.row__name:hover {
  color: var(--accent-light);
}

.row__meta {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  color: var(--text-dim);
  font-size: 12px;
}

.row__actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.btn-action {
  padding: 7px 12px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.btn-action:hover {
  color: var(--text);
  border-color: var(--border-hover);
  background: rgba(255, 255, 255, 0.03);
}

.btn-action.danger:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 24px;
}

.pagination button {
  min-height: 36px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination span {
  color: var(--text-dim);
  font-size: 13px;
}

@media (max-width: 900px) {
  .row {
    flex-direction: column;
    align-items: flex-start;
  }

  .row__actions {
    width: 100%;
    flex-wrap: wrap;
  }
}
</style>