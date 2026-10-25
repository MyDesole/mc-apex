<script setup>
/**
 * Рейтинг кланов для страницы «Рейтинг».
 *
 * Тройка лидеров — крупными карточками, дальше таблица: состав,
 * победы/поражения с винрейтом, средние очки игроков и сила с полоской
 * относительно лидера. Сила = половина очков участников (RatingService).
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'

const SORTS = [
  { value: 'power', label: 'Сила' },
  { value: 'rating', label: 'Рейтинг игроков' },
  { value: 'winrate', label: 'Винрейт' },
  { value: 'wins', label: 'Победы' },
]

const MEDALS = ['#facc15', '#cbd5e1', '#d97706']

const sort = ref('power')
const clans = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const loadingMore = ref(false)

const topThree = computed(() => clans.value.slice(0, 3))
const rest = computed(() => clans.value.slice(3))

// Полоска — относительно лидера по выбранной метрике
const metric = (clan) => ({
  power: clan.power,
  rating: clan.avg_rating,
  winrate: winrate(clan),
  wins: clan.wins,
}[sort.value] ?? clan.power)

const maxMetric = computed(() => Math.max(1, ...clans.value.map(metric)))

function winrate(clan) {
  const games = (clan.wins ?? 0) + (clan.losses ?? 0)
  return games ? Math.round((clan.wins / games) * 100) : 0
}

function clanLink(clan) {
  return `/clans/${clan.id}`
}

let requestId = 0

async function load() {
  const id = ++requestId
  loading.value = true

  try {
    const data = await clansApi.list({ sort: sort.value, page: 1 })
    if (id !== requestId) return

    clans.value = data?.data ?? []
    page.value = data?.current_page ?? 1
    lastPage.value = data?.last_page ?? 1
  } catch {
    if (id === requestId) clans.value = []
  } finally {
    if (id === requestId) loading.value = false
  }
}

async function loadMore() {
  if (loadingMore.value || page.value >= lastPage.value) return

  loadingMore.value = true
  const id = requestId

  try {
    const data = await clansApi.list({ sort: sort.value, page: page.value + 1 })
    if (id !== requestId) return

    const known = new Set(clans.value.map((c) => c.id))
    clans.value = [...clans.value, ...(data?.data ?? []).filter((c) => !known.has(c.id))]
    page.value = data?.current_page ?? page.value + 1
  } finally {
    loadingMore.value = false
  }
}

watch(sort, load)
onMounted(load)
</script>

<template>
  <section class="clan-ranking">
    <div class="clan-ranking__sorts" role="group" aria-label="Сортировка кланов">
      <button
          v-for="s in SORTS"
          :key="s.value"
          type="button"
          :class="{ active: sort === s.value }"
          @click="sort = s.value"
      >
        {{ s.label }}
      </button>
    </div>

    <div v-if="loading" class="clan-ranking__state">Загрузка…</div>

    <div v-else-if="!clans.length" class="clan-ranking__state">Кланов пока нет</div>

    <template v-else>
      <!-- Тройка лидеров -->
      <div class="clan-ranking__top">
        <RouterLink
            v-for="(clan, i) in topThree"
            :key="clan.id"
            :to="clanLink(clan)"
            class="top-card"
            :style="{ '--medal': MEDALS[i], '--clan': clan.banner_color || '#7c3aed' }"
        >
          <span class="top-card__place">#{{ clan.position }}</span>

          <span class="top-card__avatar">
            <img v-if="clan.avatar_url" :src="clan.avatar_url" :alt="clan.name" />
            <span v-else>{{ (clan.tag || clan.name || '?').charAt(0) }}</span>
          </span>

          <strong class="top-card__name">
            <span class="top-card__tag">[{{ clan.tag }}]</span> {{ clan.name }}
          </strong>

          <span class="top-card__power">{{ clan.power }}</span>
          <span class="top-card__label">сила клана</span>

          <dl class="top-card__stats">
            <div><dt>Состав</dt><dd>{{ clan.members_count }}/{{ clan.max_members }}</dd></div>
            <div><dt>Победы</dt><dd>{{ clan.wins }}–{{ clan.losses }}</dd></div>
            <div><dt>Ср. очки</dt><dd>{{ clan.avg_rating }}</dd></div>
          </dl>
        </RouterLink>
      </div>

      <!-- Остальные -->
      <div v-if="rest.length" class="clan-table" role="table">
        <div class="clan-table__head" role="row">
          <span>#</span>
          <span>Клан</span>
          <span>Состав</span>
          <span>В / П</span>
          <span>Ср. очки</span>
          <span>Сила</span>
        </div>

        <RouterLink
            v-for="clan in rest"
            :key="clan.id"
            :to="clanLink(clan)"
            class="clan-table__row"
            role="row"
            :style="{ '--clan': clan.banner_color || '#7c3aed' }"
        >
          <span class="clan-table__pos">{{ clan.position }}</span>

          <span class="clan-table__clan">
            <span class="clan-table__avatar">
              <img v-if="clan.avatar_url" :src="clan.avatar_url" :alt="clan.name" />
              <span v-else>{{ (clan.tag || clan.name || '?').charAt(0) }}</span>
            </span>
            <span class="clan-table__name">
              <strong><span class="clan-table__tag">[{{ clan.tag }}]</span> {{ clan.name }}</strong>
              <small v-if="clan.leader">Лидер: {{ clan.leader.username }}</small>
            </span>
          </span>

          <span class="clan-table__cell">{{ clan.members_count }}/{{ clan.max_members }}</span>

          <span class="clan-table__cell">
            <b class="win">{{ clan.wins }}</b> / <b class="loss">{{ clan.losses }}</b>
            <small>{{ winrate(clan) }}%</small>
          </span>

          <span class="clan-table__cell">{{ clan.avg_rating }}</span>

          <span class="clan-table__power">
            <span class="clan-table__bar">
              <span :style="{ width: `${Math.max(4, (metric(clan) / maxMetric) * 100)}%` }" />
            </span>
            <strong>{{ clan.power }}</strong>
          </span>
        </RouterLink>
      </div>

      <button
          v-if="page < lastPage"
          type="button"
          class="clan-ranking__more"
          :disabled="loadingMore"
          @click="loadMore"
      >
        {{ loadingMore ? 'Загрузка…' : 'Показать ещё' }}
      </button>
    </template>
  </section>
</template>

<style scoped>
.clan-ranking {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.clan-ranking__sorts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.clan-ranking__sorts button {
  padding: 7px 12px;
  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.clan-ranking__sorts button.active {
  color: var(--text);
  background: var(--accent-soft);
  border-color: var(--accent);
}

.clan-ranking__state {
  padding: 40px;
  color: var(--text-muted);
  text-align: center;
}

/* ---------- Тройка ---------- */

