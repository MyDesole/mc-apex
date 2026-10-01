<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/api.js'
import { playersApi } from '@/services/players.js'
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
/* ============================================================
   ПОДГРУЗКА РЕЙТИНГА (курсорная пагинация + виртуальное окно)
   ============================================================ */

.rating-more {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.rating-more__btn {
  min-height: 42px;
  padding: 0 22px;
  color: var(--text, #e2e2e8);
  background: var(--bg-card, #12121a);
  border: 1px solid var(--border, #22222e);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease, transform 0.15s ease;
}

.rating-more__btn:hover:not(:disabled) {
  background: var(--bg-card-hover, #1a1a26);
  border-color: var(--accent, #7c3aed);
  transform: translateY(-1px);
}

.rating-more__btn:disabled {
  opacity: 0.6;
  cursor: progress;
}

.rating-more__end {
  margin-top: 20px;
  color: var(--text-muted, #5e5e70);
  font-size: 12px;
  text-align: center;
}

/* Браузер пропускает отрисовку строк за пределами экрана —
   это виртуализация без ручного расчёта высот */
.rest__row,
:deep(.rest__row) {
  content-visibility: auto;
  contain-intrinsic-size: auto 88px;
}

/* ============================================================
   PAGE
   ============================================================ */

.rating-page {
  width: min(1100px, calc(100% - 40px));
  margin: 32px auto 70px;
}

/* ============================================================
   HEADER
   ============================================================ */

.rating-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;

  margin-bottom: 18px;

  flex-wrap: wrap;
}

.rating-head__eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;

  margin-bottom: 7px;

  color: #64748b;

  font-size: 9px;
  font-weight: 900;
  letter-spacing: 2px;
}

.rating-head__eyebrow-line {
  width: 22px;
  height: 1px;

  background: #facc15;

  box-shadow: 0 0 8px rgba(250, 204, 21, .6);
}

.rating-head__title {
  margin: 0 0 6px;

  color: var(--text);

  font-size: 30px;
  font-weight: 950;
  letter-spacing: -.8px;
}

.apex {
  background: linear-gradient(
      135deg,
      #fff7ae 0%,
      #facc15 35%,
      #f97316 100%
  );

  -webkit-background-clip: text;
  background-clip: text;

  color: transparent;

  filter:
      drop-shadow(0 0 12px rgba(250, 204, 21, .25))
      drop-shadow(0 0 28px rgba(250, 204, 21, .12));
}

.rating-head__sub {
  margin: 0;

  color: var(--text-dim);

  font-size: 13.5px;
}

.rating-head__sub strong {
  color: var(--text);
  font-weight: 800;
}

/* ============================================================
   MAIN TABS
   ============================================================ */

.mode-tabs {
  display: inline-flex;
  gap: 5px;

  padding: 4px;

  background: rgba(10, 10, 18, .72);

  border: 1px solid var(--border);
  border-radius: 13px;

  box-shadow:
      0 10px 30px rgba(0, 0, 0, .2),
      inset 0 1px rgba(255, 255, 255, .025);

  backdrop-filter: blur(12px);
}

.mode-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  padding: 8px 14px;

  color: var(--text-dim);
  background: transparent;

  border: 0;
  border-radius: 9px;

  font: inherit;
  font-size: 13px;
  font-weight: 750;

  cursor: pointer;

  transition:
      color .2s ease,
      background .2s ease,
      box-shadow .2s ease,
      transform .2s ease;
}

.mode-tab:hover {
  color: var(--text);
}

.mode-tab:active {
  transform: translateY(1px);
}

.mode-tab--active {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--tab-color) 22%, transparent),
          color-mix(in srgb, var(--tab-color) 8%, transparent)
      );

  box-shadow:
      inset 0 0 0 1px
      color-mix(in srgb, var(--tab-color) 48%, transparent),
      0 4px 16px
      color-mix(in srgb, var(--tab-color) 10%, transparent);
}

.mode-tab__icon {
  flex-shrink: 0;
}

/* ============================================================
   SUB TABS
   ============================================================ */

.sub-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  margin-bottom: 24px;
  padding: 0 4px;

  animation: fadeIn .2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.sub-tab {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  padding: 6px 13px;

  color: var(--text-dim);
  background: rgba(10, 10, 18, .35);

  border: 1px solid var(--border);
  border-radius: 999px;

  font: inherit;
  font-size: 12.5px;
  font-weight: 700;

  cursor: pointer;

  transition: all .18s ease;
}

.sub-tab:hover {
  color: var(--text);

  border-color: color-mix(
      in srgb,
      var(--tab-color) 30%,
      var(--border)
  );
}

.sub-tab__dot {
  width: 7px;
  height: 7px;

  border-radius: 50%;

  background: var(--tab-color);
  opacity: .35;

  transition:
      opacity .18s ease,
      box-shadow .18s ease;
}

.sub-tab--active {
  color: #fff;

  border-color: color-mix(
      in srgb,
      var(--tab-color) 55%,
      transparent
  );

  background: color-mix(
      in srgb,
      var(--tab-color) 10%,
      transparent
  );
}

.sub-tab--active .sub-tab__dot {
  opacity: 1;

  box-shadow: 0 0 9px var(--tab-color);
}

/* ============================================================
   STATES
   ============================================================ */

.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;

  padding: 90px 20px;

  text-align: center;

  color: var(--text-dim);

  font-size: 14px;
}

