<script setup>
/**
 * Рынок: прилавки с товарами других игроков. Сверху фильтры-вывески,
 * ниже ящики с лотами; справа — твои лоты и кнопка «Выставить» (материалы
 * и снаряжение берутся со склада).
 */
import { computed, onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { dungeonApi } from '@/services/pets/dungeon.js'
import { useAuthStore } from '@/stores/core/auth.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import CountdownText from '@/components/core/CountdownText.vue'
import SellDialog from '@/game/SellDialog.vue'

const auth = useAuthStore()
const COLORS = { common: '#9ca3af', rare: '#60a5fa', epic: '#c084fc', legendary: '#fbbf24' }
const FILTERS = [['all', 'Всё'], ['material', 'Материалы'], ['gear', 'Снаряжение']]

const data = ref(null)
const kind = ref('all')
const error = ref('')
const notice = ref('')
const busy = ref(null)
const sell = ref(null)
const stock = ref({ materials: [], gear: [] })
const showSell = ref(false)

const fmt = (n) => Number(n).toLocaleString('ru-RU')
const listings = computed(() => (data.value?.listings ?? []).filter((l) => kind.value === 'all' || l.kind === kind.value))
const stats = (s) => (s ? [['atk', 'атака'], ['def', 'защита'], ['hp', 'здоровье'], ['spd', 'скорость']].filter(([k]) => s[k]).map(([k, n]) => `${n} ${s[k] > 0 ? '+' : ''}${s[k]}`).join(', ') : '')

async function load() {
  try {
    data.value = await api.get('/market')
  } catch (e) {
    error.value = e.message || 'Не удалось открыть рынок.'
  }
}

async function loadStock() {
  try {
    const [craft, overview] = await Promise.all([dungeonApi.craftIndex(), dungeonApi.overview()])
    stock.value = {
      materials: Object.entries(craft.materials).map(([key, qty]) => ({ key, qty, ...craft.catalog[key] })),
      gear: (overview.gear_bag ?? []).filter((g) => !g.worn_by),
    }
  } catch (e) {
    error.value = e.message || 'Не удалось открыть склад.'
  }
}

async function act(key, fn, ok) {
  busy.value = key
  error.value = ''
  try {
    await fn()
    notice.value = ok
    auth.fetchMe()
    await Promise.all([load(), loadStock()])
  } catch (e) {
    error.value = e.message || 'Не получилось.'
    await load()
  } finally {
    busy.value = null
  }
}

const buy = (l) => act(`buy${l.id}`, () => api.post(`/market/${l.id}/buy`), `Куплено: ${l.name}.`)
const cancel = (l) => act(`cancel${l.id}`, () => api.post(`/market/${l.id}/cancel`), 'Лот снят, товар вернулся.')

function done(message) {
  notice.value = message
  Promise.all([load(), loadStock()])
}

onMounted(() => { load(); loadStock() })
</script>

<template>
  <div class="g-scene mk">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <header class="mk-top">
      <nav class="mk-signs">
        <button v-for="[id, label] in FILTERS" :key="id" type="button" class="g-plank mk-sign" :class="{ 'is-on': kind === id }" @click="kind = id">{{ label }}</button>
      </nav>
      <span class="g-chip">₳ <b>{{ fmt(data?.balance ?? auth.user?.apex_coins ?? 0) }}</b></span>
      <button type="button" class="g-btn g-btn--gold g-btn--sm" @click="showSell = !showSell">{{ showSell ? 'Закрыть склад' : 'Выставить товар' }}</button>
    </header>

    <!-- Что можно выставить: со склада -->
    <section v-if="showSell" class="mk-stock g-wood">
      <h3 class="g-title">Твой склад <small>нажми, чтобы выставить</small></h3>
      <div class="mk-crates">
        <button v-for="m in stock.materials" :key="m.key" type="button" class="g-slot" :title="`${m.name} × ${m.qty}`"
                @click="sell = { kind: 'material', name: m.name, material: m.key, maxQty: m.qty }">
          <PixelSprite :sprite="m.sprite" :size="30" /><i class="g-slot__tag">{{ m.qty }}</i>
        </button>
        <button v-for="g in stock.gear" :key="g.inventory_id" type="button" class="g-slot" :style="{ '--rar': COLORS[g.rarity] }" :title="`${g.name} · ${g.rarity_name}`"
                @click="sell = { kind: 'gear', name: g.name, item: g.inventory_id }">
          <PixelSprite :sprite="g.sprite || 'mob-rat'" :size="30" />
        </button>
        <p v-if="!stock.materials.length && !stock.gear.length" class="g-hint">На складе пусто: материалы и снаряжение падают в подземельях.</p>
      </div>
    </section>

    <!-- Прилавок -->
    <div class="mk-counter">
      <p v-if="data && !listings.length" class="g-hint">На прилавках пусто. Выставь первым — другие игроки увидят лот.</p>
      <article v-for="l in listings" :key="l.id" class="mk-lot g-stone" :style="{ '--rar': COLORS[l.rarity] ?? '#6b5a45' }">
        <span class="mk-lot__art"><PixelSprite :sprite="l.sprite || 'mob-rat'" :size="34" /></span>
        <span class="mk-lot__main">
          <b>{{ l.name }}<template v-if="l.qty > 1"> × {{ l.qty }}</template></b>
          <small v-if="l.rarity" :style="{ color: COLORS[l.rarity] }">{{ l.rarity_name }} · {{ stats(l.stats) }}</small>
          <small v-else>по {{ fmt(l.unit) }} ₳ за штуку</small>
          <small class="mk-lot__seller">{{ l.seller }} · <CountdownText :to="l.expires_at" done="истёк" /></small>
        </span>
        <button type="button" class="g-btn g-btn--go g-btn--sm" :disabled="busy === `buy${l.id}`" @click="buy(l)">₳ {{ fmt(l.price) }}</button>
      </article>
    </div>

    <!-- Мои лоты -->
    <section v-if="data?.mine?.length" class="mk-mine g-dark">
      <h3 class="g-title">Мои лоты <small>{{ data.mine.length }} / {{ data.max_active }}</small></h3>
      <article v-for="l in data.mine" :key="l.id" class="mk-lot">
        <span class="mk-lot__art"><PixelSprite :sprite="l.sprite || 'mob-rat'" :size="28" /></span>
        <span class="mk-lot__main"><b>{{ l.name }}<template v-if="l.qty > 1"> × {{ l.qty }}</template></b><small>₳ {{ fmt(l.price) }} · <CountdownText :to="l.expires_at" done="истёк" /></small></span>
        <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="busy === `cancel${l.id}`" @click="cancel(l)">Снять</button>
      </article>
    </section>

    <p class="g-hint">Комиссия {{ Math.round((data?.fee_share ?? 0.05) * 100) }}%, лот живёт {{ data?.hours ?? 72 }} ч. Торговать можно с аккаунта старше {{ data?.min_account_days ?? 7 }} дней. Питомцы продаются на аукционе.</p>

    <SellDialog v-if="sell" v-bind="sell" @close="sell = null" @done="done" />
  </div>
</template>

<style scoped>
.mk-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.mk-signs { display: flex; gap: 6px; margin-right: auto; }
.mk-sign { padding: 5px 16px; font: inherit; font-size: 13px; cursor: pointer; }
.mk-sign.is-on { background: #3f9a2f; border-color: #1f4d17; }
.mk-stock { margin-bottom: 12px; padding: 12px; }
.mk-stock h3 small { font-size: 11px; color: #ffe9a8; }
.mk-crates { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.mk-counter { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px; padding: 12px; background: repeating-linear-gradient(90deg, #7c4a21 0 56px, #6b3f1b 56px 60px); border: 4px solid #1b0f06; min-height: 140px; }
.mk-lot { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: #2a2433; border: 3px solid var(--rar, #1b0f06); }
.mk-lot__art { display: grid; place-items: center; width: 48px; height: 48px; flex: none; background: #20150c; border: 3px solid #1b0f06; }
.mk-lot__main { display: flex; flex-direction: column; margin-right: auto; min-width: 0; font-size: 13px; }
.mk-lot__main small { font-size: 11px; color: #c9b9e8; }
.mk-lot__seller { color: #9b8bb8 !important; }
.mk-mine { margin-top: 12px; padding: 10px 12px; }
.mk-mine .mk-lot { margin-top: 6px; background: rgba(0, 0, 0, 0.3); border-color: #3a2f4a; }
</style>
