<script setup>
/**
 * Карта-мир: вся игровая механика на одной сцене. Постройки — это
 * механики; клик открывает дверь и ведёт внутрь: у каждого здания свой
 * интерьер (стены, пол, декор), а не вкладка. Выйти можно через дверь.
 * Питомцы игрока бродят по дорожке. Сводка для значков — GET /api/world.
 */
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import '@/game/gameKit.css'
import '@/game/battleSkin.css'

const route = useRoute()
const router = useRouter()

const FarmView = defineAsyncComponent(() => import('@/views/pets/FarmView.vue'))
const HouseScene = defineAsyncComponent(() => import('@/game/HouseScene.vue'))
const ExpeditionScene = defineAsyncComponent(() => import('@/game/ExpeditionScene.vue'))
const ArenaScene = defineAsyncComponent(() => import('@/game/ArenaScene.vue'))
const ForgeScene = defineAsyncComponent(() => import('@/game/ForgeScene.vue'))
const MuseumScene = defineAsyncComponent(() => import('@/game/MuseumScene.vue'))
const HallScene = defineAsyncComponent(() => import('@/game/HallScene.vue'))
const DungeonView = defineAsyncComponent(() => import('@/views/pets/DungeonView.vue'))
const MineScene = defineAsyncComponent(() => import('@/game/MineScene.vue'))
const RaidScene = defineAsyncComponent(() => import('@/game/RaidScene.vue'))
const LavkaScene = defineAsyncComponent(() => import('@/game/LavkaScene.vue'))
const MarketScene = defineAsyncComponent(() => import('@/game/MarketScene.vue'))
const AuctionView = defineAsyncComponent(() => import('@/views/shop/AuctionView.vue'))
const PetBag = defineAsyncComponent(() => import('@/components/pets/PetBag.vue'))

/**
 * Постройки. x, y — точка на земле (в процентах сцены), где здание стоит;
 * only — какая вкладка механики живёт внутри (остальные скрыты).
 */
const PLACES = [
  { id: 'gear', name: 'Кузница', kind: 'forge', x: 7, y: 60, view: ForgeScene, hint: 'Снаряжение питомцев' },
  { id: 'farm', name: 'Общая ферма', kind: 'barn', x: 21, y: 60, view: FarmView, props: { only: 'common' }, hint: 'Бронь и выкуп питомцев', badge: 'farm' },
  { id: 'miner', name: 'Шахта', kind: 'mine', x: 35, y: 60, view: MineScene, hint: 'Копай руду, зарабатывай ApexCoin' },
  { id: 'dungeon', name: 'Подземелья', kind: 'dungeon', x: 49, y: 60, view: DungeonView, hint: 'Отряд, боссы и добыча', badge: 'dungeon' },
  { id: 'raid', name: 'Разлом', kind: 'rift', x: 63, y: 60, view: RaidScene, hint: 'Рейд на группу в 18:00 и 22:00' },
  { id: 'museum', name: 'Питомцедекс', kind: 'museum', x: 77, y: 60, view: MuseumScene, hint: 'Коллекция видов и окрасов' },
  { id: 'hall', name: 'Доска почёта', kind: 'hall', x: 91, y: 60, view: HallScene, hint: 'Лучшие питомцы сервера' },
  { id: 'pets', name: 'Дом питомцев', kind: 'house', x: 6, y: 92, view: HouseScene, hint: 'Уход, уровни, перки и трюки' },
  { id: 'personal', name: 'Личная ферма', kind: 'gate', x: 19, y: 92, view: FarmView, props: { only: 'personal' }, hint: 'Грядки, Кладовка и горн', badge: 'personal' },
  { id: 'bag', name: 'Склад питомцев', kind: 'store', x: 32, y: 92, view: PetBag, hint: 'Инвентарь питомцев: снаряжение, материалы, ключи' },
  { id: 'expeditions', name: 'Экспедиции', kind: 'camp', x: 45, y: 92, view: ExpeditionScene, hint: 'Поход за монетами и ключами', badge: 'expeditions' },
  { id: 'arena', name: 'Арена', kind: 'arena', x: 58, y: 92, view: ArenaScene, hint: 'Пошаговые бои питомцев' },
  { id: 'auction', name: 'Аукцион', kind: 'stall', x: 71, y: 92, view: AuctionView, hint: 'Торги питомцами' },
  { id: 'market', name: 'Рынок', kind: 'market', x: 83, y: 92, view: MarketScene, hint: 'Продать и купить материалы и снаряжение' },
  { id: 'lavka', name: 'Лавка', kind: 'lavka', x: 95, y: 92, view: LavkaScene, hint: 'Привилегии за Рубли, вещи за Осколки' },
]

