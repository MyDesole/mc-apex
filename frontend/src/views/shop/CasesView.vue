<script setup>
/**
 * Кейсы с косметикой: бесплатный сундук раз в день и платные с
 * повышенными шансами. Исход решает сервер; здесь — рулетка, которая
 * докручивается до выигрыша, и карточка награды.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { casesApi } from '@/services/shop/cases.js'
import { RARITY_LABELS, RARITY_COLORS, TYPE_LABELS, formatCoins } from '@/data/wallet/economy.js'
import { playCaseTick, playCaseWin } from '@/utils/sound.js'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'

const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const data = ref(null)

const selectedSlug = ref('daily')
const opening = ref(false)
const openError = ref('')

const reel = ref([])
const reelOffset = ref(0)
const reelDuration = ref(0)
const reelEl = ref(null)
const trackEl = ref(null)

const result = ref(null)
const showResult = ref(false)

const now = ref(Date.now())
let clock = null
let tickFrame = null

const CARD_WIDTH = 132
const CARD_GAP = 10
const SPIN_MS = 6200

const OUTCOME_LABELS = {
  nothing: 'Пусто',
  coins: 'Монеты',
  ...RARITY_LABELS,
}

const OUTCOME_COLORS = {
  nothing: '#4b4757',
  coins: '#f59e0b',
  ...RARITY_COLORS,
}

const cases = computed(() => data.value?.cases ?? [])
const selected = computed(() => cases.value.find((c) => c.slug === selectedSlug.value) ?? cases.value[0])
const balance = computed(() => auth.user?.apex_coins ?? data.value?.balance ?? 0)

function chanceList(caseData) {
  return Object.entries(caseData.chances)
      .filter(([, value]) => value > 0)
      .map(([outcome, value]) => ({ outcome, value, color: OUTCOME_COLORS[outcome], label: OUTCOME_LABELS[outcome] }))
}

function countdown(iso) {
  if (!iso) return ''
  const left = Math.max(0, new Date(iso).getTime() - now.value)
  const h = String(Math.floor(left / 3600000)).padStart(2, '0')
  const m = String(Math.floor((left % 3600000) / 60000)).padStart(2, '0')
  const s = String(Math.floor((left % 60000) / 1000)).padStart(2, '0')
  return `${h}:${m}:${s}`
}

function canOpen(caseData) {
  if (!auth.isAuthenticated || opening.value) return false
  if (caseData.free_daily) return caseData.available
  return balance.value >= caseData.price
}

function openLabel(caseData) {
  if (!auth.isAuthenticated) return 'Войдите, чтобы открыть'
  if (opening.value) return 'Открываем…'
  if (caseData.free_daily) return caseData.available ? 'Открыть бесплатно' : `Новый через ${countdown(caseData.next_free_at)}`
  if (balance.value < caseData.price) return `Не хватает ₳ ${formatCoins(caseData.price - balance.value)}`
  return `Открыть за ₳ ${formatCoins(caseData.price)}`
}

/** Лента до прокрутки: содержимое кейса вперемешку, чтобы не пустовала. */
function idleReel(caseData) {
  const items = caseData?.preview ?? []
  const out = []
  for (let i = 0; i < 14; i++) {
    const item = items[(i * 7) % Math.max(1, items.length)]
    out.push(item ? { outcome: item.rarity, rarity: item.rarity, item, coins: 0 } : { outcome: 'nothing', coins: 0 })
  }
  return out
}

function selectCase(slug) {
  if (opening.value) return
  selectedSlug.value = slug
  reelDuration.value = 0
  reelOffset.value = 0
  reel.value = idleReel(selected.value)
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    data.value = await casesApi.list()
    reel.value = idleReel(selected.value)
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить кейсы.'
  } finally {
    loading.value = false
  }
}

