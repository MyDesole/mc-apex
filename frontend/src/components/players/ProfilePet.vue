<script setup>
/**
 * Питомец профиля (магазин, тип profile_pet): пиксельный зверёк живёт
 * в шапке профиля. Сам решает, что делать: гуляет, стоит, засыпает,
 * поворачивается к курсору.
 *
 * В шапке профиля (interactive) с ним можно возиться: клик открывает
 * карточку — имя, уровень, настроение и три действия раз в день:
 * погладить (сердечки), покормить (еда падает сверху, питомец бежит
 * есть) и поиграть (гоняется за мячиком). За это растёт уровень.
 * Голодный питомец сам просит еды.
 *
 * В превью магазина и кейсов — просто гуляет, без сервера.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { profilePet } from '@/data/players/flair.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { PET_RARITY, accessoryPlacement, chanceLabel, petAccessory, petBackdrop, petModelStyle } from '@/data/players/petTraits.js'
import { PIXEL_SPRITES } from '@/data/shop/pixelSprites.js'
import PetAccessory from '@/components/players/PetAccessory.vue'
import AppIcon from '@/components/core/AppIcon.vue'

const props = defineProps({
  pet: { type: String, default: null },
  size: { type: Number, default: 38 },
  // Хозяин питомца: нужен для карточки и действий
  owner: { type: Object, default: null },
  // Своя страница: можно назвать питомца
  editable: { type: Boolean, default: false },
  interactive: { type: Boolean, default: false },
  // Черты экземпляра (окрас, фон, аксессуар) — для превью в инвентаре и кейсах
  traits: { type: Object, default: null },
  // Несколько питомцев: какой экземпляр, его имя и «дорожка» (стартовая позиция)
  instanceId: { type: Number, default: null },
  name: { type: String, default: null },
  lane: { type: Number, default: 0 },
  // Выученный трюк: питомец иногда показывает его сам и при наведении
  trick: { type: String, default: null },
})

const trickPlaying = ref(null)

function performTrick() {
  if (!props.trick) return false
  trickPlaying.value = null
  requestAnimationFrame(() => { trickPlaying.value = props.trick })
  setTimeout(() => { trickPlaying.value = null }, 1700)
  return true
}

const auth = useAuthStore()
const data = computed(() => profilePet(props.pet))

const zoneEl = ref(null)
const petEl = ref(null)

const x = ref(12)          // позиция в процентах ширины
const y = ref(55)          // для летающих: высота полёта в процентах
const facingLeft = ref(false)
const state = ref('idle')  // idle | walk | run | sleep | eat
const walkMs = ref(0)
const speech = ref('')
const jumping = ref(false)
const hearts = ref([])
const food = ref(null)     // { id, x, landed }
const ball = ref(null)     // { id, x, y }
const levelUp = ref(false)
const busy = ref(false)    // идёт сценка — сам не гуляет

let timer = null
let speechTimer = null
let moodTimer = null
let heartId = 0

function rand(min, max) {
  return min + Math.random() * (max - min)
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

/* =========================================================
   ПОВЕДЕНИЕ
   ========================================================= */

/** Следующее действие: чаще гуляет, иногда стоит или спит. */
function nextAction() {
  // Пока открыто меню, питомец стоит на месте — иначе улетает от карточки
  if (busy.value || panelOpen.value) return schedule()

  const roll = Math.random()

  // Иногда сам показывает выученный трюк
  if (roll > 0.88 && performTrick()) {
    state.value = 'idle'
    schedule(rand(2200, 3200))
    return
  }

  if (roll < 0.6) {
    walkTo(rand(4, 86))
    timer = setTimeout(() => { state.value = 'idle'; schedule() }, walkMs.value)
    return
  }

  if (roll < 0.82 || data.value?.fly) {
    state.value = 'idle'
    schedule(rand(1500, 3500))
    return
  }

  state.value = 'sleep'
  timer = setTimeout(() => { state.value = 'idle'; schedule() }, rand(5000, 8000))
}

function schedule(delay = rand(900, 2200)) {
  clearTimeout(timer)
  timer = setTimeout(nextAction, delay)
}

function walkTo(target, speed = 70, mode = 'walk') {
  facingLeft.value = target < x.value
  walkMs.value = Math.abs(target - x.value) * speed + 300
  state.value = mode
  x.value = target
  if (data.value?.fly) y.value = rand(25, 60)
  return walkMs.value
}

function say(text, ms = 2400) {
  speech.value = text
  clearTimeout(speechTimer)
  speechTimer = setTimeout(() => { speech.value = '' }, ms)
}

function jump() {
  jumping.value = false
  requestAnimationFrame(() => { jumping.value = true })
}

function burstHearts(count = 5) {
  const burst = Array.from({ length: count }, () => ({
    id: ++heartId,
    dx: `${rand(-34, 34)}px`,
    delay: `${rand(0, 0.2)}s`,
  }))

  hearts.value.push(...burst)
  setTimeout(() => {
    hearts.value = hearts.value.filter((h) => !burst.includes(h))
  }, 1300)
}

/**
 * Остановиться там, где сейчас стоишь: посреди прогулки left ещё
 * «едет» к цели, поэтому берём настоящую позицию на экране.
 */