.clan-ranking__top {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.top-card {
  position: relative;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;

  padding: 22px 16px 16px;

  color: var(--text);
  text-decoration: none;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-top: 3px solid var(--medal);
  border-radius: 6px;

  transition: border-color 0.15s ease, transform 0.15s ease;
}

.top-card:hover {
  transform: translateY(-2px);
  border-color: color-mix(in srgb, var(--medal) 60%, var(--border));
}

.top-card__place {
  position: absolute;
  top: 10px;
  left: 12px;

  color: var(--medal);

  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
}

.top-card__avatar,
.clan-table__avatar {
  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #fff;
  background: var(--clan);
  border-radius: 6px;

  font-family: var(--font-display);
  font-weight: 700;
}

.top-card__avatar {
  width: 64px;
  height: 64px;
  font-size: 24px;
  box-shadow: inset 0 -5px 0 rgba(0, 0, 0, 0.25);
}

.top-card__avatar img,
.clan-table__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.top-card__name {
  max-width: 100%;
  overflow: hidden;
  font-size: 15px;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.top-card__tag,
.clan-table__tag {
  color: var(--clan);
}

.top-card__power {
  margin-top: 4px;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 800;
  line-height: 1;
}

.top-card__label {
  color: var(--text-muted);
  font-size: 12px;
}

.top-card__stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;

  width: 100%;
  margin: 10px 0 0;
  padding-top: 10px;

  border-top: 1px solid var(--border);
}

.top-card__stats div {
  text-align: center;
}

.top-card__stats dt {
  color: var(--text-muted);
  font-size: 11px;
}

.top-card__stats dd {
  margin: 2px 0 0;
  font-weight: 700;
  font-size: 14px;
}

/* ---------- Таблица ---------- */

.clan-table {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.clan-table__head,
.clan-table__row {
  display: grid;
  grid-template-columns: 36px minmax(0, 2.4fr) 70px 110px 80px minmax(120px, 1.2fr);
  gap: 12px;
  align-items: center;
}

.clan-table__head {
  padding: 0 14px;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 600;
}

.clan-table__row {
  padding: 10px 14px;

  color: var(--text);
  text-decoration: none;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.clan-table__row:hover {
  border-color: color-mix(in srgb, var(--clan) 50%, var(--border));
}

.clan-table__pos {
  color: var(--text-muted);
  font-family: var(--font-display);
  font-weight: 700;
}

.clan-table__clan {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.clan-table__avatar {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  font-size: 15px;
}

.clan-table__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.clan-table__name strong {
  overflow: hidden;
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-table__name small,
.clan-table__cell small {
  color: var(--text-muted);
  font-size: 12px;
}

.clan-table__cell {
  display: flex;
  align-items: baseline;
  gap: 4px;
  font-size: 14px;
}

.clan-table__cell .win { color: #4ade80; }
.clan-table__cell .loss { color: #f87171; }

.clan-table__power {
  display: flex;
  align-items: center;
  gap: 10px;
}

.clan-table__bar {
  flex: 1;
  height: 8px;
  overflow: hidden;
  background: var(--border);
  border-radius: 3px;
}

.clan-table__bar span {
  display: block;
  height: 100%;
  background: var(--clan);
  border-radius: 3px;
}

.clan-table__power strong {
  min-width: 36px;
  font-family: var(--font-display);
  text-align: right;
}

.clan-ranking__more {
  align-self: center;
  padding: 10px 22px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

/* ---------- Мобилка ---------- */

@media (max-width: 760px) {
  .clan-ranking__top {
    grid-template-columns: 1fr;
  }

  .clan-table__head {
    display: none;
  }

  /* На телефоне — место, клан и сила; подробности на странице клана */
  .clan-table__row {
    grid-template-columns: 28px minmax(0, 1fr) auto;
  }

  .clan-table__bar,
  .clan-table__cell {
    display: none;
  }
}
</style>
