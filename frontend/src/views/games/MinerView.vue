<script setup>
/**
 * Мини-игра «Шахтёр»: копаешь шахту вниз, собираешь руду — монеты
 * на счёт. Где руда, знает только сервер: блок виден после удара.
 * Копать можно только рядом с открытыми блоками. Ударов кирки на забег
 * ограничено, TNT бесплатно взрывает соседей, бедрок не копается.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'

const auth = useAuthStore()

const BLOCKS = {
  dirt: 'Земля',
  stone: 'Камень',
  coal: 'Уголь',
  copper: 'Медь',
  iron: 'Железо',
  redstone: 'Редстоун',
  lapis: 'Лазурит',
  gold: 'Золото',
  diamond: 'Алмаз',
  emerald: 'Изумруд',
  tnt: 'TNT',
  bedrock: 'Бедрок',
}

const state = ref(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const result = ref(null)
const pops = ref([])
const now = ref(Date.now())

const run = computed(() => state.value?.run ?? null)
const cols = computed(() => state.value?.cols ?? 8)

const cooldownLeft = computed(() => {
  const until = state.value?.cooldown_until
  if (!until) return 0
  return Math.max(0, Math.ceil((new Date(until).getTime() - now.value) / 1000))
})

/** Клетка доступна, если рядом (по сторонам) есть открытая. */
function reachable(i) {
  const cells = run.value?.cells
  if (!cells || cells[i] !== null) return false
  const r = Math.floor(i / cols.value)
  const c = i % cols.value
  return [[r - 1, c], [r + 1, c], [r, c - 1], [r, c + 1]].some(([nr, nc]) =>
    nr >= 0 && nc >= 0 && nc < cols.value && nr < (state.value?.rows ?? 7) && cells[nr * cols.value + nc] !== null)
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
  busy.value = true
  error.value = ''
  result.value = null
  try {
    state.value = await api.post('/games/miner/start')
  } catch (e) {
    error.value = e.message || 'Не получилось начать.'
  } finally {
    busy.value = false
  }
}

async function dig(i) {
  if (busy.value || !reachable(i)) return
  busy.value = true
  error.value = ''
  try {
    const data = await api.post('/games/miner/dig', { cell: i })
    for (const f of data.found) {
      const value = data.values?.[f.block] ?? 0
      if (value > 0) {
        const id = `${f.cell}-${Date.now()}`
        pops.value.push({ id, cell: f.cell, text: `+${value}` })
        setTimeout(() => { pops.value = pops.value.filter((p) => p.id !== id) }, 900)
      }
    }

    // После последнего удара сервер уже закрыл забег — показываем итог на открытой шахте
    if (data.finished) {
      const cells = [...(run.value?.cells ?? [])]
      for (const f of data.found) cells[f.cell] = f.block
      result.value = data.finished
      state.value = { ...data, run: { ...run.value, cells, energy: 0, coins: data.finished.coins, done: true } }
      if (auth.user) auth.user.apex_coins = data.finished.balance
    } else {
      state.value = data
    }
  } catch (e) {
    error.value = e.message || 'Не получилось копнуть.'
  } finally {
    busy.value = false
  }
}