/** Декор: деревья, кусты, цветы. Координаты — проценты сцены, стоит основанием на точке. */
const TREES = [[2, 47], [11, 45], [20, 43], [31, 46], [42, 44], [57, 45], [69, 43], [82, 46], [96, 44], [2, 79], [98, 80]]
const BUSHES = [[14, 61], [28, 60], [42, 61], [56, 61], [70, 60], [84, 61], [12.5, 93], [25.5, 94], [38.5, 93], [51.5, 94], [64.5, 93], [77.5, 94], [89, 93]]
const FLOWERS = Array.from({ length: 36 }, (_, i) => [(i * 29.7 + 7) % 98 + 1, 66 + ((i * 13) % 5) * 0.4 + (i % 2 ? 8 : 0), i % 4])
const LAMPS = [[7, 72], [21, 72], [35, 72], [49, 72], [63, 72], [77, 72], [91, 72]]
const BIRDS = [[12, 14, 0], [48, 9, 7], [75, 18, 14]]
const BUTTERFLIES = [[18, 66], [44, 80], [70, 64], [88, 82]]

const hour = ref(new Date().getHours())
const night = computed(() => hour.value >= 21 || hour.value < 6)
const dusk = computed(() => (hour.value >= 19 && hour.value < 21) || (hour.value >= 6 && hour.value < 8))
let clock = null

const world = ref(null)
const error = ref('')
const entering = ref(null)
let timer = null
let enterTimer = null

const openId = computed(() => (PLACES.some((p) => p.id === route.query.at) ? route.query.at : null))
const open = computed(() => PLACES.find((p) => p.id === openId.value) ?? null)

const fmt = (n) => Number(n ?? 0).toLocaleString('ru-RU')

async function load() {
  try {
    world.value = await api.get('/world')
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить карту.'
  }
}

/** Открыть дверь и зайти: сначала дверь светится, потом сменяется интерьер. */
function enter(place) {
  if (entering.value) return
  entering.value = place.id

  enterTimer = setTimeout(() => {
    entering.value = null
    router.replace({ query: { at: place.id } })
  }, 420)
}

function leave() {
  router.replace({ query: {} })
  // Вышли на улицу — значки могли измениться
  load()
}

const badge = (place) => (place.badge ? (world.value?.badges?.[place.badge] ?? 0) : 0)
const locked = (place) => place.id === 'personal' && world.value && !world.value.personal_unlocked

// Мир занимает весь экран: прокрутку страницы убираем, пока он открыт
document.documentElement.style.overflow = 'hidden'

onMounted(() => {
  load()
  timer = setInterval(load, 60000)
  clock = setInterval(() => { hour.value = new Date().getHours() }, 60000)
})

onBeforeUnmount(() => {
  clearInterval(clock)
  document.documentElement.style.overflow = ''
  clearInterval(timer)
  clearTimeout(enterTimer)
})
</script>

