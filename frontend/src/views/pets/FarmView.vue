<script setup>
/**
 * Фермы питомцев — игровая сцена, а не список.
 *
 * Общая ферма: поле с загонами, питомцы бродят и растут на глазах;
 * клик по питомцу открывает табличку с бронью и выкупом.
 * Личная ферма: грядки-клетки, амбар «Кладовка», стадо и загон для пары.
 * Спавн, рост и снятие броней считает сервер, здесь только показ.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/core/api.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import PageLoader from '@/components/core/PageLoader.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import CountdownText from '@/components/core/CountdownText.vue'
import AppIcon from '@/components/core/AppIcon.vue'
import { useNow } from '@/composables/core/useNow.js'
import { PET_RARITY } from '@/data/players/petTraits.js'

const route = useRoute()
const router = useRouter()
const now = useNow()

const SCENES = {
  common: 'Общая ферма',
  personal: 'Личная ферма',
}

/** Внутри здания карты-мира (WorldView) показана одна ферма без указателя на другую. */
const props = defineProps({ only: { type: String, default: '' } })

const scene = computed({
  get: () => props.only || (route.query.tab === 'personal' ? 'personal' : 'common'),
  set: (value) => router.replace({ query: { ...route.query, tab: value } }),
})

const farm = ref(null)
const personal = ref(null)
const error = ref('')
const notice = ref('')
const busy = ref(null)
const shortage = ref(0)
const selected = ref(null) // питомец с Общей фермы, открытый на табличке
const seedPanel = ref(false)

const fmt = (n) => Number(n).toLocaleString('ru-RU')
const shortName = (name) => String(name ?? '').replace('Питомец ', '').replace(/[«»]/g, '')
const tierColor = { common: '#9ca3af', uncommon: '#4ade80', rare: '#60a5fa', epic: '#c084fc', legendary: '#fbbf24' }
const tierName = { common: 'Обычный', uncommon: 'Необычный', rare: 'Редкий', epic: 'Эпический', legendary: 'Легендарный' }

function fail(e, fallback) {
  if (e.data?.missing) {
    shortage.value = e.data.missing
    error.value = ''
  } else {
    shortage.value = 0
    error.value = e.message || fallback
  }
}

async function loadCommon() {
  try {
    farm.value = await api.get('/farm')
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить ферму.'
  }
}

async function loadPersonal() {
  try {
    personal.value = await api.get('/farm/personal')
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить Личную ферму.'
  }
}

async function act(key, fn, fallback) {
  busy.value = key
  error.value = ''
  notice.value = ''
  shortage.value = 0
  try {
    await fn()
  } catch (e) {
    fail(e, fallback)
    // Отказ сервера — повод показать свежее состояние (питомца могли выкупить, бронь слететь)
    const message = error.value
    await Promise.all([loadCommon(), loadPersonal()])
    if (message) error.value = message
  } finally {
    busy.value = null
  }
}

/* ---------- Общая ферма ---------- */

const isReady = (pet) => new Date(pet.ready_at).getTime() <= now.value

/** Доля роста 0..1 — питомец на поле увеличивается по мере роста. */
const growth = (pet) => {
  const start = new Date(pet.spawned_at).getTime()
  const total = new Date(pet.ready_at).getTime() - start || 1
  return Math.min(1, Math.max(0, (now.value - start) / total))
}
const petScale = (pet) => 0.5 + growth(pet) * 0.5

const pens = computed(() => farm.value?.pets ?? [])
// Табличка следит за свежими данными: питомца могли выкупить или снять бронь
const current = computed(() => pens.value.find((p) => p.id === selected.value) ?? null)

function pen(pet, index) {
  // Каждый питомец гуляет по-своему: разный путь и темп
  return {
    '--walk': `${6 + (index * 7) % 5}s`,
    '--delay': `-${(index * 1.7) % 6}s`,
    '--range': `${10 + (index * 5) % 14}px`,
    '--scale': petScale(pet),
  }
}