async function cashOut() {
  busy.value = true
  try {
    const data = await api.post('/games/miner/cash-out')
    result.value = data.finished
    state.value = { ...data, run: run.value ? { ...run.value, done: true } : null }
    if (auth.user) auth.user.apex_coins = data.finished.balance
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

async function again() {
  result.value = null
  await load()
}

function popsFor(i) {
  return pops.value.filter((p) => p.cell === i)
}

let tick = null
onMounted(() => {
  load()
  tick = setInterval(() => { now.value = Date.now() }, 1000)
})
onUnmounted(() => clearInterval(tick))
</script>

<template>
  <div class="mn">
    <header class="mn-head">
      <div>
        <span class="mn-head__kicker"><AppIcon icon="gem" :size="14" /> Мини-игра</span>
        <h1>Шахтёр</h1>
        <p>
          Копай вниз — чем глубже, тем богаче руда. Копать можно только рядом с открытыми блоками,
          у кирки {{ state?.energy ?? 24 }} ударов. TNT бесплатно взрывает соседей, бедрок не копается.
          Вся добыча — в ApexCoin. Между забегами кирке нужно остыть 10 минут.
        </p>
      </div>

      <dl class="mn-head__stats">
        <div><dt>Рекорд</dt><dd>{{ state?.best ?? 0 }}</dd></div>
        <div><dt>Всего добыто</dt><dd>{{ state?.total ?? 0 }}</dd></div>
        <div><dt>Забегов</dt><dd>{{ state?.runs ?? 0 }}</dd></div>
      </dl>
    </header>

    <PageLoader v-if="loading" />

    <div v-else class="mn-layout">
      <section class="mn-mine">
        <!-- Забег идёт или только что закончился -->
        <div
            v-if="run"
            class="mn-grid"
            :class="{ 'is-done': run.done }"
            :style="{ '--cols': cols }"
        >
          <button
              v-for="(block, i) in run.cells"
              :key="i"
              type="button"
              class="mn-cell"
              :class="[block ? `mn-cell--${block}` : 'mn-cell--hidden', { 'is-reach': !run.done && reachable(i) }]"
              :disabled="run.done || busy || !reachable(i)"
              :title="block ? `${BLOCKS[block]}${state.values?.[block] ? ` · +${state.values[block]}` : ''}` : 'Копнуть'"
              @click="dig(i)"
          >
            <span v-for="p in popsFor(i)" :key="p.id" class="mn-pop">{{ p.text }}</span>
          </button>
        </div>

        <div v-else class="mn-start">
          <div class="mn-start__art" aria-hidden="true">
            <i class="mn-cell mn-cell--stone"></i><i class="mn-cell mn-cell--diamond"></i><i class="mn-cell mn-cell--gold"></i>
            <i class="mn-cell mn-cell--coal"></i><i class="mn-cell mn-cell--emerald"></i><i class="mn-cell mn-cell--stone"></i>
          </div>
          <button
              type="button"
              class="mn-btn mn-btn--primary"
              :disabled="busy || cooldownLeft > 0"
              @click="start"
          >
            {{ cooldownLeft > 0 ? `Кирка остывает · ${cooldownLeft} с` : 'Спуститься в шахту' }}
          </button>
        </div>

        <p v-if="error" class="mn-error">{{ error }}</p>
      </section>

      <aside class="mn-side">
        <div class="mn-panel">
          <div class="mn-meter">
            <span>Удары кирки</span>
            <b>{{ run && !run.done ? run.energy : 0 }} / {{ state?.energy ?? 24 }}</b>
            <i><em :style="{ width: `${run && !run.done ? (run.energy / (state?.energy || 24)) * 100 : 0}%` }"></em></i>
          </div>
          <div class="mn-coins">
            <span>Добыто за забег</span>
            <b>₳ {{ run?.coins ?? 0 }}</b>
          </div>

          <button
              v-if="run && !run.done"
              type="button"
              class="mn-btn"
              :disabled="busy"
              @click="cashOut"
          >Подняться и забрать</button>

          <div v-if="result" class="mn-result">
            <strong>+{{ result.coins }} ApexCoin</strong>
            <span>Добыча на счёте. Баланс: {{ result.balance }}</span>
            <button type="button" class="mn-btn mn-btn--primary" @click="again">Ещё забег</button>
          </div>
        </div>

        <div class="mn-panel">
          <h3>Что сколько стоит</h3>
          <ul class="mn-legend">
            <li v-for="(label, id) in BLOCKS" :key="id">
              <i class="mn-cell" :class="`mn-cell--${id}`"></i>
              <span>{{ label }}</span>
              <b>{{ state?.values?.[id] ? `+${state.values[id]}` : '—' }}</b>
            </li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.mn {
  width: min(1100px, calc(100% - 32px));
  margin: 28px auto 60px;

  display: flex;
  flex-direction: column;
  gap: 20px;
}

.mn-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 24px;

  padding-bottom: 22px;

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

.mn-head p { max-width: 560px; margin: 0; color: #8a8598; font-size: 14px; line-height: 1.55; }

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

.mn-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}

.mn-mine {
  padding: 14px;

  background:
      linear-gradient(180deg, #5b8c3a 0 10px, transparent 10px),
      #1a1612;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

.mn-grid {
  display: grid;
  grid-template-columns: repeat(var(--cols), minmax(0, 1fr));
  gap: 3px;
}

/* ---------- Блоки: пиксельные текстуры из градиентов ---------- */

.mn-cell {
  --base: #6b7280;
  --ore: transparent;

  position: relative;

  aspect-ratio: 1;
  min-width: 0;

  display: block;

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
  --base: #3a3530;
  background:
      repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.03) 0 4px, transparent 4px 8px),
      #2b2723;
}

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
.mn-cell--bedrock { --base: #27272a; --ore: #0a0a0a; }

.mn-cell--tnt {
  background:
      linear-gradient(180deg, transparent 38%, #f5f5f4 38% 62%, transparent 62%),
      repeating-linear-gradient(90deg, #dc2626 0 6px, #b91c1c 6px 9px);
}

.mn-cell.is-reach {
  cursor: pointer;
  outline: 2px solid rgba(250, 204, 21, 0.35);
  outline-offset: -2px;
}

.mn-cell.is-reach:hover {
  outline-color: #facc15;
  filter: brightness(1.25);
}

.mn-cell:disabled:not(.is-reach) { cursor: default; }
.mn-grid.is-done .mn-cell--hidden { opacity: 0.55; }

.mn-pop {
  position: absolute;
  left: 50%;
  top: 10%;
  z-index: 2;

  color: #fde047;
  font-size: 14px;
  font-weight: 900;
  text-shadow: 0 2px 0 rgba(0, 0, 0, 0.6);

  pointer-events: none;
  animation: mn-pop 0.9s ease-out forwards;
}

@keyframes mn-pop {
  from { opacity: 1; transform: translate(-50%, 0) scale(0.8); }
  to { opacity: 0; transform: translate(-50%, -34px) scale(1.15); }
}

.mn-start {
  min-height: 300px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 18px;
}

.mn-start__art {
  width: 180px;

  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3px;
}

.mn-side { display: flex; flex-direction: column; gap: 12px; }

.mn-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 14px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

.mn-panel h3 { margin: 0; color: #8a8598; font-size: 11px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }

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
  gap: 6px;

  padding: 12px;

  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.35);
  border-radius: 12px;
}

.mn-result strong { color: #86efac; font-size: 18px; }
.mn-result span { color: #a8a3b8; font-size: 12px; }

.mn-legend { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; }
.mn-legend li { display: grid; grid-template-columns: 20px 1fr auto; align-items: center; gap: 8px; font-size: 12.5px; color: #d6d2e2; }
.mn-legend .mn-cell { width: 20px; }
.mn-legend b { color: #fde68a; font-size: 12px; }

.mn-btn {
  min-height: 42px;
  padding: 0 18px;

  color: #e6e3ee;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 11px;

  font: inherit;
  font-size: 14px;
  font-weight: 800;

  cursor: pointer;
}

.mn-btn--primary { color: #1a1206; background: linear-gradient(135deg, #fde047, #f59e0b); border-color: transparent; }
.mn-btn:disabled { opacity: 0.55; cursor: default; }

.mn-error { margin: 10px 0 0; color: #fca5a5; font-size: 13px; text-align: center; }

@media (max-width: 860px) {
  .mn-head { grid-template-columns: minmax(0, 1fr); }
  .mn-head__stats div { flex: 1; min-width: 0; }
  .mn-layout { grid-template-columns: minmax(0, 1fr); }
  .mn-mine { padding: 8px; }
  .mn-grid { gap: 2px; }
}
</style>