function freeze() {
  clearTimeout(timer)
  if (!['walk', 'run'].includes(state.value) || !petEl.value || !zoneEl.value) return

  const zone = zoneEl.value.getBoundingClientRect()
  const pet = petEl.value.getBoundingClientRect()
  walkMs.value = 0
  x.value = ((pet.left + pet.width / 2 - zone.left) / zone.width) * 100
  if (data.value?.fly) y.value = ((pet.top - zone.top) / zone.height) * 100
  state.value = 'idle'
}

/** Наведение: замирает под курсором, просыпается, подпрыгивает и говорит. */
function onHover() {
  if (!data.value || busy.value) return
  freeze()
  if (state.value === 'sleep') state.value = 'idle'

  // Гостю — трюк, если питомец его знает
  if (Math.random() < 0.4 && performTrick()) return

  jump()

  if (stats.value?.mood === 'hungry' && Math.random() < 0.6) {
    say('Есть хочу…')
    return
  }

  const phrases = data.value.phrases
  say(phrases[Math.floor(Math.random() * phrases.length)])
}

/** Курсор над шапкой: стоящий питомец смотрит на него. */
function onZoneMove(event) {
  if (state.value !== 'idle' || busy.value || !zoneEl.value) return
  const rect = zoneEl.value.getBoundingClientRect()
  const cursor = ((event.clientX - rect.left) / rect.width) * 100
  facingLeft.value = cursor < x.value
}

/* =========================================================
   КАРТОЧКА ПИТОМЦА И ДЕЙСТВИЯ
   ========================================================= */

const stats = ref(null)
const panelOpen = ref(false)
const panelPos = ref({ left: 0, top: 0 })
const panelError = ref('')
const renaming = ref(false)
const nameDraft = ref('')

const ACTIONS = [
  { id: 'pet', label: 'Погладить', icon: 'heart', xp: 1 },
  { id: 'feed', label: 'Покормить', icon: 'meat', xp: 2 },
  { id: 'play', label: 'Поиграть', icon: 'ball', xp: 2 },
]

const MOODS = {
  hungry: { label: 'Голодный', icon: 'meat', text: 'Давно не ел — покорми!' },
  happy: { label: 'Счастлив', icon: 'sparkle', text: 'С ним недавно играли' },
  calm: { label: 'Спокоен', icon: 'smile', text: 'Скучает по вниманию' },
}

const petName = computed(() => stats.value?.name || props.name || data.value?.name || 'Питомец')
const mood = computed(() => MOODS[stats.value?.mood] ?? MOODS.calm)

// Уникальный экземпляр: окрас, фон, аксессуар, номер
const traits = computed(() => stats.value?.traits ?? props.traits ?? null)

// Питомец растёт с уровнем: +3 % за уровень, максимум в 1,6 раза
const grownSize = computed(() => Math.round(props.size * (1 + Math.min(20, (stats.value?.level ?? 1) - 1) * 0.03)))
const skinStyle = computed(() => petModelStyle(traits.value))
const aura = computed(() => petBackdrop(traits.value))
const accessory = computed(() => petAccessory(traits.value))

// Аксессуар по точкам спрайта: очки на глазах, шляпа на макушке
function placeAccessory(size) {
  const id = traits.value?.accessory?.id
  if (!accessory.value || !id) return null
  return accessoryPlacement(data.value.sprite, id, size, PIXEL_SPRITES[data.value.sprite]?.rows)
}

const accPlace = computed(() => placeAccessory(grownSize.value))
const panelAcc = computed(() => placeAccessory(36))
const rarity = computed(() => PET_RARITY[traits.value?.rarity] ?? PET_RARITY.common)

const TRAIT_ROWS = [
  { key: 'model', label: 'Окрас' },
  { key: 'backdrop', label: 'Фон' },
  { key: 'accessory', label: 'Аксессуар' },
]

const xpProgress = computed(() => {
  const s = stats.value
  if (!s || s.next_level_xp === null) return 100
  return Math.round(((s.xp - s.level_xp) / (s.next_level_xp - s.level_xp)) * 100)
})

async function loadStats() {
  if (!props.interactive || !props.owner?.id) return
  try {
    stats.value = await api.get(`/players/${props.owner.id}/pet`, {
      params: props.instanceId ? { pet: props.instanceId } : undefined,
    })
  } catch {
    // Без статистики питомец просто гуляет
  }
}

function placePanel() {
  /*
   * Меряем само тело и бейдж с именем, а не обёртку: у летающего
   * питомца обёртка тянется до низа шапки, и карточка «снизу»
   * открывалась далеко под ним.
   */
  const body = petEl.value?.querySelector('.pet__body')?.getBoundingClientRect()
  if (!body) return
  const tag = petEl.value.querySelector('.pet__tag')?.getBoundingClientRect()
  const rect = { left: body.left, width: body.width, top: body.top, bottom: Math.max(body.bottom, tag?.bottom ?? 0) }

  // Над питомцем не хватает места (компактная шапка, верх экрана) — открываем снизу
  const below = rect.top < 400

  panelPos.value = {
    left: Math.min(window.innerWidth - 150, Math.max(150, rect.left + rect.width / 2)),
    top: below ? rect.bottom + 10 : rect.top - 10,
    below,
  }
}