<template>
  <div class="world">
    <div class="world__scene" :class="{ 'is-dim': open, 'is-entering': entering, 'is-night': night, 'is-dusk': dusk }">
      <!-- Небо и холмы -->
      <div class="w-sky" aria-hidden="true">
        <i class="w-cloud w-cloud--a" /><i class="w-cloud w-cloud--b" /><i class="w-cloud w-cloud--c" />
        <i :class="night ? 'w-moon' : 'w-sun'" />
        <i v-for="n in 26" :key="`s${n}`" class="w-star" :style="{ left: (n * 37) % 100 + '%', top: (n * 53) % 34 + '%', animationDelay: (n % 5) * -0.7 + 's' }" />
        <i v-for="[x, y, d] in BIRDS" :key="`b${x}`" class="w-bird" :style="{ left: x + '%', top: y + '%', animationDelay: -d + 's' }" />
      </div>
      <div class="w-hills" aria-hidden="true"><i /><i /><i /></div>
      <div class="w-ground" aria-hidden="true"><i class="w-path w-path--a" /><i class="w-path w-path--b" /></div>

      <!-- Декор: растёт и живёт сам по себе -->
      <div class="w-decor" aria-hidden="true">
        <i v-for="[x, y, k] in FLOWERS" :key="`f${x}`" class="w-flower" :class="`w-flower--${k}`" :style="{ left: x + '%', top: y + '%' }" />
        <i v-for="[x, y] in BUSHES" :key="`h${x}${y}`" class="w-bush" :style="{ left: x + '%', top: y + '%' }" />
        <i v-for="[x, y] in TREES" :key="`t${x}${y}`" class="w-tree" :style="{ left: x + '%', top: y + '%', '--s': 0.85 + ((x * 7) % 5) / 10 }"><b /></i>
        <i v-for="[x, y] in LAMPS" :key="`l${x}`" class="w-lamp" :style="{ left: x + '%', top: y + '%' }" />
        <i v-for="[x, y] in BUTTERFLIES" :key="`p${x}`" class="w-fly" :style="{ left: x + '%', top: y + '%', animationDelay: -x / 10 + 's' }" />
        <div class="w-river"><i class="w-bridge" /></div>
      </div>
      <div class="w-dark" aria-hidden="true" />

      <!-- HUD -->
      <header class="w-hud">
        <div class="w-title">
          <PixelSprite sprite="paw" :size="22" />
          <h1>Мир APEX</h1>
        </div>
        <div class="w-wallet">
          <span class="w-chip" title="ApexCoin">₳ {{ fmt(world?.coins) }}</span>
          <span class="w-chip w-chip--ruble" title="Рубли: падают только в Рублёвых копях, тратятся на ускорения и привилегии. Не передаются.">₽ {{ fmt(world?.rubles) }}</span>
        </div>
      </header>

      <p v-if="error" class="w-error">{{ error }}</p>

      <!-- Постройки: стоят на земле, дверь открывается при входе -->
      <button
          v-for="place in PLACES"
          :key="place.id"
          type="button"
          class="bld"
          :class="[`bld--${place.kind}`, { 'is-locked': locked(place), 'is-entering': entering === place.id }]"
          :style="{ left: place.x + '%', top: place.y + '%' }"
          :title="place.hint"
          @click="enter(place)"
      >
        <span class="bld__art" aria-hidden="true"><i class="bld__roof" /><i class="bld__wall" /><i class="bld__door" /><i class="bld__deco" /></span>
        <span class="bld__name">{{ place.name }}</span>
        <b v-if="badge(place) > 0" class="bld__badge">{{ badge(place) }}</b>
      </button>

      <!-- Питомцы бродят по дорожке -->
      <div class="w-herd" aria-hidden="true">
        <span v-for="(pet, i) in world?.pets ?? []" :key="pet.inventory_id" class="w-walker" :style="{ '--i': i }">
          <PetFigure :species="pet.species" :traits="pet.traits" :size="40" />
        </span>
      </div>

      <p class="w-legend">Зайди в здание — у каждого свой интерьер. ₳ — ApexCoin, ₽ — Рубли, их дают только Рублёвые копи.</p>
    </div>

    <!-- Интерьер здания: стены, пол, декор — вместо вкладки -->
    <transition name="room">
      <div v-if="open" class="room" :class="`room--${open.kind}`" role="dialog" :aria-label="open.name" @keydown.esc="leave">
        <div class="room__doors" aria-hidden="true"><i /><i /></div>

        <header class="room__top">
          <button type="button" class="room__exit" @click="leave"><span aria-hidden="true">←</span> На улицу</button>
          <div class="room__sign">{{ open.name }}</div>
          <div class="room__wallet">
            <span class="w-chip">₳ {{ fmt(world?.coins) }}</span>
            <span class="w-chip w-chip--ruble">₽ {{ fmt(world?.rubles) }}</span>
          </div>
        </header>

        <div class="room__wall" aria-hidden="true"><i v-for="n in 6" :key="n" /></div>

        <div class="room__body">
          <component :is="open.view" :key="open.id" v-bind="open.props" />
        </div>

        <div class="room__floor" aria-hidden="true" />
      </div>
    </transition>
  </div>
</template>

<style scoped>
@import "./WorldView.css";
</style>
