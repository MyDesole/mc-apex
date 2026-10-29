<script setup>
/**
 * Мини-игра «Шахтёр»: копаешь шахту вниз, собираешь руду — монеты
 * на счёт. Где руда, знает только сервер: блок виден после удара,
 * копать можно только рядом с открытыми (список даёт сервер).
 *
 * Сверху вниз — четыре яруса: Пещеры, Глубинный сланец, Незер, Энд.
 * Руда подряд копит комбо, TNT и динамит взрывают, лава и гравий
 * отнимают удары, в сундуке бывает мимик, житель торгует. Между
 * забегами — мастерская, задания дня, топ недели и коллекция.
 */
import { computed, onMounted, ref } from 'vue'
import { useNow } from '@/composables/core/useNow.js'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import MinerUpgrades from '@/components/games/miner/MinerUpgrades.vue'
import MinerQuests from '@/components/games/miner/MinerQuests.vue'
import MinerTop from '@/components/games/miner/MinerTop.vue'
import MinerCollection from '@/components/games/miner/MinerCollection.vue'
import {
  BLOCKS, BLOCK_SPRITES, HINTS, SPECIALS, TIER_BLOCKS, TRADE_SPRITES, eventText, formatSeconds,
} from '@/data/games/miner.js'

const auth = useAuthStore()

const state = ref(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const result = ref(null)
const pops = ref([])
const log = ref([])
const shaking = ref(false)
const tierFlash = ref(null)
const dynamiteMode = ref(false)
const traderCell = ref(null)
const legendTier = ref(1)
const now = useNow()

const run = computed(() => state.value?.run ?? null)
const cols = computed(() => state.value?.cols ?? 8)
const reach = computed(() => new Set(run.value?.reach ?? []))
const balance = computed(() => auth.user?.apex_coins ?? 0)
const live = computed(() => run.value && !run.value.done)

const cooldownLeft = computed(() => {
  const until = state.value?.cooldown_until
  if (!until) return 0
  return Math.max(0, Math.ceil((new Date(until).getTime() - now.value) / 1000))
})

const rush = computed(() => state.value?.rush ?? null)
const rushLeft = computed(() => rush.value?.active
  ? formatSeconds((new Date(rush.value.until).getTime() - now.value) / 1000) : '')
const rushNext = computed(() => rush.value?.next
  ? new Date(rush.value.next).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : '')

const trader = computed(() => {
  const v = run.value?.villagers?.[traderCell.value]
  return live.value && v?.status === 'open' ? v : null
})

const readyQuests = computed(() => (state.value?.quests ?? []).filter((q) => !q.claimed && q.progress >= q.goal).length)

const CATEGORY_HINTS = new Set(['danger', 'wall', 'special'])

function hintOf(i) {
  return run.value?.cells?.[i] === null ? run.value?.hints?.[i] ?? null : null
}

function cellClass(block, i) {
  const out = []
  const hint = hintOf(i)

  if (block) {
    out.push(`mn-cell--${block}`)
    if (block === 'chest' && run.value.chests?.[i] === 'closed') out.push('is-closed-chest')
    if (block === 'villager' && run.value.villagers?.[i]?.status === 'open') out.push('is-trader')
  } else if (hint && !CATEGORY_HINTS.has(hint)) {
    // Карта жителя: блок виден полупрозрачным
    out.push(`mn-cell--${hint}`, 'is-ghost')
  } else {
    out.push('mn-cell--hidden')
    if (hint) out.push(`has-hint--${hint}`)
  }

  if (live.value && reach.value.has(i)) out.push('is-reach', dynamiteMode.value ? 'is-aim' : '')
  if (traderCell.value === i && trader.value) out.push('is-picked')

  return out
}

function spriteOf(block, i) {
  if (block === 'chest' && run.value.chests?.[i] === 'open') return 'mine_chest_open'
  return BLOCK_SPRITES[block] ?? null
}

function cellTitle(block, i) {
  if (!block) {
    const hint = hintOf(i)
    if (hint) return HINTS[hint] ?? `Карта: ${BLOCKS[hint]}`
    return reach.value.has(i) ? (dynamiteMode.value ? 'Взорвать динамитом' : 'Копнуть') : ''
  }
  if (block === 'chest' && run.value.chests?.[i] === 'closed') return 'Сундук: открыть? Может оказаться мимиком'
  if (block === 'villager') return 'Житель: поторговать'
  const value = state.value.values?.[block]
  return `${BLOCKS[block]}${value ? ` · +${value}` : ''}`
}

function clickable(block, i) {
  if (!live.value || busy.value) return false
  if (reach.value.has(i)) return true
  if (block === 'chest' && run.value.chests?.[i] === 'closed') return true
  return block === 'villager' && run.value.villagers?.[i]?.status === 'open'
}

function pop(cell, text, tone = '') {
  const id = `${cell}-${Math.random()}`
  pops.value.push({ id, cell, text, tone })
  setTimeout(() => { pops.value = pops.value.filter((p) => p.id !== id) }, 1000)
}

function popsFor(i) {
  return pops.value.filter((p) => p.cell === i)
}

function pushLog(entry) {
  log.value = [{ ...entry, id: Math.random() }, ...log.value].slice(0, 6)
}

function shake() {
  shaking.value = false
  requestAnimationFrame(() => { shaking.value = true })
  setTimeout(() => { shaking.value = false }, 450)
}

/** Разобрать ответ сервера: всплывашки, журнал, итог забега. */
function apply(data) {
  for (const f of data.found ?? []) {
    if (f.value > 0) pop(f.cell, f.double ? `×2 +${f.value}` : `+${f.value}`, f.double ? 'double' : '')
  }

  for (const e of data.events ?? []) {
    const entry = eventText(e)
    if (entry) pushLog(entry)
    if (e.type === 'blast') shake()
    if (e.lost && e.cell !== undefined) pop(e.cell, e.type === 'mimic' ? `−${e.lost}` : `−${e.lost} уд.`, 'bad')
    if (e.type === 'loot') pop(e.cell, `+${e.coins}`)
    if (e.type === 'descend') {
      tierFlash.value = e.name
      setTimeout(() => { tierFlash.value = null }, 1600)
    }
  }

  // После последнего хода сервер уже закрыл забег — показываем итог на открытой шахте
  if (data.finished) {
    const cells = [...(run.value?.cells ?? [])]
    for (const f of data.found ?? []) cells[f.cell] = f.block
    result.value = data.finished
    state.value = { ...data, run: { ...run.value, cells, reach: [], hints: {}, coins: data.finished.mined, done: true } }
    if (auth.user) auth.user.apex_coins = data.finished.balance
  } else {
    state.value = data
  }

  if (data.balance !== undefined && auth.user) auth.user.apex_coins = data.balance
  if (!run.value?.dynamite) dynamiteMode.value = false
}

async function call(path, body, fallback = 'Не получилось.') {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    apply(await api.post(path, body))
  } catch (e) {
    error.value = e.message || fallback
  } finally {
    busy.value = false
  }
}

