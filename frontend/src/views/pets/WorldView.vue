<script setup>
/**
 * Карта-мир: вся игровая механика на одной сцене. Постройки — это
 * механики (ферма, шахта, арена, подземелья…); клик открывает панель
 * поверх карты, а питомцы игрока тем временем бродят по дорожке.
 * Сводка для значков приходит с сервера (GET /api/world).
 */
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const route = useRoute()
const router = useRouter()

const FarmView = defineAsyncComponent(() => import('@/views/pets/FarmView.vue'))
const PetsView = defineAsyncComponent(() => import('@/views/pets/PetsView.vue'))
const DungeonView = defineAsyncComponent(() => import('@/views/pets/DungeonView.vue'))
const MinerView = defineAsyncComponent(() => import('@/views/games/MinerView.vue'))
const AuctionView = defineAsyncComponent(() => import('@/views/shop/AuctionView.vue'))
const CasesView = defineAsyncComponent(() => import('@/views/shop/CasesView.vue'))

/** Постройки: вид, где стоят на карте, что открывают. */
const PLACES = [
  { id: 'pets', name: 'Дом питомцев', kind: 'house', x: 7, y: 50, view: PetsView, tab: 'pets', hint: 'Уход, уровни, перки и трюки' },
  { id: 'gear', name: 'Кузница', kind: 'forge', x: 14, y: 17, view: PetsView, tab: 'gear', hint: 'Экипировка питомцев' },
  { id: 'farm', name: 'Общая ферма', kind: 'barn', x: 26, y: 33, view: FarmView, tab: 'common', hint: 'Бронь и выкуп питомцев', badge: 'farm' },
  { id: 'personal', name: 'Личная ферма', kind: 'gate', x: 43, y: 50, view: FarmView, tab: 'personal', hint: 'Грядки, Кладовка и горн', badge: 'personal' },
  { id: 'miner', name: 'Шахта', kind: 'mine', x: 61, y: 14, view: MinerView, hint: 'Копай руду, зарабатывай ApexCoin' },
  { id: 'dungeon', name: 'Подземелья', kind: 'dungeon', x: 80, y: 30, view: DungeonView, hint: 'Отряд, боссы и Рубли', badge: 'dungeon' },
  { id: 'arena', name: 'Арена', kind: 'arena', x: 74, y: 60, view: PetsView, tab: 'arena', hint: 'Пошаговые бои питомцев' },
  { id: 'expeditions', name: 'Экспедиции', kind: 'camp', x: 55, y: 70, view: PetsView, tab: 'expeditions', hint: 'Поход за монетами и ключами', badge: 'expeditions' },
  { id: 'auction', name: 'Аукцион', kind: 'stall', x: 30, y: 72, view: AuctionView, hint: 'Торги питомцами' },
  { id: 'cases', name: 'Сундуки', kind: 'chest', x: 90, y: 66, view: CasesView, hint: 'Косметика и ключи' },
]

const world = ref(null)
const error = ref('')
let timer = null

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

function enter(place) {
  // Вкладку внутри панели выбирает сама механика, поэтому кладём её в query
  router.replace({ query: { at: place.id, ...(place.tab ? { tab: place.tab } : {}) } })
}

function leave() {
  router.replace({ query: {} })
  // Закрыли панель — значки могли измениться
  load()
}

const badge = (place) => (place.badge ? (world.value?.badges?.[place.badge] ?? 0) : 0)
const locked = (place) => place.id === 'personal' && world.value && !world.value.personal_unlocked

onMounted(() => {
  load()
  timer = setInterval(load, 60000)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="world">
    <div class="world__scene" :class="{ 'is-dim': open }">
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
          <RouterLink to="/pets" class="w-chip w-chip--link">Питомцы</RouterLink>
        </div>
      </header>

      <p v-if="error" class="w-error">{{ error }}</p>

      <!-- Постройки -->
      <button
          v-for="place in PLACES"
          :key="place.id"
          type="button"
          class="bld"
          :class="[`bld--${place.kind}`, { 'is-locked': locked(place), 'has-badge': badge(place) > 0 }]"
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

      <p class="w-legend">Нажми на постройку. ₳ — ApexCoin, ₽ — Рубли за подземелья и фермы.</p>
    </div>

    <!-- Панель механики поверх карты -->
    <transition name="w-panel">
      <div v-if="open" class="panel" role="dialog" :aria-label="open.name" @keydown.esc="leave">
        <div class="panel__frame">
          <header class="panel__bar">
            <b>{{ open.name }}</b>
            <span class="panel__nav">
              <button v-for="p in PLACES" :key="p.id" type="button" class="panel__tab" :class="{ 'is-on': p.id === open.id }" :title="p.name" @click="enter(p)">{{ p.name }}</button>
            </span>
            <button type="button" class="panel__x" aria-label="Закрыть" @click="leave">×</button>
          </header>
          <div class="panel__body">
            <component :is="open.view" :key="open.id" />
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
@import "./WorldView.css";
</style>