function onPetClick() {
  if (!props.interactive) {
    burstHearts()
    onHover()
    return
  }

  burstHearts(3)
  // Касание без наведения (телефон): сначала остановить, потом мерить
  freeze()
  placePanel()
  panelError.value = ''
  panelOpen.value = !panelOpen.value
}

function closePanel() {
  if (!panelOpen.value) return
  panelOpen.value = false
  renaming.value = false
  if (!busy.value) schedule(1200)
}

function onKey(event) {
  if (event.key === 'Escape') closePanel()
}

function isDone(action) {
  return stats.value?.done_today?.includes(action)
}

async function act(action) {
  if (busy.value || isDone(action)) return

  if (!auth.isAuthenticated) {
    panelError.value = 'Войди, чтобы возиться с питомцем.'
    return
  }

  panelError.value = ''
  busy.value = true
  closePanel()
  clearTimeout(timer)

  let result = null
  try {
    result = await api.post(`/players/${props.owner.id}/pet/${action}`, { pet: props.instanceId })
  } catch (e) {
    panelError.value = e.message || 'Не получилось.'
    panelOpen.value = true
    busy.value = false
    schedule()
    return
  }

  if (action === 'pet') await scenePet()
  if (action === 'feed') await sceneFeed()
  if (action === 'play') await scenePlay()

  stats.value = result
  if (result.level_up) await sceneLevelUp(result.level)

  busy.value = false
  state.value = 'idle'
  schedule(1800)
}

/** Погладили: мурчит, сердечки. */
async function scenePet() {
  state.value = 'idle'
  jump()
  burstHearts(8)
  say('Мрр!')
  await wait(1400)
}

/** Покормили: еда падает сверху, питомец бежит и ест. */
async function sceneFeed() {
  const target = Math.min(86, Math.max(6, x.value + (Math.random() < 0.5 ? -18 : 18)))
  food.value = { id: Date.now(), x: target, landed: false }
  say('Еда!!')
  await wait(700)
  food.value = { ...food.value, landed: true }

  await wait(walkTo(target, 25, 'run'))
  state.value = 'eat'
  await wait(1600)
  food.value = null
  state.value = 'idle'
  burstHearts(4)
  say('Вкусно!')
  await wait(900)
}

/** Поиграли: мячик прыгает по шапке, питомец за ним гоняется. */
async function scenePlay() {
  say('Мячик!')

  for (let round = 0; round < 3; round++) {
    const target = rand(6, 86)
    ball.value = { id: Date.now() + round, x: target, y: data.value?.fly ? rand(25, 60) : 0 }
    await wait(450)
    await wait(walkTo(target, 18, 'run'))
    jump()
    await wait(450)
  }

  ball.value = null
  state.value = 'idle'
  burstHearts(5)
  say('Ещё! Ещё!')
  await wait(900)
}

async function sceneLevelUp(level) {
  levelUp.value = level
  jump()
  burstHearts(10)
  await wait(2200)
  levelUp.value = false
}

function startRename() {
  nameDraft.value = stats.value?.name || ''
  renaming.value = true
}

async function saveName() {
  try {
    const res = await api.put('/players/me/pet-name', { name: nameDraft.value, inventory_id: props.instanceId })
    stats.value = { ...stats.value, name: res.name }
    renaming.value = false
  } catch (e) {
    panelError.value = e.message || 'Не удалось сохранить имя.'
  }
}

/* =========================================================
   ЖИЗНЕННЫЙ ЦИКЛ
   ========================================================= */

onMounted(async () => {
  if (!data.value) return

  // Несколько питомцев стартуют в разных местах, а не друг на друге
  x.value = props.lane ? 15 + props.lane * 28 + rand(-6, 6) : rand(8, 70)
  schedule(800 + props.lane * 500)

  zoneEl.value?.parentElement?.addEventListener('mousemove', onZoneMove)
  window.addEventListener('keydown', onKey)
  window.addEventListener('scroll', closePanel, { passive: true })

  await loadStats()

  // Голодный время от времени напоминает о себе
  moodTimer = setInterval(() => {
    if (stats.value?.mood === 'hungry' && !busy.value && !speech.value && Math.random() < 0.5) {
      say(['Есть хочу…', 'Покорми меня…', '*урчит животом*'][Math.floor(Math.random() * 3)])
    }
  }, 9000)
})

onUnmounted(() => {
  clearTimeout(timer)
  clearTimeout(speechTimer)
  clearInterval(moodTimer)
  zoneEl.value?.parentElement?.removeEventListener('mousemove', onZoneMove)
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('scroll', closePanel)
})
</script>

