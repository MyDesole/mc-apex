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
const MarketScene = defineAsyncComponent(() => import('@/game/MarketScene.vue'))
const AuctionView = defineAsyncComponent(() => import('@/views/shop/AuctionView.vue'))
const PetBag = defineAsyncComponent(() => import('@/components/pets/PetBag.vue'))

/**
 * Постройки. x, y — точка на земле (в процентах сцены), где здание стоит;
 * only — какая вкладка механики живёт внутри (остальные скрыты).
 */
const PLACES = [
  { id: 'gear', name: 'Кузница', kind: 'forge', x: 10, y: 60, view: ForgeScene, hint: 'Снаряжение питомцев' },
  { id: 'farm', name: 'Общая ферма', kind: 'barn', x: 26, y: 60, view: FarmView, props: { only: 'common' }, hint: 'Бронь и выкуп питомцев', badge: 'farm' },
  { id: 'miner', name: 'Шахта', kind: 'mine', x: 42, y: 60, view: MineScene, hint: 'Копай руду, зарабатывай ApexCoin' },
  { id: 'dungeon', name: 'Подземелья', kind: 'dungeon', x: 58, y: 60, view: DungeonView, hint: 'Отряд, боссы и Рубли', badge: 'dungeon' },
  { id: 'museum', name: 'Питомцедекс', kind: 'museum', x: 74, y: 60, view: MuseumScene, hint: 'Коллекция видов и окрасов' },
  { id: 'hall', name: 'Доска почёта', kind: 'hall', x: 90, y: 60, view: HallScene, hint: 'Лучшие питомцы сервера' },
  { id: 'pets', name: 'Дом питомцев', kind: 'house', x: 7, y: 92, view: HouseScene, hint: 'Уход, уровни, перки и трюки' },
  { id: 'personal', name: 'Личная ферма', kind: 'gate', x: 21, y: 92, view: FarmView, props: { only: 'personal' }, hint: 'Грядки, Кладовка и горн', badge: 'personal' },
  { id: 'bag', name: 'Склад питомцев', kind: 'store', x: 35, y: 92, view: PetBag, hint: 'Инвентарь питомцев: снаряжение, материалы, ключи' },
  { id: 'expeditions', name: 'Экспедиции', kind: 'camp', x: 49, y: 92, view: ExpeditionScene, hint: 'Поход за монетами и ключами', badge: 'expeditions' },
  { id: 'arena', name: 'Арена', kind: 'arena', x: 63, y: 92, view: ArenaScene, hint: 'Пошаговые бои питомцев' },
  { id: 'auction', name: 'Аукцион', kind: 'stall', x: 77, y: 92, view: AuctionView, hint: 'Торги питомцами' },
  { id: 'market', name: 'Рынок', kind: 'market', x: 91, y: 92, view: MarketScene, hint: 'Продать и купить материалы и снаряжение' },
]

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

watch(open, (place) => { document.documentElement.style.overflow = place ? 'hidden' : '' }, { immediate: true })

onMounted(() => {
  load()
  timer = setInterval(load, 60000)
})

onBeforeUnmount(() => {
  document.documentElement.style.overflow = ''
  clearInterval(timer)
  clearTimeout(enterTimer)
})
</script>

<template>
  <div class="world">
    <div class="world__scene" :class="{ 'is-dim': open, 'is-entering': entering }">
      <!-- Небо и холмы -->
      <div class="w-sky" aria-hidden="true">
        <i class="w-cloud w-cloud--a" /><i class="w-cloud w-cloud--b" /><i class="w-cloud w-cloud--c" />
        <i class="w-sun" />
      </div>
      <div class="w-hills" aria-hidden="true"><i /><i /><i /></div>
      <div class="w-ground" aria-hidden="true"><i class="w-path w-path--a" /><i class="w-path w-path--b" /></div>

      <!-- HUD -->
      <header class="w-hud">
        <div class="w-title">
          <PixelSprite sprite="paw" :size="22" />
          <h1>Мир APEX</h1>
        </div>
        <div class="w-wallet">
          <span class="w-chip" title="ApexCoin">₳ {{ fmt(world?.coins) }}</span>
          <span class="w-chip w-chip--ruble" title="Рубли: награда подземелий и ферм, тратятся на ускорения. Не передаются.">₽ {{ fmt(world?.rubles) }}</span>
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

      <p class="w-legend">Зайди в здание — у каждого свой интерьер. ₳ — ApexCoin, ₽ — Рубли за подземелья и фермы.</p>
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
