<template>
  <section class="clans-page">
    <!-- ============================================================
         HEADER
         ============================================================ -->

    <div class="page-head">
      <div>

        <h1>Кланы</h1>

        <p>
          Найди свою команду и присоединяйся к игре.
        </p>
      </div>

      <RouterLink
          to="/clans/create"
          class="create-clan-btn"
      >
        + Создать клан
      </RouterLink>
    </div>

    <!-- ============================================================
         ПОИСК И ФИЛЬТРЫ
         ============================================================ -->

    <div class="clan-filters">
      <label class="clan-filters__search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="7" />
          <path d="m21 21-4.3-4.3" />
        </svg>
        <input
            v-model="filters.search"
            type="search"
            placeholder="Название или тег клана"
            aria-label="Поиск клана"
        />
      </label>

      <div class="clan-filters__row">
        <div class="clan-filters__sorts" role="group" aria-label="Сортировка">
          <button
              v-for="s in SORTS"
              :key="s.value"
              type="button"
              :class="{ active: filters.sort === s.value }"
              @click="filters.sort = s.value"
          >
            {{ s.label }}
          </button>
        </div>

        <div class="clan-filters__toggles">
          <label>
            <input v-model="filters.open" type="checkbox" />
            Открытый набор
          </label>
          <label>
            <input v-model="filters.has_slots" type="checkbox" />
            Есть места
          </label>
        </div>
      </div>

      <p v-if="!loading && !error" class="clan-filters__count">
        {{ total }} {{ plural(total, ['клан', 'клана', 'кланов']) }}
        <button v-if="isFiltered" type="button" class="clan-filters__reset" @click="resetFilters">
          Сбросить фильтры
        </button>
      </p>
    </div>

    <!-- ============================================================
         ERROR
         ============================================================ -->

    <div
        v-if="error"
        class="state error-state"
    >
      <div class="state-icon">!</div>

      <div>
        <strong>Не удалось загрузить кланы</strong>
        <span>{{ error }}</span>
      </div>
    </div>

    <!-- ============================================================
         LOADING
         ============================================================ -->

    <div
        v-else-if="loading"
        class="clans-list"
    >
      <div
          v-for="i in 6"
          :key="i"
          class="clan-row skeleton-row"
      >
        <div class="skeleton avatar"></div>

        <div class="skeleton-content">
          <div class="skeleton line large"></div>
          <div class="skeleton line small"></div>
        </div>

        <div class="skeleton-stat"></div>
        <div class="skeleton-stat"></div>
      </div>
    </div>

    <!-- ============================================================
         CLANS
         ============================================================ -->

    <div
        v-else-if="clans.length"
        class="clans-list"
    >
      <article
          v-for="clan in clans"
          :key="clan.id"
          class="clan-row"
          :class="rowClasses(clan)"
          :style="rowStyle(clan)"
          @click="openClan(clan)"
      >
        <!-- ========================================================
             EFFECT
             ======================================================== -->

        <div
            v-if="
            isHighlighted(clan) &&
            effectiveClanEffect(clan) !== 'none'
          "
            class="clan-effect"
            aria-hidden="true"
        >
          <div class="clan-effect-bg"></div>
          <div class="clan-effect-glow"></div>
          <div class="clan-effect-shimmer"></div>
        </div>

        <!-- ========================================================
             AVATAR
             ======================================================== -->

        <div class="clan-avatar-wrap">
          <div class="clan-avatar">
            <img
                v-if="clan.avatar_url"
                :src="clan.avatar_url"
                :alt="clan.name"
            />

            <span v-else>
              {{ clan.name?.charAt(0)?.toUpperCase() || '?' }}
            </span>
          </div>

          <div
              v-if="isHighlighted(clan)"
              class="avatar-aura"
          ></div>
        </div>

        <!-- ========================================================
             INFO
             ======================================================== -->

        <div class="clan-main">
          <div class="clan-title">
            <h2>
              {{ clan.name }}
            </h2>

            <span
                v-if="clan.tag"
                class="clan-tag"
            >
              [{{ clan.tag }}]
            </span>

            <span
                v-if="clan.level > 1"
                class="clan-level"
                title="Уровень клана: растёт от отметок участников и побед в КВ"
            >
              {{ clan.level }} ур.
            </span>

            <span
                v-if="isMine(clan)"
                class="my-badge"
            >
              Мой
            </span>

            <!-- Эффект выделения — чипом рядом с названием, а не поверх статистики -->
            <span
                v-if="isHighlighted(clan)"
                class="effect-label"
            >
              {{ effectLabel(effectiveClanEffect(clan)) }}
            </span>
          </div>

          <p class="clan-description">
            {{ clan.description || 'Описание отсутствует' }}
          </p>

          <div class="clan-meta">
            <span>
              <i></i>
              {{ clan.is_open ? 'Открытый набор' : 'Закрытый набор' }}
            </span>

            <span>
              {{ clan.members_count || 0 }}/{{ clan.max_members || 0 }}
              участников
            </span>

            <span v-if="clan.leader">
              Лидер: {{ clan.leader.username }}
            </span>
          </div>
        </div>

        <!-- ========================================================
             POWER
             ======================================================== -->

        <div class="clan-stat">
          <strong>
            {{ clan.power || 0 }}
          </strong>

          <span>
            Сила
          </span>
        </div>

        <!-- ========================================================
             W / L
             ======================================================== -->

        <div class="clan-stat record">
          <strong>
            <b>
              {{ clan.wins || 0 }}
            </b>

            <em>/</em>

            <small>
              {{ clan.losses || 0 }}
            </small>
          </strong>

          <span>
            Победы / поражения
          </span>
        </div>
      </article>

      <button
          v-if="hasMore"
          type="button"
          class="clan-filters__more"
          :disabled="loadingMore"
          @click="loadMore"
      >
        {{ loadingMore ? 'Загрузка…' : 'Показать ещё' }}
      </button>
    </div>

    <!-- ============================================================
         EMPTY
         ============================================================ -->

    <div
        v-else
        class="state empty-state"
    >
      <div class="state-icon">⌁</div>

      <strong>
        {{ isFiltered ? 'Ничего не найдено' : 'Кланов пока нет' }}
      </strong>

      <span>
        {{ isFiltered ? 'Попробуй изменить поиск или фильтры.' : 'Попробуй обновить страницу позже.' }}
      </span>

      <button v-if="isFiltered" type="button" class="clan-filters__reset" @click="resetFilters">
        Сбросить фильтры
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'

