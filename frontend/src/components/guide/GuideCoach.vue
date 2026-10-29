<script setup>
/**
 * Путеводитель по сайту. Ведёт гид — самый редкий питомец APEX прямо
 * сейчас: приветствие со стартовыми монетами, панель «Первые шаги» с
 * чеклистом и наградами и подсветка нужного места на странице с
 * подсказкой. Порядок шагов и награды решает сервер.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { useGuideStore } from '@/stores/core/guide.js'
import { GUIDE_STEPS, onStepRoute } from '@/data/guide/steps.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const LATER_KEY = 'guide_intro_later'

const auth = useAuthStore()
const guide = useGuideStore()
const route = useRoute()
const router = useRouter()

const later = ref(readLater())
const rect = ref(null)
const tipEl = ref(null)
const tipHeight = ref(220)
const narrow = ref(typeof window !== 'undefined' && window.innerWidth < 640)
let findTimer = null
let pollTimer = null
let raf = 0

function readLater() {
  try {
    return sessionStorage.getItem(LATER_KEY) === '1'
  } catch {
    return false
  }
}

const isBridge = computed(() => auth.user?.profile_mode === 'bridge')
const step = computed(() => (guide.current ? GUIDE_STEPS[guide.current] : null))
const stepInfo = computed(() => guide.state?.steps?.find((s) => s.id === guide.current) ?? null)

const title = computed(() => (guide.current === 'test' && isBridge.value ? step.value?.bridgeTitle : step.value?.title))
const text = computed(() => (guide.current === 'test' && isBridge.value ? step.value?.bridgeText : step.value?.text))

const mascotName = computed(() => guide.mascot?.name ?? 'Крипер')

const showIntro = computed(() => auth.isAuthenticated && guide.state && !guide.started && !guide.hidden && !later.value)
// В сообщениях кнопка закрывала бы поле ввода; во время подсказки — лишняя
const showPill = computed(() => auth.isAuthenticated && guide.state && !guide.hidden
  && (!guide.finished || guide.panelOpen) && !spotActive.value && !route.path.startsWith('/messages'))

const spotActive = computed(() =>
  guide.started && !guide.finished && !guide.hidden && step.value
  && onStepRoute(step.value, route) && !guide.dismissed.has(guide.current),
)

// На телефоне подсказка — снизу, а если подсвечено что-то внизу экрана — сверху
const sheetTop = computed(() => narrow.value && rect.value && rect.value.top > window.innerHeight / 2)

const progress = computed(() => (guide.stepCount ? Math.round((guide.doneCount / guide.stepCount) * 100) : 0))

/* ---------- Загрузка и обновление ---------- */

watch(() => auth.isAuthenticated, (on) => {
  if (on) guide.load()
  else guide.reset()
}, { immediate: true })

// Шаги, которые видно по базе (питомец, экспедиция, бой, шахта), засчитываются
// сами: обновляем состояние при переходах и изредка, пока игрок на странице шага
watch(() => route.fullPath, () => {
  if (guide.started && !guide.finished) guide.load()
})

// Последний шаг позади — открываем панель с поздравлением
watch(() => guide.finished, (now, before) => {
  if (now && before === false) guide.panelOpen = true
})

// Открыли панель — показываем свежие шаги (сундук могли открыть только что)
watch(() => guide.panelOpen, (open) => {
  if (open && guide.started && !guide.finished) guide.load()
})

// Пункт «Путеводитель» в мобильном меню ведёт на ?guide=1
watch(() => route.query.guide, async (flag) => {
  if (flag !== '1' || !auth.isAuthenticated) return
  if (!guide.state) await guide.load()
  if (guide.hidden) await guide.setHidden(false)
  guide.panelOpen = true
  later.value = false
  router.replace({ query: { ...route.query, guide: undefined } })
}, { immediate: true })

watch([spotActive, () => stepInfo.value?.auto], ([active, auto]) => {
  clearInterval(pollTimer)
  if (active && auto) pollTimer = setInterval(() => guide.load(), 15000)
})

/* ---------- Подсветка ---------- */

