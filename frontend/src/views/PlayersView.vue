<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/api.js'
import PlayerProfileHeader from '@/components/player-profile/PlayerProfileHeader.vue'

const category = ref('pvp') // 'pvp' | 'other'
const pvpMode = ref('overall') // 'overall' | 'pvp' | 'bedwars'

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

async function load() {
  if (category.value === 'other') {
    players.value = []
    loading.value = false
    return
  }

  loading.value = true
  try {
    const data = await api.get(`/players/rating?mode=${pvpMode.value}`)
    players.value = (data.data ?? []).slice(0, 10)
  } catch (e) {
    console.error(e)
    players.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch([category, pvpMode], load)

const topThree = computed(() => players.value.slice(0, 3))
const rest = computed(() => players.value.slice(3))

const PODIUM_ORDER = [1, 0, 2]   // десктоп: слева 2, центр 1, справа 3
const MOBILE_ORDER = [0, 1, 2]   // мобилка: 1, 2, 3 сверху вниз

// Защита от короткого topThree (меньше 3 игроков)
const podiumSlots = computed(() =>
    PODIUM_ORDER.filter(i => i < topThree.value.length)
)
const mobileSlots = computed(() =>
    MOBILE_ORDER.filter(i => i < topThree.value.length)
)

const TIER_ACCENTS = {
  'S+': '#fbbf24', S: '#facc15', A: '#f97316',
  B: '#8b5cf6', C: '#06b6d4', D: '#22c55e', E: '#6b7280',
}
function accent(p) { return TIER_ACCENTS[p?.tier] || '#7c3aed' }
function rankOf(slotIdx) { return slotIdx + 1 }

function scoreOf(player) {
  return player?.rating_score ?? 0
}
</script>

<template>
  <div class="rating-page">
    <!-- HEAD -->
    <header class="rating-head">
      <div class="rating-head__text">
        <h1 class="rating-head__title">
          Восхождение к <span class="apex">APEX</span>
        </h1>
        <p class="rating-head__sub">
          Лучшие игроки ·
          <strong>{{ CATEGORIES.find(c => c.value === category)?.label }}</strong>
          <template v-if="category === 'pvp'">
            · <strong>{{ PVP_MODES.find(m => m.value === pvpMode)?.label }}</strong>
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
          <svg v-if="c.value === 'pvp'" class="mode-tab__icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.5 17.5 3 6V3h3l11.5 11.5" />
            <path d="M13 19l6-6" />
            <path d="M16 16l4 4" />
            <path d="M19 21l2-2" />
            <path d="M9.5 6.5 21 18v3h-3L6.5 9.5" />
            <path d="M5 14l6 6" />
            <path d="M2 19l4-4" />
            <path d="M3 21l2-2" />
          </svg>
          <svg v-else class="mode-tab__icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
          <span class="mode-tab__label">{{ c.label }}</span>
        </button>
      </div>
    </header>

    <!-- SUBTABS -->
    <div v-if="category === 'pvp'" class="sub-tabs">
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
        <span class="sub-tab__label">{{ m.label }}</span>
      </button>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="state">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <!-- ЗАГЛУШКА: Не PvP -->
    <div v-else-if="category === 'other'" class="state state--empty">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
        <path d="M12 8v4" />
        <circle cx="12" cy="16" r="0.5" fill="currentColor" />
      </svg>
      <span class="state__title">В разработке</span>
      <span class="state__sub">Скоро здесь появятся рейтинги по другим режимам</span>
    </div>

    <template v-else>
      <!-- ============================================
           MOUNTAIN (desktop / tablet)
           ============================================ -->
      <section v-if="topThree.length" class="mountain">
        <svg class="mountain__bg" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
          <defs>
            <linearGradient id="skyGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#0a0a18" />
              <stop offset="60%" stop-color="#0d0d1a" />
              <stop offset="100%" stop-color="#0a0a12" />
            </linearGradient>

            <linearGradient id="peakBackGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#1b1b2e" />
              <stop offset="100%" stop-color="#0e0e18" />
            </linearGradient>

            <linearGradient id="peakMidGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#25253f" />
              <stop offset="100%" stop-color="#12121f" />
            </linearGradient>

            <linearGradient id="peakFrontGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#2f2f52" />
              <stop offset="100%" stop-color="#15152a" />
            </linearGradient>

            <linearGradient id="snowGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="rgba(255,255,255,0.85)" />
              <stop offset="100%" stop-color="rgba(255,255,255,0.15)" />
            </linearGradient>

            <radialGradient id="apexGlow" cx="50%" cy="30%" r="55%">
              <stop offset="0%" stop-color="rgba(250, 204, 21, 0.28)" />
              <stop offset="60%" stop-color="rgba(250, 204, 21, 0.06)" />
              <stop offset="100%" stop-color="rgba(250, 204, 21, 0)" />
            </radialGradient>

            <linearGradient id="ridgeGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="rgba(255,255,255,0.16)" />
              <stop offset="100%" stop-color="rgba(255,255,255,0)" />
            </linearGradient>
          </defs>

          <rect width="1200" height="500" fill="url(#skyGrad)" />

          <g fill="rgba(255,255,255,0.5)">
            <circle cx="120" cy="70" r="1.2" />
            <circle cx="230" cy="40" r="1" />
            <circle cx="340" cy="90" r="1.4" />
            <circle cx="820" cy="55" r="1.1" />
            <circle cx="960" cy="80" r="1.3" />
            <circle cx="1080" cy="45" r="1" />
            <circle cx="600" cy="30" r="1.2" />
          </g>

          <ellipse cx="600" cy="120" rx="260" ry="140" fill="url(#apexGlow)" />

          <g fill="url(#peakBackGrad)">
            <polygon points="60,500 180,300 300,500" />
            <polygon points="240,500 380,260 520,500" />
            <polygon points="700,500 850,270 1000,500" />
            <polygon points="880,500 1020,300 1160,500" />
          </g>

          <g>
            <polygon points="140,500 340,270 540,500" fill="url(#peakMidGrad)" />
            <polygon points="340,270 300,330 340,350 380,320" fill="url(#snowGrad)" />
            <polygon points="660,500 860,300 1060,500" fill="url(#peakMidGrad)" />
            <polygon points="860,300 820,360 860,380 900,350" fill="url(#snowGrad)" />
            <polygon points="440,500 600,320 760,500" fill="url(#peakMidGrad)" opacity="0.75" />
          </g>

          <g>
            <polygon points="380,500 600,60 820,500" fill="url(#peakFrontGrad)" />
            <polygon points="600,60 545,155 600,185 655,150" fill="url(#snowGrad)" />
            <polygon points="600,60 600,500 820,500" fill="rgba(0,0,0,0.18)" />
            <line x1="600" y1="60" x2="600" y2="500" stroke="rgba(255,255,255,0.05)" stroke-width="1" />
            <polygon points="-40,500 140,360 320,500" fill="url(#peakFrontGrad)" />
            <polygon points="880,500 1060,360 1240,500" fill="url(#peakFrontGrad)" />
          </g>

          <path d="M0,470 L200,400 L340,340 L600,180 L860,340 L1000,400 L1200,470"
                stroke="url(#ridgeGrad)" stroke-width="1.2" fill="none" opacity="0.5" />
        </svg>

        <!-- PODIUM -->
        <div class="podium">
          <div
              v-for="slot in podiumSlots"
              :key="slot"
              class="podium__slot"
              :class="`podium__slot--rank${rankOf(slot)}`"
          >
            <div
                v-if="slot === 0"
                class="podium__crown"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                <path d="M3 8l4 3 5-7 5 7 4-3v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8z" fill="currentColor" />
                <circle cx="3" cy="8" r="1.6" fill="currentColor" />
                <circle cx="12" cy="4" r="1.8" fill="currentColor" />
                <circle cx="21" cy="8" r="1.6" fill="currentColor" />
              </svg>
            </div>

            <!-- Медаль -->
            <div class="podium__badge" :style="{ '--accent': accent(topThree[slot]) }">
              <svg v-if="slot === 0" class="podium__medal" width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M8 2l4 8 4-8" stroke="#facc15" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="12" cy="15" r="6" fill="#facc15" stroke="#a16207" stroke-width="1.4" />
                <path d="M12 12.5l1 2 2.2.3-1.6 1.5.4 2.2-2-1.1-2 1.1.4-2.2-1.6-1.5 2.2-.3z" fill="#7c2d12" />
              </svg>
              <svg v-else-if="slot === 1" class="podium__medal" width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M8 2l4 8 4-8" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="12" cy="15" r="6" fill="#cbd5e1" stroke="#64748b" stroke-width="1.4" />
                <text x="12" y="18.6" text-anchor="middle" font-size="7" font-weight="900" fill="#334155">2</text>
              </svg>
              <svg v-else class="podium__medal" width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M8 2l4 8 4-8" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="12" cy="15" r="6" fill="#f59e0b" stroke="#92400e" stroke-width="1.4" />
                <text x="12" y="18.6" text-anchor="middle" font-size="7" font-weight="900" fill="#78350f">3</text>
              </svg>
              <span class="podium__rank">#{{ rankOf(slot) }}</span>
            </div>

            <!-- 🆕 Очки рейтинга -->
            <div class="podium__score" :style="{ '--accent': accent(topThree[slot]) }">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
              </svg>
              <span>{{ scoreOf(topThree[slot]) }}</span>
            </div>

            <RouterLink
                :to="`/players/${topThree[slot].id}`"
                class="podium__card"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <PlayerProfileHeader
                  compact
                  :user="topThree[slot]"
                  size="md"
                  :cover-url="topThree[slot].cover_url"
              />
            </RouterLink>
          </div>
        </div>
      </section>

      <!-- ============================================
           MOBILE PODIUM (< 640px) — порядок 1, 2, 3
           ============================================ -->
      <section v-if="topThree.length" class="mobile-podium">
        <div
            v-for="slot in mobileSlots"
            :key="slot"
            class="mobile-podium__row"
            :class="`mobile-podium__row--rank${rankOf(slot)}`"
        >
          <div class="mobile-podium__rank-wrap" :style="{ '--accent': accent(topThree[slot]) }">
            <svg v-if="slot === 0" class="mobile-podium__medal" width="28" height="28" viewBox="0 0 24 24" fill="none">
              <path d="M8 2l4 8 4-8" stroke="#facc15" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              <circle cx="12" cy="15" r="6" fill="#facc15" stroke="#a16207" stroke-width="1.4" />
              <path d="M12 12.5l1 2 2.2.3-1.6 1.5.4 2.2-2-1.1-2 1.1.4-2.2-1.6-1.5 2.2-.3z" fill="#7c2d12" />
            </svg>
            <svg v-else-if="slot === 1" class="mobile-podium__medal" width="28" height="28" viewBox="0 0 24 24" fill="none">
              <path d="M8 2l4 8 4-8" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              <circle cx="12" cy="15" r="6" fill="#cbd5e1" stroke="#64748b" stroke-width="1.4" />
              <text x="12" y="18.6" text-anchor="middle" font-size="7" font-weight="900" fill="#334155">2</text>
            </svg>
            <svg v-else class="mobile-podium__medal" width="28" height="28" viewBox="0 0 24 24" fill="none">
              <path d="M8 2l4 8 4-8" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              <circle cx="12" cy="15" r="6" fill="#f59e0b" stroke="#92400e" stroke-width="1.4" />
              <text x="12" y="18.6" text-anchor="middle" font-size="7" font-weight="900" fill="#78350f">3</text>
            </svg>
          </div>

          <RouterLink
              :to="`/players/${topThree[slot].id}`"
              class="mobile-podium__card"
              :style="{ '--accent': accent(topThree[slot]) }"
          >
            <PlayerProfileHeader
                compact
                :user="topThree[slot]"
                :cover-url="topThree[slot].cover_url"
            />
          </RouterLink>

          <!-- 🆕 Очки -->
          <span class="mobile-podium__score" :style="{ '--accent': accent(topThree[slot]) }">
            {{ scoreOf(topThree[slot]) }}
          </span>
        </div>
      </section>

      <!-- REST -->
      <section v-if="rest.length" class="rest">
        <header class="rest__head">
          <h2 class="rest__title">Остальные восходители</h2>
          <span class="rest__count">{{ rest.length }}</span>
        </header>

        <div class="rest__grid">
          <RouterLink
              v-for="(p, i) in rest"
              :key="p.id"
              :to="`/players/${p.id}`"
              class="rest__row"
          >
            <span class="rest__rank">#{{ i + 4 }}</span>

            <!-- 🆕 Очки -->
            <span class="rest__score">
              {{ scoreOf(p) }}
            </span>

            <PlayerProfileHeader
                compact
                :user="p"
                :cover-url="p.cover_url"
                class="rest__header"
            />
          </RouterLink>
        </div>
      </section>

      <div v-if="!players.length" class="state state--empty">
        <span>Пока никто не покорил вершину</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* ============================================
   PAGE
   ============================================ */
.rating-page {
  width: min(1100px, calc(100% - 40px));
  margin: 32px auto 60px;
}

/* HEAD */
.rating-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}

.rating-head__title {
  margin: 0 0 6px;
  font-size: 30px;
  font-weight: 900;
  letter-spacing: -0.5px;
  color: var(--text);
}

.apex {
  background: linear-gradient(135deg, #facc15, #f97316);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  filter: drop-shadow(0 0 20px rgba(250, 204, 21, 0.35));
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

/* TABS */
.mode-tabs {
  display: inline-flex;
  gap: 6px;
  padding: 4px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.mode-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 9px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s ease;
}

.mode-tab:hover { color: var(--text); }

.mode-tab--active {
  color: #fff;
  background: color-mix(in srgb, var(--tab-color) 20%, transparent);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--tab-color) 55%, transparent);
}

.mode-tab__icon { flex-shrink: 0; }

/* SUBTABS */
.sub-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 24px;
  padding: 0 4px;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-3px); }
  to { opacity: 1; transform: translateY(0); }
}