<template>
  <div v-if="data" ref="zoneEl" class="pet-zone" :class="{ 'pet-zone--interactive': interactive }">
    <!-- Еда падает сверху -->
    <span
        v-if="food"
        :key="food.id"
        class="pet-food"
        :class="{ 'is-landed': food.landed }"
        :style="{ left: `${food.x}%` }"
    ><PixelSprite sprite="meat" :size="14" /></span>

    <!-- Мячик -->
    <span
        v-if="ball"
        :key="ball.id"
        class="pet-ball"
        :style="{ left: `${ball.x}%`, bottom: data.fly ? `${100 - ball.y}%` : '8px' }"
    ><PixelSprite sprite="ball" :size="12" /></span>

    <div
        ref="petEl"
        class="pet"
        :class="[`pet--${state}`, trickPlaying ? `pet-trick--${trickPlaying}` : null, { 'pet--fly': data.fly, 'pet--jump': jumping, 'pet--hungry': stats?.mood === 'hungry' }]"
        :style="{
          left: `${x}%`,
          top: data.fly ? `${y}%` : 'auto',
          transitionDuration: `${['walk', 'run'].includes(state) ? walkMs : 0}ms`,
        }"
        :title="interactive ? `${petName} — нажми, чтобы поиграть` : undefined"
        @mouseenter="onHover"
        @mouseleave="!busy && !panelOpen && schedule(1500)"
        @click.stop="onPetClick"
        @animationend="jumping = false"
    >
      <Transition name="pet-speech">
        <span v-if="speech" class="pet__speech">{{ speech }}</span>
      </Transition>

      <span v-if="levelUp" class="pet__levelup">LEVEL {{ levelUp }}!</span>

      <span v-if="state === 'sleep'" class="pet__zzz">
        <i>z</i><i>z</i><i>Z</i>
      </span>

      <span v-for="heart in hearts" :key="heart.id" class="pet__heart" :style="{ '--dx': heart.dx, animationDelay: heart.delay }"><PixelSprite sprite="heart" :size="10" /></span>

      <span class="pet__body" :class="{ 'is-left': facingLeft }">
        <!-- Фон экземпляра: сияние позади -->
        <span v-if="aura" class="pet__aura" :style="{ background: aura }"></span>
        <span class="pet__skin" :style="skinStyle">
          <PixelSprite :sprite="data.sprite" :size="grownSize" />
        </span>
        <!-- Аксессуар поверх головы -->
        <span
            v-if="accPlace"
            class="pet__acc"
            :style="{ left: `${accPlace.left}px`, top: `${accPlace.top}px` }"
        ><PetAccessory :id="accPlace.art" :width="accPlace.width" /></span>
      </span>
      <span class="pet__shadow"></span>

      <!-- Имя и уровень -->
      <span v-if="interactive && stats" class="pet__tag">
        {{ petName }} <b>{{ stats.level }}</b>
      </span>
    </div>

    <!-- Карточка питомца -->
    <Teleport to="body">
      <div v-if="panelOpen" class="pet-panel-backdrop" @click="closePanel"></div>
      <Transition name="pet-panel">
        <div
            v-if="panelOpen && stats"
            class="pet-panel"
            :class="{ 'pet-panel--below': panelPos.below }"
            :style="{ left: `${panelPos.left}px`, top: `${panelPos.top}px` }"
            @click.stop
        >
          <header class="pet-panel__head">
            <span class="pet-panel__avatar" :style="aura ? { background: aura } : null">
              <span class="pet-panel__sprite">
              <span :style="skinStyle"><PixelSprite :sprite="data.sprite" :size="36" /></span>
              <span v-if="panelAcc" class="pet-panel__acc" :style="{ left: `${panelAcc.left}px`, top: `${panelAcc.top}px` }"><PetAccessory :id="panelAcc.art" :width="panelAcc.width" /></span>
              </span>
            </span>
            <div class="pet-panel__title">
              <div v-if="!renaming" class="pet-panel__name-row">
                <strong>{{ petName }}</strong>
                <em v-if="traits?.serial" class="pet-panel__serial">#{{ traits.serial }}</em>
                <button v-if="editable" type="button" class="pet-panel__rename" title="Дать имя" @click="startRename"><AppIcon icon="doc" :size="13" /></button>
              </div>
              <form v-else class="pet-panel__name-form" @submit.prevent="saveName">
                <input v-model="nameDraft" maxlength="20" placeholder="Имя питомца" autofocus />
                <button type="submit" aria-label="Сохранить имя"><AppIcon icon="check" :size="14" /></button>
              </form>
              <small class="pet-panel__mood"><PixelSprite :sprite="mood.icon" :size="12" /> {{ mood.label }} · {{ mood.text }}</small>
            </div>
            <span class="pet-panel__level">ур. {{ stats.level }}</span>
          </header>

          <!-- Черты экземпляра с шансами — как у подарков -->
          <div v-if="traits" class="pet-panel__traits" :style="{ '--rarity': rarity.color }">
            <span class="pet-panel__rarity">{{ rarity.label }} экземпляр</span>
            <div v-for="row in TRAIT_ROWS" :key="row.key" class="pet-panel__trait">
              <span>{{ row.label }}</span>
              <b>{{ traits[row.key]?.name }}</b>
              <i>{{ chanceLabel(traits[row.key]?.chance) }}</i>
            </div>
          </div>

          <div class="pet-panel__xp">
            <i :style="{ width: `${xpProgress}%` }"></i>
          </div>
          <div class="pet-panel__xp-label">
            <span>{{ stats.xp }} опыта</span>
            <span v-if="stats.next_level_xp">до {{ stats.level + 1 }} ур.: {{ stats.next_level_xp - stats.xp }}</span>
            <span v-else>максимальный уровень</span>
          </div>

          <div class="pet-panel__arena">
            <span>Арена: <b>{{ stats.rating ?? 1000 }}</b> · {{ stats.wins ?? 0 }}–{{ stats.losses ?? 0 }}</span>
            <RouterLink v-if="auth.user?.id === owner?.id" to="/pets" @click="closePanel">Все мои питомцы</RouterLink>
          </div>

          <div class="pet-panel__actions">
            <button
                v-for="action in ACTIONS"
                :key="action.id"
                type="button"
                class="pet-action"
                :class="[`pet-action--${action.id}`, { 'is-done': isDone(action.id) }]"
                :disabled="isDone(action.id)"
                @click="act(action.id)"
            >
              <span class="pet-action__icon"><PixelSprite :sprite="action.icon" :size="18" /></span>
              <span class="pet-action__label">{{ action.label }}</span>
              <small>{{ isDone(action.id) ? 'завтра снова' : `+${action.xp} опыта` }}</small>
            </button>
          </div>

          <p v-if="panelError" class="pet-panel__error">{{ panelError }}</p>

          <footer class="pet-panel__foot">
            <span class="pet-panel__counts">
              <PixelSprite sprite="heart" :size="11" /> {{ stats.counts.pet }}
              <PixelSprite sprite="meat" :size="11" /> {{ stats.counts.feed }}
              <PixelSprite sprite="ball" :size="11" /> {{ stats.counts.play }}
            </span>
            <span v-if="stats.friends.length" class="pet-panel__friends">
              Дружит с: {{ stats.friends.map((f) => f.username).join(', ') }}
            </span>
          </footer>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.pet-zone {
  position: absolute;
  inset: 0;
  z-index: 15;

  overflow: hidden;
  border-radius: inherit;

  pointer-events: none;
}