async function reserve(pet) {
  const ok = await confirmDialog(
      `Задаток ${fmt(pet.deposit)} ApexCoin. Питомец ждёт тебя ${farm.value.hold_hours} ч; не выкупишь вовремя — бронь слетит, а задаток сгорит.`,
      { title: `Забронировать: ${pet.name}`, confirmText: 'Забронировать' },
  )
  if (!ok) return
  await act(`r${pet.id}`, async () => {
    farm.value = await api.post(`/farm/${pet.id}/reserve`)
    notice.value = 'Питомец забронирован — загон закрыт для других.'
  }, 'Не удалось забронировать.')
}

async function buy(pet) {
  const ok = await confirmDialog(
      `${pet.name} — к оплате ${fmt(pet.to_pay)} ApexCoin.`,
      { title: 'Выкупить питомца', confirmText: 'Выкупить' },
  )
  if (!ok) return
  await act(`b${pet.id}`, async () => {
    const result = await api.post(`/farm/${pet.id}/buy`)
    farm.value = result.farm
    selected.value = null
    notice.value = `${result.pet.name} теперь с тобой. Загляни в «Мои питомцы».`
    await loadPersonal()
  }, 'Не удалось выкупить.')
}

/* ---------- Личная ферма ---------- */

const seedTiers = computed(() => {
  const groups = {}
  for (const seed of personal.value?.seeds ?? []) (groups[seed.tier] ??= []).push(seed)
  return groups
})

const emptyPlots = computed(() => Math.max(0, (personal.value?.plot_count ?? 0) - (personal.value?.plots.length ?? 0)))

async function unlock() {
  const ok = await confirmDialog(
      `Личная ферма стоит ${fmt(personal.value.price)} ApexCoin. Откроются грядки редких и легендарных питомцев, Кладовка и скрещивание.`,
      { title: 'Открыть Личную ферму', confirmText: 'Открыть' },
  )
  if (!ok) return
  await act('unlock', async () => {
    personal.value = await api.post('/farm/personal/unlock')
    notice.value = 'Личная ферма открыта.'
  }, 'Не удалось открыть ферму.')
}

async function plant(seed) {
  await act(`p${seed.species}`, async () => {
    personal.value = await api.post('/farm/personal/plant', { species: seed.species })
    seedPanel.value = false
    notice.value = `${seed.name} посажен — вырастет через ${seed.hours} ч.`
  }, 'Не удалось посадить.')
}

async function harvest(plot) {
  if (!plot.ready) return
  await act(`h${plot.id}`, async () => {
    const result = await api.post(`/farm/personal/plots/${plot.id}/harvest`)
    notice.value = `${result.pet.name} вырос и теперь с тобой.`
    await loadPersonal()
  }, 'Не удалось забрать.')
}

/* Пара для спаривания и скрещивания */
const parent1 = ref(null)
const parent2 = ref(null)

const herd = computed(() => personal.value?.pets ?? [])
const petById = (id) => herd.value.find((p) => p.inventory_id === id)

function pick(pet) {
  if (pet.busy) return
  if (parent1.value === pet.inventory_id) parent1.value = null
  else if (parent2.value === pet.inventory_id) parent2.value = null
  else if (!parent1.value) parent1.value = pet.inventory_id
  else parent2.value = pet.inventory_id
}

const pairReady = computed(() => parent1.value && parent2.value && parent1.value !== parent2.value)
const sameTier = computed(() => pairReady.value && petById(parent1.value)?.tier === petById(parent2.value)?.tier)
const nextTier = computed(() => {
  const order = ['common', 'uncommon', 'rare', 'epic', 'legendary']
  const tier = petById(parent1.value)?.tier
  return tier ? order[order.indexOf(tier) + 1] : null
})

function resetPair() {
  parent1.value = null
  parent2.value = null
}