async function load() {
  try {
    state.value = await api.get('/games/miner')
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить шахту.'
  } finally {
    loading.value = false
  }
}

async function start() {
  result.value = null
  log.value = []
  traderCell.value = null
  await call('/games/miner/start', {}, 'Не получилось начать.')
}

function onCell(block, i) {
  if (!clickable(block, i)) return

  if (reach.value.has(i)) {
    traderCell.value = null
    if (dynamiteMode.value) {
      dynamiteMode.value = false
      return call('/games/miner/blast', { cell: i })
    }
    return call('/games/miner/dig', { cell: i }, 'Не получилось копнуть.')
  }

  if (block === 'chest') return call('/games/miner/chest', { cell: i })
  if (block === 'villager') traderCell.value = i
}

async function trade(offer) {
  await call('/games/miner/trade', { cell: traderCell.value, offer })
  traderCell.value = null
}

const descend = () => call('/games/miner/descend')
const cashOut = () => call('/games/miner/cash-out')
const buy = (upgrade) => call('/games/miner/upgrade', { upgrade }, 'Не получилось улучшить.')
const claim = (quest) => call(`/games/miner/quests/${quest}/claim`)

async function again() {
  result.value = null
  log.value = []
  await load()
}

onMounted(load)
</script>

<template>
  <div class="mn">
    <header class="mn-head">
      <div>
        <span class="mn-head__kicker"><AppIcon icon="gem" :size="14" /> Мини-игра</span>
        <h1>Шахтёр</h1>
        <p>
          Копай вниз — чем глубже, тем богаче руда, а со дна яруса можно спуститься ещё ниже: Пещеры,
          Глубинный сланец, Незер, Энд. Руда подряд копит комбо, лава и гравий отнимают удары,
          в сундуках бывает мимик. Вся добыча — в ApexCoin.
        </p>
      </div>

      <dl class="mn-head__stats">
        <div><dt>Рекорд</dt><dd>{{ state?.best ?? 0 }}</dd></div>
        <div><dt>Всего добыто</dt><dd>{{ state?.total ?? 0 }}</dd></div>
        <div><dt>Забегов</dt><dd>{{ state?.runs ?? 0 }}</dd></div>
      </dl>
    </header>

    <div v-if="rush?.active" class="mn-rush">
      <PixelSprite sprite="art_coin" :size="22" anim="spin" />
      <div>
        <b>Золотая лихорадка!</b>
        <span>Редкой руды в новых ярусах вдвое больше · ещё {{ rushLeft }}</span>
      </div>
    </div>
    <p v-else-if="rushNext" class="mn-rush-next">
      <AppIcon icon="clock" :size="13" /> Следующая золотая лихорадка в {{ rushNext }} — редкой руды вдвое больше целый час
    </p>

    <PageLoader v-if="loading" />

    <div v-else class="mn-layout">
      <section class="mn-mine" :class="[`mn-mine--t${run?.tier ?? 1}`, { 'is-shaking': shaking }]">
        <template v-if="run">
          <div class="mn-bar">
            <span class="mn-tier">
              <span class="mn-depth" aria-hidden="true">
                <i v-for="t in 4" :key="t" :class="{ on: t <= run.tier }"></i>
              </span>
              Ярус {{ run.tier }} · {{ run.tier_name }}
            </span>
            <span v-if="run.rush" class="mn-chip mn-chip--rush">Лихорадка</span>
            <span v-if="run.map" class="mn-chip mn-chip--map">Карта</span>
            <span v-if="run.combo >= 2" :key="run.combo" class="mn-chip mn-chip--combo">
              Комбо ×{{ run.combo }} · {{ run.combo_mult.toFixed(2) }}
            </span>
          </div>

          <div class="mn-grid-wrap">
            <div class="mn-grid" :class="{ 'is-done': run.done }" :style="{ '--cols': cols }">
              <button
                  v-for="(block, i) in run.cells"
                  :key="`${run.tier}-${i}`"
                  type="button"
                  class="mn-cell"
                  :class="cellClass(block, i)"
                  :disabled="!clickable(block, i)"
                  :title="cellTitle(block, i)"
                  @click="onCell(block, i)"
              >
                <PixelSprite v-if="spriteOf(block, i)" class="mn-cell__sprite" :sprite="spriteOf(block, i)" :size="22" :anim="block === 'relic' ? 'pulse' : null" />
                <AppIcon v-else-if="!block && hintOf(i) === 'danger'" class="mn-cell__hint" icon="flame" :size="14" />
                <AppIcon v-else-if="!block && hintOf(i) === 'special'" class="mn-cell__hint" icon="sparkles" :size="14" />
                <span v-for="p in popsFor(i)" :key="p.id" class="mn-pop" :class="p.tone ? `mn-pop--${p.tone}` : null">{{ p.text }}</span>
              </button>
            </div>

            <div v-if="tierFlash" class="mn-flash">
              <small>Спуск</small>
              <b>{{ tierFlash }}</b>
            </div>
          </div>

          <div v-if="trader" class="mn-trader">
            <PixelSprite sprite="mine_villager" :size="34" anim="wiggle" />
            <div class="mn-trader__body">
              <b>Житель: «Хм-м. Меняю за долю добычи»</b>
              <div class="mn-trader__offers">
                <button
                    v-for="o in trader.offers"
                    :key="o.id"
                    type="button"
                    class="mn-offer"
                    :disabled="busy || run.coins < o.cost"
                    @click="trade(o.id)"
                >
                  <PixelSprite :sprite="TRADE_SPRITES[o.id]" :size="16" />
                  <span>{{ o.text }}</span>
                  <b>₳ {{ o.cost }}</b>
                </button>
              </div>
            </div>
            <button type="button" class="mn-trader__close" aria-label="Закрыть" @click="traderCell = null">
              <AppIcon icon="close" :size="14" />
            </button>
          </div>

          <div v-if="live" class="mn-tools">
            <button
                type="button"
                class="mn-btn mn-btn--tool"
                :class="{ 'is-on': dynamiteMode }"
                :disabled="busy || !run.dynamite"
                @click="dynamiteMode = !dynamiteMode"
            >
              <PixelSprite sprite="mine_dynamite" :size="16" :anim="dynamiteMode ? 'shake' : null" />
              {{ dynamiteMode ? 'Выбери блок для взрыва' : `Динамит · ${run.dynamite}` }}
            </button>
            <button
                v-if="run.can_descend"
                type="button"
                class="mn-btn mn-btn--descend"
                :disabled="busy || run.energy < 4"
                @click="descend"
            >
              <AppIcon icon="rocket" :size="14" /> Спуститься ниже · 4 удара
            </button>
            <button type="button" class="mn-btn" :disabled="busy" @click="cashOut">Подняться и забрать</button>
          </div>

          <ul v-if="log.length" class="mn-log">
            <li v-for="l in log" :key="l.id" :class="`is-${l.tone}`">{{ l.text }}</li>
          </ul>
        </template>

        <div v-else class="mn-start">
          <div class="mn-start__art" aria-hidden="true">
            <i class="mn-cell mn-cell--stone"></i><i class="mn-cell mn-cell--diamond"></i><i class="mn-cell mn-cell--gold"></i>
            <i class="mn-cell mn-cell--deepslate"></i><i class="mn-cell mn-cell--amethyst"></i><i class="mn-cell mn-cell--lava"></i>
            <i class="mn-cell mn-cell--netherrack"></i><i class="mn-cell mn-cell--debris"></i><i class="mn-cell mn-cell--purpur"></i>
          </div>
          <button
              type="button"
              class="mn-btn mn-btn--primary"
              :disabled="busy || cooldownLeft > 0"
              @click="start"
          >
            {{ cooldownLeft > 0 ? `Кирка остывает · ${formatSeconds(cooldownLeft)}` : 'Спуститься в шахту' }}
          </button>
          <p class="mn-start__note">
            {{ state?.energy ?? 24 }} ударов кирки · перерыв между забегами {{ formatSeconds(state?.cooldown ?? 600) }}.
            Улучшения из мастерской действуют со следующего забега.
          </p>
        </div>

        <p v-if="error" class="mn-error">{{ error }}</p>
      </section>

      <aside class="mn-side">
        <div class="mn-panel">
          <div class="mn-meter">
            <span>Удары кирки</span>
            <b>{{ live ? run.energy : 0 }} / {{ run?.max_energy ?? state?.energy ?? 24 }}</b>
            <i><em :style="{ width: `${live ? Math.min(100, (run.energy / run.max_energy) * 100) : 0}%` }"></em></i>
          </div>
          <div class="mn-coins">
            <span>Добыто за забег</span>
            <b>₳ {{ run?.coins ?? 0 }}</b>
          </div>

          <div v-if="result" class="mn-result">
            <strong>+{{ result.coins }} ApexCoin</strong>
            <dl>
              <div><dt>Добыча</dt><dd>{{ result.mined }}</dd></div>
              <div v-if="result.pet_bonus"><dt>Питомец</dt><dd>+{{ result.pet_bonus }}</dd></div>
              <div v-if="result.artifact_bonus"><dt>Коллекция</dt><dd>+{{ result.artifact_bonus }}</dd></div>
              <div><dt>Глубина</dt><dd>ярус {{ result.tier }}</dd></div>
              <div v-if="result.best_combo > 1"><dt>Лучшее комбо</dt><dd>×{{ result.best_combo }}</dd></div>
            </dl>
            <span>Баланс: {{ result.balance }}</span>
            <button type="button" class="mn-btn mn-btn--primary" @click="again">Ещё забег</button>
          </div>
        </div>

        <div class="mn-panel">
          <h3>
            <AppIcon icon="target" :size="13" /> Задания на сегодня
            <span v-if="readyQuests" class="mn-badge">{{ readyQuests }}</span>
          </h3>
          <MinerQuests v-if="state?.quests" :quests="state.quests" :busy="busy" @claim="claim" />
        </div>
      </aside>
    </div>

    <!-- Всё, что между забегами, — широким блоком под шахтой -->
    <div v-if="!loading" class="mn-hub">
        <div class="mn-panel mn-panel--wide">
          <h3><PixelSprite sprite="mine_pickaxe" :size="14" /> Мастерская</h3>
          <MinerUpgrades v-if="state?.upgrades" :upgrades="state.upgrades" :balance="balance" :busy="busy" @buy="buy" />
        </div>

        <div class="mn-panel">
          <h3><AppIcon icon="trophy" :size="13" /> Шахтёры недели</h3>
          <MinerTop v-if="state?.top" :top="state.top" />
        </div>

        <div class="mn-panel">
          <h3><AppIcon icon="star" :size="13" /> Коллекция артефактов</h3>
          <MinerCollection v-if="state?.artifacts" :artifacts="state.artifacts" />
        </div>

        <div class="mn-panel">
          <h3>Что сколько стоит</h3>
          <div class="mn-tabs">
            <button
                v-for="(name, t) in state?.tiers ?? {}"
                :key="t"
                type="button"
                :class="{ on: Number(t) === legendTier }"
                @click="legendTier = Number(t)"
            >{{ name }}</button>
          </div>
          <ul class="mn-legend">
            <li v-for="id in TIER_BLOCKS[legendTier]" :key="id">
              <i class="mn-cell" :class="`mn-cell--${id}`"></i>
              <span>{{ BLOCKS[id] }}</span>
              <b>+{{ state?.values?.[id] ?? 0 }}</b>
            </li>
          </ul>
          <ul class="mn-specials">
            <li v-for="(text, id) in SPECIALS" :key="id">
              <i class="mn-cell" :class="`mn-cell--${id}`">
                <PixelSprite v-if="BLOCK_SPRITES[id]" class="mn-cell__sprite" :sprite="BLOCK_SPRITES[id]" :size="14" />
              </i>
              <span><b>{{ BLOCKS[id] }}</b> — {{ text }}</span>
            </li>
          </ul>
        </div>
    </div>
  </div>
