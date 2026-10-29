<script setup>
/**
 * Аукцион питомцев: лоты со ставками, таймером и чатом.
 * Ставка сразу замораживает монеты, перебитому лидеру они возвращаются.
 * Ставка в последние 2 минуты продлевает торги. Выставить питомца —
 * кнопкой «На аукцион» в инвентаре (до трёх лотов сразу).
 *
 * Список живой: ставки и снятие лотов приходят через Reverb, клик по
 * карточке открывает лот с историей ставок и чатом.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import AuctionLotCard from '@/components/shop/AuctionLotCard.vue'
import AuctionLotPanel from '@/components/shop/AuctionLotPanel.vue'
import { useLiveChannel } from '@/composables/shop/useLiveChannel.js'
import { pageHidden } from '@/composables/core/useNow.js'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const MAX_LOTS = 3

const lots = ref([])
const loading = ref(true)
const error = ref('')
const notice = ref('')
const filter = ref('all')
const sort = ref('ending')

/** mine/leading считаем на месте: из сокета лот приходит без зрителя. */
function withViewer(lot) {
  const me = auth.user?.id
  return { ...lot, mine: !!me && lot.seller?.id === me, leading: !!me && lot.leader?.id === me }
}

async function load() {
  try {
    const data = await api.get('/auctions')
    lots.value = (data.lots ?? []).map(withViewer)
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить аукцион.'
  } finally {
    loading.value = false
  }
}

/** Лот изменился: обновить, а закрытый — убрать из списка. */
function upsert(lot) {
  if (!lot?.id) return
  const index = lots.value.findIndex((l) => l.id === lot.id)

  if (lot.status && lot.status !== 'active') {
    if (index >= 0) lots.value.splice(index, 1)
    return
  }

  const fresh = withViewer({ ...(index >= 0 ? lots.value[index] : {}), ...lot })
  if (index >= 0) lots.value.splice(index, 1, fresh)
  else lots.value.push(fresh)
}

const { live, subscribe } = useLiveChannel({
  'auction.lot': (payload) => upsert(payload?.lot),
})

const shown = computed(() => {
  let list = lots.value
  if (filter.value === 'mine') list = list.filter((l) => l.mine)
  if (filter.value === 'leading') list = list.filter((l) => l.leading)

  const price = (l) => l.current_price || l.start_price
  const sorters = {
    ending: (a, b) => new Date(a.ends_at) - new Date(b.ends_at),
    cheap: (a, b) => price(a) - price(b),
    level: (a, b) => (b.level ?? 1) - (a.level ?? 1),
    hot: (a, b) => b.bids_count - a.bids_count,
  }
  return [...list].sort(sorters[sort.value])
})

const myCount = computed(() => lots.value.filter((l) => l.mine).length)

// Открытый лот — в адресе (?lot=), чтобы ссылкой из уведомления попадать прямо в него
const openId = computed(() => Number(route.query.lot) || null)
const openLot = computed(() => lots.value.find((l) => l.id === openId.value) ?? null)

function open(lot) {
  router.replace({ query: { ...route.query, lot: lot.id } })
}

function close() {
  const { lot, ...rest } = route.query
  router.replace({ query: rest })
}

// Лот из ссылки уже закрыт — его нет в активных, подгружаем отдельно
watch([openId, loading], async ([id, isLoading]) => {
  if (!id || isLoading || openLot.value) return
  try {
    const lot = await api.get(`/auctions/${id}`)
    if (lot.status === 'active') upsert(lot)
    else {
      notice.value = 'Торги по этому лоту уже закончились.'
      close()
    }
  } catch {
    close()
  }
}, { immediate: true })

function changed(lot) {
  if (lot?.status && lot.status !== 'active') {
    notice.value = lot.status === 'cancelled' ? 'Лот снят с торгов — питомец вернулся в инвентарь.' : 'Торги по лоту закончились.'
  }
  upsert(lot)
}

let poll = null

onMounted(() => {
  load()
  subscribe('auctions')
  // Сокет лежит — опрашиваем чаще, жив — изредка для надёжности; в фоне — никогда
  let ticks = 0
  poll = setInterval(() => { if (!pageHidden() && (!live.value || ++ticks % 4 === 0)) load() }, 15000)
  if (route.query.created) notice.value = 'Питомец выставлен на торги.'
})

onUnmounted(() => {
  clearInterval(poll)
})
</script>