function findTarget() {
  const targets = isBridge.value && step.value?.bridgeTarget ? step.value.bridgeTarget : step.value?.target
  for (const selector of targets ?? []) {
    const el = document.querySelector(selector)
    if (el && el.getBoundingClientRect().width > 0) return el
  }

  return null
}

function measure() {
  const el = findTarget()
  if (!el) {
    rect.value = null
    return
  }

  const r = el.getBoundingClientRect()
  rect.value = { top: r.top - 8, left: r.left - 8, width: r.width + 16, height: r.height + 16 }
}

function track() {
  measure()
  if (tipEl.value) tipHeight.value = tipEl.value.offsetHeight
  raf = requestAnimationFrame(track)
}

// Ищем цель несколько секунд: страницы догружают данные
watch([spotActive, () => guide.current, () => route.fullPath], async ([active]) => {
  clearInterval(findTimer)
  cancelAnimationFrame(raf)
  rect.value = null
  if (!active) return

  await nextTick()
  let tries = 0
  findTimer = setInterval(() => {
    const el = findTarget()
    tries++
    if (el || tries > 20) {
      clearInterval(findTimer)
      if (el) {
        el.scrollIntoView({ block: 'center', behavior: 'smooth' })
        setTimeout(() => { raf = requestAnimationFrame(track) }, 350)
      }
    }
  }, 200)
}, { immediate: true })

function onResize() {
  narrow.value = window.innerWidth < 640
}

if (typeof window !== 'undefined') window.addEventListener('resize', onResize)

onBeforeUnmount(() => {
  clearInterval(findTimer)
  clearInterval(pollTimer)
  cancelAnimationFrame(raf)
  window.removeEventListener('resize', onResize)
})

/** Подсказка — под целью, если там есть место, иначе над ней. */
const tipStyle = computed(() => {
  if (narrow.value || !rect.value) return null

  const width = 340
  const left = Math.min(Math.max(12, rect.value.left), window.innerWidth - width - 12)
  const below = rect.value.top + rect.value.height + 14
  const fitsBelow = below + tipHeight.value + 12 < window.innerHeight
  const above = rect.value.top - tipHeight.value - 14

  return fitsBelow
    ? { top: `${below}px`, left: `${left}px`, width: `${width}px` }
    : { top: `${Math.max(12, above)}px`, left: `${left}px`, width: `${width}px` }
})

const holeStyle = computed(() => rect.value && {
  top: `${rect.value.top}px`,
  left: `${rect.value.left}px`,
  width: `${rect.value.width}px`,
  height: `${rect.value.height}px`,
})

/* ---------- Действия ---------- */

async function begin() {
  await guide.start()
  guide.panelOpen = false
  go()
}

function postpone() {
  later.value = true
  try { sessionStorage.setItem(LATER_KEY, '1') } catch { /* приватный режим */ }
}

/** Перейти на страницу текущего шага и показать подсказку. */
function go() {
  if (!step.value?.route) return
  guide.show(guide.current)
  guide.panelOpen = false
  router.push(step.value.route)
}

async function next() {
  if (stepInfo.value?.auto) {
    // Такие шаги засчитываются сами, когда дело сделано
    guide.dismiss(guide.current)
    return
  }

  await guide.complete(guide.current)
  if (!guide.error && step.value?.route) go()
}

async function skipStep() {
  await guide.skip(guide.current)
  if (!guide.error && step.value?.route) go()
}

function closeForGood() {
  guide.panelOpen = false
  guide.setHidden(true)
}

function fmt(n) {
  return Number(n).toLocaleString('ru-RU')
}
</script>