async function mate() {
  const a = petById(parent1.value)
  const b = petById(parent2.value)
  const l = personal.value.limits
  const ok = await confirmDialog(
      `${a.name} и ${b.name} уйдут в Кладовку на ${l.mating_hours} ч и вернутся с новым питомцем. Цена ${fmt(l.mating_cost)} ApexCoin и ${l.energy_cost} энергии у каждого.`,
      { title: 'Спаривание', confirmText: 'Отправить' },
  )
  if (!ok) return
  await act('mate', async () => {
    await api.post('/farm/personal/mating', { parent1: parent1.value, parent2: parent2.value })
    resetPair()
    notice.value = 'Питомцы зашли в амбар.'
    await loadPersonal()
  }, 'Не удалось отправить.')
}

/** Цена ускорения — как на сервере: Рубли за каждый начатый час ожидания. */
const speedCost = (iso) => Math.max(1, Math.ceil((new Date(iso).getTime() - now.value) / 3600000)) * (personal.value?.speedup_per_hour ?? 2)

async function speedUp(kind, id, iso) {
  const cost = speedCost(iso)
  const ok = await confirmDialog(
      `Ускорить за ${cost} Рублей? Ждать больше не придётся.`,
      { title: 'Ускорение', confirmText: 'Ускорить' },
  )
  if (!ok) return
  await act(`s${kind}${id}`, async () => {
    const path = kind === 'plot' ? `/farm/personal/plots/${id}/speedup` : `/farm/personal/mating/${id}/speedup`
    const result = await api.post(path)
    notice.value = `Ускорено: −${result.spent} ₽.`
    await loadPersonal()
  }, 'Не удалось ускорить.')
}

async function claimMating(pair) {
  await act(`c${pair.id}`, async () => {
    const result = await api.post(`/farm/personal/mating/${pair.id}/claim`)
    notice.value = `Родился ${result.name}!`
    await loadPersonal()
  }, 'Не удалось забрать.')
}

async function cross() {
  const a = petById(parent1.value)
  const b = petById(parent2.value)
  const ok = await confirmDialog(
      `${a.name} и ${b.name} сгорят навсегда — вместо них появится один питомец ступенью выше («${tierName[nextTier.value]}»). Цена ${fmt(personal.value.limits.cross_cost)} ApexCoin.`,
      { title: 'Скрещивание', confirmText: 'Скрестить' },
  )
  if (!ok) return
  await act('cross', async () => {
    const result = await api.post('/farm/personal/cross', { parent1: parent1.value, parent2: parent2.value })
    resetPair()
    notice.value = `Получился ${result.name}!`
    await loadPersonal()
  }, 'Не удалось скрестить.')
}

watch(scene, () => {
  error.value = ''
  notice.value = ''
  selected.value = null
  seedPanel.value = false
})

onMounted(() => Promise.all([loadCommon(), loadPersonal()]))
</script>