.sub-tab {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 13px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 999px;
  font: inherit;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s ease;
}

.sub-tab:hover {
  color: var(--text);
  border-color: var(--border-hover, var(--border));
}

.sub-tab__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--tab-color);
  opacity: 0.4;
  transition: opacity 0.18s ease, box-shadow 0.18s ease;
}

.sub-tab--active {
  color: #fff;
  border-color: color-mix(in srgb, var(--tab-color) 55%, transparent);
  background: color-mix(in srgb, var(--tab-color) 12%, transparent);
}

.sub-tab--active .sub-tab__dot {
  opacity: 1;
  box-shadow: 0 0 8px var(--tab-color);
}

/* STATES */
.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 20px;
  text-align: center;
  color: var(--text-dim);
  font-size: 14px;
}
.state--empty { opacity: 0.85; }

.state__title {
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
}

.state__sub {
  font-size: 12.5px;
  color: var(--text-muted);
  max-width: 280px;
  text-align: center;
  line-height: 1.5;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 2.5px solid color-mix(in srgb, var(--accent) 25%, transparent);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ============================================
   MOUNTAIN
   ============================================ */
.mountain {
  position: relative;
  width: 100%;
  aspect-ratio: 1200 / 520;
  min-height: 520px;
  margin-bottom: 40px;
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.mountain__bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  display: block;
}

/* ============================================
   PODIUM
   ============================================ */
.podium {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.podium__slot {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-width: 0;
  transform: translate(-50%, -50%);
  pointer-events: auto;
}

.podium__slot--rank1 {
  left: 50%;
  top: 16%;
  z-index: 3;
  width: clamp(220px, 30%, 360px);
}

.podium__slot--rank2 {
  left: 27%;
  top: 58%;
  z-index: 2;
  width: clamp(180px, 24%, 300px);
}

.podium__slot--rank3 {
  left: 73%;
  top: 80%;
  z-index: 1;
  width: clamp(180px, 24%, 300px);
}

.podium__crown {
  position: absolute;
  top: -34px;
  color: #facc15;
  filter: drop-shadow(0 0 12px rgba(250, 204, 21, 0.7));
  animation: crownFloat 2.4s ease-in-out infinite;
}
@keyframes crownFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-5px); }
}