.state--empty {
  opacity: .85;
}

.state__title {
  color: var(--text);

  font-size: 16px;
  font-weight: 800;
}

.state__sub {
  max-width: 280px;

  color: var(--text-muted);

  font-size: 12.5px;
  line-height: 1.5;

  text-align: center;
}

.spinner {
  width: 28px;
  height: 28px;

  border: 2.5px solid
  color-mix(in srgb, var(--accent) 25%, transparent);

  border-top-color: var(--accent);

  border-radius: 50%;

  animation: spin .8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ============================================================
   MOUNTAIN
   ============================================================ */

.mountain {
  position: relative;

  width: 100%;
  min-height: 520px;

  margin-bottom: 42px;

  aspect-ratio: 1400 / 620;

  overflow: hidden;

  border: 1px solid rgba(255, 255, 255, .07);
  border-radius: 22px;

  background: #070710;

  box-shadow:
      0 35px 70px rgba(0, 0, 0, .4),
      0 10px 25px rgba(0, 0, 0, .25),
      inset 0 1px rgba(255, 255, 255, .04);
}

.mountain::after {
  content: "";

  position: absolute;
  inset: 0;

  pointer-events: none;

  border-radius: inherit;

  box-shadow:
      inset 0 0 80px rgba(0, 0, 0, .45),
      inset 0 -40px 80px rgba(0, 0, 0, .4);
}

.mountain__bg {
  position: absolute;
  inset: 0;

  display: block;

  width: 100%;
  height: 100%;
}

.mountain__vignette {
  position: absolute;
  z-index: 2;
  inset: 0;

  pointer-events: none;

  background:
      radial-gradient(
          ellipse at 50% 20%,
          transparent 0%,
          rgba(0, 0, 0, .04) 45%,
          rgba(0, 0, 0, .48) 100%
      );
}

.mountain__noise {
  position: absolute;
  z-index: 3;
  inset: 0;

  pointer-events: none;

  opacity: .035;

  background-image:
      url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.7'/%3E%3C/svg%3E");
}

/* ============================================================
   APEX MARKER
   ============================================================ */

.apex-marker {
  position: absolute;
  z-index: 5;

  top: 9%;
  left: 50%;

  display: flex;
  flex-direction: column;
  align-items: center;

  transform: translateX(-50%);

  color: rgba(250, 204, 21, .8);

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 3px;

  pointer-events: none;
}

.apex-marker__line {
  width: 1px;
  height: 22px;

  background: linear-gradient(
      to bottom,
      transparent,
      rgba(250, 204, 21, .8)
  );
}

.apex-marker__glow {
  width: 5px;
  height: 5px;

  margin-bottom: 5px;

  border-radius: 50%;

  background: #fde68a;

  box-shadow:
      0 0 6px #facc15,
      0 0 16px #facc15,
      0 0 30px rgba(250, 204, 21, .5);

  animation: apexPulse 2.5s ease-in-out infinite;
}

@keyframes apexPulse {
  0%,
  100% {
    opacity: .65;
    transform: scale(.9);
  }

  50% {
    opacity: 1;
    transform: scale(1.15);
  }
}

/* ============================================================
   PODIUM
   ============================================================ */

.podium {
  position: absolute;
  z-index: 10;
  inset: 0;

  pointer-events: none;
}

.podium__slot {
  position: absolute;

  display: flex;
  flex-direction: column;
  align-items: center;

  gap: 7px;

  min-width: 0;

  transform: translate(-50%, -50%);

  pointer-events: auto;
}

.podium__slot--rank1 {
  top: 20%;
  left: 50%;

  z-index: 3;

  width: clamp(220px, 30%, 350px);
}

.podium__slot--rank2 {
  top: 61%;
  left: 26%;

  z-index: 2;

  width: clamp(180px, 24%, 290px);
}

.podium__slot--rank3 {
  top: 80%;
  left: 74%;

  z-index: 1;

  width: clamp(180px, 24%, 290px);
}

/* ============================================================
   BADGE
   ============================================================ */

.podium__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 4px 10px;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--accent) 22%, rgba(8, 8, 14, .92)),
          rgba(8, 8, 14, .88)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 58%, transparent);

  border-radius: 999px;

  box-shadow:
      0 7px 22px
      color-mix(in srgb, var(--accent) 25%, transparent),
      inset 0 1px rgba(255, 255, 255, .07);

  backdrop-filter: blur(10px);
}

