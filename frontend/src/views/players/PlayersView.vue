<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { playersApi } from '@/services/players/players.js'
import { userLink } from '@/utils/links.js'
import RatingPodium from '@/components/players/RatingPodium.vue'
import TabTransition from '@/components/core/TabTransition.vue'
import {
  accent,
  avatarFrame,
  avatarLetter,
  profileEffectStyle,
  roleLabel,
  scoreOf,
} from '@/utils/playerStyling.js'

const category = ref('pvp')
const pvpMode = ref('overall')

const players = ref([])
const loading = ref(true)

const CATEGORIES = [
  { value: 'pvp', label: 'PvP', color: '#ef4444' },
  { value: 'other', label: 'Не PvP', color: '#06b6d4' },
]

const PVP_MODES = [
  { value: 'overall', label: 'Общий рейтинг', color: '#facc15' },
  { value: 'pvp', label: 'PvP', color: '#ef4444' },
  { value: 'bedwars', label: 'BedWars', color: '#8b5cf6' },
]

// --- Курсорная пагинация рейтинга ---
const PAGE_SIZE = 30
const cursor = ref(null)
const hasMore = ref(false)
const loadingMore = ref(false)

// Виртуальное окно: рендерим не весь список сразу, а растущую порцию.
// Полные данные остаются в players, поэтому позиции и подиум не ломаются.
const RENDER_STEP = 60
const renderLimit = ref(RENDER_STEP)

const visiblePlayers = computed(() => players.value.slice(0, renderLimit.value))
const hiddenCount = computed(() => Math.max(0, players.value.length - visiblePlayers.value.length))

const topThree = computed(() => visiblePlayers.value.slice(0, 3))
const rest = computed(() => visiblePlayers.value.slice(3))

/** Режим рейтинга: категория «Не PvP» — это бридж. */
const rankingMode = computed(() =>
    category.value === 'other' ? 'bridge' : pvpMode.value,
)

async function load() {
  loading.value = true
  cursor.value = null
  hasMore.value = false
  renderLimit.value = RENDER_STEP

  try {
    const data = await playersApi.ranking({
      mode: rankingMode.value,
      limit: PAGE_SIZE,
    })

    players.value = data.data ?? []
    cursor.value = data.next_cursor
    hasMore.value = Boolean(data.has_more)
  } catch (e) {
    console.error(e)
    players.value = []
  } finally {
    loading.value = false
  }
}

/** Догрузить следующую страницу по курсору. */
async function loadMore() {
  if (loadingMore.value || !hasMore.value || !cursor.value) return

  loadingMore.value = true

  try {
    const data = await playersApi.ranking({
      mode: rankingMode.value,
      limit: PAGE_SIZE,
      cursor: cursor.value,
    })

    const known = new Set(players.value.map((p) => p.id))
    const fresh = (data.data ?? []).filter((p) => !known.has(p.id))

    players.value = [...players.value, ...fresh]
    cursor.value = data.next_cursor
    hasMore.value = Boolean(data.has_more)

    // Показываем догруженное
    renderLimit.value += RENDER_STEP
  } catch (e) {
    console.error(e)
  } finally {
    loadingMore.value = false
  }
}

function showMoreRendered() {
  renderLimit.value += RENDER_STEP
  window.scrollBy({ top: 300, behavior: 'smooth' })
}

/**
 * Автоподгрузка при скролле: срабатывает, когда пользователь
 * подошёл к концу видимого списка.
 */
function onWindowScroll() {
  const scrolled = window.innerHeight + window.scrollY
  const total = document.documentElement.scrollHeight

  if (total - scrolled > 700) return

  if (hiddenCount.value > 0) {
    renderLimit.value += RENDER_STEP
    return
  }

  loadMore()
}