/** Щелчки под указателем: считаем, сколько карточек прошло. */
function watchTicks() {
  let lastIndex = -1

  const step = () => {
    if (!trackEl.value || !reelEl.value) return
    const matrix = new DOMMatrixReadOnly(getComputedStyle(trackEl.value).transform)
    const shifted = -matrix.m41 + reelEl.value.clientWidth / 2
    const index = Math.floor(shifted / (CARD_WIDTH + CARD_GAP))

    if (index !== lastIndex) {
      lastIndex = index
      playCaseTick()
    }

    tickFrame = requestAnimationFrame(step)
  }

  tickFrame = requestAnimationFrame(step)
}

async function openCase() {
  const caseData = selected.value
  if (!caseData || !canOpen(caseData)) return

  opening.value = true
  openError.value = ''
  showResult.value = false

  try {
    const response = await casesApi.open(caseData.slug)

    // Сбрасываем ленту в начало без анимации, затем крутим до выигрыша
    reelDuration.value = 0
    reelOffset.value = 0
    reel.value = response.reel
    await nextTick()
    trackEl.value?.getBoundingClientRect()

    const viewport = reelEl.value?.clientWidth ?? 800
    // Берём реальное положение выигрышной карточки, а не формулу:
    // так рулетка не промахнётся, даже если вёрстка карточки поменяется
    const winCard = trackEl.value?.children[response.win_index]
    const cardLeft = winCard?.offsetLeft ?? response.win_index * (CARD_WIDTH + CARD_GAP)
    const cardWidth = winCard?.offsetWidth ?? CARD_WIDTH
    // Небольшой разброс внутри карточки — каждая прокрутка выглядит по-своему
    const jitter = Math.round((Math.random() - 0.5) * (cardWidth * 0.7))
    const target = cardLeft + cardWidth / 2 - viewport / 2 + jitter

    requestAnimationFrame(() => {
      reelDuration.value = SPIN_MS
      reelOffset.value = -target
      watchTicks()
    })

    setTimeout(() => {
      cancelAnimationFrame(tickFrame)
      result.value = response
      showResult.value = true
      playCaseWin(response.prize.outcome)

      if (auth.user) auth.user.apex_coins = response.balance
      data.value.balance = response.balance

      Object.assign(caseData, response.case)
      opening.value = false
      refreshHistory()
    }, SPIN_MS + 250)
  } catch (e) {
    openError.value = e.message || 'Не удалось открыть кейс.'
    opening.value = false
  }
}

async function refreshHistory() {
  try {
    const fresh = await casesApi.list()
    data.value.history = fresh.history
  } catch {
    // история — не главное
  }
}

function closeResult() {
  showResult.value = false
}

function again() {
  showResult.value = false
  openCase()
}

const prize = computed(() => result.value?.prize)
const prizeColor = computed(() => OUTCOME_COLORS[prize.value?.outcome] ?? '#a78bfa')