.podium__medal {
  display: block;
}

.podium__rank {
  color: #fff;

  font-size: 11px;
  font-weight: 950;
  letter-spacing: .5px;
}

/* ============================================================
   SCORE
   ============================================================ */

.podium__score {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 4px 11px;

  background: rgba(5, 5, 11, .9);

  border: 1px solid
  color-mix(in srgb, var(--accent) 45%, transparent);

  border-radius: 999px;

  color:
      color-mix(in srgb, var(--accent) 82%, #fff);

  font-size: 12px;
  font-weight: 950;
  letter-spacing: .35px;

  backdrop-filter: blur(10px);

  box-shadow:
      0 5px 18px
      color-mix(in srgb, var(--accent) 18%, transparent),
      inset 0 1px rgba(255, 255, 255, .04);
}

.podium__score svg {
  color: var(--accent);

  filter:
      drop-shadow(
          0 0 5px
          color-mix(in srgb, var(--accent) 70%, transparent)
      );
}

.podium__score small {
  margin-left: 2px;

  color: rgba(255, 255, 255, .3);

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
}

/* ============================================================
   PLAYER CARD
   ============================================================ */

.podium__card {
  position: relative;

  display: block;

  width: 100%;

  color: inherit;
  text-decoration: none;

  cursor: pointer;

  filter:
      drop-shadow(0 20px 30px rgba(0, 0, 0, .55))
      drop-shadow(
          0 0 22px
          color-mix(in srgb, var(--accent) 28%, transparent)
      );

  transition:
      transform .25s ease,
      filter .25s ease;
}

.podium__card-glow {
  position: absolute;
  z-index: -1;

  inset: 15% 10% -10%;

  border-radius: 50%;

  background:
      radial-gradient(
          ellipse,
          color-mix(in srgb, var(--accent) 25%, transparent),
          transparent 68%
      );

  filter: blur(20px);

  opacity: .65;

  transition: opacity .25s ease;
}

.podium__card:hover {
  transform: translateY(-5px) scale(1.015);

  filter:
      drop-shadow(0 24px 35px rgba(0, 0, 0, .6))
      drop-shadow(
          0 0 30px
          color-mix(in srgb, var(--accent) 42%, transparent)
      );
}

.podium__card:hover .podium__card-glow {
  opacity: 1;
}

/* ============================================================
   RANK PLAYER
   ============================================================ */

.rank-player {
  position: relative;

  display: flex;
  align-items: center;
  gap: 12px;

  width: 100%;
  min-width: 0;

  padding: 8px 10px;

  overflow: hidden;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--accent) 10%, transparent),
          rgba(8, 8, 14, .82)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 22%, var(--border));

  border-radius: 14px;

  box-shadow:
      inset 0 1px rgba(255,255,255,.04),
      0 10px 25px rgba(0,0,0,.2);
}