<template>
  <div class="farm" :class="`farm--${scene}`">
    <!-- Небо, облака и вывеска -->
    <div class="farm__sky" aria-hidden="true">
      <i class="farm__cloud farm__cloud--a" />
      <i class="farm__cloud farm__cloud--b" />
      <i class="farm__sun" />
    </div>

    <header class="farm__bar">
      <div class="farm__sign">
        <PixelSprite sprite="paw" :size="22" />
        <h1>{{ SCENES[scene] }}</h1>
      </div>

      <div class="farm__hud">
        <span class="hud-chip"><AppIcon name="coin" :size="14" />{{ fmt(scene === 'common' ? (farm?.balance ?? 0) : (personal?.balance ?? 0)) }}</span>
        <span v-if="scene === 'personal' && personal?.unlocked" class="hud-chip hud-chip--ruble" title="Рубли: награда подземелий и ферм, тратятся на ускорения">₽ {{ fmt(personal.rubles) }}</span>
        <template v-if="scene === 'common' && farm">
          <span class="hud-chip">Брони {{ farm.reservations }}/{{ farm.max_reservations }}</span>
          <span class="hud-chip">Покупок {{ farm.buys_left }}/{{ farm.buys_per_day }}</span>
        </template>
        
      </div>
    </header>

    <p v-if="error" class="farm__msg farm__msg--error">{{ error }}</p>
    <p v-if="shortage" class="farm__msg farm__msg--error">
      Не хватает {{ fmt(shortage) }} ApexCoin. <RouterLink to="/miner">Накопай в шахте</RouterLink>
    </p>
    <p v-if="notice" class="farm__msg">{{ notice }}</p>

    <!-- ================= Общая ферма ================= -->
    <section v-if="scene === 'common'" class="field">
      <PageLoader v-if="!farm" />

      <template v-else>
        <div class="field__grass">
          <p v-if="!pens.length" class="field__empty">Загоны пусты — скоро появятся новые питомцы.</p>

          <button
              v-for="(pet, i) in pens"
              :key="pet.id"
              type="button"
              class="pen"
              :class="{
              'pen--ready': isReady(pet) && !pet.reserved,
              'pen--taken': pet.reserved && !pet.mine,
              'pen--mine': pet.mine,
              'is-open': selected === pet.id,
            }"
              :style="{ ...pen(pet, i), '--tier': tierColor[pet.tier] }"
              @click="selected = selected === pet.id ? null : pet.id"
          >
            <span class="pen__fence" aria-hidden="true" />
            <span class="pen__walker">
              <PetFigure :species="pet.species" :traits="pet.traits" :size="62" />
            </span>
            <span class="pen__shadow" aria-hidden="true" />
            <i v-if="isReady(pet) && !pet.reserved" class="pen__spark" aria-hidden="true" />
            <span class="pen__plank">
              <b>{{ pet.name.replace('Питомец ', '').replace(/[«»]/g, '') }}</b>
              <small v-if="pet.reserved">{{ pet.mine ? 'Твоя бронь' : 'Занят' }}</small>
              <small v-else-if="!isReady(pet)"><CountdownText :to="pet.ready_at" /></small>
              <small v-else>{{ fmt(pet.price) }} ₳</small>
            </span>
            <span v-if="!isReady(pet)" class="pen__grow"><i :style="{ width: growth(pet) * 100 + '%' }" /></span>
          </button>
        </div>

        <!-- Табличка выбранного питомца -->
        <transition name="board">
          <aside v-if="current" class="board">
            <button type="button" class="board__x" aria-label="Закрыть" @click="selected = null">×</button>
            <div class="board__stage"><PetFigure :species="current.species" :traits="current.traits" :size="96" /></div>
            <div class="board__text">
              <h2>{{ current.name }}</h2>
              <p :style="{ color: tierColor[current.tier] }">{{ current.tier_name }}</p>
              <p v-if="current.traits" :style="{ color: PET_RARITY[current.traits.rarity]?.color }">
                {{ PET_RARITY[current.traits.rarity]?.label ?? current.traits.rarity }} окрас
              </p>
              <p class="board__time">
                <template v-if="current.reserved && !current.mine">Забронирован другим игроком</template>
                <template v-else-if="!isReady(current)">Вырастет через <CountdownText :to="current.ready_at" /></template>
                <template v-else-if="current.mine">Бронь слетит через <CountdownText :to="current.reserved_until" done="сейчас" /></template>
                <template v-else>Вырос и ждёт хозяина</template>
              </p>
            </div>
            <div class="board__act">
              <button
                  v-if="!current.mine"
                  type="button"
                  class="game-btn"
                  :disabled="current.reserved || busy === `r${current.id}` || farm.reservations >= farm.max_reservations"
                  @click="reserve(current)"
              >Бронь · {{ fmt(current.deposit) }} ₳</button>
              <button
                  type="button"
                  class="game-btn game-btn--go"
                  :disabled="(current.reserved && !current.mine) || !isReady(current) || busy === `b${current.id}`"
                  @click="buy(current)"
              >{{ current.mine ? 'Доплатить' : 'Выкупить' }} · {{ fmt(current.to_pay) }} ₳</button>
            </div>
          </aside>
        </transition>

        <p class="field__hint">
          Нажми на питомца в загоне. Бронь — задаток {{ Math.round(farm.deposit_share * 100) }}%, держится {{ farm.hold_hours }} ч,
          потом слетает вместе с задатком. Выросшего можно купить сразу.
        </p>
      </template>

      <button v-if="!only" type="button" class="signpost" @click="scene = 'personal'">
        <span>Личная ферма</span><i aria-hidden="true">→</i>
      </button>
    </section>

    <!-- ================= Личная ферма ================= -->
    <section v-else class="field field--personal">
      <PageLoader v-if="!personal" />

      <!-- Запертые ворота -->
      <div v-else-if="!personal.unlocked" class="gate">
        <div class="gate__door" aria-hidden="true"><i /><i /></div>
        <div class="gate__sign">
          <h2>Личная ферма</h2>
          <p>Три грядки для редких, эпических и легендарных питомцев, амбар-Кладовка для спаривания и горн скрещивания.</p>
          <button type="button" class="game-btn game-btn--go" :disabled="busy === 'unlock'" @click="unlock">
            Купить участок · {{ fmt(personal.price) }} ₳
          </button>
        </div>
      </div>

      <template v-else>
        <div class="field__grass field__grass--personal">
          <!-- Амбар -->
          <div class="barn">
            <div class="barn__roof" aria-hidden="true" />
            <div class="barn__wall">
              <span class="barn__title">Кладовка</span>
              <div class="barn__doors">
                <div v-for="n in personal.limits.max_in_storage" :key="n" class="barn__door"
                     :class="{ 'is-busy': personal.storage[n - 1], 'is-ready': personal.storage[n - 1]?.ready }">
                  <template v-if="personal.storage[n - 1]">
                    <template v-if="!personal.storage[n - 1].ready">
                      <small><CountdownText :to="personal.storage[n - 1].ends_at" /></small>
                      <button type="button" class="game-btn game-btn--sm game-btn--ruble"
                              :disabled="busy === `smating${personal.storage[n - 1].id}`"
                              @click="speedUp('mating', personal.storage[n - 1].id, personal.storage[n - 1].ends_at)">Ускорить ₽{{ speedCost(personal.storage[n - 1].ends_at) }}</button>
                    </template>
                    <button v-else type="button" class="game-btn game-btn--go game-btn--sm"
                            :disabled="busy === `c${personal.storage[n - 1].id}`" @click="claimMating(personal.storage[n - 1])">Забрать</button>
                  </template>
                  <small v-else>Пусто</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Грядки -->
          <div class="plots">
            <button
                v-for="plot in personal.plots"
                :key="plot.id"
                type="button"
                class="plot"
                :class="{ 'plot--ready': plot.ready }"
                :style="{ '--tier': tierColor[plot.tier] }"
                @click="harvest(plot)"
            >
              <span class="plot__pet"><PetFigure :species="plot.species" :traits="null" :size="58" /></span>
              <span class="plot__label">
                <b>{{ plot.name.replace('Питомец ', '').replace(/[«»]/g, '') }}</b>
                <small v-if="!plot.ready"><CountdownText :to="plot.ready_at" /></small>
                <small v-else>Забрать</small>
                <span v-if="!plot.ready" class="plot__speed" role="button" tabindex="0"
                      @click.stop="speedUp('plot', plot.id, plot.ready_at)"
                      @keydown.enter.stop="speedUp('plot', plot.id, plot.ready_at)">Ускорить ₽{{ speedCost(plot.ready_at) }}</span>
              </span>
            </button>
            <button v-for="n in emptyPlots" :key="`e${n}`" type="button" class="plot plot--empty" @click="seedPanel = !seedPanel">
              <span class="plot__plus">+</span>
              <span class="plot__label"><small>Посадить</small></span>
            </button>
          </div>
        </div>

        <!-- Мешок семян -->
        <transition name="board">
          <aside v-if="seedPanel" class="board board--seeds">
            <button type="button" class="board__x" aria-label="Закрыть" @click="seedPanel = false">×</button>
            <h2>Семена</h2>
            <div v-for="(seeds, tier) in seedTiers" :key="tier" class="seed-row">
              <b :style="{ color: tierColor[tier] }">{{ tierName[tier] }}</b>
              <button v-for="seed in seeds" :key="seed.species" type="button" class="game-btn game-btn--sm"
                      :disabled="busy === `p${seed.species}` || emptyPlots === 0" @click="plant(seed)">
                {{ seed.name.replace('Питомец ', '').replace(/[«»]/g, '') }} · {{ fmt(seed.price) }} ₳ · {{ seed.hours }} ч
              </button>
            </div>
          </aside>
        </transition>

        <!-- Стадо и загон пары -->
        <div class="herd">
          <div class="herd__pair">
            <div class="slot" :class="{ 'is-filled': parent1 }">
              <PetFigure v-if="parent1" :species="petById(parent1).species" :traits="petById(parent1).traits" :size="58" />
              <small v-else>Родитель</small>
            </div>
            <i class="herd__heart" aria-hidden="true" />
            <div class="slot" :class="{ 'is-filled': parent2 }">
              <PetFigure v-if="parent2" :species="petById(parent2).species" :traits="petById(parent2).traits" :size="58" />
              <small v-else>Родитель</small>
            </div>

            <div class="herd__act">
              <button type="button" class="game-btn game-btn--go" :disabled="!pairReady || busy === 'mate'" @click="mate">
                В Кладовку · {{ fmt(personal.limits.mating_cost) }} ₳
              </button>
              <button type="button" class="game-btn" :disabled="!sameTier || !nextTier || busy === 'cross'" @click="cross">
                Горн<template v-if="sameTier && nextTier"> → {{ tierName[nextTier] }}</template> · {{ fmt(personal.limits.cross_cost) }} ₳
              </button>
              <button v-if="parent1 || parent2" type="button" class="game-btn game-btn--ghost" @click="resetPair">Сбросить</button>
            </div>
            <p class="herd__rule">
              Спаривание: {{ personal.limits.mating_hours }} ч, −{{ personal.limits.energy_cost }} энергии, потом отдых.
              Горн: два питомца одной ступени сгорают, выходит один на ступень выше.
            </p>
          </div>

          <p v-if="!herd.length" class="field__empty">В стаде пусто — выкупи питомцев на Общей ферме.</p>
          <div class="herd__row">
            <button
                v-for="pet in herd"
                :key="pet.inventory_id"
                type="button"
                class="critter"
                :class="{ 'is-picked': pet.inventory_id === parent1 || pet.inventory_id === parent2, 'is-busy': pet.busy }"
                :title="pet.busy || ''"
                @click="pick(pet)"
            >
              <PetFigure :species="pet.species" :traits="pet.traits" :size="46" />
              <b>{{ shortName(pet.name) }}</b>
              <small :style="{ color: tierColor[pet.tier] }">{{ pet.tier_name ?? '—' }} · ур. {{ pet.level }}</small>
              <span class="critter__energy"><i :style="{ width: (pet.energy / personal.limits.energy_max) * 100 + '%' }" /></span>
            </button>
          </div>
        </div>
      </template>

      <button v-if="!only" type="button" class="signpost signpost--back" @click="scene = 'common'">
        <i aria-hidden="true">←</i><span>Общая ферма</span>
      </button>
    </section>
  </div>
</template>

<style scoped>
@import "./FarmView.css";
</style>
