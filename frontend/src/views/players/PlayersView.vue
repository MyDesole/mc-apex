<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { playersApi } from '@/services/players/players.js'
import { userLink } from '@/utils/links.js'

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

const ROLE_LABELS = {
  admin: 'Администратор',
  tester: 'Тестер',
  moderator: 'Модератор',
  media: 'Медийка',
}

const TIER_ACCENTS = {
  'S+': '#fbbf24',
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

const AVATAR_FRAMES = {
  purple: '#7c3aed',
  cyan: '#06b6d4',
  green: '#22c55e',
  gold: '#facc15',
  orange: '#f97316',
  pink: '#ec4899',
  red: '#ef4444',
  legendary: '#facc15',
  season1: '#06b6d4',
}

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

async function load() {
  if (category.value === 'other') {
    players.value = []
    loading.value = false
    return
  }

  loading.value = true
  cursor.value = null
  hasMore.value = false
  renderLimit.value = RENDER_STEP

  try {
    const data = await playersApi.ranking({
      mode: pvpMode.value,
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
      mode: pvpMode.value,
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

const PODIUM_ORDER = [1, 0, 2]
const MOBILE_ORDER = [0, 1, 2]

const podiumSlots = computed(() =>
    PODIUM_ORDER.filter(i => i < topThree.value.length)
)

const mobileSlots = computed(() =>
    MOBILE_ORDER.filter(i => i < topThree.value.length)
)

function accent(player) {
  return TIER_ACCENTS[player?.tier] || '#7c3aed'
}

function rankOf(slotIdx) {
  return slotIdx + 1
}

function scoreOf(player) {
  return player?.rating_score ?? 0
}

function roleLabel(player) {
  if (!player?.role || player.role === 'player') {
    return ''
  }

  return ROLE_LABELS[player.role] || player.role
}

function avatarLetter(player) {
  return player?.username?.trim()?.charAt(0)?.toUpperCase() || '?'
}

function avatarFrame(player) {
  const frame = player?.avatar_frame

  if (!frame || frame === 'default') {
    return {}
  }

  if (frame === 'rainbow') {
    return {
      '--frame-color': '#7c3aed',
      '--frame-gradient':
          'linear-gradient(135deg, #ef4444, #facc15, #22c55e, #06b6d4, #7c3aed)',
    }
  }

  if (frame === 'legendary') {
    return {
      '--frame-color': '#facc15',
      '--frame-gradient':
          'linear-gradient(135deg, #facc15, #f97316, #ef4444)',
    }
  }

  if (frame === 'season1') {
    return {
      '--frame-color': '#06b6d4',
      '--frame-gradient':
          'linear-gradient(135deg, #7c3aed, #06b6d4)',
    }
  }

  return {
    '--frame-color': AVATAR_FRAMES[frame] || accent(player),
  }
}

function profileEffectStyle(player) {
  const effect = player?.profile_effect

  if (!effect) {
    return {}
  }

  if (effect === 'legendary') {
    return {
      '--effect-color': '#facc15',
    }
  }

  if (effect === 'fire') {
    return {
      '--effect-color': '#f97316',
    }
  }

  if (effect === 'ice') {
    return {
      '--effect-color': '#06b6d4',
    }
  }

  if (effect === 'glow') {
    return {
      '--effect-color': '#7c3aed',
    }
  }

  if (effect === 'pulse') {
    return {
      '--effect-color': '#06b6d4',
    }
  }

  return {}
}

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
         OTHER
         ========================================================= -->

    <div
        v-else-if="category === 'other'"
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
        <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
        <path d="M12 8v4" />
        <circle
            cx="12"
            cy="16"
            r="0.5"
            fill="currentColor"
        />
      </svg>

      <span class="state__title">
        В разработке
      </span>

      <span class="state__sub">
        Скоро здесь появятся рейтинги по другим режимам
      </span>
    </div>

    <template v-else>

      <!-- =======================================================
           DESKTOP MOUNTAIN
           ======================================================= -->

      <section
          v-if="topThree.length"
          class="mountain"
      >
        <div class="mountain__vignette" />
        <div class="mountain__noise" />

        <svg
            class="mountain__bg"
            viewBox="0 0 1400 620"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
          <defs>
            <linearGradient
                id="skyGradient"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#05050d" />
              <stop offset="42%" stop-color="#090a18" />
              <stop offset="72%" stop-color="#111225" />
              <stop offset="100%" stop-color="#080811" />
            </linearGradient>

            <linearGradient
                id="mountainBack"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#20213c" />
              <stop offset="100%" stop-color="#0b0b15" />
            </linearGradient>

            <linearGradient
                id="mountainMid"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#303153" />
              <stop offset="42%" stop-color="#1b1c34" />
              <stop offset="100%" stop-color="#0b0b17" />
            </linearGradient>

            <linearGradient
                id="mountainMain"
                x1="0"
                y1="0"
                x2="1"
                y2="1"
            >
              <stop offset="0%" stop-color="#3c3e67" />
              <stop offset="38%" stop-color="#282a4b" />
              <stop offset="70%" stop-color="#17182e" />
              <stop offset="100%" stop-color="#0a0a14" />
            </linearGradient>

            <linearGradient
                id="leftFace"
                x1="0"
                y1="0"
                x2="1"
                y2="1"
            >
              <stop offset="0%" stop-color="#55577f" stop-opacity=".75" />
              <stop offset="65%" stop-color="#262743" stop-opacity=".45" />
              <stop offset="100%" stop-color="#0b0b15" stop-opacity=".1" />
            </linearGradient>

            <linearGradient
                id="rightFace"
                x1="1"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#10111f" stop-opacity=".9" />
              <stop offset="100%" stop-color="#05050c" stop-opacity=".35" />
            </linearGradient>

            <linearGradient
                id="snow"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#ffffff" stop-opacity=".94" />
              <stop offset="40%" stop-color="#dbeafe" stop-opacity=".7" />
              <stop offset="100%" stop-color="#94a3b8" stop-opacity=".05" />
            </linearGradient>

            <radialGradient
                id="goldGlow"
                cx="50%"
                cy="30%"
                r="55%"
            >
              <stop offset="0%" stop-color="#facc15" stop-opacity=".3" />
              <stop offset="30%" stop-color="#facc15" stop-opacity=".13" />
              <stop offset="70%" stop-color="#facc15" stop-opacity=".025" />
              <stop offset="100%" stop-color="#facc15" stop-opacity="0" />
            </radialGradient>

            <radialGradient
                id="purpleGlow"
                cx="50%"
                cy="50%"
                r="50%"
            >
              <stop offset="0%" stop-color="#8b5cf6" stop-opacity=".2" />
              <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0" />
            </radialGradient>

            <linearGradient
                id="mist"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
            >
              <stop offset="0%" stop-color="#c4b5fd" stop-opacity="0" />
              <stop offset="50%" stop-color="#c4b5fd" stop-opacity=".08" />
              <stop offset="100%" stop-color="#c4b5fd" stop-opacity="0" />
            </linearGradient>

            <filter
                id="softBlur"
                x="-30%"
                y="-30%"
                width="160%"
                height="160%"
            >
              <feGaussianBlur stdDeviation="18" />
            </filter>

            <filter
                id="smallBlur"
                x="-30%"
                y="-30%"
                width="160%"
                height="160%"
            >
              <feGaussianBlur stdDeviation="5" />
            </filter>
          </defs>

          <rect
              width="1400"
              height="620"
              fill="url(#skyGradient)"
          />

          <ellipse
              cx="700"
              cy="280"
              rx="500"
              ry="190"
              fill="url(#goldGlow)"
          />

          <ellipse
              cx="700"
              cy="390"
              rx="500"
              ry="170"
              fill="url(#purpleGlow)"
          />

          <circle
              cx="700"
              cy="120"
              r="88"
              fill="#facc15"
              opacity=".035"
              filter="url(#softBlur)"
          />

          <circle
              cx="700"
              cy="120"
              r="46"
              fill="#facc15"
              opacity=".035"
          />

          <g fill="#fff">
            <circle cx="94" cy="76" r="1.1" opacity=".6" />
            <circle cx="158" cy="144" r=".8" opacity=".45" />
            <circle cx="228" cy="62" r="1.5" opacity=".72" />
            <circle cx="315" cy="120" r=".8" opacity=".4" />
            <circle cx="396" cy="54" r="1.1" opacity=".58" />
            <circle cx="478" cy="105" r=".7" opacity=".4" />
            <circle cx="550" cy="42" r="1.3" opacity=".7" />
            <circle cx="626" cy="82" r=".7" opacity=".4" />
            <circle cx="774" cy="72" r=".9" opacity=".5" />
            <circle cx="852" cy="42" r="1.3" opacity=".7" />
            <circle cx="932" cy="118" r=".8" opacity=".45" />
            <circle cx="1024" cy="60" r="1.2" opacity=".65" />
            <circle cx="1104" cy="136" r=".7" opacity=".4" />
            <circle cx="1180" cy="76" r="1.3" opacity=".7" />
            <circle cx="1280" cy="118" r=".8" opacity=".5" />
            <circle cx="1340" cy="58" r="1.1" opacity=".55" />
          </g>

          <g stroke="#fff" stroke-linecap="round">
            <path
                d="M176 88v8M172 92h8"
                stroke-width="1"
                opacity=".4"
            />

            <path
                d="M1118 94v10M1113 99h10"
                stroke-width="1"
                opacity=".5"
            />

            <path
                d="M128 210v8M124 214h8"
                stroke-width="1"
                opacity=".35"
            />
          </g>

          <path
              d="M0 470
               L110 392
               L220 438
               L330 350
               L440 428
               L560 344
               L700 418
               L830 340
               L960 420
               L1080 350
               L1200 430
               L1400 370
               V620
               H0Z"
              fill="url(#mountainBack)"
              opacity=".95"
          />

          <path
              d="M0 620
               L180 382
               L330 510
               L430 620Z"
              fill="url(#mountainBack)"
          />

          <path
              d="M970 620
               L1130 382
               L1400 580
               L1400 620Z"
              fill="url(#mountainBack)"
          />

          <path
              d="M80 620
               L350 282
               L590 620Z"
              fill="url(#mountainMid)"
          />

          <path
              d="M350 282
               L300 350
               L340 338
               L365 370
               L397 335
               L430 362
               L350 282Z"
              fill="url(#snow)"
              opacity=".7"
          />

          <path
              d="M810 620
               L1060 300
               L1340 620Z"
              fill="url(#mountainMid)"
          />

          <path
              d="M1060 300
               L1008 365
               L1048 350
               L1078 380
               L1110 342
               L1140 370
               L1060 300Z"
              fill="url(#snow)"
              opacity=".65"
          />

          <path
              d="M285 620
               L700 74
               L1115 620Z"
              fill="url(#mountainMain)"
          />

          <path
              d="M700 74
               L285 620
               L700 620Z"
              fill="url(#leftFace)"
              opacity=".75"
          />

          <path
              d="M700 74
               L1115 620
               L700 620Z"
              fill="url(#rightFace)"
              opacity=".95"
          />

          <path
              d="M700 74
               L628 170
               L655 158
               L676 190
               L700 176
               L725 194
               L747 158
               L772 174
               Z"
              fill="url(#snow)"
          />

          <path
              d="M700 74
               L653 160
               L680 147
               L700 177
               L724 145
               L750 161Z"
              fill="#fff"
              opacity=".22"
          />

          <path
              d="M700 74 L700 620"
              stroke="#fff"
              stroke-width="1"
              opacity=".055"
          />

          <path
              d="M700 74 L520 620"
              stroke="#fff"
              stroke-width="1"
              opacity=".04"
          />

          <path
              d="M700 74 L880 620"
              stroke="#000"
              stroke-width="1"
              opacity=".12"
          />

          <path
              d="M285 620 L700 74 L1115 620"
              fill="none"
              stroke="#a5b4fc"
              stroke-width="1"
              opacity=".12"
          />

          <path
              d="M520 430 L565 360 L600 420"
              fill="none"
              stroke="#fff"
              stroke-width="2"
              opacity=".06"
          />

          <path
              d="M850 430 L815 365 L780 420"
              fill="none"
              stroke="#fff"
              stroke-width="2"
              opacity=".045"
          />

          <path
              d="M0 550
               C160 500 260 515 390 550
               C530 590 610 570 700 550
               C810 525 920 575 1040 545
               C1170 512 1280 510 1400 550
               V620
               H0Z"
              fill="#06060d"
              opacity=".55"
          />

          <rect
              x="0"
              y="475"
              width="1400"
              height="120"
              fill="url(#mist)"
              filter="url(#smallBlur)"
          />

          <g fill="#fff">
            <circle cx="150" cy="470" r="1" opacity=".2" />
            <circle cx="265" cy="530" r=".8" opacity=".18" />
            <circle cx="420" cy="490" r=".7" opacity=".2" />
            <circle cx="980" cy="500" r=".8" opacity=".18" />
            <circle cx="1150" cy="470" r="1" opacity=".2" />
            <circle cx="1260" cy="525" r=".7" opacity=".15" />
          </g>
        </svg>

        <div class="apex-marker">
          <div class="apex-marker__line" />
          <div class="apex-marker__glow" />
          <span>APEX</span>
        </div>

        <!-- =====================================================
             DESKTOP PODIUM
             ===================================================== -->

        <div class="podium">
          <div
              v-for="slot in podiumSlots"
              :key="slot"
              class="podium__slot"
              :class="`podium__slot--rank${rankOf(slot)}`"
          >
            <div
                class="podium__badge"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <svg
                  v-if="slot === 0"
                  class="podium__medal"
                  width="21"
                  height="21"
                  viewBox="0 0 24 24"
                  fill="none"
              >
                <path
                    d="M8 2l4 8 4-8"
                    stroke="#facc15"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <circle
                    cx="12"
                    cy="15"
                    r="6"
                    fill="#facc15"
                    stroke="#a16207"
                    stroke-width="1.4"
                />

                <path
                    d="M12 12.5l1 2 2.2.3-1.6 1.5.4 2.2-2-1.1-2 1.1.4-2.2-1.6-1.5 2.2-.3z"
                    fill="#7c2d12"
                />
              </svg>

              <svg
                  v-else-if="slot === 1"
                  class="podium__medal"
                  width="21"
                  height="21"
                  viewBox="0 0 24 24"
                  fill="none"
              >
                <path
                    d="M8 2l4 8 4-8"
                    stroke="#cbd5e1"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <circle
                    cx="12"
                    cy="15"
                    r="6"
                    fill="#cbd5e1"
                    stroke="#64748b"
                    stroke-width="1.4"
                />

                <text
                    x="12"
                    y="18.6"
                    text-anchor="middle"
                    font-size="7"
                    font-weight="900"
                    fill="#334155"
                >
                  2
                </text>
              </svg>

              <svg
                  v-else
                  class="podium__medal"
                  width="21"
                  height="21"
                  viewBox="0 0 24 24"
                  fill="none"
              >
                <path
                    d="M8 2l4 8 4-8"
                    stroke="#d97706"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <circle
                    cx="12"
                    cy="15"
                    r="6"
                    fill="#f59e0b"
                    stroke="#92400e"
                    stroke-width="1.4"
                />

                <text
                    x="12"
                    y="18.6"
                    text-anchor="middle"
                    font-size="7"
                    font-weight="900"
                    fill="#78350f"
                >
                  3
                </text>
              </svg>

              <span class="podium__rank">
                #{{ rankOf(slot) }}
              </span>
            </div>

            <div
                class="podium__score"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.4"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
              </svg>

              <span>
                {{ scoreOf(topThree[slot]) }}
              </span>

              <small>RATING</small>
            </div>

            <RouterLink
                :to="userLink(topThree[slot])"
                class="podium__card"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <div class="podium__card-glow" />

              <div
                  class="rank-player"
                  :class="{
                    'rank-player--legendary':
                        topThree[slot].profile_effect === 'legendary'
                  }"
                  :style="profileEffectStyle(topThree[slot])"
              >
                <div class="rank-player__avatar-wrap">

                  <div
                      v-if="topThree[slot].avatar_url"
                      class="rank-player__avatar"
                  >
                    <img
                        :src="topThree[slot].avatar_url"
                        :alt="topThree[slot].username"
                    />
                  </div>

                  <div
                      v-else
                      class="rank-player__avatar rank-player__avatar--fallback"
                      :style="{ '--accent': accent(topThree[slot]) }"
                  >
                    {{ avatarLetter(topThree[slot]) }}
                  </div>

                  <span
                      v-if="topThree[slot].avatar_frame && topThree[slot].avatar_frame !== 'default'"
                      class="rank-player__frame"
                      :class="`rank-player__frame--${topThree[slot].avatar_frame}`"
                      :style="avatarFrame(topThree[slot])"
                  />

                  <span class="rank-player__online" />
                </div>

                <div class="rank-player__body">
                  <div class="rank-player__top">
                    <span class="rank-player__tier">
                      {{ topThree[slot].tier || '—' }}
                    </span>

                    <span
                        v-if="topThree[slot].clan_tag"
                        class="rank-player__clan"
                    >
                      [{{ topThree[slot].clan_tag }}]
                    </span>
                  </div>

                  <div class="rank-player__name">
                    {{ topThree[slot].username }}
                  </div>

                  <div
                      v-if="roleLabel(topThree[slot])"
                      class="rank-player__role"
                  >
                    {{ roleLabel(topThree[slot]) }}
                  </div>
                </div>
              </div>
            </RouterLink>
          </div>
        </div>
      </section>

      <!-- =======================================================
           MOBILE PODIUM
           ======================================================= -->

      <section
          v-if="topThree.length"
          class="mobile-podium"
      >
        <div
            v-for="slot in mobileSlots"
            :key="slot"
            class="mobile-podium__row"
            :class="`mobile-podium__row--rank${rankOf(slot)}`"
            :style="{ '--accent': accent(topThree[slot]) }"
        >
          <div class="mobile-podium__rank-wrap">
            <span class="mobile-podium__rank">
              #{{ rankOf(slot) }}
            </span>
          </div>

          <RouterLink
              :to="userLink(topThree[slot])"
              class="mobile-podium__card"
          >
            <div
                class="rank-player rank-player--mobile"
                :class="{
                  'rank-player--legendary':
                      topThree[slot].profile_effect === 'legendary'
                }"
                :style="{
                  '--accent': accent(topThree[slot]),
                  ...profileEffectStyle(topThree[slot])
                }"
            >
              <div class="rank-player__avatar-wrap">

                <div
                    v-if="topThree[slot].avatar_url"
                    class="rank-player__avatar"
                >
                  <img
                      :src="topThree[slot].avatar_url"
                      :alt="topThree[slot].username"
                  />
                </div>

                <div
                    v-else
                    class="rank-player__avatar rank-player__avatar--fallback"
                    :style="{ '--accent': accent(topThree[slot]) }"
                >
                  {{ avatarLetter(topThree[slot]) }}
                </div>

                <span
                    v-if="topThree[slot].avatar_frame && topThree[slot].avatar_frame !== 'default'"
                    class="rank-player__frame"
                    :class="`rank-player__frame--${topThree[slot].avatar_frame}`"
                    :style="avatarFrame(topThree[slot])"
                />
              </div>

              <div class="rank-player__body">
                <div class="rank-player__top">
                  <span class="rank-player__tier">
                    {{ topThree[slot].tier || '—' }}
                  </span>

                  <span
                      v-if="topThree[slot].clan_tag"
                      class="rank-player__clan"
                  >
                    [{{ topThree[slot].clan_tag }}]
                  </span>
                </div>

                <div class="rank-player__name">
                  {{ topThree[slot].username }}
                </div>

                <div
                    v-if="roleLabel(topThree[slot])"
                    class="rank-player__role"
                >
                  {{ roleLabel(topThree[slot]) }}
                </div>
              </div>
            </div>
          </RouterLink>

          <span class="mobile-podium__score">
            <svg
                width="11"
                height="11"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
            >
              <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
            </svg>

            {{ scoreOf(topThree[slot]) }}
          </span>
        </div>
      </section>

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