.rank-player::before {
  content: "";

  position: absolute;
  inset: 0;

  pointer-events: none;

  background:
      radial-gradient(
          circle at 0% 50%,
          color-mix(in srgb, var(--accent) 20%, transparent),
          transparent 55%
      );

  opacity: .7;
}

.rank-player--legendary {
  border-color:
      color-mix(in srgb, var(--effect-color) 55%, var(--border));

  box-shadow:
      inset 0 1px rgba(255,255,255,.06),
      0 0 25px
      color-mix(in srgb, var(--effect-color) 18%, transparent);
}

.rank-player--legendary::after {
  content: "";

  position: absolute;
  inset: -50%;

  background:
      conic-gradient(
          from 0deg,
          transparent,
          rgba(250,204,21,.08),
          transparent,
          rgba(249,115,22,.08),
          transparent
      );

  animation: legendarySpin 5s linear infinite;

  pointer-events: none;
}

@keyframes legendarySpin {
  to {
    transform: rotate(360deg);
  }
}

/* ============================================================
   RANK AVATAR
   ============================================================ */

.rank-player__avatar-wrap {
  position: relative;
  z-index: 2;

  flex: 0 0 auto;

  width: 54px;
  height: 54px;
}

.rank-player__avatar {
  display: block;

  width: 100%;
  height: 100%;

  overflow: hidden;

  background: #090910;

  border: 2px solid
  color-mix(in srgb, var(--accent) 45%, transparent);

  border-radius: 12px;

  box-shadow:
      0 0 20px
      color-mix(in srgb, var(--accent) 20%, transparent);
}