/* В шапке профиля — над содержимым, иначе клики ловила шапка */
.pet-zone--interactive {
  z-index: 26;
}

.pet {
  position: absolute;
  bottom: 6px;

  display: flex;
  flex-direction: column;
  align-items: center;

  transform: translateX(-50%);
  transition-property: left, top;
  transition-timing-function: linear;

  pointer-events: auto;
  cursor: pointer;
}

.pet--run {
  transition-timing-function: cubic-bezier(0.4, 0, 0.3, 1);
}

.pet__body {
  position: relative;

  display: block;
  filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.45));
  image-rendering: pixelated;
  transition: filter 0.2s ease;
}

/* Уникальный экземпляр: окрас, сияние фона, аксессуар */
.pet__skin {
  position: relative;
  z-index: 1;
  display: block;
}

.pet__aura {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 0;

  width: 170%;
  height: 170%;

  border-radius: 50%;
  filter: blur(6px);
  opacity: 0.8;

  transform: translate(-50%, -50%);
  animation: pet-aura 6s linear infinite;
  pointer-events: none;
}

@keyframes pet-aura {
  to { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Место считает accessoryPlacement — от левого верхнего угла спрайта */
.pet__acc {
  position: absolute;
  z-index: 2;

  line-height: 0;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));

  pointer-events: none;
}

.pet__skin {
  line-height: 0;
}

.pet:hover .pet__body {
  filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.45)) drop-shadow(0 0 6px rgba(255, 255, 255, 0.35));
}

.pet__body.is-left {
  transform: scaleX(-1);
}

/* Тень под лапками: на прыжке и в полёте меньше */
.pet__shadow {
  width: 70%;
  height: 5px;
  margin-top: 2px;

  background: radial-gradient(ellipse, rgba(0, 0, 0, 0.45), transparent 70%);
}

.pet--fly .pet__shadow {
  display: none;
}

/* Имя и уровень под питомцем */
.pet__tag {
  margin-top: 1px;
  padding: 1px 6px;

  color: #fff;
  background: rgba(10, 8, 16, 0.65);
  border-radius: 6px;

  font-size: 9px;
  font-weight: 800;
  white-space: nowrap;

  opacity: 0.75;
  transition: opacity 0.2s ease;
}

.pet:hover .pet__tag {
  opacity: 1;
}

.pet__tag b {
  margin-left: 2px;
  padding: 0 4px;

  color: #1c1917;
  background: #fde047;
  border-radius: 4px;
}

/* Походка: покачивание; бег — быстрее; полёт — плавные волны */
.pet--walk .pet__body {
  animation: pet-walk 0.36s ease-in-out infinite alternate;
}

.pet--run .pet__body {
  animation: pet-walk 0.18s ease-in-out infinite alternate;
}

.pet--walk .pet__body.is-left,
.pet--run .pet__body.is-left {
  animation-name: pet-walk-left;
}

.pet--idle .pet__body {
  animation: pet-breathe 2.4s ease-in-out infinite;
}

.pet--eat .pet__body {
  animation: pet-eat 0.22s ease-in-out infinite alternate;
}

.pet--fly .pet__body {
  animation: pet-hover 1.6s ease-in-out infinite;
}

.pet--sleep .pet__body {
  animation: pet-sleep 3s ease-in-out infinite;
  filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.45)) brightness(0.85);
}

.pet--hungry.pet--idle .pet__body {
  animation: pet-sad 2.8s ease-in-out infinite;
}

.pet--jump {
  animation: pet-jump 0.55s cubic-bezier(0.3, 0, 0.3, 1);
}

@keyframes pet-walk {
  from { transform: translateY(0) rotate(-4deg); }
  to { transform: translateY(-3px) rotate(4deg); }
}