const router = useRouter()
const route = useRoute()

const clans = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const error = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

/*
 * ============================================================
 * ФИЛЬТРЫ
 * ============================================================
 *
 * Хранятся в адресе (?sort=wins&open=1): ссылкой можно поделиться,
 * а «назад» из клана возвращает к той же выдаче.
 */

const SORTS = [
  { value: 'power', label: 'По силе' },
  { value: 'rating', label: 'По рейтингу игроков' },
  { value: 'members', label: 'По составу' },
  { value: 'wins', label: 'По победам' },
  { value: 'winrate', label: 'По винрейту' },
  { value: 'new', label: 'Новые' },
]

const DEFAULTS = { search: '', sort: 'power', open: false, has_slots: false }

const filters = reactive({
  search: String(route.query.search ?? ''),
  sort: SORTS.some((s) => s.value === route.query.sort) ? route.query.sort : 'power',
  open: route.query.open === '1',
  has_slots: route.query.has_slots === '1',
})

const isFiltered = computed(() =>
    filters.search.trim() !== '' || filters.open || filters.has_slots,
)

const hasMore = computed(() => page.value < lastPage.value)

function queryParams(pageNumber) {
  const params = { page: pageNumber, sort: filters.sort }

  if (filters.search.trim()) params.search = filters.search.trim()
  if (filters.open) params.open = 1
  if (filters.has_slots) params.has_slots = 1

  return params
}

