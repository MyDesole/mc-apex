<script setup>
/**
 * Страница турнира: большой баннер с призом и кнопкой участия,
 * обратный отсчёт до конца регистрации или старта, цифры турнира,
 * участники с аватарками, чемпион и сетка.
 */
import PageLoader from '@/components/core/PageLoader.vue'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { tournamentsApi } from '@/services/tournaments/tournaments.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AdminTournamentBracket from '@/components/admin/AdminTournamentBracket.vue'
import PageState from '@/components/core/PageState.vue'
import UserName from '@/components/players/UserName.vue'
import { clanLink, userLink } from '@/utils/links.js'
import { rankColor, rankLabel } from '@/utils/playerStyling.js'

const route = useRoute()
const auth = useAuthStore()

const data = ref(null)
const loading = ref(true)
const registering = ref(false)
const error = ref('')

const tournament = computed(() => data.value?.tournament)
const myParticipation = computed(() => data.value?.my_participation)
const registrationOpen = computed(() => data.value?.registration_open)

const STATUS = {
  draft: { label: 'Черновик', tone: 'muted' },
  registration: { label: 'Регистрация открыта', tone: 'open' },
  ongoing: { label: 'Идёт сейчас', tone: 'live' },
  completed: { label: 'Завершён', tone: 'done' },
  cancelled: { label: 'Отменён', tone: 'muted' },
}

const MY_STATUS = {
  pending: 'Заявка на рассмотрении',
  approved: 'Вы в турнире',
  rejected: 'Заявка отклонена',
  withdrawn: 'Заявка отозвана',
}

const status = computed(() => STATUS[tournament.value?.status] ?? STATUS.draft)

const approved = computed(() => (tournament.value?.participants ?? []).filter((p) => p.status === 'approved'))
const pending = computed(() => (tournament.value?.participants ?? []).filter((p) => p.status === 'pending'))

const fill = computed(() => {
  const max = tournament.value?.max_participants || 0
  return max ? Math.min(100, Math.round((approved.value.length / max) * 100)) : 0
})

const prize = computed(() => {
  const amount = Number(tournament.value?.prize_pool || 0)
  return amount > 0 ? `${amount.toLocaleString('ru-RU')} ${tournament.value.prize_currency || ''}`.trim() : null
})

/* ---------- Обратный отсчёт ---------- */

const now = ref(Date.now())
let clock = null

const countdown = computed(() => {
  const t = tournament.value
  if (!t) return null

  let target = null
  let label = ''

  if (t.status === 'registration' && t.registration_ends_at) {
    target = new Date(t.registration_ends_at).getTime()
    label = 'До конца регистрации'
  } else if (t.starts_at && new Date(t.starts_at).getTime() > now.value) {
    target = new Date(t.starts_at).getTime()
    label = 'До старта'
  }

  if (!target || target <= now.value) return null

  const left = target - now.value
  return {
    label,
    parts: [
      [Math.floor(left / 86400000), 'дн'],
      [Math.floor((left % 86400000) / 3600000), 'ч'],
      [Math.floor((left % 3600000) / 60000), 'мин'],
      [Math.floor((left % 60000) / 1000), 'сек'],
    ],
  }
})

