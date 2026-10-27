<script setup>
/**
 * Турниры: герой-блок, главный турнир (открыта регистрация или идёт)
 * крупной карточкой с отсчётом, фильтры чипами и сетка карточек со
 * стопкой аватарок участников, заполненностью и датой старта.
 */
import PageLoader from '@/components/core/PageLoader.vue'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { tournamentsApi } from '@/services/tournaments/tournaments.js'

const tournaments = ref([])
const loading = ref(true)
const search = ref('')
const typeFilter = ref('')
const statusFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

let timer = null

async function load() {
  loading.value = !tournaments.value.length
  try {
    const params = { page: page.value }
    if (search.value) params.search = search.value
    if (typeFilter.value) params.type = typeFilter.value
    if (statusFilter.value) params.status = statusFilter.value

    const data = await tournamentsApi.list(params)
    tournaments.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; load() }, 300)
})

watch([typeFilter, statusFilter], () => { page.value = 1; load() })
watch(page, load)

const STATUS = {
  draft: { label: 'Черновик', tone: 'muted' },
  registration: { label: 'Регистрация', tone: 'open' },
  ongoing: { label: 'Идёт', tone: 'live' },
  completed: { label: 'Завершён', tone: 'done' },
  cancelled: { label: 'Отменён', tone: 'muted' },
}

const STATUS_FILTERS = [
  ['', 'Все'],
  ['registration', 'Регистрация'],
  ['ongoing', 'Идут'],
  ['completed', 'Завершены'],
]

const TYPE_FILTERS = [
  ['', 'Любой формат'],
  ['solo', '1 на 1'],
  ['clan', 'Клан на клан'],
]

// Главный турнир: первый, куда можно записаться или что идёт прямо сейчас
const featured = computed(() =>
    !search.value && !statusFilter.value && page.value === 1
        ? tournaments.value.find((t) => ['registration', 'ongoing'].includes(t.status)) ?? null
        : null,
)

const rest = computed(() => tournaments.value.filter((t) => t !== featured.value))

/* ---------- Отсчёт для главного турнира ---------- */

const now = ref(Date.now())
const clock = setInterval(() => { now.value = Date.now() }, 1000)
onUnmounted(() => clearInterval(clock))

function countdown(t) {
  const target = t.status === 'registration' && t.registration_ends_at
      ? new Date(t.registration_ends_at).getTime()
      : t.starts_at ? new Date(t.starts_at).getTime() : null

  if (!target || target <= now.value) return null

  const left = target - now.value
  return {
    label: t.status === 'registration' ? 'До конца регистрации' : 'До старта',
    parts: [
      [Math.floor(left / 86400000), 'дн'],
      [Math.floor((left % 86400000) / 3600000), 'ч'],
      [Math.floor((left % 3600000) / 60000), 'мин'],
      [Math.floor((left % 60000) / 1000), 'сек'],
    ],
  }
}

function prize(t) {
  const amount = Number(t.prize_pool || 0)
  return amount > 0 ? `${amount.toLocaleString('ru-RU')} ${t.prize_currency || ''}`.trim() : null
}

function fill(t) {
  const count = t.approved_count ?? t.participants_count ?? 0
  return t.max_participants ? Math.min(100, Math.round((count / t.max_participants) * 100)) : 0
}