.rank-player__avatar img {
  display: block;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

.rank-player__avatar--fallback {
  display: flex;
  align-items: center;
  justify-content: center;

  color: color-mix(in srgb, var(--accent) 88%, #fff);

  background:
      radial-gradient(
          circle at 30% 25%,
          color-mix(in srgb, var(--accent) 32%, transparent),
          transparent 62%
      ),
      color-mix(in srgb, var(--accent) 10%, #090910);

  font-size: 21px;
  font-weight: 950;

  text-shadow:
      0 0 14px
      color-mix(in srgb, var(--accent) 60%, transparent);
}

.rank-player__frame {
  position: absolute;
  z-index: 3;

  inset: -3px;

  border-radius: 14px;

  pointer-events: none;
}

.rank-player__frame--purple {
  border: 2px solid #7c3aed;
  box-shadow: 0 0 12px #7c3aed;
}

.rank-player__frame--cyan {
  border: 2px solid #06b6d4;
  box-shadow: 0 0 12px #06b6d4;
}

.rank-player__frame--green {
  border: 2px solid #22c55e;
  box-shadow: 0 0 12px #22c55e;
}

.rank-player__frame--gold {
  border: 2px solid #facc15;
  box-shadow: 0 0 14px rgba(250,204,21,.65);
}

.rank-player__frame--orange {
  border: 2px solid #f97316;
  box-shadow: 0 0 14px rgba(249,115,22,.6);
}

.rank-player__frame--pink {
  border: 2px solid #ec4899;
  box-shadow: 0 0 14px rgba(236,72,153,.6);
}

.rank-player__frame--red {
  border: 2px solid #ef4444;
  box-shadow: 0 0 14px rgba(239,68,68,.6);
}

.rank-player__frame--rainbow {
  border: 2px solid transparent;

}

.rank-player__frame--legendary {
  border: 2px solid transparent;


  box-shadow:
      0 0 10px #facc15,
      0 0 25px rgba(249,115,22,.45);
}

.rank-player__frame--season1 {
  border: 2px solid transparent;


  box-shadow:
      0 0 14px rgba(124,58,237,.4);
}

.rank-player__online {
  position: absolute;
  z-index: 4;

  right: -2px;
  bottom: -2px;

  width: 10px;
  height: 10px;

  border: 2px solid #08080f;
  border-radius: 50%;

  background: #22c55e;
}

/* ============================================================
   RANK INFO
   ============================================================ */

.rank-player__body {
  position: relative;
  z-index: 2;

  min-width: 0;
}

.rank-player__top {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 2px;
}

.rank-player__tier {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-width: 25px;
  height: 20px;

  padding: 0 6px;

  color: var(--accent);

  background:
      color-mix(in srgb, var(--accent) 12%, transparent);

  border: 1px solid
  color-mix(in srgb, var(--accent) 35%, transparent);

  border-radius: 6px;

  font-size: 10px;
  font-weight: 950;
}

.rank-player__clan {
  color: var(--text-muted);

  font-size: 10px;
  font-weight: 800;
}

.rank-player__name {
  overflow: hidden;

  color: #fff;

  font-size: 14px;
  font-weight: 900;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.rank-player__role {
  margin-top: 1px;

  overflow: hidden;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 700;

  white-space: nowrap;
  text-overflow: ellipsis;
}

/* ============================================================
   MOBILE PODIUM
   ============================================================ */

.mobile-podium {
  display: none;

  flex-direction: column;
  gap: 10px;

  margin-bottom: 32px;
}

.mobile-podium__row {
  position: relative;

  display: grid;

  grid-template-columns: 40px minmax(0, 1fr) auto;

  align-items: center;

  gap: 12px;

  padding: 10px 12px;

  overflow: visible;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--accent) 7%, transparent),
          transparent 55%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 14px;

  box-shadow:
      0 8px 25px rgba(0, 0, 0, .12);

  transition:
      transform .2s ease,
      border-color .2s ease;
}

.mobile-podium__row:hover {
  transform: translateY(-1px);
}

.mobile-podium__row--rank1 {
  border-color: rgba(250, 204, 21, .4);

  box-shadow:
      0 0 30px -12px rgba(250, 204, 21, .4),
      0 10px 30px rgba(0, 0, 0, .15);
}

.mobile-podium__row--rank2 {
  border-color: rgba(203, 213, 225, .25);
}

.mobile-podium__row--rank3 {
  border-color: rgba(245, 158, 11, .28);
}

.mobile-podium__rank-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.mobile-podium__rank {
  color: var(--accent);

  font-size: 12px;
  font-weight: 950;
}

.mobile-podium__card {
  display: block;

  min-width: 0;

  color: inherit;
  text-decoration: none;

  cursor: pointer;
}

.mobile-podium__score {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;

  min-width: 48px;

  padding: 5px 8px;

  color:
      color-mix(in srgb, var(--accent) 82%, #fff);

  background:
      color-mix(
          in srgb,
          var(--accent) 14%,
          rgba(10, 10, 15, .9)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 42%, transparent);

  border-radius: 999px;

  font-size: 12px;
  font-weight: 950;
}

/* ============================================================
   REST
   ============================================================ */

.rest {
  position: relative;

  margin-top: 16px;
}

.rest__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 20px;

  padding: 0 4px 14px;
  margin-bottom: 12px;

  border-bottom: 1px solid var(--border);
}

.rest__heading {
  min-width: 0;
}

.rest__eyebrow {
  display: block;

  margin-bottom: 4px;

  color: #64748b;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.8px;
}

.rest__heading-row {
  display: flex;
  align-items: center;
  gap: 9px;
}

.rest__title {
  margin: 0;

  color: var(--text);

  font-size: 15px;
  font-weight: 850;

  text-transform: uppercase;
  letter-spacing: 1px;
}

.rest__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-width: 25px;
  height: 22px;

  padding: 0 7px;

  color: var(--text-muted);

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 999px;

  font-size: 11px;
  font-weight: 800;
}

.rest__legend {
  display: flex;
  align-items: center;
  gap: 6px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1.5px;
}

.rest__legend-dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: #22c55e;

  box-shadow:
      0 0 8px rgba(34, 197, 94, .65);
}

.rest__grid {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.rest__row {
  position: relative;

  display: grid;

  grid-template-columns: 62px minmax(220px, 1.1fr) minmax(180px, .8fr) 90px 24px;

  align-items: center;

  gap: 16px;

  min-height: 66px;

  padding: 8px 12px 8px 8px;

  overflow: hidden;

  color: inherit;
  text-decoration: none;

  background:
      linear-gradient(
          90deg,
          color-mix(in srgb, var(--accent) 4%, transparent),
          rgba(10, 10, 17, .28) 45%,
          transparent
      );

  border: 1px solid rgba(255, 255, 255, .045);
  border-radius: 13px;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .025),
      0 5px 18px rgba(0, 0, 0, .08);

  cursor: pointer;

  transition:
      transform .2s ease,
      border-color .2s ease,
      background .2s ease,
      box-shadow .2s ease;
}