@keyframes pet-walk-left {
  from { transform: scaleX(-1) translateY(0) rotate(-4deg); }
  to { transform: scaleX(-1) translateY(-3px) rotate(4deg); }
}

@keyframes pet-breathe {
  0%, 100% { scale: 1 1; }
  50% { scale: 1.04 0.96; }
}

@keyframes pet-eat {
  from { scale: 1 1; translate: 0 0; }
  to { scale: 1.08 0.9; translate: 0 2px; }
}

@keyframes pet-sad {
  0%, 100% { scale: 1 1; rotate: 0deg; }
  50% { scale: 0.98 0.95; rotate: -3deg; }
}

@keyframes pet-hover {
  0%, 100% { translate: 0 0; }
  50% { translate: 0 -6px; }
}

@keyframes pet-sleep {
  0%, 100% { scale: 1 0.92; }
  50% { scale: 1.03 0.88; }
}

/*
 * translate складывается с transform: translateX(-50%) у .pet, поэтому
 * по X тут 0 — раньше было -50%, и питомец на прыжке отскакивал вбок.
 */
@keyframes pet-jump {
  0%, 100% { translate: 0 0; }
  40% { translate: 0 -22px; }
  70% { translate: 0 -8px; }
}

/* Реплика: облачко над головой */
.pet__speech {
  position: absolute;
  bottom: calc(100% + 6px);

  padding: 5px 9px;

  color: #1c1917;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);

  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.pet__speech::after {
  content: '';

  position: absolute;
  left: 50%;
  bottom: -5px;

  border: 5px solid transparent;
  border-top-color: #fff;
  border-bottom: 0;
  transform: translateX(-50%);
}

.pet-speech-enter-active,
.pet-speech-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.pet-speech-enter-from,
.pet-speech-leave-to {
  opacity: 0;
  transform: translateY(4px) scale(0.9);
}

/* Новый уровень */
.pet__levelup {
  position: absolute;
  bottom: calc(100% + 30px);

  color: #fde047;

  font-family: var(--font-display);
  font-size: 14px;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-shadow: 0 0 10px rgba(253, 224, 71, 0.8), 0 2px 0 #92400e;
  white-space: nowrap;

  animation: pet-levelup 2.2s ease-out forwards;
}

@keyframes pet-levelup {
  0% { opacity: 0; transform: translateY(10px) scale(0.6); }
  15% { opacity: 1; transform: translateY(0) scale(1.15); }
  30% { transform: scale(1); }
  80% { opacity: 1; }
  100% { opacity: 0; transform: translateY(-18px); }
}

/* Сон: буквы Z всплывают */
.pet__zzz {
  position: absolute;
  bottom: 85%;
  left: 70%;

  color: #c4b5fd;
  font-size: 11px;
  font-weight: 900;
}

.pet__zzz i {
  position: absolute;
  font-style: normal;
  opacity: 0;
  animation: pet-z 2.4s ease-out infinite;
}

.pet__zzz i:nth-child(2) { animation-delay: 0.8s; }
.pet__zzz i:nth-child(3) { animation-delay: 1.6s; font-size: 14px; }

@keyframes pet-z {
  0% { opacity: 0; transform: translate(0, 0); }
  20% { opacity: 1; }
  100% { opacity: 0; transform: translate(10px, -22px); }
}

/* Сердечки */
.pet__heart {
  position: absolute;
  bottom: 60%;

  color: #fb7185;
  font-size: 13px;
  text-shadow: 0 0 6px rgba(251, 113, 133, 0.7);

  animation: pet-heart 1s ease-out forwards;
}

@keyframes pet-heart {
  0% { opacity: 1; transform: translate(0, 0) scale(0.6); }
  100% { opacity: 0; transform: translate(var(--dx), -40px) scale(1.2); }
}

/* Еда падает сверху и лежит, пока её не съедят */
.pet-food {
  position: absolute;
  top: -30px;

  font-size: 20px;
  filter: drop-shadow(0 3px 3px rgba(0, 0, 0, 0.5));

  transform: translateX(-50%);
  transition: top 0.6s cubic-bezier(0.5, 0, 0.75, 0);
}

.pet-food.is-landed {
  top: calc(100% - 30px);
  animation: pet-food-bounce 0.35s ease-out;
}

@keyframes pet-food-bounce {
  0% { transform: translateX(-50%) scale(1.2, 0.7); }
  100% { transform: translateX(-50%) scale(1); }
}

/* Мячик прыгает с места на место */
.pet-ball {
  position: absolute;

  font-size: 16px;
  filter: drop-shadow(0 3px 3px rgba(0, 0, 0, 0.5));

  transform: translateX(-50%);
  animation: pet-ball-in 0.45s cubic-bezier(0.3, 0, 0.3, 1);
}

@keyframes pet-ball-in {
  0% { opacity: 0; translate: 0 -60px; rotate: -180deg; }
  70% { opacity: 1; translate: 0 4px; }
  100% { translate: 0 0; rotate: 0deg; }
}

@media (prefers-reduced-motion: reduce) {
  .pet,
  .pet *,
  .pet-food,
  .pet-ball {
    animation: none !important;
    transition: none !important;
  }
}
</style>

<!-- Карточка питомца вынесена в body — стили без scoped -->
<style>
/* Анимированные окрасы: имя анимации задаётся в style, поэтому без scoped */
@keyframes pet-rainbow {
  from { filter: saturate(2) hue-rotate(0deg) drop-shadow(0 0 4px rgba(255, 255, 255, 0.6)); }
  to { filter: saturate(2) hue-rotate(360deg) drop-shadow(0 0 4px rgba(255, 255, 255, 0.6)); }
}

@keyframes pet-lava {
  0%, 100% { filter: sepia(1) saturate(5) hue-rotate(-30deg) brightness(1) drop-shadow(0 0 4px rgba(249, 115, 22, 0.9)); }
  50% { filter: sepia(1) saturate(6) hue-rotate(-15deg) brightness(1.25) drop-shadow(0 0 9px rgba(239, 68, 68, 0.95)); }
}

@keyframes pet-holo {
  0% { filter: saturate(1.6) brightness(1.3) hue-rotate(0deg) drop-shadow(0 0 5px rgba(165, 243, 252, 0.9)); }
  50% { filter: saturate(1.6) brightness(1.4) hue-rotate(180deg) drop-shadow(0 0 7px rgba(244, 114, 182, 0.9)); }
  100% { filter: saturate(1.6) brightness(1.3) hue-rotate(360deg) drop-shadow(0 0 5px rgba(165, 243, 252, 0.9)); }
}

/* Глитч: рывками сдвигает оттенок и раздваивает контур */
@keyframes pet-glitch {
  0%, 86%, 100% { filter: saturate(1.4) drop-shadow(0 0 0 transparent); }
  88% { filter: hue-rotate(90deg) saturate(3) drop-shadow(-2px 0 0 #22d3ee) drop-shadow(2px 0 0 #f43f5e); }
  92% { filter: hue-rotate(-60deg) saturate(2) drop-shadow(2px 0 0 #22d3ee) drop-shadow(-2px 0 0 #f43f5e); }
  95% { filter: invert(1) hue-rotate(180deg); }
}

@keyframes pet-aurora {
  0%, 100% { filter: hue-rotate(100deg) saturate(1.8) brightness(1.2) drop-shadow(0 0 6px rgba(34, 197, 94, 0.9)); }
  50% { filter: hue-rotate(200deg) saturate(2) brightness(1.3) drop-shadow(0 0 8px rgba(139, 92, 246, 0.9)); }
}

/* Проклятый: тёмно-красный, временами вспыхивает и «моргает» негативом */
@keyframes pet-cursed {
  0%, 100% { filter: hue-rotate(320deg) saturate(2) brightness(0.8) drop-shadow(0 0 5px rgba(220, 38, 38, 0.9)); }
  45% { filter: hue-rotate(320deg) saturate(2.4) brightness(1.15) drop-shadow(0 0 10px rgba(127, 29, 29, 0.95)); }
  47% { filter: invert(1) hue-rotate(140deg) drop-shadow(0 0 8px rgba(220, 38, 38, 0.95)); }
  49% { filter: hue-rotate(320deg) saturate(2) brightness(0.8) drop-shadow(0 0 5px rgba(220, 38, 38, 0.9)); }
}

/* Звёздный: светлый, мерцает то белым, то золотым */
@keyframes pet-starlight {
  0%, 100% { filter: brightness(1.3) saturate(0.6) drop-shadow(0 0 4px rgba(255, 255, 255, 0.95)); }
  50% { filter: brightness(1.55) saturate(0.9) hue-rotate(20deg) drop-shadow(0 0 11px rgba(253, 224, 71, 0.95)); }
}

@keyframes pet-galaxy {
  0%, 100% { filter: hue-rotate(230deg) saturate(2) drop-shadow(0 0 5px rgba(236, 72, 153, 0.9)); }
  50% { filter: hue-rotate(290deg) saturate(2.4) drop-shadow(0 0 8px rgba(99, 102, 241, 0.9)); }
}

.pet-panel__avatar {
  position: relative;
}

.pet-panel__sprite {
  position: relative;
  display: inline-block;
  line-height: 0;
}

.pet-panel__acc {
  position: absolute;
  z-index: 1;

  line-height: 0;

  pointer-events: none;
}

.pet-panel__serial {
  flex-shrink: 0;

  color: #a8a3b8;

  font-size: 12px;
  font-style: normal;
  font-weight: 700;
}

.pet-panel__traits {
  margin-top: 12px;
  padding: 10px 12px;

  background: color-mix(in srgb, var(--rarity) 10%, rgba(255, 255, 255, 0.03));
  border: 1px solid color-mix(in srgb, var(--rarity) 35%, transparent);
  border-radius: 12px;
}

.pet-panel__rarity {
  display: block;
  margin-bottom: 6px;

  color: var(--rarity);

  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.pet-panel__trait {
  display: grid;
  grid-template-columns: 72px 1fr auto;
  align-items: baseline;
  gap: 8px;

  padding: 3px 0;

  font-size: 12px;
}

.pet-panel__trait span {
  color: #8a8598;
}

.pet-panel__trait b {
  color: #fff;
}

.pet-panel__trait i {
  padding: 1px 6px;

  color: #c4b5fd;
  background: rgba(139, 92, 246, 0.15);
  border-radius: 6px;

  font-size: 10px;
  font-style: normal;
  font-weight: 800;
}

.pet-panel-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1190;
}

.pet-panel {
  position: fixed;
  z-index: 1200;

  width: 290px;

  padding: 14px;

  color: #e6e3ee;
  background: rgba(20, 17, 30, 0.97);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 18px;
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(14px);

  transform: translate(-50%, -100%);
}

.pet-panel::after {
  content: "";

  position: absolute;
  left: 50%;
  bottom: -7px;

  width: 14px;
  height: 14px;

  background: inherit;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);

  transform: translateX(-50%) rotate(45deg);
}

/* Под питомцем: стрелка сверху */
.pet-panel--below {
  transform: translate(-50%, 0);
}

.pet-panel--below::after {
  top: -7px;
  bottom: auto;

  transform: translateX(-50%) rotate(225deg);
}

.pet-panel-enter-active,
.pet-panel-leave-active {
  transition: opacity 0.18s ease, translate 0.18s ease;
}

.pet-panel-enter-from,
.pet-panel-leave-to {
  opacity: 0;
  translate: 0 8px;
}

.pet-panel__head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pet-panel__avatar {
  width: 46px;
  height: 46px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  background: radial-gradient(circle, rgba(167, 139, 250, 0.25), rgba(255, 255, 255, 0.04));
  border-radius: 14px;
}

.pet-panel__title {
  min-width: 0;
  flex: 1;

  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pet-panel__title strong {
  color: #fff;
  font-size: 15px;
}

.pet-panel__title small {
  color: #a8a3b8;
  font-size: 11px;
  line-height: 1.3;
}

.pet-panel__name-row {
  min-width: 0;

  display: flex;
  align-items: center;
  gap: 4px;
}

.pet-panel__name-row strong {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pet-panel__rename {
  flex-shrink: 0;

  width: 22px;
  height: 22px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #a8a3b8;
  background: rgba(255, 255, 255, 0.06);
  border: 0;
  border-radius: 6px;

  font-size: 12px;

  cursor: pointer;
}

.pet-panel__rename:hover {
  color: #fff;
  background: rgba(139, 92, 246, 0.35);
}

.pet-panel__name-form {
  display: flex;
  gap: 4px;
}

.pet-panel__name-form input {
  min-width: 0;
  flex: 1;

  padding: 4px 8px;

  color: #fff;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(167, 139, 250, 0.5);
  border-radius: 8px;

  font: inherit;
  font-size: 13px;
}

.pet-panel__name-form button {
  color: #fff;
  background: #7c3aed;
  border: 0;
  border-radius: 8px;

  cursor: pointer;
}

.pet-panel__level {
  flex-shrink: 0;

  padding: 4px 9px;

  color: #1c1917;
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border-radius: 999px;

  font-size: 12px;
  font-weight: 900;
}

.pet-panel__xp {
  height: 7px;
  margin-top: 12px;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.07);
  border-radius: 99px;
}

.pet-panel__xp i {
  display: block;
  height: 100%;

  background: linear-gradient(90deg, #a78bfa, #f472b6);
  border-radius: inherit;
  box-shadow: 0 0 10px rgba(244, 114, 182, 0.5);

  transition: width 0.6s ease;
}

.pet-panel__arena {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;

  margin-top: 8px;

  color: #a8a3b8;
  font-size: 11.5px;
}

.pet-panel__arena b { color: #fda4af; }
.pet-panel__arena a { color: #c4b5fd; font-weight: 700; text-decoration: none; }
.pet-panel__arena a:hover { color: #fff; }

.pet-panel__xp-label {
  display: flex;
  justify-content: space-between;

  margin-top: 5px;

  color: #8a8598;
  font-size: 10px;
  font-weight: 700;
}

.pet-panel__actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;

  margin-top: 12px;
}

.pet-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;

  padding: 10px 4px 8px;

  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 12px;

  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}

.pet-action:hover:not(:disabled) {
  transform: translateY(-2px);
}

.pet-action--pet:hover:not(:disabled) { background: rgba(244, 114, 182, 0.18); border-color: rgba(244, 114, 182, 0.5); }
.pet-action--feed:hover:not(:disabled) { background: rgba(251, 146, 60, 0.18); border-color: rgba(251, 146, 60, 0.5); }
.pet-action--play:hover:not(:disabled) { background: rgba(163, 230, 53, 0.16); border-color: rgba(163, 230, 53, 0.5); }

.pet-action__icon {
  display: grid;
  place-items: center;
  height: 22px;
}

.pet-panel__mood,
.pet-panel__counts {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.pet-action__label {
  font-size: 11px;
  font-weight: 800;
}

.pet-action small {
  color: #8a8598;
  font-size: 9px;
  font-weight: 700;
}

.pet-action.is-done {
  opacity: 0.45;
  cursor: default;
}

.pet-panel__error {
  margin: 10px 0 0;
  color: #fca5a5;
  font-size: 12px;
}

.pet-panel__foot {
  display: flex;
  flex-direction: column;
  gap: 3px;

  margin-top: 10px;
  padding-top: 9px;

  color: #8a8598;
  border-top: 1px solid rgba(255, 255, 255, 0.06);

  font-size: 11px;
}

.pet-panel__friends {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