function shortDate(value) {
  if (!value) return null
  return new Date(value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

function avatarOf(p) {
  return p.user?.avatar_url || p.clan?.avatar_url || null
}

function letterOf(p) {
  return (p.user?.username || p.clan?.tag || '?').charAt(0).toUpperCase()
}

onMounted(load)
</script>

<template>
  <div class="tl">
    <!-- ===================== ГЕРОЙ ===================== -->
    <header class="tl-hero">
      <div class="tl-hero__glow" aria-hidden="true"></div>
      <div>
        <span class="tl-hero__kicker">⚔ Арена APEX</span>
        <h1 class="tl-hero__title">Турниры</h1>
        <p class="tl-hero__lead">Сражайся за призы, славу и место в истории сервера. Регистрируйся, проходи сетку, забирай кубок.</p>
      </div>
      <div class="tl-hero__stat">
        <strong>{{ total }}</strong>
        <span>турниров</span>
      </div>
    </header>

    <PageLoader v-if="loading" />

    <template v-else>
      <!-- ===================== ГЛАВНЫЙ ===================== -->
      <RouterLink
          v-if="featured"
          :to="`/tournaments/${featured.id}`"
          class="tl-featured"
          :class="`tl--${STATUS[featured.status]?.tone}`"
      >
        <div
            class="tl-featured__bg"
            :style="featured.banner_url ? { backgroundImage: `url(${featured.banner_url})` } : null"
        ></div>
        <div class="tl-featured__shade"></div>

        <div class="tl-featured__main">
          <span class="tl-status"><i></i>{{ STATUS[featured.status]?.label }}</span>
          <h2>{{ featured.name }}</h2>
          <p v-if="featured.description">{{ featured.description }}</p>

          <div v-if="countdown(featured)" class="tl-count">
            <span class="tl-count__label">{{ countdown(featured).label }}</span>
            <div class="tl-count__digits">
              <span v-for="[value, unit] in countdown(featured).parts" :key="unit">
                <b>{{ String(value).padStart(2, '0') }}</b>{{ unit }}
              </span>
            </div>
          </div>

          <span class="tl-featured__cta">{{ featured.status === 'registration' ? 'Участвовать →' : 'Смотреть сетку →' }}</span>
        </div>

        <div class="tl-featured__side">
          <div class="tl-prize">
            <span>🏆 Призовой фонд</span>
            <strong>{{ prize(featured) || 'Слава и титул' }}</strong>
          </div>
          <div class="tl-slots">
            <div class="tl-avatars">
              <span v-for="p in featured.participants || []" :key="p.id" class="tl-avatar" :style="p.clan ? { background: p.clan.banner_color } : null">
                <img v-if="avatarOf(p)" :src="avatarOf(p)" alt="" loading="lazy" />
                <template v-else>{{ letterOf(p) }}</template>
              </span>
            </div>
            <span>{{ featured.approved_count ?? featured.participants_count }} / {{ featured.max_participants }} мест</span>
            <div class="tl-bar"><i :style="{ width: `${fill(featured)}%` }"></i></div>
          </div>
        </div>
      </RouterLink>

      <!-- ===================== ФИЛЬТРЫ ===================== -->
      <div class="tl-filters">
        <label class="tl-search">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
          </svg>
          <input v-model="search" placeholder="Найти турнир" />
        </label>

        <div class="tl-chips">
          <button
              v-for="[value, label] in STATUS_FILTERS"
              :key="`s-${value}`"
              type="button"
              class="tl-chip"
              :class="{ 'is-on': statusFilter === value }"
              @click="statusFilter = value"
          >{{ label }}</button>
        </div>

        <div class="tl-chips">
          <button
              v-for="[value, label] in TYPE_FILTERS"
              :key="`t-${value}`"
              type="button"
              class="tl-chip"
              :class="{ 'is-on': typeFilter === value }"
              @click="typeFilter = value"
          >{{ label }}</button>
        </div>
      </div>

      <!-- ===================== СЕТКА ===================== -->
      <div v-if="!tournaments.length" class="tl-empty">
        <span>🏟</span>
        Турниров не нашлось — загляни позже, скоро будут новые.
      </div>

      <div v-else class="tl-grid">
        <RouterLink
            v-for="(t, i) in rest"
            :key="t.id"
            :to="`/tournaments/${t.id}`"
            class="tl-card"
            :class="`tl--${STATUS[t.status]?.tone}`"
            :style="{ '--i': i }"
        >
          <div class="tl-card__banner" :style="t.banner_url ? { backgroundImage: `url(${t.banner_url})` } : null">
            <span v-if="!t.banner_url" class="tl-card__letter">{{ t.name.charAt(0).toUpperCase() }}</span>
            <span class="tl-status"><i></i>{{ STATUS[t.status]?.label }}</span>
            <span class="tl-card__type">{{ t.type === 'solo' ? '1 на 1' : 'Клан на клан' }}</span>
          </div>

          <div class="tl-card__body">
            <h3>{{ t.name }}</h3>

            <div class="tl-card__meta">
              <span v-if="prize(t)" class="tl-card__prize">🏆 {{ prize(t) }}</span>
              <span v-if="t.min_tier || t.max_tier">🎯 {{ t.min_tier || 'E' }}–{{ t.max_tier || 'S' }}</span>
              <span v-if="shortDate(t.starts_at)">📅 {{ shortDate(t.starts_at) }}</span>
            </div>

            <div class="tl-card__foot">
              <div class="tl-avatars tl-avatars--small">
                <span v-for="p in t.participants || []" :key="p.id" class="tl-avatar" :style="p.clan ? { background: p.clan.banner_color } : null">
                  <img v-if="avatarOf(p)" :src="avatarOf(p)" alt="" loading="lazy" />
                  <template v-else>{{ letterOf(p) }}</template>
                </span>
              </div>
              <span class="tl-card__slots">{{ t.approved_count ?? t.participants_count }}/{{ t.max_participants }}</span>
            </div>
            <div class="tl-bar"><i :style="{ width: `${fill(t)}%` }"></i></div>
          </div>
        </RouterLink>
      </div>

      <div v-if="lastPage > 1" class="tl-pages">
        <button type="button" :disabled="page <= 1" @click="page--">← Назад</button>
        <span>{{ page }} / {{ lastPage }}</span>
        <button type="button" :disabled="page >= lastPage" @click="page++">Вперёд →</button>
      </div>
    </template>
  </div>
</template>

<style scoped>
@import "@/views/tournaments/TournamentsView.css";
</style>
