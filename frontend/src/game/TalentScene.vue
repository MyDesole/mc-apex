<script setup>
/**
 * Академия: древо талантов питомца. Очко даёт каждый уровень; три общие
 * ветки (Ярость, Бастион, Дух) и «Путь стихии» своего питомца. Ярусы
 * открываются вложенными в ветку очками. Сброс стоит ApexCoin. Всё считает
 * сервер.
 */
import { computed, onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { ELEMENTS } from '@/data/players/petArena.js'

const roster = ref([])
const tree = ref(null)
const picked = ref(null)
const hover = ref(null)
const error = ref('')
const busy = ref(false)

const EFFECT_NAMES = {
  atk_pct: ['Атака', '%'], def_pct: ['Защита', '%'], hp_pct: ['Здоровье', '%'], spd: ['Скорость', ''],
  crit: ['Крит', '%'], pierce: ['Пробитие', '%'], opener: ['Первый удар', '%'], execute: ['Добивание', '%'],
  rage: ['Ярость', ''], vamp: ['Вампиризм', '%'], bulwark: ['Снижение урона', '%'], regen: ['Регенерация', '%'],
  thorns: ['Шипы', '%'], dodge: ['Уклонение', '%'], phoenix: ['Феникс', '%'], stun: ['Оглушение', '%'],
  skill_power: ['Умение', '%'], ult_power: ['Ультимейт', '%'], heal_power: ['Лечение', '%'],
  skill_cd: ['Перезарядка', ' р.'], element_power: ['Мастерство', '%'], sig: ['Дар стихии', '%'],
}
const TIERS = [1, 2, 3, 4]
const ROMAN = { 1: 'I', 2: 'II', 3: 'III', 4: 'IV' }

const summary = computed(() => Object.entries(tree.value?.effects ?? {}).map(([k, v]) => ({ key: k, text: `${EFFECT_NAMES[k]?.[0] ?? k} +${v}${EFFECT_NAMES[k]?.[1] ?? ''}` })))
const element = computed(() => ELEMENTS[tree.value?.pet.element] ?? { name: '', color: '#fbbf24' })
const tierTalents = (branch, tier) => branch.talents.filter((t) => t.tier === tier)
const tierOpen = (branch, tier) => branch.spent >= (tree.value.gates[tier] ?? 0)
const hasTier = (branch, tier) => branch.talents.some((t) => t.tier === tier)

async function loadRoster() {
  try {
    roster.value = (await api.get('/talents')).pets ?? []
    if (!picked.value && roster.value.length) await pick(roster.value[0].inventory_id)
  } catch (e) {
    error.value = e.message || 'Не удалось открыть Академию.'
  }
}

async function pick(id) {
  picked.value = id
  hover.value = null
  try {
    tree.value = await api.get(`/talents/${id}`)
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить древо.'
  }
}

async function refreshRoster() {
  roster.value = (await api.get('/talents')).pets ?? roster.value
}

async function learn(branch, talent) {
  if (busy.value || !talent.unlocked || talent.rank >= talent.max || !tree.value.points.free) return
  busy.value = true
  try {
    tree.value = await api.post(`/talents/${picked.value}/learn`, { talent: talent.key })
    error.value = ''
    await refreshRoster()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

async function respec() {
  const ok = await confirmDialog(`Сбросить все таланты питомца за ${tree.value.respec_cost.toLocaleString('ru-RU')} ApexCoin?`, { title: 'Сброс талантов', confirmText: 'Сбросить' })
  if (!ok) return
  busy.value = true
  try {
    tree.value = await api.post(`/talents/${picked.value}/respec`)
    await refreshRoster()
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

function describe(branch, talent) {
  hover.value = {
    ...talent,
    color: branch.color,
    lock: talent.unlocked ? '' : `Ярус откроется, когда в ветке будет вложено ${talent.gate} очков (сейчас ${branch.spent}).`,
  }
}

onMounted(loadRoster)
</script>

<template>
  <div class="g-scene ta">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="!roster.length && !error" class="g-hint">У тебя пока нет питомцев. Заведи питомца, и он начнёт копить очки талантов.</p>

    <div v-if="roster.length" class="ta-roster">
      <button v-for="p in roster" :key="p.inventory_id" type="button" class="ta-roster__pet" :class="{ 'is-on': p.inventory_id === picked }"
              :style="{ '--ec': ELEMENTS[p.element]?.color }" :title="`${p.name} · ур. ${p.level} · свободно очков: ${p.free}`" @click="pick(p.inventory_id)">
        <PetFigure :species="p.species" :traits="p.traits" :size="40" :backdrop="false" />
        <i class="ta-roster__lvl">{{ p.level }}</i>
        <b v-if="p.free" class="ta-roster__free">{{ p.free }}</b>
      </button>
    </div>

    <template v-if="tree">
      <header class="ta-head" :style="{ '--ec': element.color }">
        <span class="ta-head__pedestal"><PetFigure :species="tree.pet.species" :traits="tree.pet.traits" :size="72" :backdrop="false" /></span>
        <div class="ta-head__info">
          <h3>{{ tree.pet.name }}</h3>
          <span class="ta-head__tags">
            <span class="ta-tag" :style="{ '--tc': element.color }">{{ element.name }}</span>
            <span class="ta-tag">Уровень {{ tree.pet.level }}</span>
          </span>
          <p v-if="summary.length" class="ta-head__fx"><i v-for="s in summary" :key="s.key">{{ s.text }}</i></p>
          <p v-else class="ta-head__fx ta-head__fx--empty">Таланты не выбраны: нажми на значок, чтобы вложить очко.</p>
        </div>
        <div class="ta-head__side">
          <span class="ta-orb" :class="{ 'is-empty': !tree.points.free }"><b>{{ tree.points.free }}</b><small>свободно из {{ tree.points.total }}</small></span>
          <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="busy || !tree.points.spent" @click="respec">Сбросить · {{ tree.respec_cost.toLocaleString('ru-RU') }} ₳</button>
        </div>
      </header>

      <div class="ta-branches">
        <section v-for="b in tree.branches" :key="b.key" class="ta-branch" :style="{ '--bc': b.color }">
          <header class="ta-banner">
            <PixelSprite :sprite="b.sprite" :size="34" />
            <div><b>{{ b.name }}</b><small>{{ b.text }}</small></div>
            <i class="ta-banner__spent">{{ b.spent }}</i>
          </header>

          <div class="ta-tree">
            <template v-for="tier in TIERS" :key="tier">
              <div v-if="hasTier(b, tier)" class="ta-tier" :class="{ 'is-closed': !tierOpen(b, tier) }">
                <span class="ta-tier__ribbon">
                  Ярус {{ ROMAN[tier] }}<template v-if="tree.gates[tier]"> · от {{ tree.gates[tier] }} оч.</template>
                  <em v-if="!tierOpen(b, tier)">закрыт</em>
                </span>
                <div class="ta-nodes">
                  <button v-for="t in tierTalents(b, tier)" :key="t.key" type="button" class="ta-node"
                          :class="{ 'is-locked': !t.unlocked, 'is-max': t.rank >= t.max, 'is-learned': t.rank > 0, 'is-can': t.unlocked && t.rank < t.max && tree.points.free > 0 }"
                          :disabled="busy" @mouseenter="describe(b, t)" @focus="describe(b, t)" @mouseleave="hover = null" @click="learn(b, t)">
                    <span class="ta-node__frame"><PixelSprite :sprite="t.sprite" :size="44" /></span>
                    <span class="ta-node__name">{{ t.name }}</span>
                    <span class="ta-pips"><i v-for="n in t.max" :key="n" :class="{ on: n <= t.rank }" /></span>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </section>
      </div>

      <p class="ta-tip">
        <template v-if="hover">
          <b :style="{ color: hover.color }">{{ hover.name }}</b> <span class="ta-tip__rank">ранг {{ hover.rank }} из {{ hover.max }}</span><br>
          {{ hover.text }}<template v-if="hover.lock"><br><span class="ta-tip__lock">{{ hover.lock }}</span></template>
        </template>
        <template v-else>Наведи на талант, чтобы прочитать. Очко даёт каждый уровень питомца, а всё древо не поместится даже на 100 уровне: выбирай.</template>
      </p>
    </template>
  </div>
</template>

<style scoped>
.ta-roster { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.ta-roster__pet { position: relative; display: grid; place-items: center; width: 62px; height: 62px; padding: 0; cursor: pointer; background: #1c1209; border: 3px solid #4a2a10; box-shadow: inset 0 -6px 0 color-mix(in srgb, var(--ec, #fbbf24) 55%, #000); }
.ta-roster__pet:hover { border-color: #ffd45c; }
.ta-roster__pet.is-on { border-color: var(--ec, #ffd45c); background: #2c1d0e; box-shadow: 0 0 0 3px #ffd45c, inset 0 -6px 0 var(--ec, #ffd45c); }
.ta-roster__lvl { position: absolute; left: 3px; top: 2px; font-size: 10px; font-style: normal; font-weight: 800; text-shadow: 1px 1px 0 #000; }
.ta-roster__free { position: absolute; top: -7px; right: -7px; min-width: 20px; padding: 1px 4px; font-size: 11px; text-align: center; color: #fff; background: #e23b3b; border: 2px solid #fff; }

/* Шапка: питомец на постаменте, бонусы, очки в светящемся шаре */
.ta-head { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 16px; padding: 14px 18px; background: linear-gradient(135deg, #3a2412, #24160a); border: 4px solid #1b0f06; box-shadow: inset 0 4px 0 #6b4a1c, inset 0 0 40px color-mix(in srgb, var(--ec) 20%, transparent); }
.ta-head__pedestal { display: grid; place-items: center; width: 108px; height: 108px; background: radial-gradient(circle at 50% 60%, color-mix(in srgb, var(--ec) 45%, transparent), transparent 70%), #160d06; border: 4px solid #1b0f06; box-shadow: inset 0 -10px 0 #3a2412; }
.ta-head__info h3 { margin: 0 0 6px; font-size: 22px; color: #ffe9a8; text-shadow: 2px 2px 0 #4a2a10; }
.ta-head__tags { display: flex; gap: 6px; flex-wrap: wrap; }
.ta-tag { padding: 2px 10px; font-size: 12px; font-weight: 800; color: var(--tc, #e8dcc0); background: rgba(0, 0, 0, 0.4); border: 2px solid color-mix(in srgb, var(--tc, #6b5a45) 70%, #000); }
.ta-head__fx { display: flex; flex-wrap: wrap; gap: 4px 6px; margin: 10px 0 0; }
.ta-head__fx i { padding: 1px 8px; font-size: 11px; font-style: normal; font-weight: 700; color: #ffd45c; background: rgba(0, 0, 0, 0.35); border: 1px solid #6b4a1c; }
.ta-head__fx--empty { font-size: 12px; color: #c9a36a; }
.ta-head__side { display: grid; justify-items: center; gap: 10px; }
.ta-orb { display: grid; place-items: center; width: 92px; height: 92px; text-align: center; background: radial-gradient(circle at 35% 30%, #fff3b0, #f0a020 55%, #7a4a0a); border: 4px solid #1b0f06; border-radius: 50%; box-shadow: 0 0 22px 4px rgba(255, 190, 60, 0.55); animation: ta-glow 2s steps(2) infinite; }
.ta-orb b { font-size: 30px; line-height: 1; color: #3a1f00; text-shadow: 1px 1px 0 #ffe9a8; }
.ta-orb small { margin-top: -18px; font-size: 9px; font-weight: 800; color: #3a1f00; }
.ta-orb.is-empty { filter: grayscale(0.85) brightness(0.65); box-shadow: none; animation: none; }
@keyframes ta-glow { 50% { box-shadow: 0 0 30px 8px rgba(255, 190, 60, 0.75); } }

/* Ветки: широкие колонки с баннерами */
.ta-branches { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-top: 12px; }
.ta-branch { display: flex; flex-direction: column; background: #20150c; border: 4px solid #1b0f06; box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--bc) 35%, transparent); }
.ta-banner { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: linear-gradient(180deg, color-mix(in srgb, var(--bc) 55%, #1b0f06), color-mix(in srgb, var(--bc) 25%, #1b0f06)); border-bottom: 4px solid #1b0f06; }
.ta-banner b { display: block; font-size: 15px; color: #fff; text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.55); }
.ta-banner small { font-size: 11px; color: rgba(255, 255, 255, 0.78); }
.ta-banner__spent { margin-left: auto; min-width: 30px; padding: 3px 8px; font-size: 14px; font-style: normal; font-weight: 800; text-align: center; color: #fff; background: rgba(0, 0, 0, 0.45); border: 2px solid rgba(0, 0, 0, 0.6); }
.ta-tree { display: flex; flex-direction: column; padding: 8px 10px 14px; }
.ta-tier { position: relative; padding-top: 22px; }
/* Связка между ярусами: вертикальная нить, тусклая у закрытых */
.ta-tier + .ta-tier::before { content: ""; position: absolute; left: 50%; top: -6px; width: 6px; height: 30px; margin-left: -3px; background: var(--bc); box-shadow: 0 0 10px color-mix(in srgb, var(--bc) 70%, transparent); }
.ta-tier.is-closed + .ta-tier::before, .ta-tier.is-closed::before { background: #4a3a2a; box-shadow: none; }
.ta-tier__ribbon { position: absolute; left: 0; top: 0; padding: 1px 8px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: #e8dcc0; background: rgba(0, 0, 0, 0.45); border: 2px solid #3a2a1a; }
.ta-tier__ribbon em { margin-left: 6px; font-style: normal; color: #fca5a5; }
.ta-tier.is-closed .ta-tier__ribbon { color: #8a7a5a; }
.ta-nodes { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 8px; padding-top: 6px; }

/* Узел: восьмиугольная рамка, имя и «зёрна» рангов */
.ta-node { display: grid; justify-items: center; gap: 3px; width: 92px; padding: 0; color: #fff; font: inherit; cursor: pointer; background: none; border: 0; }
.ta-node__frame { display: grid; place-items: center; width: 70px; height: 70px; background: #120a05; border: 0; clip-path: polygon(30% 0, 70% 0, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0 70%, 0 30%); box-shadow: inset 0 0 0 4px #4a3a2a; transition: transform 0.12s steps(3); }
.ta-node:hover:not(:disabled) .ta-node__frame { transform: translateY(-3px); box-shadow: inset 0 0 0 4px var(--bc); }
.ta-node.is-learned .ta-node__frame { background: color-mix(in srgb, var(--bc) 22%, #120a05); box-shadow: inset 0 0 0 4px var(--bc); }
.ta-node.is-max .ta-node__frame { box-shadow: inset 0 0 0 4px #ffd45c, inset 0 0 18px color-mix(in srgb, var(--bc) 70%, transparent); }
.ta-node.is-can .ta-node__frame { animation: ta-pulse 1.4s steps(2) infinite; }
.ta-node.is-locked { filter: grayscale(1) brightness(0.5); cursor: not-allowed; }
@keyframes ta-pulse { 50% { box-shadow: inset 0 0 0 4px var(--bc), inset 0 0 16px var(--bc); } }
.ta-node__name { max-width: 92px; font-size: 11px; font-weight: 700; line-height: 1.15; text-align: center; text-shadow: 1px 1px 0 #000; }
.ta-pips { display: flex; gap: 2px; }
.ta-pips i { width: 8px; height: 8px; background: #1b0f06; border: 2px solid #4a3a2a; }
.ta-pips i.on { background: var(--bc); border-color: color-mix(in srgb, var(--bc) 60%, #000); box-shadow: 0 0 6px var(--bc); }

.ta-tip { position: sticky; bottom: 0; min-height: 56px; margin: 14px 0 0; padding: 10px 14px; font-size: 13px; line-height: 1.5; background: rgba(12, 8, 4, 0.96); border: 4px solid #1b0f06; box-shadow: inset 0 0 0 2px #4a2a10; }
.ta-tip b { font-size: 15px; }
.ta-tip__rank { margin-left: 6px; font-size: 11px; color: #c9a36a; }
.ta-tip__lock { color: #fca5a5; }
@media (max-width: 760px) {
  .ta-head { grid-template-columns: auto 1fr; }
  .ta-head__side { grid-column: 1 / -1; grid-auto-flow: column; justify-content: space-between; }
}
@media (prefers-reduced-motion: reduce) { .ta-orb, .ta-node.is-can .ta-node__frame { animation: none; } }
</style>