function timeAgo(value) {
  const diff = (Date.now() - new Date(value).getTime()) / 1000
  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`
  return `${Math.floor(diff / 86400)} дн назад`
}

onMounted(() => {
  load()
  clock = setInterval(() => { now.value = Date.now() }, 1000)
})

onUnmounted(() => {
  clearInterval(clock)
  cancelAnimationFrame(tickFrame)
})
</script>

<template>
  <div class="cases-page">
    <!-- Шапка -->
    <header class="cases-hero">
      <div class="cases-hero__glow"></div>
      <div class="cases-hero__text">
        <span class="cases-hero__kicker">Магазин · Кейсы</span>
        <h1>Сундуки с косметикой</h1>
        <p>
          Каждый день — бесплатный сундук. Платные дают больше шансов на редкое,
          эпическое и легендарное. Повторы превращаются в ApexCoin.
        </p>
      </div>

      <div class="cases-hero__side">
        <div v-if="auth.isAuthenticated" class="cases-balance">
          <span>Баланс</span>
          <strong>₳ {{ formatCoins(balance) }}</strong>
        </div>
        <RouterLink to="/shop" class="cases-back">← В магазин</RouterLink>
      </div>
    </header>

    <div v-if="loading" class="cases-state">Достаём сундуки…</div>
    <div v-else-if="error" class="cases-state cases-state--error">{{ error }}</div>

    <template v-else>
      <!-- Выбор сундука -->
      <section class="chest-grid">
        <button
            v-for="caseData in cases"
            :key="caseData.slug"
            type="button"
            class="chest-card"
            :class="[`chest-card--${caseData.theme}`, { 'chest-card--active': caseData.slug === selected?.slug }]"
            @click="selectCase(caseData.slug)"
        >
          <span v-if="caseData.free_daily && caseData.available" class="chest-card__ribbon">Бесплатно</span>

          <span class="chest" :class="`chest--${caseData.theme}`" aria-hidden="true">
            <span class="chest__shine"></span>
            <span class="chest__lid"></span>
            <span class="chest__body"></span>
            <span class="chest__lock"></span>
          </span>

          <span class="chest-card__name">{{ caseData.name }}</span>
          <span class="chest-card__price">
            <template v-if="caseData.free_daily">
              {{ caseData.available ? 'Бесплатно сегодня' : `через ${countdown(caseData.next_free_at)}` }}
            </template>
            <template v-else>₳ {{ formatCoins(caseData.price) }}</template>
          </span>

          <span class="chance-bar">
            <i
                v-for="chance in chanceList(caseData)"
                :key="chance.outcome"
                :style="{ width: `${chance.value}%`, background: chance.color }"
                :title="`${chance.label}: ${chance.value}%`"
            ></i>
          </span>
        </button>
      </section>

      <!-- Рулетка -->
      <section v-if="selected" class="opener" :class="`opener--${selected.theme}`">
        <div class="opener__head">
          <div>
            <h2>{{ selected.name }}</h2>
            <p>{{ selected.description }}</p>
          </div>

          <div v-if="selected.pity_left" class="pity">
            <span>Гарантия эпика</span>
            <strong>через {{ selected.pity_left }}</strong>
            <span class="pity__bar">
              <i :style="{ width: `${(10 - selected.pity_left) * 10}%` }"></i>
            </span>
          </div>
        </div>

        <div ref="reelEl" class="reel">
          <div class="reel__marker"></div>
          <div class="reel__fade reel__fade--left"></div>
          <div class="reel__fade reel__fade--right"></div>

          <div
              ref="trackEl"
              class="reel__track"
              :style="{
                transform: `translateX(${reelOffset}px)`,
                transitionDuration: `${reelDuration}ms`,
              }"
          >
            <div
                v-for="(cell, index) in reel"
                :key="index"
                class="reel-card"
                :class="`reel-card--${cell.outcome}`"
                :style="{ '--rarity': OUTCOME_COLORS[cell.outcome] }"
            >
              <div class="reel-card__visual">
                <ShopItemPreview v-if="cell.item" :item="cell.item" />
                <span v-else-if="cell.outcome === 'coins'" class="reel-coin">₳</span>
                <span v-else class="reel-empty">∅</span>
              </div>
              <span class="reel-card__name">
                {{ cell.item ? cell.item.name : cell.outcome === 'coins' ? `${formatCoins(cell.coins)} ₳` : 'Пусто' }}
              </span>
            </div>
          </div>
        </div>

        <div class="opener__actions">
          <button
              type="button"
              class="open-button"
              :class="`open-button--${selected.theme}`"
              :disabled="auth.isAuthenticated && !canOpen(selected)"
              @click="auth.isAuthenticated ? openCase() : router.push('/login')"
          >
            {{ openLabel(selected) }}
          </button>
          <p v-if="openError" class="open-error">{{ openError }}</p>
        </div>

        <!-- Шансы -->
        <div class="chances">
          <div v-for="chance in chanceList(selected)" :key="chance.outcome" class="chance">
            <i :style="{ background: chance.color }"></i>
            <span>{{ chance.label }}</span>
            <b>{{ chance.value }}%</b>
          </div>
          <div class="chance chance--note">
            Монеты: ₳ {{ formatCoins(selected.coins[0]) }}–{{ formatCoins(selected.coins[1]) }}
          </div>
        </div>
      </section>

      <!-- Что внутри -->
      <section v-if="selected?.preview?.length" class="contents">
        <h3>Что может выпасть</h3>
        <div class="contents__grid">
          <div
              v-for="item in selected.preview"
              :key="item.id"
              class="content-item"
              :style="{ '--rarity': RARITY_COLORS[item.rarity] }"
          >
            <ShopItemPreview :item="item" class="content-item__preview" />
            <strong>{{ item.name }}</strong>
            <span>{{ RARITY_LABELS[item.rarity] }} · {{ TYPE_LABELS[item.type] || item.type }}</span>
          </div>
        </div>
        <p class="contents__note">
          И ещё {{ Math.max(0, data.pool_size - selected.preview.length) }} предметов из магазина.
          Повтор возвращает 15% цены предмета монетами.
        </p>
      </section>

      <!-- История -->
      <section v-if="data.history?.length" class="history">
        <h3>Ваши последние открытия</h3>
        <div v-for="entry in data.history" :key="entry.id" class="history-row">
          <i :style="{ background: OUTCOME_COLORS[entry.outcome] }"></i>
          <span class="history-row__case">{{ entry.case }}</span>
          <span class="history-row__prize">
            <template v-if="entry.item">
              {{ entry.item.name }}
              <em v-if="entry.duplicate">повтор · +{{ entry.coins }} ₳</em>
            </template>
            <template v-else-if="entry.outcome === 'coins'">+{{ formatCoins(entry.coins) }} ₳</template>
            <template v-else>Пусто</template>
          </span>
          <span class="history-row__time">{{ timeAgo(entry.created_at) }}</span>
        </div>
      </section>
    </template>

    <!-- Награда -->
    <Transition name="prize">
      <div v-if="showResult && prize" class="prize-overlay" @click.self="closeResult">
        <div
            class="prize-card"
            :class="`prize-card--${prize.outcome}`"
            :style="{ '--rarity': prizeColor }"
        >
          <div class="prize-card__rays"></div>
          <div v-if="prize.outcome === 'legendary' || prize.outcome === 'epic'" class="prize-card__sparks">
            <i v-for="n in 18" :key="n" :style="{ '--n': n }"></i>
          </div>

          <span class="prize-card__label">
            {{ result.pity ? 'Гарантия сработала!' : prize.outcome === 'nothing' ? 'Не повезло' : 'Вам выпало' }}
          </span>

          <div class="prize-card__visual">
            <ShopItemPreview v-if="prize.item" :item="prize.item" />
            <span v-else-if="prize.outcome === 'coins'" class="reel-coin reel-coin--big">₳</span>
            <span v-else class="reel-empty reel-empty--big">∅</span>
          </div>

          <h2 class="prize-card__title">
            {{ prize.item ? prize.item.name : prize.outcome === 'coins' ? `${formatCoins(prize.coins)} ApexCoin` : 'Сундук оказался пустым' }}
          </h2>

          <p class="prize-card__sub">
            <template v-if="prize.item">
              {{ RARITY_LABELS[prize.rarity] }} · {{ TYPE_LABELS[prize.item.type] || prize.item.type }}
            </template>
            <template v-else-if="prize.outcome === 'coins'">Уже на вашем балансе</template>
            <template v-else>В следующий раз повезёт больше</template>
          </p>

          <p v-if="result.duplicate" class="prize-card__dup">
            Такой предмет уже есть — вместо него +{{ formatCoins(prize.coins) }} ApexCoin
          </p>

          <div class="prize-card__actions">
            <RouterLink v-if="prize.item && !result.duplicate" to="/inventory" class="prize-button prize-button--ghost">
              Надеть в инвентаре
            </RouterLink>
            <button v-if="canOpen(selected)" type="button" class="prize-button" @click="again">
              Открыть ещё
            </button>
            <button v-else type="button" class="prize-button" @click="closeResult">Отлично</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
@import "./CasesView.css";
</style>