</template>

<style scoped>
.mn {
  width: min(1180px, calc(100% - 32px));
  margin: 28px auto 60px;

  display: flex;
  flex-direction: column;
  gap: 16px;
}

.mn-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 24px;

  padding-bottom: 20px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.mn-head__kicker {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: #5eead4;

  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.mn-head h1 {
  margin: 8px 0 6px;

  color: #fff;

  font-family: var(--font-display);
  font-size: clamp(30px, 4.5vw, 46px);
  letter-spacing: -0.02em;
  line-height: 1;
}

.mn-head p { max-width: 620px; margin: 0; color: #8a8598; font-size: 14px; line-height: 1.55; }

.mn-head__stats {
  display: flex;
  gap: 1px;

  margin: 0;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
}

.mn-head__stats div { min-width: 92px; padding: 12px 16px; background: var(--bg-card, #15131c); }
.mn-head__stats dt { color: #8a8598; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; }
.mn-head__stats dd { margin: 4px 0 0; color: #fff; font-size: 18px; font-weight: 800; }

/* ---------- Золотая лихорадка ---------- */

.mn-rush {
  display: flex;
  align-items: center;
  gap: 12px;

  padding: 12px 16px;

  background: linear-gradient(90deg, rgba(250, 204, 21, 0.18), rgba(245, 158, 11, 0.06));
  border: 1px solid rgba(250, 204, 21, 0.45);
  border-radius: 14px;

  animation: mn-rush 2.4s ease-in-out infinite;
}

.mn-rush b { display: block; color: #fde047; font-size: 15px; }
.mn-rush span { color: #e6d9a8; font-size: 12.5px; }

@keyframes mn-rush {
  0%, 100% { box-shadow: 0 0 0 rgba(250, 204, 21, 0); }
  50% { box-shadow: 0 0 22px rgba(250, 204, 21, 0.25); }
}

.mn-rush-next { display: flex; align-items: center; gap: 6px; margin: 0; color: #8a8598; font-size: 12.5px; }

.mn-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 16px;
  align-items: start;
}

/* ---------- Шахта и ярусы ---------- */

.mn-mine {
  --sky: #5b8c3a;

  padding: 14px;

  background:
      linear-gradient(180deg, var(--sky) 0 10px, transparent 10px),
      #1a1612;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;

  transition: background-color 0.6s ease;
}

.mn-mine--t2 { --sky: #3f4650; background-color: #111317; }
.mn-mine--t3 { --sky: #7f1d1d; background-color: #1f0b0b; }
.mn-mine--t4 {
  --sky: #a78bfa;
  background:
      radial-gradient(circle at 15% 30%, rgba(255, 255, 255, 0.5) 0 1px, transparent 2px),
      radial-gradient(circle at 70% 12%, rgba(255, 255, 255, 0.4) 0 1px, transparent 2px),
      radial-gradient(circle at 88% 60%, rgba(255, 255, 255, 0.35) 0 1px, transparent 2px),
      linear-gradient(180deg, var(--sky) 0 10px, transparent 10px),
      #0d0b16;
}

.mn-mine.is-shaking { animation: mn-shake 0.42s linear; }

@keyframes mn-shake {
  0%, 100% { transform: translate(0, 0); }
  20% { transform: translate(-4px, 2px); }
  40% { transform: translate(4px, -2px); }
  60% { transform: translate(-3px, -1px); }
  80% { transform: translate(2px, 2px); }
}

.mn-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;

  margin: 4px 0 10px;
}

.mn-tier {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  color: #fff;
  font-size: 13px;
  font-weight: 800;
}

.mn-depth { display: inline-flex; flex-direction: column; gap: 2px; }
.mn-depth i { width: 14px; height: 3px; background: rgba(255, 255, 255, 0.12); border-radius: 1px; }
.mn-depth i.on { background: #facc15; }

.mn-chip {
  padding: 3px 8px;

  border-radius: 99px;

  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.04em;
}

.mn-chip--rush { color: #1a1206; background: #facc15; }
.mn-chip--map { color: #0c1a33; background: #93c5fd; }
.mn-chip--combo { color: #fff; background: linear-gradient(90deg, #ef4444, #f59e0b); animation: mn-combo 0.35s ease-out; }

@keyframes mn-combo {
  from { transform: scale(1.35); }
  to { transform: scale(1); }
}

.mn-grid-wrap { position: relative; }

.mn-grid {
  display: grid;
  grid-template-columns: repeat(var(--cols), minmax(0, 1fr));
  gap: 3px;
}

.mn-flash {
  position: absolute;
  inset: 0;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  background: rgba(0, 0, 0, 0.55);
  border-radius: 6px;

  pointer-events: none;
  animation: mn-flash 1.6s ease forwards;
}

.mn-flash small { color: #a8a3b8; font-size: 12px; font-weight: 800; letter-spacing: 0.2em; text-transform: uppercase; }
.mn-flash b { color: #fff; font-family: var(--font-display); font-size: clamp(22px, 4vw, 34px); }

@keyframes mn-flash {
  0% { opacity: 0; }
  15%, 70% { opacity: 1; }
  100% { opacity: 0; }
}

/* ---------- Блоки: пиксельные текстуры из градиентов ---------- */

.mn-cell {
  --base: #7d8288;
  --ore: transparent;

  position: relative;

  aspect-ratio: 1;
  min-width: 0;

  display: grid;
  place-items: center;

  padding: 0;

  background:
      radial-gradient(circle at 28% 30%, var(--ore) 0 11%, transparent 12%),
      radial-gradient(circle at 70% 42%, var(--ore) 0 10%, transparent 11%),
      radial-gradient(circle at 42% 72%, var(--ore) 0 12%, transparent 13%),
      radial-gradient(circle at 78% 78%, var(--ore) 0 8%, transparent 9%),
      linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(0, 0, 0, 0.18)),
      var(--base);
  border: 0;
  border-radius: 3px;
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25), inset 0 2px 0 rgba(255, 255, 255, 0.08);

  font: inherit;
  image-rendering: pixelated;
}

.mn-cell--hidden {
  background:
      repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.03) 0 4px, transparent 4px 8px),
      #2b2723;
}

.mn-mine--t2 .mn-cell--hidden { background: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.03) 0 4px, transparent 4px 8px), #23262b; }
.mn-mine--t3 .mn-cell--hidden { background: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.03) 0 4px, transparent 4px 8px), #3a1616; }
.mn-mine--t4 .mn-cell--hidden { background: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.04) 0 4px, transparent 4px 8px), #2a2640; }

/* Пещеры */
.mn-cell--dirt { --base: #7c4f2c; --ore: #5b3a1f; }
.mn-cell--stone { --base: #7d8288; --ore: #6b7076; }
.mn-cell--coal { --ore: #18181b; }
.mn-cell--copper { --ore: #ea8a4f; }
.mn-cell--iron { --ore: #e5c7a8; }
.mn-cell--redstone { --ore: #ef4444; }
.mn-cell--lapis { --ore: #2563eb; }
.mn-cell--gold { --ore: #facc15; }
.mn-cell--diamond { --ore: #5eead4; box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25), 0 0 10px rgba(94, 234, 212, 0.5); }
.mn-cell--emerald { --ore: #22c55e; box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25), 0 0 10px rgba(34, 197, 94, 0.5); }

/* Глубинный сланец */
.mn-cell--deepslate { --base: #4a4e55; --ore: #3a3d43; }
.mn-mine--t2 .mn-cell--iron, .mn-mine--t2 .mn-cell--redstone, .mn-mine--t2 .mn-cell--lapis,
.mn-mine--t2 .mn-cell--gold, .mn-mine--t2 .mn-cell--diamond, .mn-mine--t2 .mn-cell--emerald { --base: #4a4e55; }
.mn-cell--amethyst { --base: #4a4e55; --ore: #c084fc; box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25), 0 0 10px rgba(192, 132, 252, 0.45); }

/* Незер */
.mn-cell--netherrack { --base: #7a2e2e; --ore: #5c1f1f; }
.mn-cell--quartz { --base: #7a2e2e; --ore: #f5f5f4; }
.mn-cell--nether_gold { --base: #7a2e2e; --ore: #facc15; }
.mn-mine--t3 .mn-cell--gold { --base: #7a2e2e; }
.mn-cell--glowstone { --base: #b98a2a; --ore: #fde68a; box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.2), 0 0 12px rgba(253, 230, 138, 0.45); }
.mn-cell--debris {
  --base: #4a3a33;
  --ore: #8b6b5c;
  background:
      repeating-linear-gradient(0deg, rgba(0, 0, 0, 0.25) 0 3px, transparent 3px 7px),
      radial-gradient(circle at 50% 50%, #8b6b5c 0 30%, transparent 32%),
      #4a3a33;
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.3), 0 0 12px rgba(249, 115, 22, 0.45);
}

/* Энд */
.mn-cell--end_stone { --base: #dcdba5; --ore: #c4c28a; }
.mn-cell--chorus { --base: #6b4f7a; --ore: #d8b4fe; }
.mn-cell--purpur {
  background:
      linear-gradient(90deg, rgba(0, 0, 0, 0.18) 1px, transparent 1px) 0 0 / 50% 50%,
      linear-gradient(0deg, rgba(0, 0, 0, 0.18) 1px, transparent 1px) 0 0 / 50% 50%,
      #a77bca;
}
.mn-cell--shulker { --base: #7c3aed; --ore: #fcd34d; box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25), 0 0 12px rgba(167, 139, 250, 0.55); }

/* Особые */
.mn-cell--bedrock { --base: #27272a; --ore: #0a0a0a; }
.mn-cell--obsidian { --base: #1a1225; --ore: #3b2a55; }
.mn-cell--gravel {
  background:
      radial-gradient(circle at 20% 25%, #6b6560 0 9%, transparent 10%),
      radial-gradient(circle at 65% 30%, #a8a29e 0 8%, transparent 9%),
      radial-gradient(circle at 35% 70%, #57534e 0 10%, transparent 11%),
      radial-gradient(circle at 80% 75%, #a8a29e 0 8%, transparent 9%),
      #8a8580;
}
.mn-cell--lava {
  background:
      radial-gradient(circle at 30% 30%, #fde047 0 10%, transparent 12%),
      radial-gradient(circle at 70% 65%, #fde047 0 8%, transparent 10%),
      linear-gradient(135deg, #f97316, #dc2626 60%, #f59e0b);
  background-size: 100% 100%, 100% 100%, 200% 200%;
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.2), 0 0 12px rgba(249, 115, 22, 0.55);
  animation: mn-lava 3s ease-in-out infinite;
}

@keyframes mn-lava {
  0%, 100% { background-position: 0 0, 0 0, 0% 0%; }
  50% { background-position: 0 0, 0 0, 100% 100%; }
}

.mn-cell--tnt {
  background:
      linear-gradient(180deg, transparent 38%, #f5f5f4 38% 62%, transparent 62%),
      repeating-linear-gradient(90deg, #dc2626 0 6px, #b91c1c 6px 9px);
}

.mn-cell--chest, .mn-cell--mimic, .mn-cell--villager, .mn-cell--relic { --base: #5a554f; --ore: transparent; }
.mn-mine--t2 :is(.mn-cell--chest, .mn-cell--mimic, .mn-cell--villager, .mn-cell--relic) { --base: #3a3d43; }
.mn-mine--t3 :is(.mn-cell--chest, .mn-cell--mimic, .mn-cell--villager, .mn-cell--relic) { --base: #5c1f1f; }
.mn-mine--t4 :is(.mn-cell--chest, .mn-cell--mimic, .mn-cell--villager, .mn-cell--relic) { --base: #c4c28a; }
.mn-cell--relic { box-shadow: inset 0 0 0 2px rgba(250, 204, 21, 0.4); }

.mn-cell__sprite { width: 62% !important; height: 62% !important; }
.mn-cell__hint { position: absolute; top: 3px; right: 3px; }

/* Подсказки фонаря */
.has-hint--danger { box-shadow: inset 0 0 0 2px rgba(249, 115, 22, 0.55), inset 0 -10px 14px rgba(249, 115, 22, 0.25); color: #fb923c; }
.has-hint--wall { background: repeating-linear-gradient(45deg, #0a0a0a 0 3px, #27272a 3px 6px); }
.has-hint--special { box-shadow: inset 0 0 0 2px rgba(250, 204, 21, 0.45); color: #fde047; }
.mn-cell.is-ghost { opacity: 0.5; }
.mn-cell.is-ghost.is-reach { opacity: 0.7; }

/* Состояния */
.mn-cell.is-reach { cursor: pointer; outline: 2px solid rgba(250, 204, 21, 0.35); outline-offset: -2px; }
.mn-cell.is-reach:hover { outline-color: #facc15; filter: brightness(1.25); }
.mn-cell.is-aim { outline-color: rgba(239, 68, 68, 0.6); }
.mn-cell.is-aim:hover { outline-color: #ef4444; }
.mn-cell.is-closed-chest, .mn-cell.is-trader { cursor: pointer; animation: mn-bob 1.6s ease-in-out infinite; }
.mn-cell.is-closed-chest:hover, .mn-cell.is-trader:hover { filter: brightness(1.3); }
.mn-cell.is-picked { outline: 2px solid #5eead4; outline-offset: -2px; }
.mn-cell:disabled:not(.is-reach) { cursor: default; }
.mn-grid.is-done .mn-cell--hidden { opacity: 0.55; }

@keyframes mn-bob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-2px); }
}

.mn-pop {
  position: absolute;
  left: 50%;
  top: 10%;
  z-index: 2;

  color: #fde047;
  font-size: 14px;
  font-weight: 900;
  white-space: nowrap;
  text-shadow: 0 2px 0 rgba(0, 0, 0, 0.7);

  pointer-events: none;
  animation: mn-pop 1s ease-out forwards;
}

.mn-pop--double { color: #5eead4; font-size: 15px; }
.mn-pop--bad { color: #f87171; }

@keyframes mn-pop {
  from { opacity: 1; transform: translate(-50%, 0) scale(0.8); }
  to { opacity: 0; transform: translate(-50%, -36px) scale(1.15); }
}

/* ---------- Житель, инструменты, журнал ---------- */

.mn-trader {
  position: relative;

  display: flex;
  align-items: flex-start;
  gap: 12px;

  margin-top: 12px;
  padding: 12px;

  background: rgba(21, 128, 61, 0.12);
  border: 1px solid rgba(34, 197, 94, 0.35);
  border-radius: 12px;
}

.mn-trader__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
.mn-trader__body > b { color: #bbf7d0; font-size: 13px; }
.mn-trader__offers { display: flex; flex-wrap: wrap; gap: 6px; }

.mn-offer {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  min-height: 34px;
  padding: 0 10px;

  color: #e6e3ee;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;

  font: inherit;
  font-size: 12px;

  cursor: pointer;
}

.mn-offer b { color: #fde68a; }
.mn-offer:hover:not(:disabled) { border-color: #86efac; }
.mn-offer:disabled { opacity: 0.45; cursor: default; }

.mn-trader__close {
  position: absolute;
  top: 6px;
  right: 6px;

  display: grid;
  place-items: center;

  width: 24px;
  height: 24px;

  color: #8a8598;
  background: none;
  border: 0;

  cursor: pointer;
}

.mn-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }

.mn-log {
  margin: 12px 0 0;
  padding: 0;
  list-style: none;

  display: flex;
  flex-direction: column;
  gap: 3px;
}

.mn-log li { color: #a8a3b8; font-size: 12.5px; animation: mn-in 0.3s ease-out; }
.mn-log li:first-child { color: #e6e3ee; font-weight: 700; }
.mn-log li.is-good:first-child { color: #86efac; }
.mn-log li.is-bad:first-child { color: #fca5a5; }
.mn-log li:nth-child(n + 4) { opacity: 0.5; }

@keyframes mn-in {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: none; }
}

.mn-start {
  min-height: 340px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 18px;

  text-align: center;
}

.mn-start__art {
  width: 180px;

  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
}

.mn-start__note { max-width: 380px; margin: 0; color: #8a8598; font-size: 12.5px; line-height: 1.5; }

/* ---------- Боковая панель и блок под шахтой ---------- */

.mn-side { display: flex; flex-direction: column; gap: 12px; }

.mn-hub {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}

.mn-panel--wide { grid-column: 1 / -1; }

.mn-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 14px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

.mn-panel h3 {
  display: flex;
  align-items: center;
  gap: 7px;

  margin: 0;

  color: #8a8598;

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.mn-badge {
  min-width: 18px;
  padding: 1px 5px;

  color: #052e16;
  background: #86efac;
  border-radius: 99px;

  font-size: 10px;
  text-align: center;
  letter-spacing: 0;
}

.mn-meter { display: grid; grid-template-columns: 1fr auto; gap: 6px; }
.mn-meter span, .mn-coins span { color: #8a8598; font-size: 12px; font-weight: 700; }
.mn-meter b { color: #fff; font-size: 13px; }
.mn-meter i { grid-column: 1 / -1; height: 8px; overflow: hidden; background: rgba(255, 255, 255, 0.07); border-radius: 99px; }
.mn-meter em { display: block; height: 100%; background: linear-gradient(90deg, #f59e0b, #facc15); border-radius: inherit; transition: width 0.25s ease; }

.mn-coins { display: flex; justify-content: space-between; align-items: baseline; }
.mn-coins b { color: #fde68a; font-size: 24px; font-weight: 900; }

.mn-result {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 12px;

  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.35);
  border-radius: 12px;
}

.mn-result strong { color: #86efac; font-size: 18px; }
.mn-result > span { color: #a8a3b8; font-size: 12px; }
.mn-result dl { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; margin: 0; }
.mn-result dl div { display: flex; justify-content: space-between; gap: 6px; font-size: 12px; }
.mn-result dt { color: #8a8598; }
.mn-result dd { margin: 0; color: #e6e3ee; font-weight: 800; }

.mn-tabs { display: flex; flex-wrap: wrap; gap: 4px; }

.mn-tabs button {
  padding: 4px 8px;

  color: #a8a3b8;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid transparent;
  border-radius: 7px;

  font: inherit;
  font-size: 11.5px;
  font-weight: 700;

  cursor: pointer;
}

.mn-tabs button.on { color: #fff; border-color: rgba(250, 204, 21, 0.5); }

.mn-legend { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; }
.mn-legend li { display: grid; grid-template-columns: 20px 1fr auto; align-items: center; gap: 8px; font-size: 12.5px; color: #d6d2e2; }
.mn-legend .mn-cell, .mn-specials .mn-cell { width: 20px; }
.mn-legend b { color: #fde68a; font-size: 12px; }

.mn-specials { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 6px; }
.mn-specials li { display: grid; grid-template-columns: 20px 1fr; align-items: center; gap: 8px; color: #8a8598; font-size: 11.5px; }
.mn-specials b { color: #d6d2e2; }

.mn-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  min-height: 42px;
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

.mn-btn--primary { color: #1a1206; background: linear-gradient(135deg, #fde047, #f59e0b); border-color: transparent; }
.mn-btn--descend { color: #fff; background: linear-gradient(135deg, #7c3aed, #4c1d95); border-color: transparent; }
.mn-btn--tool.is-on { color: #fff; background: rgba(239, 68, 68, 0.25); border-color: #ef4444; }
.mn-btn:disabled { opacity: 0.55; cursor: default; }

.mn-error { margin: 10px 0 0; color: #fca5a5; font-size: 13px; text-align: center; }

@media (max-width: 1100px) and (min-width: 961px) {
  .mn-hub { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 960px) {
  .mn-head { grid-template-columns: minmax(0, 1fr); }
  .mn-head__stats div { flex: 1; min-width: 0; }
  .mn-layout { grid-template-columns: minmax(0, 1fr); }
  .mn-hub { grid-template-columns: minmax(0, 1fr); }
  .mn-mine { padding: 8px; }
  .mn-grid { gap: 2px; }
  .mn-tools .mn-btn { flex: 1 1 auto; }
}

@media (prefers-reduced-motion: reduce) {
  .mn-mine.is-shaking, .mn-cell--lava, .mn-cell.is-closed-chest, .mn-cell.is-trader, .mn-rush, .mn-chip--combo { animation: none; }
}
</style>