onMounted(() => {
  load()
  window.addEventListener('scroll', onWindowScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', onWindowScroll)
})

watch([category, pvpMode], load)

function scorePercent(player) {
  if (!players.value.length) {
    return 0
  }

  const max = Math.max(
      ...players.value.map(p => Number(p?.rating_score ?? 0)),
      1
  )

  return Math.min(
      100,
      Math.max(
          5,
          (Number(player?.rating_score ?? 0) / max) * 100
      )
  )
}
</script>

<template>
  <div class="rating-page">

    <!-- =========================================================
         HEADER
         ========================================================= -->

    <header class="rating-head">
      <div class="rating-head__text">
        <div class="rating-head__eyebrow">
          <span class="rating-head__eyebrow-line" />
          RANKING SYSTEM
        </div>

        <h1 class="rating-head__title">
          Восхождение к <span class="apex">APEX</span>
        </h1>

        <p class="rating-head__sub">
          Лучшие игроки ·
          <strong>
            {{ CATEGORIES.find(c => c.value === category)?.label }}
          </strong>

          <template v-if="category === 'pvp'">
            ·
            <strong>
              {{ PVP_MODES.find(m => m.value === pvpMode)?.label }}
            </strong>
          </template>
        </p>
      </div>

      <div class="mode-tabs">
        <button
            v-for="c in CATEGORIES"
            :key="c.value"
            type="button"
            class="mode-tab"
            :class="{ 'mode-tab--active': category === c.value }"
            :style="{ '--tab-color': c.color }"
            @click="category = c.value"
        >
          <svg
              v-if="c.value === 'pvp'"
              class="mode-tab__icon"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M14.5 17.5 3 6V3h3l11.5 11.5" />
            <path d="M13 19l6-6" />
            <path d="M16 16l4 4" />
            <path d="M19 21l2-2" />
            <path d="M9.5 6.5 21 18v3h-3L6.5 9.5" />
            <path d="M5 14l6 6" />
            <path d="M2 19l4-4" />
            <path d="M3 21l2-2" />
          </svg>

          <svg
              v-else
              class="mode-tab__icon"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
            <path d="M9 12l2 2 4-4" />
          </svg>

          <span class="mode-tab__label">
            {{ c.label }}
          </span>
        </button>
      </div>
    </header>

    <!-- =========================================================
         SUB TABS
         ========================================================= -->

    <div
        v-if="category === 'pvp'"
        class="sub-tabs"
    >
      <button
          v-for="m in PVP_MODES"
          :key="m.value"
          type="button"
          class="sub-tab"
          :class="{ 'sub-tab--active': pvpMode === m.value }"
          :style="{ '--tab-color': m.color }"
          @click="pvpMode = m.value"
      >
        <span class="sub-tab__dot" />
        <span class="sub-tab__label">
          {{ m.label }}
        </span>
      </button>
    </div>

    <!-- =========================================================
         LOADING
         ========================================================= -->

    <div
        v-if="loading"
        class="state"
    >
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <!-- =========================================================
         BRIDGE: категория «Не PvP» — топ бриджеров
         ========================================================= -->

    <div
        v-else-if="category === 'other' && !visiblePlayers.length"
        class="state state--empty"
    >
      <svg
          width="48"
          height="48"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
      >
        <path d="M3 20h18" />
        <path d="M5 20V9l7-5 7 5v11" />
        <path d="M9 20v-6h6v6" />
      </svg>

      <span class="state__title">
        Пока никого
      </span>

      <span class="state__sub">
        Топ появится, когда бридж-тестер подтвердит первые виды бриджа
      </span>
    </div>

    <template v-else>

      <!-- =======================================================
           DESKTOP MOUNTAIN
           ======================================================= -->

      <!--
        Ключ по категории и режиму: при переключении содержимое
        пересоздаётся, и переход играет заново.
      -->
      <TabTransition :active="`${category}-${pvpMode}`" name="fade-scale">
      <RatingPodium :players="visiblePlayers" />

      <!-- =======================================================
           REST
           ======================================================= -->

      <section
          v-if="rest.length"
          class="rest"
      >
        <header class="rest__head">
          <div class="rest__heading">
            <span class="rest__eyebrow">
              THE CLIMB CONTINUES
            </span>

            <div class="rest__heading-row">
              <h2 class="rest__title">
                Остальные восходители
              </h2>

              <span class="rest__count">
                {{ rest.length }}
              </span>
            </div>
          </div>

          <div class="rest__legend">
            <span class="rest__legend-dot" />
            RANKED PLAYERS
          </div>
        </header>

        <div class="rest__grid">
          <RouterLink
              v-for="(p, i) in rest"
              :key="p.id"
              :to="userLink(p)"
              class="rest__row"
              :style="{ '--accent': accent(p) }"
          >
            <!-- RANK -->

            <div class="rest__rank">
              <span class="rest__rank-number">
                #{{ p.position ?? (i + 4) }}
              </span>

              <span class="rest__rank-line" />
            </div>

            <!-- PLAYER -->

            <div
                class="rest-player"
                :class="{
                  'rest-player--legendary':
                      p.profile_effect === 'legendary'
                }"
                :style="profileEffectStyle(p)"
            >
              <div class="rest-player__avatar-wrap">

                <div
                    v-if="p.avatar_url"
                    class="rest-player__avatar"
                >
                  <img
                      :src="p.avatar_url"
                      :alt="p.username"
                  />
                </div>

                <div
                    v-else
                    class="rest-player__avatar rest-player__avatar--fallback"
                    :style="{ '--accent': accent(p) }"
                >
                  {{ avatarLetter(p) }}
                </div>

                <span
                    v-if="p.avatar_frame && p.avatar_frame !== 'default'"
                    class="rest-player__frame"
                    :class="`rest-player__frame--${p.avatar_frame}`"
                    :style="avatarFrame(p)"
                />
              </div>

              <div class="rest-player__info">
                <div class="rest-player__name">
                  <span
                      v-if="p.clan_tag"
                      class="rest-player__clan"
                  >
                    [{{ p.clan_tag }}]
                  </span>

                  {{ p.username }}
                </div>

                <div class="rest-player__meta">
                  <span class="rest-player__tier">
                    {{ p.tier || '—' }}
                  </span>

                  <span
                      v-if="roleLabel(p)"
                      class="rest-player__role"
                  >
                    {{ roleLabel(p) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- PROGRESS -->

            <div class="rest__progress">
              <div class="rest__progress-top">
                <span>RATING</span>

                <span class="rest__progress-value">
                  {{ scoreOf(p) }}
                </span>
              </div>

              <div class="rest__progress-track">
                <span
                    class="rest__progress-fill"
                    :style="{ width: `${scorePercent(p)}%` }"
                />
              </div>
            </div>

            <!-- SCORE -->

            <div class="rest__score">
              <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
              </svg>

              <span>
                {{ scoreOf(p) }}
              </span>
            </div>

            <!-- ARROW -->

            <span class="rest__arrow">
              <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="m13 6 6 6-6 6" />
              </svg>
            </span>
          </RouterLink>
        </div>

        <!-- Подгрузка: сначала раскрываем уже загруженное, затем тянем следующую страницу -->
        <div v-if="hiddenCount || hasMore" class="rating-more">
          <button
              v-if="hiddenCount"
              class="rating-more__btn"
              type="button"
              @click="showMoreRendered"
          >
            Показать ещё {{ Math.min(hiddenCount, RENDER_STEP) }} из {{ hiddenCount }}
          </button>

          <button
              v-else
              class="rating-more__btn"
              type="button"
              :disabled="loadingMore"
              @click="loadMore"
          >
            {{ loadingMore ? 'Загружаем…' : 'Загрузить следующих' }}
          </button>
        </div>

        <div v-else-if="players.length" class="rating-more__end">
          Это весь рейтинг — {{ players.length }} игроков
        </div>
      </section>
      </TabTransition>

      <!-- EMPTY -->

      <div
          v-if="!players.length"
          class="state state--empty"
      >
        <span>
          Пока никто не покорил вершину
        </span>
      </div>

    </template>
  </div>
</template>

<style scoped>
@import "@/views/players/PlayersView.css";
</style>
