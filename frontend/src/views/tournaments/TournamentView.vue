<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { tournamentsApi } from '@/services/tournaments/tournaments.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AdminTournamentBracket from "@/components/admin/AdminTournamentBracket.vue";

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
  if (!await confirmDialog('Снять заявку?')) return
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
    <section class="block">
      <header class="block-head">
        <h2>Турнирная сетка</h2>
        <span v-if="tournament.matches?.length" class="block-count">
            {{ tournament.matches.length }} матчей
        </span>
      </header>

      <AdminTournamentBracket
          :matches="tournament.matches ?? []"
          :tournament-type="tournament.type"
      />
    </section>
  </div>
</template>

<style scoped>
@import "@/views/tournaments/TournamentView.css";
</style>
