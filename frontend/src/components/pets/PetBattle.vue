<script setup>
/**
 * Пошаговый бой на арене: два питомца на сцене выбранной арены, здоровье,
 * ярость, статусы, пять приёмов и журнал. Сервер считает раунд, здесь —
 * только показ: удар подсвечивается, урон всплывает над целью, а над
 * соперником видно, что он готовит.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import { ARENA_THEMES, ELEMENTS, STATUS_COLORS } from '@/data/players/petArena.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const props = defineProps({
  battle: { type: Object, required: true },
})

const emit = defineEmits(['update', 'close'])

const auth = useAuthStore()
const busy = ref(false)
const error = ref('')
const hit = ref({ me: null, foe: null })
const shake = ref(false)
const logEl = ref(null)

const me = computed(() => props.battle.me)
const foe = computed(() => props.battle.foe)
const over = computed(() => props.battle.status !== 'active')
const skill = computed(() => props.battle.skills?.[me.value?.element] ?? { name: 'Умение' })
const ultimate = computed(() => props.battle.ultimates?.[me.value?.element] ?? { name: 'Ультимейт' })
const rageMax = computed(() => props.battle.rage_max ?? 100)
const rageFull = computed(() => (me.value?.rage ?? 0) >= rageMax.value)
const stunned = computed(() => (me.value?.fx?.stun ?? 0) > 0)
const arena = computed(() => props.battle.arena ?? null)
const theme = computed(() => ARENA_THEMES[arena.value?.key] ?? ARENA_THEMES.colosseum)
const streak = computed(() => me.value?.streak ?? 0)

const SIDES = ['me', 'foe']

function pct(f) {
  return Math.max(0, Math.round((f.hp / f.max) * 100))
}

function hpColor(f) {
  const p = pct(f)
  return p > 50 ? '#4ade80' : p > 25 ? '#facc15' : '#f87171'
}

function ragePct(f) {
  return Math.min(100, Math.round(((f.rage ?? 0) / rageMax.value) * 100))
}

function statuses(f) {
  return Object.entries(f.fx ?? {}).map(([key, left]) => ({
    key,
    left,
    name: props.battle.statuses?.[key]?.name ?? key,
    color: STATUS_COLORS[key] ?? '#a8a3b8',
  }))
}

// Новые записи журнала — всплывающий урон над целью и тряска на ультимейте
watch(() => props.battle.log?.length, (now, before) => {
  const fresh = (props.battle.log ?? []).slice(before ?? 0)
  let delay = 0

  for (const entry of fresh) {
    if (entry.move === 'ultimate' || entry.target === 'both') {
      setTimeout(() => {
        shake.value = false
        requestAnimationFrame(() => { shake.value = true })
      }, delay)
    }

    if (entry.damage) {
      const targets = entry.target === 'both' ? SIDES
        : entry.target ? [entry.target]
          : [entry.who === 'me' ? 'foe' : 'me']

      for (const target of targets) {
        setTimeout(() => {
          hit.value = { ...hit.value, [target]: { value: entry.damage, crit: entry.crit, tick: entry.move === 'status', id: Math.random() } }
        }, delay)
      }
      delay += 420
    }
  }

  nextTick(() => { if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight })
})

async function move(kind) {
  busy.value = true
  error.value = ''
  try {
    const fresh = await api.post(`/pets/battles/${props.battle.id}/move`, { move: kind })
    emit('update', fresh)
    if (fresh.status !== 'active') auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

async function forfeit() {
  if (!await confirmDialog('Сдаться? Бой засчитается как поражение.')) return
  busy.value = true
  try {
    emit('update', await api.post(`/pets/battles/${props.battle.id}/forfeit`))
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section
      v-if="me && foe"
      class="pb"
      :class="{ 'is-over': over, 'is-shaking': shake }"
      :style="{ '--sky': theme.sky, '--ground': theme.ground, '--glow': theme.glow }"
  >
    <header class="pb__head">
      <span>Раунд {{ battle.round }}</span>
      <strong v-if="!over">{{ stunned ? 'Ты оглушён' : 'Твой ход' }}</strong>
      <strong v-else-if="battle.status === 'won'" class="pb__win">Победа!</strong>
      <strong v-else class="pb__lose">Поражение</strong>
      <button v-if="!over" type="button" class="pb__forfeit" :disabled="busy" @click="forfeit">Сдаться</button>
    </header>

    <div v-if="arena" class="pb__arena">
      <b>{{ arena.name }}</b>
      <span>{{ arena.about }}</span>
      <i v-if="arena.element" class="pb-el" :style="{ '--el': ELEMENTS[arena.element]?.color }">{{ ELEMENTS[arena.element]?.name }} +20%</i>
    </div>

    <div class="pb__field">
      <div v-for="side in SIDES" :key="side" class="pb-side" :class="`pb-side--${side}`">
        <div class="pb-side__info">
          <div class="pb-side__tags">
            <span class="pb-el" :style="{ '--el': ELEMENTS[battle[side].element]?.color }">{{ ELEMENTS[battle[side].element]?.name }}</span>
            <span v-if="side === 'foe' && foe.temper" class="pb-temper">{{ foe.temper }}</span>
          </div>
          <strong>{{ battle[side].name }}</strong>
          <small>Ур. {{ battle[side].level }}<template v-if="side === 'foe' && foe.owner"> · {{ foe.owner.username }}</template></small>

          <div class="pb-hp">
            <i :style="{ width: `${pct(battle[side])}%`, background: hpColor(battle[side]) }"></i>
            <span>{{ battle[side].hp }} / {{ battle[side].max }}</span>
          </div>
          <div class="pb-rage" :class="{ 'is-full': ragePct(battle[side]) >= 100 }" :title="`Ярость: ${battle[side].rage ?? 0} / ${rageMax}`">
            <i :style="{ width: `${ragePct(battle[side])}%` }"></i>
            <span>{{ ragePct(battle[side]) >= 100 ? 'Ярость!' : 'Ярость' }}</span>
          </div>

          <ul v-if="statuses(battle[side]).length" class="pb-fx">
            <li v-for="s in statuses(battle[side])" :key="s.key" :style="{ '--fx': s.color }">{{ s.name }} · {{ s.left }}</li>
          </ul>
        </div>

        <div class="pb-side__stage">
          <p v-if="side === 'foe' && battle.tell && !over" class="pb-tell" :class="`pb-tell--${battle.tell.kind}`">
            {{ battle.tell.text }}
          </p>
          <span
              v-if="hit[side]"
              :key="hit[side].id"
              class="pb-dmg"
              :class="{ 'is-crit': hit[side].crit, 'is-tick': hit[side].tick }"
          >−{{ hit[side].value }}</span>
          <PetFigure
              :species="battle[side].species"
              :traits="battle[side].traits"
              :size="88"
              :flip="side === 'foe'"
              :class="{ 'is-ko': battle[side].hp <= 0, 'is-hit': hit[side], 'is-stunned': (battle[side].fx?.stun ?? 0) > 0 }"
              :key="`${side}-${hit[side]?.id}`"
          />
        </div>
      </div>
    </div>

    <p v-if="error" class="pb__error">{{ error }}</p>

    <div v-if="!over && stunned" class="pb__stunned">
      <span>Корни сковали твоего питомца — этот ход он пропустит.</span>
      <button type="button" class="pb-move pb-move--attack" :disabled="busy" @click="move('attack')">Прийти в себя</button>
    </div>

    <div v-else-if="!over" class="pb__moves">
      <button type="button" class="pb-move pb-move--attack" :disabled="busy" @click="move('attack')">
        <AppIcon icon="sword" :size="16" />
        <span>
          <b>Атака</b>
          <small>{{ streak >= 2 ? 'Следующая — серия!' : `Серия: ${streak} / 3` }}</small>
        </span>
      </button>
      <button type="button" class="pb-move pb-move--defend" :disabled="busy" @click="move('defend')">
        <AppIcon icon="shield" :size="16" />
        <span><b>Защита</b><small>−60% урона, лечение</small></span>
      </button>
      <button type="button" class="pb-move pb-move--counter" :disabled="busy || me.ccd > 0" @click="move('counter')">
        <AppIcon icon="bolt" :size="16" />
        <span>
          <b>Контратака</b>
          <small>{{ me.ccd > 0 ? `перезарядка: ${me.ccd}` : 'Ловит атаку и умение' }}</small>
        </span>
      </button>
      <button
          type="button"
          class="pb-move pb-move--skill"
          :style="{ '--el': ELEMENTS[me.element]?.color }"
          :disabled="busy || me.cd > 0"
          @click="move('skill')"
      >
        <AppIcon icon="sparkles" :size="16" />
        <span>
          <b>{{ skill.name }}</b>
          <small>{{ me.cd > 0 ? `перезарядка: ${me.cd}` : ELEMENTS[me.element]?.skill }}</small>
        </span>
      </button>
      <button
          type="button"
          class="pb-move pb-move--ult"
          :class="{ 'is-ready': rageFull }"
          :style="{ '--el': ELEMENTS[me.element]?.color }"
          :disabled="busy || !rageFull"
          :title="ultimate.about"
          @click="move('ultimate')"
      >
        <AppIcon icon="flame" :size="16" />
        <span>
          <b>{{ ultimate.name }}</b>
          <small>{{ rageFull ? ultimate.about : `ярость ${ragePct(me)}%` }}</small>
        </span>
      </button>
    </div>

    <div v-else class="pb__result">
      <p>
        Рейтинг питомца: <b :class="battle.rating_change >= 0 ? 'is-up' : 'is-down'">{{ battle.rating_change >= 0 ? '+' : '' }}{{ battle.rating_change }}</b>
        <template v-if="battle.coins"> · награда <b class="is-coins">₳ {{ battle.coins }}</b></template>
        · опыт <b>+{{ battle.status === 'won' ? 4 : 1 }}</b>
      </p>
      <table v-if="me.stats" class="pb-stats-table">
        <thead>
          <tr><th></th><th>{{ me.name }}</th><th>{{ foe.name }}</th></tr>
        </thead>
        <tbody>
          <tr><td>Урона нанесено</td><td>{{ me.stats.dealt }}</td><td>{{ foe.stats?.dealt ?? 0 }}</td></tr>
          <tr><td>Лучший удар</td><td>{{ me.stats.best }}</td><td>{{ foe.stats?.best ?? 0 }}</td></tr>
          <tr><td>Критов</td><td>{{ me.stats.crits }}</td><td>{{ foe.stats?.crits ?? 0 }}</td></tr>
          <tr><td>Ультимейтов</td><td>{{ me.stats.ults }}</td><td>{{ foe.stats?.ults ?? 0 }}</td></tr>
        </tbody>
      </table>
      <button type="button" class="pb-move pb-move--skill" @click="emit('close')">К арене</button>
    </div>

    <ol ref="logEl" class="pb__log">
      <li v-for="(entry, i) in battle.log" :key="i" :class="[`is-${entry.who}`, entry.move ? `is-move-${entry.move}` : null]">{{ entry.text }}</li>
    </ol>
  </section>
</template>

<style scoped>
.pb {
  display: flex;
  flex-direction: column;
  gap: 14px;

  padding: 18px;

  background:
      radial-gradient(ellipse at 50% 100%, var(--glow), transparent 60%),
      linear-gradient(180deg, color-mix(in srgb, var(--sky) 55%, transparent), transparent 55%),
      var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
}

.pb.is-shaking { animation: pb-shake 0.45s linear; }

@keyframes pb-shake {
  20% { translate: -5px 2px; }
  40% { translate: 5px -2px; }
  60% { translate: -3px -1px; }
  80% { translate: 2px 2px; }
}

.pb__head { display: flex; align-items: center; gap: 12px; }
.pb__head span { color: #8a8598; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.pb__head strong { color: #fff; font-size: 16px; }
.pb__win { color: #4ade80 !important; }
.pb__lose { color: #f87171 !important; }

.pb__forfeit {
  margin-left: auto;
  padding: 6px 12px;

  color: #a8a3b8;
  background: none;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;

  font: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;
}

.pb__forfeit:hover { color: #fecaca; border-color: rgba(239, 68, 68, 0.5); }

.pb__arena {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 10px;

  padding: 8px 12px;

  background: color-mix(in srgb, var(--sky) 70%, rgba(0, 0, 0, 0.3));
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
}

.pb__arena b { color: #fff; font-size: 13px; }
.pb__arena span { flex: 1; min-width: 180px; color: #d6d2e2; font-size: 12px; }
.pb__arena i { font-style: normal; }

.pb__field {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.pb-side { display: flex; flex-direction: column; gap: 10px; }
.pb-side--foe { text-align: right; }
.pb-side--foe .pb-side__info { align-items: flex-end; }

.pb-side__info { display: flex; flex-direction: column; gap: 3px; }
.pb-side__info strong { color: #fff; font-size: 17px; }
.pb-side__info small { color: #8a8598; font-size: 12px; }

.pb-side__tags { display: flex; gap: 5px; }

.pb-el {
  align-self: flex-start;

  padding: 1px 7px;

  color: var(--el);
  background: color-mix(in srgb, var(--el) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--el) 40%, transparent);
  border-radius: 6px;

  font-size: 10.5px;
  font-weight: 800;
  text-transform: uppercase;
}

.pb-temper {
  padding: 1px 7px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 6px;

  font-size: 10.5px;
  font-weight: 700;
}

.pb-hp, .pb-rage {
  position: relative;

  width: 100%;
  overflow: hidden;

  background: rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.pb-hp { height: 16px; margin-top: 4px; border-radius: 6px; }
.pb-rage { height: 9px; border-radius: 4px; }

.pb-hp i, .pb-rage i { display: block; height: 100%; transition: width 0.45s ease, background 0.45s ease; }
.pb-rage i { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.pb-side--foe .pb-hp i, .pb-side--foe .pb-rage i { margin-left: auto; }

.pb-hp span, .pb-rage span {
  position: absolute;
  inset: 0;

  display: grid;
  place-items: center;

  color: #fff;
  text-shadow: 0 1px 2px #000;

  font-size: 10.5px;
  font-weight: 800;
}

.pb-rage span { font-size: 7.5px; letter-spacing: 0.12em; text-transform: uppercase; }
.pb-rage.is-full { box-shadow: 0 0 10px rgba(239, 68, 68, 0.6); animation: pb-rage 1.2s ease-in-out infinite; }

@keyframes pb-rage {
  50% { box-shadow: 0 0 18px rgba(249, 115, 22, 0.9); }
}

.pb-fx { display: flex; flex-wrap: wrap; gap: 4px; margin: 3px 0 0; padding: 0; list-style: none; }
.pb-side--foe .pb-fx { justify-content: flex-end; }

.pb-fx li {
  padding: 1px 6px;

  color: var(--fx);
  background: color-mix(in srgb, var(--fx) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--fx) 45%, transparent);
  border-radius: 5px;

  font-size: 10.5px;
  font-weight: 800;
}

.pb-side__stage {
  position: relative;

  height: 160px;

  display: grid;
  place-items: center;

  background:
      radial-gradient(ellipse at 50% 88%, rgba(0, 0, 0, 0.5) 0 20%, transparent 21%),
      linear-gradient(180deg, transparent 72%, color-mix(in srgb, var(--ground) 45%, transparent) 72%);
  border-radius: 12px;
}

.pb-tell {
  position: absolute;
  top: 4px;
  left: 50%;
  z-index: 3;

  width: max-content;
  max-width: 92%;
  margin: 0;
  padding: 5px 10px;

  color: #fff;
  background: rgba(0, 0, 0, 0.75);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;

  font-size: 11.5px;
  font-weight: 700;
  text-align: center;

  translate: -50% 0;
  animation: pb-tell 0.3s ease-out;
}

.pb-tell--skill { border-color: rgba(249, 115, 22, 0.6); color: #fed7aa; }
.pb-tell--ultimate { border-color: #ef4444; color: #fecaca; animation: pb-tell 0.3s ease-out, pb-warn 0.9s ease-in-out infinite; }
.pb-tell--counter { border-color: rgba(56, 189, 248, 0.6); color: #bae6fd; }
.pb-tell--stunned { border-color: rgba(250, 204, 21, 0.6); color: #fef08a; }

@keyframes pb-tell {
  from { opacity: 0; translate: -50% 6px; }
  to { opacity: 1; translate: -50% 0; }
}

@keyframes pb-warn {
  50% { box-shadow: 0 0 16px rgba(239, 68, 68, 0.7); }
}

.is-hit { animation: pb-hit 0.4s ease; }
.is-ko { opacity: 0.35; filter: grayscale(1); rotate: 90deg; transition: rotate 0.5s ease, opacity 0.5s ease; }
.is-stunned { filter: saturate(0.4) brightness(0.8); animation: pb-dizzy 1.4s ease-in-out infinite; }

@keyframes pb-hit {
  25% { translate: -6px 0; filter: brightness(2.2); }
  50% { translate: 6px 0; }
  75% { translate: -3px 0; }
}

@keyframes pb-dizzy {
  25% { rotate: -6deg; }
  75% { rotate: 6deg; }
}

.pb-dmg {
  position: absolute;
  top: 34px;
  z-index: 4;

  color: #fecaca;
  text-shadow: 0 2px 0 #7f1d1d;

  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 900;

  animation: pb-dmg 1s ease-out forwards;
}

.pb-dmg.is-crit { color: #fde047; font-size: 28px; text-shadow: 0 2px 0 #92400e; }
.pb-dmg.is-tick { color: #fdba74; font-size: 16px; }

@keyframes pb-dmg {
  from { opacity: 1; translate: 0 10px; }
  to { opacity: 0; translate: 0 -30px; }
}

.pb__error {
  margin: 0;
  padding: 8px 12px;

  color: #fecaca;
  background: rgba(239, 68, 68, 0.12);
  border-radius: 10px;

  font-size: 13px;
}

.pb__stunned {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;

  padding: 10px 12px;

  color: #fef08a;
  background: rgba(250, 204, 21, 0.08);
  border: 1px solid rgba(250, 204, 21, 0.35);
  border-radius: 12px;

  font-size: 13px;
}

.pb__moves { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }

.pb-move {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;

  min-height: 58px;
  padding: 8px 10px;

  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;

  font: inherit;
  text-align: left;

  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease;
}

.pb-move span { display: flex; flex-direction: column; min-width: 0; }
.pb-move b { font-size: 13.5px; }
.pb-move small { color: #a8a3b8; font-size: 10.5px; line-height: 1.3; }
.pb-move:hover:not(:disabled) { transform: translateY(-2px); }
.pb-move:disabled { opacity: 0.45; cursor: default; }

.pb-move--attack:hover:not(:disabled) { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.5); }
.pb-move--defend:hover:not(:disabled) { background: rgba(56, 189, 248, 0.2); border-color: rgba(56, 189, 248, 0.5); }
.pb-move--counter:hover:not(:disabled) { background: rgba(250, 204, 21, 0.16); border-color: rgba(250, 204, 21, 0.5); }

.pb-move--skill {
  background: color-mix(in srgb, var(--el, #8b5cf6) 22%, rgba(255, 255, 255, 0.04));
  border-color: color-mix(in srgb, var(--el, #8b5cf6) 55%, transparent);
}

.pb-move--ult { border-style: dashed; }

.pb-move--ult.is-ready {
  background: linear-gradient(135deg, color-mix(in srgb, var(--el, #ef4444) 55%, #7f1d1d), #1c1917);
  border: 1px solid var(--el, #ef4444);
  animation: pb-ult 1.1s ease-in-out infinite;
}

@keyframes pb-ult {
  50% { box-shadow: 0 0 22px color-mix(in srgb, var(--el, #ef4444) 70%, transparent); }
}

.pb__result { display: flex; flex-direction: column; gap: 12px; }
.pb__result p { margin: 0; color: #d6d2e2; font-size: 14px; }
.pb__result .is-up { color: #4ade80; }
.pb__result .is-down { color: #f87171; }
.pb__result .is-coins { color: #fde68a; }
.pb__result .pb-move { align-self: flex-start; min-height: 40px; }

.pb-stats-table {
  width: 100%;
  max-width: 460px;

  border-collapse: collapse;

  font-size: 12.5px;
}

.pb-stats-table th, .pb-stats-table td { padding: 5px 8px; text-align: right; }
.pb-stats-table th:first-child, .pb-stats-table td:first-child { color: #8a8598; text-align: left; }
.pb-stats-table th { color: #fff; font-weight: 800; }
.pb-stats-table td { color: #d6d2e2; font-weight: 700; }
.pb-stats-table tbody tr:nth-child(odd) { background: rgba(255, 255, 255, 0.03); }

.pb__log {
  max-height: 170px;
  margin: 0;
  padding: 10px 12px;
  overflow-y: auto;

  background: rgba(0, 0, 0, 0.3);
  border-radius: 12px;

  font-size: 12.5px;
  line-height: 1.6;
  list-style: none;
}

.pb__log li.is-me { color: #c4b5fd; }
.pb__log li.is-foe { color: #fca5a5; }
.pb__log li.is-system { color: #fde68a; }
.pb__log li.is-event { color: #86efac; }
.pb__log li.is-move-ultimate { font-weight: 800; }

@media (max-width: 960px) {
  .pb__moves { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .pb { padding: 12px; }
  .pb__field { gap: 8px; }
  .pb-side__stage { height: 130px; }
  .pb__moves { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pb-move--ult { grid-column: 1 / -1; }
}

@media (prefers-reduced-motion: reduce) {
  .pb.is-shaking, .pb-rage.is-full, .pb-move--ult.is-ready, .pb-tell--ultimate, .is-stunned { animation: none; }
}
</style>