.rest__row::before {
  content: "";

  position: absolute;

  top: 0;
  bottom: 0;
  left: 0;

  width: 2px;

  background: var(--accent);

  opacity: .3;

  transition:
      opacity .2s ease,
      box-shadow .2s ease;
}

.rest__row:hover {
  transform: translateX(3px);

  border-color:
      color-mix(in srgb, var(--accent) 22%, rgba(255,255,255,.05));

  background:
      linear-gradient(
          90deg,
          color-mix(in srgb, var(--accent) 9%, transparent),
          rgba(10, 10, 17, .42) 48%,
          transparent
      );

  box-shadow:
      0 10px 28px rgba(0, 0, 0, .15),
      0 0 25px
      color-mix(in srgb, var(--accent) 7%, transparent);
}

.rest__row:hover::before {
  opacity: 1;

  box-shadow:
      0 0 14px
      color-mix(in srgb, var(--accent) 70%, transparent);
}

/* ============================================================
   REST RANK
   ============================================================ */

.rest__rank {
  position: relative;

  display: flex;
  align-items: center;
  justify-content: center;

  min-height: 44px;
}

.rest__rank-number {
  position: relative;
  z-index: 1;

  color: color-mix(in srgb, var(--accent) 70%, var(--text-muted));

  font-size: 12px;
  font-weight: 950;
  letter-spacing: .3px;
}

.rest__rank-line {
  position: absolute;

  left: 50%;
  top: 0;
  bottom: 0;

  width: 1px;

  background:
      linear-gradient(
          to bottom,
          transparent,
          color-mix(in srgb, var(--accent) 22%, transparent),
          transparent
      );

  transform: translateX(-50%);

  opacity: .7;
}

/* ============================================================
   REST PLAYER
   ============================================================ */

.rest-player {
  position: relative;

  display: flex;
  align-items: center;
  gap: 11px;

  min-width: 0;

  padding: 2px 0;
}

.rest-player--legendary::after {
  content: "";

  position: absolute;

  inset: -15px -30px;

  background:
      radial-gradient(
          ellipse at left,
          rgba(250,204,21,.1),
          transparent 65%
      );

  pointer-events: none;
}

.rest-player__avatar-wrap {
  position: relative;

  flex: 0 0 auto;

  width: 38px;
  height: 38px;
}

.rest-player__avatar {
  display: block;

  width: 100%;
  height: 100%;

  overflow: hidden;

  background: #090910;

  border: 1px solid
  color-mix(in srgb, var(--accent) 30%, var(--border));

  border-radius: 10px;

  box-shadow:
      0 0 12px
      color-mix(in srgb, var(--accent) 8%, transparent);
}