<template>
  <div class="au">
    <header class="au-head">
      <div>
        <span class="au-head__kicker"><AppIcon icon="coin" :size="14" /> Магазин</span>
        <h1>Аукцион питомцев</h1>
        <p>
          Каждый питомец уникален: номер, черты и уровень. Ставка сразу замораживает монеты, если тебя перебьют — они вернутся.
          Ставка в последние 2 минуты продлевает торги. Выставить до {{ MAX_LOTS }} питомцев — кнопкой «На аукцион» в
          <RouterLink to="/inventory">инвентаре</RouterLink>.
        </p>
      </div>

      <dl class="au-head__stats">
        <div><dt>Лотов</dt><dd>{{ lots.length }}</dd></div>
        <div><dt>Лидирую</dt><dd>{{ lots.filter((l) => l.leading).length }}</dd></div>
        <div><dt>Мои лоты</dt><dd>{{ myCount }}<small>/{{ MAX_LOTS }}</small></dd></div>
      </dl>
    </header>

    <div class="au-bar">
      <div class="au-tabs">
        <button
            v-for="[id, label] in [['all', 'Все лоты'], ['leading', 'Лидирую'], ['mine', 'Мои лоты']]"
            :key="id"
            type="button"
            :class="{ 'is-on': filter === id }"
            @click="filter = id"
        >{{ label }}</button>
      </div>

      <div class="au-sort">
        <span class="au-live" :class="{ 'is-on': live }"><i></i>{{ live ? 'Живые ставки' : 'Обновляется' }}</span>
        <select v-model="sort" aria-label="Сортировка">
          <option value="ending">Скоро конец</option>
          <option value="hot">Больше ставок</option>
          <option value="level">Выше уровень</option>
          <option value="cheap">Дешевле</option>
        </select>
      </div>
    </div>

    <p v-if="notice" class="au-msg au-msg--ok">{{ notice }}</p>
    <p v-if="error" class="au-msg au-msg--err">{{ error }}</p>

    <PageLoader v-if="loading" />

    <p v-else-if="!shown.length" class="au-empty">
      {{ filter === 'all' ? 'Сейчас на торгах никого нет. Выставь своего питомца первым!' : 'Тут пусто.' }}
    </p>

    <div v-else class="au-grid">
      <AuctionLotCard v-for="lot in shown" :key="lot.id" :lot="lot" @open="open" />
    </div>

    <AuctionLotPanel
        v-if="openLot"
        :key="openLot.id"
        :lot="openLot"
        @close="close"
        @changed="changed"
    />
  </div>
</template>

<style scoped>
.au {
  width: min(1100px, calc(100% - 32px));
  margin: 28px auto 60px;

  display: flex;
  flex-direction: column;
  gap: 18px;
}

.au-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 24px;

  padding-bottom: 22px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.au-head__kicker {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: #fbbf24;

  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.au-head h1 {
  margin: 8px 0 6px;

  color: #fff;

  font-family: var(--font-display);
  font-size: clamp(30px, 4.5vw, 46px);
  letter-spacing: -0.02em;
  line-height: 1;
}

.au-head p {
  max-width: 560px;
  margin: 0;

  color: #8a8598;

  font-size: 14px;
  line-height: 1.55;
}

.au-head p a { color: #c4b5fd; }

.au-head__stats {
  display: flex;
  gap: 1px;

  margin: 0;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
}

.au-head__stats div { min-width: 92px; padding: 12px 16px; background: var(--bg-card, #15131c); }
.au-head__stats dt { color: #8a8598; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; }
.au-head__stats dd { margin: 4px 0 0; color: #fff; font-size: 18px; font-weight: 800; }
.au-head__stats dd small { color: #6f6a80; font-size: 13px; }

.au-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.au-tabs {
  display: flex;
  gap: 2px;

  padding: 3px;

  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 11px;
}

.au-tabs button {
  min-height: 34px;
  padding: 0 14px;

  color: #a8a3b8;
  background: none;
  border: 0;
  border-radius: 8px;

  font: inherit;
  font-size: 13px;
  font-weight: 700;

  cursor: pointer;
}

.au-tabs button.is-on { color: #fff; background: rgba(251, 191, 36, 0.22); }

.au-sort { display: flex; align-items: center; gap: 12px; }

.au-sort select {
  height: 40px;
  padding: 0 12px;

  color: #e9e5f5;
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 11px;

  font: inherit;
  font-size: 13px;
  font-weight: 700;
}

.au-sort select option { background: #15131c; }

.au-live {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: #8a8598;

  font-size: 12px;
  font-weight: 700;
}

.au-live i { width: 8px; height: 8px; background: #6f6a80; border-radius: 50%; }
.au-live.is-on { color: #86efac; }
.au-live.is-on i { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.22); }

.au-msg { margin: 0; padding: 10px 14px; border-radius: 10px; font-size: 13px; }
.au-msg--ok { color: #bbf7d0; background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); }
.au-msg--err { color: #fecaca; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); }

.au-empty {
  margin: 0;
  padding: 44px 16px;

  color: #8a8598;
  border: 1px dashed rgba(255, 255, 255, 0.12);
  border-radius: 16px;

  text-align: center;
}

.au-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
}

@media (max-width: 720px) {
  .au-head { grid-template-columns: minmax(0, 1fr); }
  .au-head__stats div { flex: 1; min-width: 0; }
  .au-bar { flex-direction: column; align-items: stretch; }
  .au-tabs button { flex: 1; }
  .au-sort { justify-content: space-between; }
  .au-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
}

@media (max-width: 420px) {
  .au-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