function date(value, withTime = true) {
  if (!value) return '—'
  return new Date(value).toLocaleString('ru-RU', {
    day: 'numeric',
    month: 'long',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

/* ---------- Данные и действия ---------- */

/** Ошибка загрузки: 404 или прочая — без неё страница оставалась пустой. */
const loadError = ref(null)

async function load() {
  loading.value = !data.value
  loadError.value = null
  try {
    data.value = await tournamentsApi.show(route.params.id)
  } catch (e) {
    loadError.value = e?.status === 404 ? 'notFound' : 'error'
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

function participantLink(p) {
  return p.user ? userLink(p.user) : clanLink(p.clan)
}

onMounted(() => {
  load()
  clock = setInterval(() => { now.value = Date.now() }, 1000)
})

onUnmounted(() => clearInterval(clock))
</script>

<template>
  <PageLoader v-if="loading" />

  <div v-else-if="tournament" class="tp" :class="`tp--${status.tone}`">
    <!-- ===================== ГЕРОЙ ===================== -->
    <header class="tp-hero">
      <div
          class="tp-hero__bg"
          :style="tournament.banner_url ? { backgroundImage: `url(${tournament.banner_url})` } : null"
      ></div>
      <div class="tp-hero__shade"></div>
      <div class="tp-hero__grid" aria-hidden="true"></div>

      <div class="tp-hero__inner">
        <div class="tp-hero__main">
          <div class="tp-chips">
            <span class="tp-chip tp-chip--status"><i></i>{{ status.label }}</span>
            <span class="tp-chip">{{ tournament.type === 'solo' ? '⚔ 1 на 1' : '⛨ Клан на клан' }}</span>
            <span v-if="tournament.min_tier || tournament.max_tier" class="tp-chip">
              Тиры {{ tournament.min_tier || 'E' }}–{{ tournament.max_tier || 'S' }}
            </span>
          </div>

          <h1 class="tp-title">{{ tournament.name }}</h1>

          <!-- Отсчёт -->
          <div v-if="countdown" class="tp-count">
            <span class="tp-count__label">{{ countdown.label }}</span>
            <div class="tp-count__digits">
              <span v-for="[value, unit] in countdown.parts" :key="unit">
                <b>{{ String(value).padStart(2, '0') }}</b>{{ unit }}
              </span>
            </div>
          </div>

          <!-- Участие -->
          <div class="tp-cta">
            <p v-if="error" class="tp-cta__error">{{ error }}</p>

            <template v-if="!myParticipation">
              <button
                  v-if="registrationOpen && auth.isAuthenticated"
                  class="tp-btn tp-btn--primary"
                  :disabled="registering"
                  @click="register"
              >{{ registering ? 'Отправляем…' : 'Участвовать' }}</button>
              <RouterLink v-else-if="registrationOpen" to="/login" class="tp-btn tp-btn--primary">Войти и участвовать</RouterLink>
              <span v-else class="tp-cta__note">Регистрация закрыта</span>
            </template>

            <template v-else>
              <span class="tp-mine" :class="`tp-mine--${myParticipation.status}`">
                {{ MY_STATUS[myParticipation.status] ?? myParticipation.status }}
              </span>
              <button v-if="myParticipation.status === 'pending'" class="tp-btn" @click="withdraw">Отменить заявку</button>
            </template>
          </div>
        </div>

        <!-- Приз -->
        <aside class="tp-prize">
          <span class="tp-prize__cup" aria-hidden="true">🏆</span>
          <span class="tp-prize__label">Призовой фонд</span>
          <strong class="tp-prize__value">{{ prize || 'Слава и титул' }}</strong>
          <p v-if="tournament.prize_description" class="tp-prize__desc">{{ tournament.prize_description }}</p>
        </aside>
      </div>
    </header>

    <!-- ===================== ЦИФРЫ ===================== -->
    <section class="tp-stats">
      <div class="tp-stat">
        <span class="tp-stat__label">Участники</span>
        <strong>{{ approved.length }} <small>/ {{ tournament.max_participants }}</small></strong>
        <div class="tp-stat__bar"><i :style="{ width: `${fill}%` }"></i></div>
      </div>
      <div class="tp-stat">
        <span class="tp-stat__label">Старт</span>
        <strong>{{ date(tournament.starts_at) }}</strong>
      </div>
      <div class="tp-stat">
        <span class="tp-stat__label">Регистрация до</span>
        <strong>{{ date(tournament.registration_ends_at) }}</strong>
      </div>
      <div class="tp-stat">
        <span class="tp-stat__label">Заявки</span>
        <strong>{{ pending.length }} <small>на рассмотрении</small></strong>
      </div>
    </section>

    <!-- ===================== ЧЕМПИОН ===================== -->
    <section v-if="tournament.champion" class="tp-champion">
      <div class="tp-champion__rays" aria-hidden="true"></div>
      <span class="tp-champion__avatar">
        <img v-if="tournament.champion.avatar_url" :src="tournament.champion.avatar_url" :alt="tournament.champion.username" />
        <template v-else>🏆</template>
      </span>
      <div>
        <span class="tp-champion__label">Чемпион турнира</span>
        <RouterLink
            v-if="tournament.champion.username"
            :to="`/user/${encodeURIComponent(tournament.champion.username)}`"
            class="tp-champion__name"
        >{{ tournament.champion.username }}</RouterLink>
        <RouterLink v-else :to="`/clans/${tournament.champion.clan_id}`" class="tp-champion__name">
          [{{ tournament.champion.tag }}] {{ tournament.champion.name }}
        </RouterLink>
      </div>
    </section>

    <div class="tp-layout">
      <div class="tp-col">
        <!-- Описание -->
        <section class="tp-card">
          <h2 class="tp-card__title">О турнире</h2>
          <p class="tp-desc">{{ tournament.description || 'Описание пока не добавили.' }}</p>
        </section>

        <!-- Сетка -->
        <section class="tp-card">
          <header class="tp-card__head">
            <h2 class="tp-card__title">Турнирная сетка</h2>
            <span v-if="tournament.matches?.length" class="tp-card__count">{{ tournament.matches.length }} матчей</span>
          </header>
          <AdminTournamentBracket
              v-if="tournament.matches?.length"
              :matches="tournament.matches"
              :tournament-type="tournament.type"
          />
          <p v-else class="tp-empty">Сетка появится, когда закроется регистрация.</p>
        </section>
      </div>

      <!-- Участники -->
      <aside class="tp-card tp-people">
        <header class="tp-card__head">
          <h2 class="tp-card__title">Участники</h2>
          <span class="tp-card__count">{{ approved.length }}</span>
        </header>

        <p v-if="!approved.length && !pending.length" class="tp-empty">Пока никто не записался — будь первым!</p>

        <RouterLink
            v-for="(p, i) in [...approved, ...pending]"
            :key="p.id"
            :to="participantLink(p)"
            class="tp-person"
            :class="{ 'tp-person--pending': p.status !== 'approved' }"
            :style="{ '--i': i }"
        >
          <span class="tp-person__avatar" :style="p.clan ? { background: p.clan.banner_color } : null">
            <img v-if="p.user?.avatar_url || p.clan?.avatar_url" :src="p.user?.avatar_url || p.clan?.avatar_url" alt="" loading="lazy" />
            <template v-else>{{ (p.user?.username || p.clan?.tag || '?').charAt(0).toUpperCase() }}</template>
          </span>
          <span class="tp-person__info">
            <span class="tp-person__name">
              <UserName v-if="p.user" :user="p.user" />
              <template v-else>[{{ p.clan?.tag }}] {{ p.clan?.name }}</template>
            </span>
            <small>{{ p.status === 'approved' ? 'Участник' : 'На рассмотрении' }}</small>
          </span>
          <span
              v-if="p.user?.tier"
              class="tp-person__tier"
              :style="{ '--tier': rankColor(p.user) }"
          >{{ rankLabel(p.user) }}</span>
        </RouterLink>
      </aside>
    </div>
  </div>

  <PageState
      v-else
      :kind="loadError || 'notFound'"
      :title="loadError === 'error' ? null : 'Турнир не найден'"
      :action="{ to: '/tournaments', label: 'Все турниры' }"
  />
</template>

<style scoped>
@import "@/views/tournaments/TournamentView.css";
</style>
