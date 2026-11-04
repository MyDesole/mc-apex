<script setup>
/**
 * Академия: древо талантов питомца. Очко даёт каждый уровень; три ветки
 * (Ярость, Бастион, Дух) по восемь талантов, ярусы открываются вложенными
 * очками. Сброс стоит ApexCoin. Всё считает сервер.
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
  crit: ['Шанс крита', '%'], pierce: ['Пробитие', '%'], opener: ['Первый удар', '%'], execute: ['Добивание', '%'],
  rage: ['Ярость за удар', ''], vamp: ['Вампиризм', '%'], bulwark: ['Снижение урона', '%'], regen: ['Регенерация', '%'],
  thorns: ['Шипы', '%'], dodge: ['Уклонение', '%'], phoenix: ['Феникс', '%'], stun: ['Шанс оглушения', '%'],
  skill_power: ['Урон умения', '%'], ult_power: ['Урон ультимейта', '%'], heal_power: ['Лечение', '%'],
  skill_cd: ['Перезарядка умения', ' р.'], element_power: ['Мастерство стихии', '%'], sig: ['Дар стихии', '%'],
}

const summary = computed(() => Object.entries(tree.value?.effects ?? {}).map(([k, v]) => `${EFFECT_NAMES[k]?.[0] ?? k} +${v}${EFFECT_NAMES[k]?.[1] ?? ''}`))

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

async function learn(talent) {
  if (busy.value || !talent.unlocked || talent.rank >= talent.max || !tree.value.points.free) return
  busy.value = true
  try {
    tree.value = await api.post(`/talents/${picked.value}/learn`, { talent: talent.key })
    error.value = ''
    roster.value = (await api.get('/talents')).pets ?? roster.value
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
    roster.value = (await api.get('/talents')).pets ?? roster.value
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

function lockText(branch, talent) {
  return talent.unlocked ? '' : `Откроется, когда в ветке будет вложено ${talent.gate} очков (сейчас ${branch.spent}).`
}

const shown = computed(() => hover.value)

onMounted(loadRoster)
</script>

<template>
  <div class="g-scene ta">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="!roster.length && !error" class="g-hint">У тебя пока нет питомцев. Заведи питомца, и он начнёт копить очки талантов.</p>

    <div v-if="roster.length" class="g-hotbar ta-roster">
      <button v-for="p in roster" :key="p.inventory_id" type="button" class="g-slot" :class="{ 'is-on': p.inventory_id === picked }"
              :title="`${p.name} · ур. ${p.level} · свободно очков: ${p.free}`" @click="pick(p.inventory_id)">
        <PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" />
        <i class="g-slot__tag">{{ p.level }}</i>
        <b v-if="p.free" class="ta-roster__free">{{ p.free }}</b>
      </button>
    </div>

    <template v-if="tree">
      <header class="ta-head g-wood">
        <PetFigure :species="tree.pet.species" :traits="tree.pet.traits" :size="54" :backdrop="false" />
        <div>
          <h3 class="g-title">{{ tree.pet.name }}</h3>
          <small>Ур. {{ tree.pet.level }} · стихия «{{ ELEMENTS[tree.pet.element]?.name ?? tree.pet.element }}»</small>
        </div>
        <span class="g-chip g-chip--gold">Очки талантов: <b>{{ tree.points.free }}</b> из {{ tree.points.total }}</span>
        <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="busy || !tree.points.spent" @click="respec">Сбросить ({{ tree.respec_cost.toLocaleString('ru-RU') }} ₳)</button>
      </header>

      <p v-if="summary.length" class="ta-sum">{{ summary.join(' · ') }}</p>
      <p v-else class="g-hint">Таланты ещё не выбраны. Нажми на значок, чтобы вложить очко.</p>

      <div class="ta-branches">
        <section v-for="b in tree.branches" :key="b.key" class="ta-branch g-stone" :style="{ '--bc': b.color }">
          <h4><PixelSprite :sprite="b.sprite" :size="26" /> {{ b.name }} <small>{{ b.spent }} оч.</small></h4>
          <p class="ta-branch__text">{{ b.text }}</p>

          <div v-for="tier in [1, 2, 3, 4]" :key="tier" class="ta-tier">
            <span class="ta-tier__gate">ярус {{ tier }}<template v-if="tree.gates[tier]"> · от {{ tree.gates[tier] }}</template></span>
            <div class="ta-nodes">
              <button v-for="t in b.talents.filter((x) => x.tier === tier)" :key="t.key" type="button" class="ta-node"
                      :class="{ 'is-locked': !t.unlocked, 'is-max': t.rank >= t.max, 'is-learned': t.rank > 0 }"
                      :disabled="busy" @mouseenter="hover = { ...t, lock: lockText(b, t), color: b.color }" @focus="hover = { ...t, lock: lockText(b, t), color: b.color }"
                      @mouseleave="hover = null" @click="learn(t)">
                <PixelSprite :sprite="t.sprite" :size="38" />
                <i class="ta-node__rank">{{ t.rank }}/{{ t.max }}</i>
              </button>
            </div>
          </div>
        </section>
      </div>

      <p class="ta-tip">
        <template v-if="shown">
          <b :style="{ color: shown.color }">{{ shown.name }}</b> · ранг {{ shown.rank }} из {{ shown.max }}<br>
          {{ shown.text }}<template v-if="shown.lock"><br><span class="ta-tip__lock">{{ shown.lock }}</span></template>
        </template>
        <template v-else>Наведи на талант, чтобы прочитать. Очко даёт каждый уровень питомца, а всё древо не поместится даже на 100 уровне: выбирай.</template>
      </p>
    </template>
  </div>
</template>

<style scoped>
.ta-roster { margin-bottom: 8px; }
.ta-roster__free { position: absolute; top: -6px; right: -6px; min-width: 18px; padding: 0 4px; font-size: 11px; text-align: center; color: #fff; background: #e23b3b; border: 2px solid #fff; }
.ta-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 8px 14px; }
.ta-head small { font-size: 11px; color: #ffe9a8; }
.ta-head .g-chip { margin-left: auto; }
.ta-sum { margin: 8px 0; padding: 6px 10px; font-size: 12px; color: #ffd45c; background: rgba(0, 0, 0, 0.35); border: 3px solid #4a2a10; }
.ta-branches { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
.ta-branch { padding: 8px 10px; border-top: 4px solid var(--bc); }
.ta-branch h4 { display: flex; align-items: center; gap: 6px; margin: 0; font-size: 14px; color: var(--bc); }
.ta-branch h4 small { margin-left: auto; font-size: 11px; color: #e8dcc0; }
.ta-branch__text { margin: 2px 0 6px; font-size: 11px; color: #c9b9e8; }
.ta-tier { margin-top: 6px; }
.ta-tier__gate { font-size: 10px; color: #8a7a5a; text-transform: uppercase; letter-spacing: 0.5px; }
.ta-nodes { display: flex; gap: 6px; margin-top: 3px; }
.ta-node { position: relative; display: grid; place-items: center; width: 54px; height: 54px; padding: 0; cursor: pointer; background: #1c1209; border: 3px solid #4a2a10; box-shadow: inset 0 0 0 2px rgba(0, 0, 0, 0.5); }
.ta-node:hover:not(:disabled) { border-color: var(--bc); }
.ta-node.is-locked { filter: grayscale(1) brightness(0.55); cursor: not-allowed; }
.ta-node.is-learned { border-color: var(--bc); box-shadow: inset 0 0 12px color-mix(in srgb, var(--bc) 45%, transparent), inset 0 0 0 2px rgba(0, 0, 0, 0.5); }
.ta-node.is-max { box-shadow: 0 0 10px 1px var(--bc), inset 0 0 0 2px rgba(0, 0, 0, 0.5); }
.ta-node__rank { position: absolute; right: 1px; bottom: 0; font-size: 10px; font-style: normal; font-weight: 800; text-shadow: 1px 1px 0 #000, -1px -1px 0 #000; }
.ta-tip { position: sticky; bottom: 0; min-height: 52px; margin: 10px 0 0; padding: 8px 12px; font-size: 13px; background: rgba(12, 8, 4, 0.95); border: 3px solid #4a2a10; }
.ta-tip__lock { color: #fca5a5; }
@media (max-width: 760px) { .ta-branches { grid-template-columns: 1fr; } }
</style>