function syncUrl() {
  const query = {}

  if (filters.search.trim()) query.search = filters.search.trim()
  if (filters.sort !== DEFAULTS.sort) query.sort = filters.sort
  if (filters.open) query.open = '1'
  if (filters.has_slots) query.has_slots = '1'

  router.replace({ query })
}

function resetFilters() {
  Object.assign(filters, { ...DEFAULTS, sort: filters.sort })
}

function plural(n, [one, few, many]) {
  const mod10 = n % 10
  const mod100 = n % 100

  if (mod10 === 1 && mod100 !== 11) return one
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few

  return many
}

// Поиск — с задержкой, чтобы не дёргать сервер на каждую букву
let searchTimer = null

watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    syncUrl()
    loadClans()
  }, 300)
})

watch(() => [filters.sort, filters.open, filters.has_slots], () => {
  syncUrl()
  loadClans()
})

/*
 * ============================================================
 * ЭФФЕКТЫ ПОДСВЕТКИ
 * ============================================================
 *
 * Ключи совпадают с effect_value предметов магазина
 * и с App\Support\ClanHighlight на сервере.
 */

const EFFECT_LABELS = {
  // Базовая подсветка без свечения
  frame: 'Рамка',
  glow: 'Glow',
  pulse: 'Pulse',
  gradient: 'Gradient',
  fire: 'Fire',
  ice: 'Ice',
  aurora: 'Aurora',
  legendary: 'Legendary',
}

/** Цвета подсветки — те же ключи, что продаются в магазине. */
const HIGHLIGHT_COLORS = {
  gold: '#facc15',
  crimson: '#ef4444',
  cyan: '#06b6d4',
  violet: '#8b5cf6',
  emerald: '#22c55e',
  rose: '#ec4899',
}

/*
 * ============================================================
 * LOAD
 * ============================================================
 */

// Номер запроса: ответ на устаревший фильтр не должен перетереть новый
let requestId = 0

const loadClans = async () => {
  const id = ++requestId

  loading.value = true
  error.value = ''

  try {
    const response = await clansApi.list(queryParams(1))

    if (id !== requestId) return

    // response.data — массив кланов страницы, остальное — пагинация
    clans.value = response?.data || []
    page.value = response?.current_page ?? 1
    lastPage.value = response?.last_page ?? 1
    total.value = response?.total ?? clans.value.length
  } catch (err) {
    console.error('Failed to load clans:', err)

    error.value =
        err?.response?.data?.message ||
        err?.message ||
        'Произошла ошибка при загрузке.'
  } finally {
    if (id === requestId) loading.value = false
  }
}

async function loadMore() {
  if (loadingMore.value || !hasMore.value) return

  loadingMore.value = true
  const id = requestId

  try {
    const response = await clansApi.list(queryParams(page.value + 1))

    if (id !== requestId) return

    const known = new Set(clans.value.map((c) => c.id))
    clans.value = [...clans.value, ...(response?.data || []).filter((c) => !known.has(c.id))]
    page.value = response?.current_page ?? page.value + 1
    lastPage.value = response?.last_page ?? lastPage.value
  } catch (err) {
    console.error('Failed to load more clans:', err)
  } finally {
    loadingMore.value = false
  }
}

onMounted(loadClans)

/*
 * ============================================================
 * NAVIGATION
 * ============================================================
 */

const openClan = (clan) => {
  if (!clan?.id) return

  // Свой клан ведём во вкладку «Мой клан»: там форум, ресурсы,
  // участники и выход, которых нет на публичной странице
  if (isMine(clan)) {
    router.push('/my-clan')

    return
  }

  router.push(`/clans/${clan.id}`)
}