.podium__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: color-mix(in srgb, var(--accent) 18%, rgba(10, 10, 15, 0.85));
  border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent);
  border-radius: 999px;
  box-shadow: 0 6px 20px color-mix(in srgb, var(--accent) 35%, transparent);
  backdrop-filter: blur(6px);
}

.podium__medal { display: block; }

.podium__rank {
  font-size: 11px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 0.5px;
}

/* 🆕 Очки рейтинга — десктоп */
.podium__score {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  background: rgba(10, 10, 15, 0.9);
  border: 1px solid color-mix(in srgb, var(--accent) 45%, transparent);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  color: color-mix(in srgb, var(--accent) 80%, #fff);
  letter-spacing: 0.4px;
  backdrop-filter: blur(6px);
  box-shadow: 0 4px 14px color-mix(in srgb, var(--accent) 25%, transparent);
}

.podium__score svg {
  color: var(--accent);
  filter: drop-shadow(0 0 4px color-mix(in srgb, var(--accent) 60%, transparent));
}

.podium__card {
  display: block;
  width: 100%;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  filter: drop-shadow(0 20px 30px rgba(0, 0, 0, 0.55))
  drop-shadow(0 0 18px color-mix(in srgb, var(--accent) 40%, transparent));
  transition: transform 0.25s ease;
}

.podium__card:hover { transform: translateY(-3px) scale(1.01); }

/* ============================================
   MOBILE PODIUM
   ============================================ */
.mobile-podium {
  display: none;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 32px;
}

.mobile-podium__row {
  display: grid;
  grid-template-columns: 40px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  position: relative;
  overflow: visible;
}

.mobile-podium__row--rank1 {
  border-color: color-mix(in srgb, #facc15 45%, transparent);
  background: linear-gradient(135deg, rgba(250, 204, 21, 0.06), transparent 60%), var(--bg-card);
  box-shadow: 0 0 30px -12px rgba(250, 204, 21, 0.4);
}

.mobile-podium__row--rank2 {
  border-color: color-mix(in srgb, #cbd5e1 35%, transparent);
}

.mobile-podium__row--rank3 {
  border-color: color-mix(in srgb, #f59e0b 35%, transparent);
}

.mobile-podium__rank-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.mobile-podium__medal { display: block; }

.mobile-podium__card {
  display: block;
  min-width: 0;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.35));
}

/* 🆕 Очки — мобилка */
.mobile-podium__score {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 38px;
  padding: 4px 8px;
  background: color-mix(in srgb, var(--accent) 18%, rgba(10, 10, 15, 0.9));
  border: 1px solid color-mix(in srgb, var(--accent) 45%, transparent);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  color: color-mix(in srgb, var(--accent) 80%, #fff);
  letter-spacing: 0.3px;
  flex-shrink: 0;
}

/* ============================================
   REST
   ============================================ */
.rest { margin-top: 12px; }

.rest__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: 0 4px 12px;
  margin-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.rest__title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--text);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.rest__count {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 700;
}

.rest__grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rest__row {
  display: grid;
  grid-template-columns: 48px 56px 1fr;
  align-items: center;
  gap: 12px;
  padding: 6px 10px 6px 4px;
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: background 0.18s ease;
}

.rest__row:hover { background: color-mix(in srgb, var(--accent) 6%, transparent); }

.rest__rank {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  min-width: 32px;
  padding: 0 8px;
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.3px;
}

/* 🆕 Очки — rest */
.rest__score {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  min-width: 48px;
  padding: 0 8px;
  color: color-mix(in srgb, var(--accent) 70%, #fff);
  background: color-mix(in srgb, var(--accent) 10%, var(--bg-card));
  border: 1px solid color-mix(in srgb, var(--accent) 30%, var(--border));
  border-radius: 9px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.3px;
}

.rest__header { min-width: 0; }

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 900px) {
  .rating-page { width: calc(100% - 32px); margin: 24px auto 48px; }
  .rating-head__title { font-size: 24px; }

  .mountain { min-height: 440px; aspect-ratio: 1200 / 620; }

  .podium__slot--rank1 {
    left: 50%;
    top: 18%;
    width: clamp(180px, 28%, 260px);
  }

  .podium__slot--rank2 {
    left: 26%;
    top: 60%;
    width: clamp(150px, 22%, 220px);
  }

  .podium__slot--rank3 {
    left: 74%;
    top: 82%;
    width: clamp(150px, 22%, 220px);
  }
}

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

  .rating-head__title { font-size: 22px; }
  .rating-head__sub { font-size: 12.5px; }

  .mode-tabs {
    width: 100%;
    justify-content: stretch;
  }

  .mode-tab {
    flex: 1;
    justify-content: center;
    padding: 9px 10px;
    font-size: 12.5px;
  }

  .sub-tabs {
    gap: 6px;
    padding: 0;
    margin-bottom: 18px;
    overflow-x: auto;
    scrollbar-width: none;
    flex-wrap: nowrap;
  }
  .sub-tabs::-webkit-scrollbar { display: none; }

  .sub-tab {
    padding: 6px 11px;
    font-size: 12px;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .mountain { display: none; }
  .mobile-podium { display: flex; }

  .rest__head { padding-bottom: 10px; margin-bottom: 10px; }
  .rest__title { font-size: 13px; letter-spacing: 0.6px; }

  .rest__row {
    grid-template-columns: 36px 44px 1fr;
    gap: 8px;
    padding: 4px 6px 4px 2px;
  }

  .rest__rank {
    height: 28px;
    min-width: 28px;
    font-size: 11px;
    border-radius: 8px;
  }

  .rest__score {
    height: 28px;
    min-width: 40px;
    font-size: 11px;
  }
}
</style>