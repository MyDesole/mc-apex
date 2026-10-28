<script setup>
/**
 * Аукцион питомцев: лоты со ставками и таймером.
 * Ставка сразу замораживает монеты, перебитому лидеру они возвращаются.
 * Ставка в последние 2 минуты продлевает торги. Выставить питомца —
 * кнопкой «На аукцион» в инвентаре.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { userLink } from '@/utils/links.js'

const auth = useAuthStore()
const route = useRoute()

const lots = ref([])
const loading = ref(true)
const error = ref('')
const notice = ref('')
const busy = ref(null)
const amounts = ref({})
const filter = ref('all')
const now = ref(Date.now())

async function load() {
  try {
    const data = await api.get('/auctions')
    lots.value = data.lots ?? []
    for (const lot of lots.value) {
      if (!amounts.value[lot.id] || amounts.value[lot.id] < lot.min_bid) amounts.value[lot.id] = lot.min_bid
    }
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить аукцион.'
  } finally {
    loading.value = false
  }
}

const shown = computed(() => {
  if (filter.value === 'mine') return lots.value.filter((l) => l.mine)
  if (filter.value === 'leading') return lots.value.filter((l) => l.leading)
  return lots.value
})

function left(lot) {
  const ms = new Date(lot.ends_at).getTime() - now.value
  if (ms <= 0) return 'завершается…'
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const s = Math.floor((ms % 60000) / 1000)
  return h > 0 ? `${h} ч ${m} мин` : `${m}:${String(s).padStart(2, '0')}`
}

function soon(lot) {
  return new Date(lot.ends_at).getTime() - now.value < 10 * 60000
}

async function bid(lot) {
  const amount = Number(amounts.value[lot.id])
  if (!await confirmDialog(`Поставить ${amount} ApexCoin на «${lot.pet?.name}»? Монеты заморозятся до конца торгов, если перебьют — вернутся.`)) return

  busy.value = lot.id
  error.value = ''
  notice.value = ''
  try {
    const fresh = await api.post(`/shop/auctions/${lot.id}/bid`, { amount })
    Object.assign(lot, fresh)
    amounts.value[lot.id] = fresh.min_bid
    notice.value = 'Ставка принята — ты лидируешь.'
    auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось сделать ставку.'
    await load()
  } finally {
    busy.value = null
  }
}

async function cancel(lot) {
  if (!await confirmDialog('Снять лот с торгов? Питомец останется у тебя.')) return
  busy.value = lot.id
  try {
    await api.post(`/shop/auctions/${lot.id}/cancel`)
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось снять лот.'
  } finally {
    busy.value = null
  }
}

let tick = null
let poll = null

onMounted(() => {
  load()
  tick = setInterval(() => { now.value = Date.now() }, 1000)
  // Ставки других игроков — раз в 15 секунд
  poll = setInterval(load, 15000)
  if (route.query.created) notice.value = 'Питомец выставлен на торги.'
})

onUnmounted(() => {
  clearInterval(tick)
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
          Каждый питомец уникален — номер и черты. Ставка сразу замораживает монеты, если тебя перебьют — они вернутся.
          Ставка в последние 2 минуты продлевает торги. Выставить своего — кнопкой «На аукцион» в
          <RouterLink to="/inventory">инвентаре</RouterLink>.
        </p>
      </div>

      <dl class="au-head__stats">
        <div><dt>Лотов</dt><dd>{{ lots.length }}</dd></div>
        <div><dt>Лидирую</dt><dd>{{ lots.filter((l) => l.leading).length }}</dd></div>
        <div><dt>Мои лоты</dt><dd>{{ lots.filter((l) => l.mine).length }}</dd></div>
      </dl>
    </header>

    <div class="au-tabs">
      <button
          v-for="[id, label] in [['all', 'Все лоты'], ['leading', 'Лидирую'], ['mine', 'Мои лоты']]"
          :key="id"
          type="button"
          :class="{ 'is-on': filter === id }"
          @click="filter = id"
      >{{ label }}</button>
    </div>

    <p v-if="notice" class="au-msg au-msg--ok">{{ notice }}</p>
    <p v-if="error" class="au-msg au-msg--err">{{ error }}</p>

    <PageLoader v-if="loading" />

    <p v-else-if="!shown.length" class="au-empty">
      {{ filter === 'all' ? 'Сейчас на торгах никого нет. Выставь своего питомца первым!' : 'Тут пусто.' }}
    </p>

    <div v-else class="au-grid">
      <article
          v-for="lot in shown"
          :key="lot.id"
          class="au-lot"
          :class="{ 'is-leading': lot.leading, 'is-mine': lot.mine }"
          :style="{ '--rar': PET_RARITY[lot.pet?.pet?.rarity]?.color || '#a8a3b8' }"
      >
        <div class="au-lot__top">
          <span class="au-lot__rarity">{{ PET_RARITY[lot.pet?.pet?.rarity]?.label || 'Питомец' }}</span>
          <span class="au-lot__timer" :class="{ 'is-soon': soon(lot) }">
            <AppIcon icon="bolt" :size="12" /> {{ left(lot) }}
          </span>
        </div>

        <ShopItemPreview v-if="lot.pet" :item="lot.pet" class="au-lot__preview" />

        <div class="au-lot__info">
          <strong>{{ lot.pet?.pet?.name || lot.pet?.name }} <em v-if="lot.pet?.pet?.serial">#{{ lot.pet.pet.serial }}</em></strong>
          <small v-if="lot.pet?.pet">
            {{ lot.pet.pet.model?.name }} · {{ lot.pet.pet.backdrop?.name }} · {{ lot.pet.pet.accessory?.name }}
          </small>
          <small>
            Продаёт
            <RouterLink v-if="lot.seller" :to="userLink(lot.seller)">{{ lot.seller.username }}</RouterLink>
          </small>
        </div>

        <div class="au-lot__price">
          <div>
            <span>{{ lot.current_price ? 'Текущая ставка' : 'Старт' }}</span>
            <b>₳ {{ (lot.current_price || lot.start_price).toLocaleString('ru-RU') }}</b>
          </div>
          <div>
            <span>Ставок</span>
            <b>{{ lot.bids_count }}</b>
          </div>
        </div>

        <p v-if="lot.leader" class="au-lot__leader">
          {{ lot.leading ? 'Ты лидируешь' : `Лидирует ${lot.leader.username}` }}
        </p>

        <template v-if="lot.mine">
          <button
              v-if="!lot.bids_count"
              type="button"
              class="au-btn"
              :disabled="busy === lot.id"
              @click="cancel(lot)"
          >Снять с торгов</button>
          <p v-else class="au-lot__note">Твой лот — ставки идут</p>
        </template>

        <form v-else-if="auth.isAuthenticated && !lot.leading" class="au-bid" @submit.prevent="bid(lot)">
          <input v-model.number="amounts[lot.id]" type="number" :min="lot.min_bid" step="1" />
          <button type="submit" class="au-btn au-btn--primary" :disabled="busy === lot.id || amounts[lot.id] < lot.min_bid">
            Ставка
          </button>
        </form>
        <p v-else-if="!auth.isAuthenticated" class="au-lot__note">Войди, чтобы делать ставки</p>
      </article>
    </div>
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

.au-tabs {
  display: flex;
  gap: 2px;
  align-self: flex-start;

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
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 12px;
}

.au-lot {
  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 12px;

  background: var(--bg-card, #15131c);
  border: 1px solid color-mix(in srgb, var(--rar) 30%, rgba(255, 255, 255, 0.06));
  border-radius: 16px;
}

.au-lot.is-leading { border-color: #22c55e; box-shadow: 0 0 0 1px #22c55e; }
.au-lot.is-mine { border-style: dashed; }

.au-lot__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }

.au-lot__rarity {
  color: var(--rar);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.au-lot__timer {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  padding: 3px 8px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 999px;

  font-size: 12px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.au-lot__timer.is-soon { color: #fecaca; background: rgba(239, 68, 68, 0.2); }

.au-lot__preview { height: 130px; overflow: hidden; border-radius: 12px; }

.au-lot__info { display: flex; flex-direction: column; gap: 2px; }
.au-lot__info strong { color: #fff; font-size: 15px; }
.au-lot__info em { color: #a8a3b8; font-style: normal; font-size: 12px; }
.au-lot__info small { color: #8a8598; font-size: 12px; }
.au-lot__info a { color: #c4b5fd; }

.au-lot__price {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;

  padding: 10px 12px;

  background: rgba(0, 0, 0, 0.25);
  border-radius: 10px;
}

.au-lot__price div { display: flex; flex-direction: column; }
.au-lot__price span { color: #8a8598; font-size: 10.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.au-lot__price b { color: #fde68a; font-size: 18px; font-weight: 900; }

.au-lot__leader,
.au-lot__note { margin: 0; color: #a8a3b8; font-size: 12px; }
.au-lot.is-leading .au-lot__leader { color: #86efac; font-weight: 700; }

.au-bid { display: grid; grid-template-columns: 1fr auto; gap: 6px; }

.au-bid input {
  min-width: 0;
  height: 38px;
  padding: 0 10px;

  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;

  font: inherit;
  font-weight: 700;
}

.au-btn {
  min-height: 38px;
  padding: 0 14px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;

  font: inherit;
  font-size: 13px;
  font-weight: 800;

  cursor: pointer;
}

.au-btn--primary { color: #1a1206; background: linear-gradient(135deg, #fde047, #f59e0b); border-color: transparent; }
.au-btn:disabled { opacity: 0.5; cursor: default; }

@media (max-width: 720px) {
  .au-head { grid-template-columns: minmax(0, 1fr); }
  .au-head__stats div { flex: 1; min-width: 0; }
}
</style>