/*
 * ============================================================
 * CLAN HELPERS
 * ============================================================
 */

/**
 * Подсветка активна.
 *
 * Сервер отдаёт is_highlighted уже с учётом срока, но подстрахуемся:
 * если пришла дата окончания и она в прошлом — подсветки нет.
 */
const isHighlighted = (clan) => {
  if (!clan?.is_highlighted) {
    return false
  }

  if (clan.highlight_until && new Date(clan.highlight_until) <= new Date()) {
    return false
  }

  return true
}

/**
 * Свой ли это клан.
 *
 * Сервер помечает свой клан полем my_clan_id, поэтому сравнивать
 * нужно его с id клана, а не id клана с самим собой.
 */
const isMine = (clan) => {
  if (!clan || clan.my_clan_id === null || clan.my_clan_id === undefined) {
    return false
  }

  return Number(clan.my_clan_id) === Number(clan.id)
}

const effectiveClanEffect = (clan) => {
  if (!isHighlighted(clan)) {
    return 'none'
  }

  // Эффект куплен в магазине; без покупки работает базовая рамка
  const effect = clan?.highlight_effect

  return EFFECT_LABELS[effect] ? effect : 'frame'
}

const effectiveClanColor = (clan) => {
  // Цвет тоже покупается; иначе берём цвет клана
  return (
      HIGHLIGHT_COLORS[clan?.highlight_color] ||
      clan?.banner_color ||
      '#8b5cf6'
  )
}

const effectLabel = (effect) => EFFECT_LABELS[effect] || effect

const rowClasses = (clan) => {
  const effect = effectiveClanEffect(clan)

  return {
    highlighted: isHighlighted(clan),

    [`effect-${effect}`]:
    isHighlighted(clan) &&
    effect !== 'none',
  }
}

const rowStyle = (clan) => {
  if (!isHighlighted(clan)) {
    return {}
  }

  return {
    '--clan-color': effectiveClanColor(clan),
  }
}
</script>

<style scoped>
@import "@/views/clan/ClansView.css";

/* ---------- Поиск и фильтры ---------- */

.clan-filters {
  display: flex;
  flex-direction: column;
  gap: 12px;

  margin-bottom: 18px;
}

.clan-filters__search {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 0 14px;

  color: var(--text-muted);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.clan-filters__search:focus-within {
  border-color: var(--accent);
}

.clan-filters__search input {
  flex: 1;
  min-width: 0;

  padding: 12px 0;

  color: var(--text);
  background: transparent;
  border: 0;
  outline: none;

  font: inherit;
  font-size: 14px;
}

.clan-filters__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.clan-filters__sorts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.clan-filters__sorts button {
  padding: 7px 12px;

  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 6px;

  font-size: 13px;
  font-weight: 600;

  cursor: pointer;
}

.clan-filters__sorts button:hover {
  color: var(--text);
}

.clan-filters__sorts button.active {
  color: var(--text);
  background: var(--accent-soft);
  border-color: var(--accent);
}

.clan-filters__toggles {
  display: flex;
  gap: 14px;
}

.clan-filters__toggles label {
  display: flex;
  align-items: center;
  gap: 6px;

  color: var(--text-dim);

  font-size: 13px;

  cursor: pointer;
}

.clan-filters__toggles input {
  accent-color: var(--accent);
}

.clan-filters__count {
  display: flex;
  align-items: center;
  gap: 12px;

  margin: 0;

  color: var(--text-muted);

  font-size: 13px;
}

.clan-filters__reset {
  padding: 0;

  color: var(--accent-light);
  background: none;
  border: 0;

  font-size: 13px;

  cursor: pointer;
}

.clan-filters__more {
  align-self: center;

  margin-top: 8px;
  padding: 10px 22px;

  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;

  font-weight: 600;

  cursor: pointer;
}

.clan-filters__more:disabled {
  opacity: 0.6;
}
</style>