<template>
  <Teleport to="body">
    <!-- Знакомство: гид и стартовые монеты -->
    <Transition name="gc-fade">
      <div v-if="showIntro" class="gc-intro" role="dialog" aria-modal="true" aria-labelledby="gc-intro-title">
        <div class="gc-intro__card">
          <div class="gc-intro__mascot">
            <PetFigure v-if="guide.mascot" :species="guide.mascot.species" :traits="guide.mascot.traits" :size="96" />
            <PixelSprite v-else sprite="creeper" :size="80" anim="bounce" />
          </div>
          <p class="gc-intro__kicker">Путеводитель по APEX</p>
          <h2 id="gc-intro-title">Привет! Я {{ mascotName }}</h2>
          <p class="gc-intro__who">
            Самый редкий питомец сайта<template v-if="guide.mascot?.owner"> — живу у @{{ guide.mascot.owner }}</template>.
            Покажу, где тут что: рейтинг, тир-тест, питомцы, арена, шахта.
          </p>
          <div class="gc-intro__gift">
            <PixelSprite sprite="art_coin" :size="26" anim="spin" />
            <span><b>{{ fmt(guide.state.total) }} ApexCoin</b> за первые шаги — хватит на сундук питомцев</span>
          </div>
          <div class="gc-intro__actions">
            <button type="button" class="gc-btn gc-btn--primary" :disabled="guide.busy" @click="begin">
              Поехали · +{{ fmt(guide.state.steps[0].reward) }}
            </button>
            <button type="button" class="gc-btn" @click="postpone">Позже</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Подсветка нужного места на странице -->
    <template v-if="spotActive">
      <div v-if="holeStyle" class="gc-hole" :style="holeStyle" aria-hidden="true"></div>
      <div v-else class="gc-dim" aria-hidden="true"></div>

      <div ref="tipEl" class="gc-tip" :class="{ 'gc-tip--sheet': narrow || !rect, 'gc-tip--top': sheetTop }" :style="tipStyle" role="dialog" aria-live="polite">
        <div class="gc-tip__head">
          <span class="gc-tip__mascot">
            <PetFigure v-if="guide.mascot" :species="guide.mascot.species" :traits="guide.mascot.traits" :size="40" :backdrop="false" />
            <PixelSprite v-else sprite="creeper" :size="34" />
          </span>
          <div>
            <small>{{ mascotName }} · шаг {{ guide.doneCount + 1 }} из {{ guide.stepCount }}</small>
            <b>{{ title }}</b>
          </div>
          <button type="button" class="gc-tip__close" aria-label="Закрыть подсказку" @click="guide.dismiss(guide.current)">
            <AppIcon icon="close" :size="14" />
          </button>
        </div>
        <p>{{ text }}</p>
        <p v-if="stepInfo?.auto" class="gc-tip__todo"><AppIcon icon="target" :size="13" /> {{ step.todo }} — шаг засчитается сам</p>
        <p v-if="guide.error" class="gc-tip__error">{{ guide.error }}</p>
        <div class="gc-tip__actions">
          <button type="button" class="gc-btn gc-btn--primary" :disabled="guide.busy" @click="next">
            {{ stepInfo?.auto ? 'Хорошо' : stepInfo?.reward ? `Понятно · +${fmt(stepInfo.reward)}` : 'Понятно, дальше' }}
          </button>
          <button type="button" class="gc-btn gc-btn--ghost" :disabled="guide.busy" @click="skipStep">Пропустить</button>
        </div>
      </div>
    </template>

    <!-- Панель «Первые шаги» -->
    <div v-if="showPill" class="gc-dock" :class="{ 'is-open': guide.panelOpen }">
      <section v-if="guide.panelOpen" class="gc-panel" aria-label="Путеводитель">
        <header class="gc-panel__head">
          <span class="gc-panel__mascot">
            <PetFigure v-if="guide.mascot" :species="guide.mascot.species" :traits="guide.mascot.traits" :size="44" :backdrop="false" />
            <PixelSprite v-else sprite="creeper" :size="36" />
          </span>
          <div>
            <b>{{ guide.finished ? 'Путеводитель пройден!' : 'Первые шаги' }}</b>
            <small v-if="guide.started">Получено {{ fmt(guide.state.earned) }} из {{ fmt(guide.state.total) }} ApexCoin</small>
            <small v-else>{{ fmt(guide.state.total) }} ApexCoin за первые шаги</small>
          </div>
          <button type="button" class="gc-tip__close" aria-label="Свернуть" @click="guide.panelOpen = false">
            <AppIcon icon="close" :size="14" />
          </button>
        </header>

        <i class="gc-panel__bar"><em :style="{ width: `${progress}%` }"></em></i>

        <ol class="gc-steps">
          <li
              v-for="s in guide.state.steps"
              :key="s.id"
              :class="[`is-${s.status}`, { 'is-current': s.id === guide.current }]"
          >
            <span class="gc-steps__mark">
              <AppIcon v-if="s.status === 'done'" icon="check" :size="12" />
              <template v-else-if="s.status === 'skipped'">–</template>
            </span>
            <span class="gc-steps__name">{{ s.id === 'test' && isBridge ? 'Выложить бридж' : GUIDE_STEPS[s.id]?.short }}</span>
            <b v-if="s.reward" class="gc-steps__reward">+{{ fmt(s.reward) }}</b>
            <button
                v-if="s.id === guide.current && GUIDE_STEPS[s.id]?.route"
                type="button"
                class="gc-steps__go"
                @click="go"
            >Показать</button>
          </li>
        </ol>

        <p v-if="guide.state.trade_lock_until" class="gc-panel__lock">
          <AppIcon icon="info" :size="12" />
          Подарки монет и аукцион откроются {{ new Date(guide.state.trade_lock_until).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' }) }}
        </p>

        <div class="gc-panel__foot">
          <button v-if="!guide.started" type="button" class="gc-btn gc-btn--primary" :disabled="guide.busy" @click="begin">Начать</button>
          <button type="button" class="gc-btn gc-btn--ghost" @click="closeForGood">
            {{ guide.finished ? 'Закрыть' : 'Скрыть путеводитель' }}
          </button>
        </div>
      </section>

      <button
          v-else
          type="button"
          class="gc-pill"
          :class="{ 'is-pulse': !guide.started || spotActive === false }"
          :aria-label="`Путеводитель: ${guide.doneCount} из ${guide.stepCount}`"
          @click="guide.panelOpen = true"
      >
        <span class="gc-pill__mascot">
          <PetFigure v-if="guide.mascot" :species="guide.mascot.species" :traits="guide.mascot.traits" :size="30" :backdrop="false" />
          <PixelSprite v-else sprite="creeper" :size="26" />
        </span>
        <span class="gc-pill__text">
          <b>{{ guide.started ? 'Первые шаги' : 'Путеводитель' }}</b>
          <small>{{ guide.started ? `${guide.doneCount} из ${guide.stepCount}` : `+${fmt(guide.state.total)}` }}</small>
        </span>
      </button>
    </div>
  </Teleport>
</template>

<style scoped>
/* ---------- Знакомство ---------- */

.gc-intro {
  position: fixed;
  inset: 0;
  z-index: 1300;

  display: grid;
  place-items: center;

  padding: 16px;

  background: rgba(5, 4, 10, 0.72);
  backdrop-filter: blur(4px);
}

.gc-intro__card {
  position: relative;

  width: min(440px, 100%);
  padding: 28px 24px 22px;

  text-align: center;

  background:
      radial-gradient(ellipse at 50% 0%, rgba(167, 139, 250, 0.22), transparent 60%),
      var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
}

.gc-intro__mascot { display: grid; place-items: center; height: 120px; margin-top: -8px; animation: gc-float 3s ease-in-out infinite; }
.gc-intro__kicker { margin: 4px 0 0; color: #a78bfa; font-size: 11px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; }
.gc-intro h2 { margin: 6px 0 8px; color: #fff; font-family: var(--font-display); font-size: 26px; }
.gc-intro__who { margin: 0; color: #a8a3b8; font-size: 14px; line-height: 1.5; }

.gc-intro__gift {
  display: flex;
  align-items: center;
  gap: 10px;

  margin: 18px 0;
  padding: 10px 12px;

  color: #e6e3ee;
  background: rgba(250, 204, 21, 0.08);
  border: 1px solid rgba(250, 204, 21, 0.3);
  border-radius: 12px;

  font-size: 13px;
  text-align: left;
}

.gc-intro__gift b { color: #fde68a; }
.gc-intro__actions { display: flex; flex-direction: column; gap: 8px; }

@keyframes gc-float {
  50% { translate: 0 -6px; }
}

/* ---------- Подсветка ---------- */

.gc-hole {
  position: fixed;
  z-index: 1200;

  border: 2px solid #a78bfa;
  border-radius: 12px;
  box-shadow: 0 0 0 9999px rgba(5, 4, 10, 0.62), 0 0 24px rgba(167, 139, 250, 0.7);

  pointer-events: none;
  transition: top 0.2s ease, left 0.2s ease, width 0.2s ease, height 0.2s ease;
  animation: gc-ring 1.6s ease-in-out infinite;
}

@keyframes gc-ring {
  50% { box-shadow: 0 0 0 9999px rgba(5, 4, 10, 0.62), 0 0 36px rgba(167, 139, 250, 1); }
}

.gc-dim {
  position: fixed;
  inset: 0;
  z-index: 1200;

  background: rgba(5, 4, 10, 0.5);

  pointer-events: none;
}

.gc-tip {
  position: fixed;
  z-index: 1201;

  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 14px;

  background: #16131f;
  border: 1px solid rgba(167, 139, 250, 0.45);
  border-radius: 16px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55);

  animation: gc-pop 0.25s ease-out;
}

.gc-tip--sheet.gc-tip--top {
  top: 72px;
  bottom: auto;
}

.gc-tip--sheet {
  left: 50%;
  bottom: 16px;

  width: min(420px, calc(100vw - 24px));

  translate: -50% 0;
}

@keyframes gc-pop {
  from { opacity: 0; transform: translateY(6px) scale(0.98); }
}

.gc-tip__head { display: flex; align-items: center; gap: 10px; }
.gc-tip__head > div { flex: 1; min-width: 0; }
.gc-tip__head small { display: block; color: #a78bfa; font-size: 11px; font-weight: 800; }
.gc-tip__head b { color: #fff; font-size: 15px; }

.gc-tip__mascot, .gc-panel__mascot, .gc-pill__mascot {
  flex-shrink: 0;

  display: grid;
  place-items: center;

  background: radial-gradient(circle, rgba(167, 139, 250, 0.25), transparent 70%);
  border-radius: 50%;
}

.gc-tip__mascot { width: 48px; height: 48px; }

.gc-tip__close {
  align-self: flex-start;

  display: grid;
  place-items: center;

  width: 28px;
  height: 28px;

  color: #8a8598;
  background: none;
  border: 0;
  border-radius: 8px;

  cursor: pointer;
}

.gc-tip__close:hover { color: #fff; background: rgba(255, 255, 255, 0.06); }

.gc-tip p { margin: 0; color: #d6d2e2; font-size: 13.5px; line-height: 1.5; }
.gc-tip .gc-tip__todo { display: flex; align-items: center; gap: 6px; color: #fde68a; font-size: 12.5px; font-weight: 700; }
.gc-tip .gc-tip__error { color: #fca5a5; font-size: 12.5px; }
.gc-tip__actions { display: flex; flex-wrap: wrap; gap: 8px; }

/* ---------- Кнопки ---------- */

.gc-btn {
  min-height: 40px;
  padding: 0 16px;

  color: #e6e3ee;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 11px;

  font: inherit;
  font-size: 13.5px;
  font-weight: 800;

  cursor: pointer;
}

.gc-btn--primary { color: #fff; background: linear-gradient(135deg, #8b5cf6, #6d28d9); border-color: transparent; }
.gc-btn--primary:hover:not(:disabled) { filter: brightness(1.1); }
.gc-btn--ghost { background: none; border-color: transparent; color: #a8a3b8; }
.gc-btn:disabled { opacity: 0.6; cursor: default; }

/* ---------- Панель и кнопка ---------- */

.gc-dock {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 1100;
}

.gc-pill {
  display: flex;
  align-items: center;
  gap: 8px;

  padding: 6px 14px 6px 6px;

  color: #fff;
  background: #16131f;
  border: 1px solid rgba(167, 139, 250, 0.45);
  border-radius: 99px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);

  font: inherit;
  text-align: left;

  cursor: pointer;
}

.gc-pill.is-pulse { animation: gc-glow 2.2s ease-in-out infinite; }

@keyframes gc-glow {
  50% { box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45), 0 0 18px rgba(167, 139, 250, 0.6); }
}

.gc-pill__mascot { width: 38px; height: 38px; }
.gc-pill__text { display: flex; flex-direction: column; line-height: 1.15; }
.gc-pill__text b { font-size: 13px; }
.gc-pill__text small { color: #a78bfa; font-size: 11.5px; font-weight: 800; }

.gc-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;

  width: 320px;
  max-height: min(560px, calc(100vh - 120px));
  padding: 14px;
  overflow-y: auto;

  background: #16131f;
  border: 1px solid rgba(167, 139, 250, 0.35);
  border-radius: 18px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);

  animation: gc-pop 0.2s ease-out;
}

.gc-panel__head { display: flex; align-items: center; gap: 10px; }
.gc-panel__head > div { flex: 1; min-width: 0; }
.gc-panel__head b { display: block; color: #fff; font-size: 15px; }
.gc-panel__head small { color: #a8a3b8; font-size: 12px; }
.gc-panel__mascot { width: 50px; height: 50px; }

.gc-panel__bar { display: block; height: 6px; overflow: hidden; background: rgba(255, 255, 255, 0.08); border-radius: 99px; }
.gc-panel__bar em { display: block; height: 100%; background: linear-gradient(90deg, #8b5cf6, #22d3ee); border-radius: inherit; transition: width 0.4s ease; }

.gc-steps { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 2px; }

.gc-steps li {
  display: grid;
  grid-template-columns: 20px minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 8px;

  min-height: 34px;
  padding: 4px 8px;

  color: #a8a3b8;
  border-radius: 9px;

  font-size: 13px;
}

.gc-steps li.is-current { color: #fff; background: rgba(139, 92, 246, 0.16); }
.gc-steps li.is-done { color: #6f6a80; }
.gc-steps li.is-done .gc-steps__name { text-decoration: line-through; }

.gc-steps__mark {
  display: grid;
  place-items: center;

  width: 18px;
  height: 18px;

  color: #fff;
  border: 1.5px solid rgba(255, 255, 255, 0.2);
  border-radius: 50%;

  font-size: 11px;
}

.is-done .gc-steps__mark { background: #22c55e; border-color: #22c55e; }
.is-current .gc-steps__mark { border-color: #a78bfa; box-shadow: 0 0 8px rgba(167, 139, 250, 0.7); }

.gc-steps__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gc-steps__reward { color: #fde68a; font-size: 12px; }
.is-done .gc-steps__reward { color: #6f6a80; }

.gc-steps__go {
  padding: 4px 10px;

  color: #fff;
  background: #7c3aed;
  border: 0;
  border-radius: 7px;

  font: inherit;
  font-size: 11.5px;
  font-weight: 800;

  cursor: pointer;
}

.gc-panel__lock { display: flex; align-items: center; gap: 6px; margin: 0; color: #8a8598; font-size: 11.5px; }
.gc-panel__foot { display: flex; flex-wrap: wrap; gap: 6px; }

.gc-fade-enter-active, .gc-fade-leave-active { transition: opacity 0.2s ease; }
.gc-fade-enter-from, .gc-fade-leave-to { opacity: 0; }

/* ---------- Телефон ---------- */

@media (max-width: 640px) {
  .gc-dock { right: 10px; bottom: 78px; }
  /* Над нижней кнопкой сайта по центру */
  .gc-tip--sheet { bottom: 70px; }
  .gc-dock.is-open { left: 10px; }
  .gc-panel { width: auto; max-height: calc(100vh - 160px); }
  .gc-pill__text small { font-size: 11px; }
  .gc-intro__card { padding: 22px 16px 16px; }
  .gc-intro h2 { font-size: 22px; }
}

@media (prefers-reduced-motion: reduce) {
  .gc-hole, .gc-pill.is-pulse, .gc-intro__mascot { animation: none; }
}
</style>
