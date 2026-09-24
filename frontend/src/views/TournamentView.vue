<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { tournamentsApi } from '@/services/tournaments.js'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const data = ref(null)
const loading = ref(true)
const registering = ref(false)
const error = ref('')

const tournament = computed(() => data.value?.tournament)
const myParticipation = computed(() => data.value?.my_participation)
const registrationOpen = computed(() => data.value?.registration_open)

const statusLabels = {
  draft: 'Черновик',
  registration: 'Регистрация открыта',
  ongoing: 'Идёт',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

async function load() {
  loading.value = true
  try {
    data.value = await tournamentsApi.show(route.params.id)
  } finally {
    loading.value = false
  }
}

async function register() {
  registering.value = true
  error.value = ''
  try {
    await tournamentsApi.register(route.params.id)
    await load()
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    registering.value = false
  }
}

async function withdraw() {
  if (!confirm('Снять заявку?')) return
  await tournamentsApi.withdraw(route.params.id)
  await load()
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="loading">Загрузка...</div>

  <div v-else-if="tournament" class="tournament-page">
    <!-- HEADER -->
    <header class="tour-header">
      <div
          class="tour-banner"
          :style="tournament.banner_url ? { backgroundImage: `url(${tournament.banner_url})` } : {}"
      >
        <div class="tour-banner__overlay" />
        <div class="tour-banner__content">
          <div class="badges">
                        <span class="badge badge--type">
                            {{ tournament.type === 'solo' ? '1 vs 1' : 'Клан vs Клан' }}
                        </span>
            <span class="badge" :class="`badge--${tournament.status}`">
                            {{ statusLabels[tournament.status] }}
                        </span>
          </div>

          <h1>{{ tournament.name }}</h1>

          <div class="tour-meta">
                        <span v-if="tournament.prize_pool > 0">
                            💰 {{ Number(tournament.prize_pool).toLocaleString('ru-RU') }} {{ tournament.prize_currency }}
                        </span>
            <span v-if="tournament.min_tier || tournament.max_tier">
                            🎯 {{ tournament.min_tier || 'E' }}–{{ tournament.max_tier || 'S' }}
                        </span>
            <span>👥 {{ tournament.participants?.length ?? 0 }} / {{ tournament.max_participants }}</span>
          </div>
        </div>
      </div>
    </header>

    <!-- ОПИСАНИЕ -->
    <section class="block">
      <h2>Описание</h2>
      <p class="description">{{ tournament.description || 'Описание отсутствует.' }}</p>
    </section>

    <!-- ДЕЙСТВИЯ -->
    <section class="block actions-block">
      <div v-if="error" class="error">{{ error }}</div>

      <template v-if="!myParticipation">
        <button
            v-if="registrationOpen"
            class="btn-apply"
            :disabled="registering"
            @click="register"
        >
          {{ registering ? 'Отправка...' : 'Подать заявку' }}
        </button>
        <div v-else class="closed">
          Регистрация закрыта
        </div>
      </template>

      <template v-else>
        <div class="participation">
                    <span class="status" :class="`status-${myParticipation.status}`">
                        {{ myParticipation.status === 'pending' ? 'Заявка на рассмотрении' :
                        myParticipation.status === 'approved' ? 'Вы в турнире' :
                            myParticipation.status === 'rejected' ? 'Заявка отклонена' : 'Отозвано' }}
                    </span>

          <button
              v-if="myParticipation.status === 'pending'"
              class="btn-withdraw"
              @click="withdraw"
          >
            Отменить заявку
          </button>
        </div>
      </template>
    </section>

    <!-- УЧАСТНИКИ -->
    <section v-if="tournament.participants?.length" class="block">
      <h2>Участники ({{ tournament.participants.length }})</h2>

      <div class="participants">
        <div
            v-for="p in tournament.participants"
            :key="p.id"
            class="participant"
            :class="{ 'participant--pending': p.status !== 'approved' }"
        >
          <div v-if="p.user" class="participant__avatar">
            {{ p.user.username.charAt(0).toUpperCase() }}
          </div>
          <div
              v-else-if="p.clan"
              class="participant__avatar"
              :style="{ background: p.clan.banner_color }"
          >
            {{ p.clan.tag.charAt(0) }}
          </div>

          <div class="participant__info">
            <div class="participant__name">
              {{ p.user?.username ?? `[${p.clan?.tag}] ${p.clan?.name}` }}
            </div>
            <div class="participant__meta">
              {{ p.status === 'pending' ? 'На рассмотрении' : 'Участник' }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- СЕТКА -->
    <section v-if="tournament.matches?.length" class="block">
      <h2>Сетка турнира</h2>
      <div class="bracket-info">
        Сетка доступна на странице турнира. Раундов: {{ Math.max(...tournament.matches.map(m => m.round)) }}
      </div>
    </section>
  </div>
</template>

<style scoped>
/* стили аналогичны другим страницам — те же переменные */
.tournament-page {
  width: min(1000px, calc(100% - 40px));
  margin: 40px auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.tour-header {
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.tour-banner {
  position: relative;
  min-height: 240px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: flex-end;
}

.tour-banner__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.2));
}

.tour-banner__content {
  position: relative;
  padding: 28px;
  width: 100%;
}

.tour-banner h1 {
  margin: 12px 0 14px;
  font-size: 32px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -0.5px;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
}

.badges {
  display: flex;
  gap: 8px;
}

.badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  backdrop-filter: blur(8px);
}

.badge--type {
  color: #fff;
  background: rgba(0, 0, 0, 0.6);
}

.badge--registration { color: #4ade80; background: rgba(34, 197, 94, 0.25); }
.badge--ongoing { color: #fbbf24; background: rgba(251, 191, 36, 0.25); }
.badge--completed { color: #60a5fa; background: rgba(96, 165, 250, 0.25); }
.badge--cancelled { color: #f87171; background: rgba(239, 68, 68, 0.25); }
.badge--draft { color: #d1d5db; background: rgba(0, 0, 0, 0.5); }

.tour-meta {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
}

.block {
  padding: 20px 24px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
}

.block h2 {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 800;
}

.description {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
  line-height: 1.7;
  white-space: pre-wrap;
}

.actions-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.btn-apply {
  padding: 12px 24px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-apply:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.btn-apply:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-withdraw {
  padding: 8px 16px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-withdraw:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
}

.closed {
  padding: 12px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px dashed var(--border);
  border-radius: 10px;
  text-align: center;
  font-size: 13px;
}

.participation {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.status {
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
}

.status-pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.status-approved { color: #4ade80; background: rgba(34, 197, 94, 0.1); }
.status-rejected { color: #f87171; background: rgba(239, 68, 68, 0.1); }

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.participants {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px;
}

.participant {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.participant--pending {
  opacity: 0.5;
}

.participant__avatar {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 9px;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
  flex-shrink: 0;
}

.participant__name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}

.participant__meta {
  font-size: 11px;
  color: var(--text-muted);
}

.bracket-info {
  padding: 20px;
  color: var(--text-dim);
  text-align: center;
  font-size: 13px;
  background: #0d0d14;
  border: 1px dashed var(--border);
  border-radius: 10px;
}

.loading {
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
}
</style>