.rest-player__avatar img {
  display: block;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

.rest-player__avatar--fallback {
  display: flex;
  align-items: center;
  justify-content: center;

  color: color-mix(in srgb, var(--accent) 88%, #fff);

  background:
      radial-gradient(
          circle at 30% 25%,
          color-mix(in srgb, var(--accent) 30%, transparent),
          transparent 65%
      ),
      color-mix(in srgb, var(--accent) 9%, #090910);

  font-size: 14px;
  font-weight: 950;

  text-shadow:
      0 0 10px
      color-mix(in srgb, var(--accent) 55%, transparent);
}

.rest-player__frame {
  position: absolute;

  inset: -2px;

  border-radius: 11px;

  pointer-events: none;
}

.rest-player__frame--purple {
  border: 1px solid #7c3aed;
}

.rest-player__frame--cyan {
  border: 1px solid #06b6d4;
}

.rest-player__frame--green {
  border: 1px solid #22c55e;
}

.rest-player__frame--gold {
  border: 1px solid #facc15;
  box-shadow: 0 0 8px rgba(250,204,21,.5);
}

.rest-player__frame--orange {
  border: 1px solid #f97316;
}

.rest-player__frame--pink {
  border: 1px solid #ec4899;
}

.rest-player__frame--red {
  border: 1px solid #ef4444;
}

.rest-player__frame--rainbow {
  border: 1px solid transparent;


}

.rest-player__frame--legendary {
  border: 1px solid transparent;


  box-shadow:
      0 0 8px rgba(250,204,21,.6);
}

.rest-player__frame--season1 {
  border: 1px solid transparent;


}

.rest-player__info {
  position: relative;
  z-index: 1;

  min-width: 0;
}

.rest-player__name {
  overflow: hidden;

  color: var(--text);

  font-size: 12.5px;
  font-weight: 850;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.rest-player__clan {
  margin-right: 4px;

  color: var(--text-muted);
}

.rest-player__meta {
  display: flex;
  align-items: center;
  gap: 7px;

  margin-top: 3px;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 700;
}

.rest-player__tier {
  color: var(--accent);

  font-weight: 900;
}

.rest-player__role {
  padding-left: 7px;

  border-left: 1px solid var(--border);
}

/* ============================================================
   REST PROGRESS
   ============================================================ */

.rest__progress {
  min-width: 0;
}

.rest__progress-top {
  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 6px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1.2px;
}

.rest__progress-value {
  color: color-mix(in srgb, var(--accent) 65%, var(--text-muted));

  font-size: 9px;
  letter-spacing: 0;
}

.rest__progress-track {
  position: relative;

  width: 100%;
  height: 4px;

  overflow: hidden;

  background: rgba(255, 255, 255, .045);

  border-radius: 999px;
}

.rest__progress-fill {
  display: block;

  height: 100%;

  min-width: 4px;

  background:
      linear-gradient(
          90deg,
          color-mix(in srgb, var(--accent) 55%, transparent),
          var(--accent)
      );

  border-radius: inherit;

  box-shadow:
      0 0 10px
      color-mix(in srgb, var(--accent) 35%, transparent);

  transition: width .5s ease;
}

/* ============================================================
   REST SCORE
   ============================================================ */

.rest__score {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;

  min-width: 72px;

  padding: 7px 9px;

  color:
      color-mix(in srgb, var(--accent) 82%, #fff);

  background:
      color-mix(
          in srgb,
          var(--accent) 8%,
          rgba(8, 8, 14, .7)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 24%, transparent);

  border-radius: 8px;

  font-size: 11px;
  font-weight: 950;

  box-shadow:
      inset 0 1px rgba(255,255,255,.025);
}

.rest__score svg {
  color: var(--accent);

  filter:
      drop-shadow(
          0 0 5px
          color-mix(in srgb, var(--accent) 60%, transparent)
      );
}

/* ============================================================
   REST ARROW
   ============================================================ */

.rest__arrow {
  display: flex;
  align-items: center;
  justify-content: center;

  color: #334155;

  transition:
      color .2s ease,
      transform .2s ease;
}

.rest__row:hover .rest__arrow {
  color: var(--accent);

  transform: translateX(3px);
}

/* ============================================================
   RESPONSIVE 900
   ============================================================ */

@media (max-width: 900px) {
  .rating-page {
    width: calc(100% - 32px);

    margin: 24px auto 48px;
  }

  .rating-head__title {
    font-size: 24px;
  }

  .mountain {
    min-height: 450px;

    aspect-ratio: 1400 / 650;
  }

  .podium__slot--rank1 {
    top: 20%;
    left: 50%;

    width: clamp(180px, 28%, 260px);
  }

  .podium__slot--rank2 {
    top: 61%;
    left: 25%;

    width: clamp(150px, 22%, 220px);
  }

  .podium__slot--rank3 {
    top: 81%;
    left: 75%;

    width: clamp(150px, 22%, 220px);
  }

  .apex-marker {
    top: 8%;
  }

  .rank-player__avatar-wrap {
    width: 48px;
    height: 48px;
  }

  .rest__row {
    grid-template-columns: 52px minmax(180px, 1fr) minmax(140px, .7fr) 76px 20px;

    gap: 11px;
  }
}

/* ============================================================
   RESPONSIVE 760
   ============================================================ */

@media (max-width: 760px) {
  .rest__row {
    grid-template-columns:
        48px
        minmax(180px, 1fr)
        76px
        20px;
  }

  .rest__progress {
    display: none;
  }

  .rest__score {
    min-width: 68px;
  }

  .rest__legend {
    display: none;
  }
}

/* ============================================================
   RESPONSIVE 640
   ============================================================ */

@media (max-width: 640px) {
  .rating-page {
    width: calc(100% - 24px);

    margin: 16px auto 40px;
  }

  .rating-head {
    flex-direction: column;
    align-items: stretch;

    gap: 14px;
  }

  .rating-head__eyebrow {
    font-size: 8px;
  }

  .rating-head__title {
    font-size: 22px;

    letter-spacing: -.5px;
  }

  .rating-head__sub {
    font-size: 12.5px;
  }

  .mode-tabs {
    width: 100%;
  }

  .mode-tab {
    flex: 1;

    padding: 9px 10px;

    font-size: 12.5px;
  }

  .sub-tabs {
    gap: 6px;

    padding: 0;
    margin-bottom: 18px;

    flex-wrap: nowrap;

    overflow-x: auto;

    scrollbar-width: none;
  }

  .sub-tabs::-webkit-scrollbar {
    display: none;
  }

  .sub-tab {
    flex-shrink: 0;

    padding: 6px 11px;

    font-size: 12px;

    white-space: nowrap;
  }

  .mountain {
    display: none;
  }

  .mobile-podium {
    display: flex;
  }

  .rank-player {
    padding: 6px 8px;

    gap: 9px;

    border-radius: 11px;
  }

  .rank-player__avatar-wrap {
    width: 42px;
    height: 42px;
  }

  .rank-player__avatar {
    border-radius: 10px;
  }

  .rank-player__name {
    font-size: 12.5px;
  }

  .rank-player__tier {
    height: 18px;

    min-width: 23px;

    font-size: 9px;
  }

  .rank-player__clan {
    font-size: 9px;
  }

  .rank-player__role {
    font-size: 8px;
  }

  .rest__head {
    padding-bottom: 10px;
    margin-bottom: 10px;
  }

  .rest__title {
    font-size: 13px;

    letter-spacing: .6px;
  }

  .rest__row {
    grid-template-columns:
        34px
        minmax(0, 1fr)
        auto
        16px;

    gap: 8px;

    min-height: 60px;

    padding: 7px 8px 7px 5px;
  }

  .rest__rank {
    min-height: 38px;
  }

  .rest__rank-number {
    font-size: 10px;
  }

  .rest__score {
    min-width: 58px;

    padding: 6px 7px;

    font-size: 10px;
  }

  .rest__arrow {
    display: flex;
  }

  .rest-player__avatar-wrap {
    width: 34px;
    height: 34px;
  }

  .rest-player__avatar {
    border-radius: 9px;
  }

  .rest-player__name {
    font-size: 11.5px;
  }

  .rest-player__meta {
    font-size: 8px;
  }

  .rest__eyebrow {
    font-size: 7px;
  }
}

/* ============================================================
   RESPONSIVE 420
   ============================================================ */

@media (max-width: 420px) {
  .rating-page {
    width: calc(100% - 18px);
  }

  .rating-head__title {
    font-size: 20px;
  }

  .mobile-podium__row {
    grid-template-columns: 34px minmax(0, 1fr) auto;

    gap: 9px;

    padding: 9px;
  }

  .mobile-podium__score {
    min-width: 43px;

    padding: 5px 6px;

    font-size: 11px;
  }

  .rank-player__avatar-wrap {
    width: 38px;
    height: 38px;
  }

  .rank-player__avatar--fallback {
    font-size: 16px;
  }

  .rank-player__name {
    font-size: 12px;
  }

  .rank-player__role {
    display: none;
  }

  .rest__row {
    grid-template-columns:
        30px
        minmax(0, 1fr)
        auto
        14px;

    gap: 6px;

    padding-right: 5px;
  }

  .rest__rank-number {
    font-size: 9px;
  }

  .rest-player {
    gap: 8px;
  }

  .rest-player__avatar-wrap {
    width: 32px;
    height: 32px;
  }

  .rest-player__name {
    font-size: 11px;
  }

  .rest-player__clan {
    margin-right: 2px;
  }

  .rest-player__meta {
    gap: 5px;
  }

  .rest__score {
    min-width: 52px;

    padding: 5px 6px;

    font-size: 9px;
  }
}
</style>