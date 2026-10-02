<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import AdminTournamentForm from '@/components/admin/AdminTournamentForm.vue'
import AdminTournamentParticipants from '@/components/admin/AdminTournamentParticipants.vue'
import AdminTournamentBracket from '@/components/admin/AdminTournamentBracket.vue'

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
  if (!await confirmDialog(`Удалить турнир «${t.name}»?`)) return
  if (!await confirmDialog('Точно уверен? Все матчи и участники будут удалены.')) return
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
@import "@/components/admin/AdminTournaments.css";
</style>
