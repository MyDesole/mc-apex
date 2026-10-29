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

// Подпись внизу сцены: что случилось в последнем раунде
const caption = ref((props.battle.log ?? []).slice(-2))
const logOpen = ref(false)

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
  if (fresh.length) caption.value = fresh.slice(-3)
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
      :class="{ 'is-over': over }"
      :style="{ '--sky': theme.sky, '--ground': theme.ground, '--glow': theme.glow }"
  >
    <header class="pb__head">
      <span class="pb__round">Раунд {{ battle.round }}</span>
      <strong v-if="!over">{{ stunned ? 'Ты оглушён' : 'Твой ход' }}</strong>
      <strong v-else-if="battle.status === 'won'" class="pb__win">Победа!</strong>
      <strong v-else class="pb__lose">Поражение</strong>
      <button v-if="!over" type="button" class="pb__forfeit" :disabled="busy" @click="forfeit">Сдаться</button>
    </header>

    <!-- Сцена: одна арена на двоих, HUD по углам, подпись снизу -->
    <div class="pb-scene" :class="{ 'is-shaking': shake }">
      <div v-if="arena" class="pb-scene__arena" :title="arena.about">
        <b>{{ arena.name }}</b>
        <small>{{ arena.about }}</small>
      </div>

      <div v-for="side in SIDES" :key="`hud-${side}`" class="pb-hud" :class="`pb-hud--${side}`">
        <div class="pb-hud__top">
          <i class="pb-hud__el" :style="{ background: ELEMENTS[battle[side].element]?.color, color: ELEMENTS[battle[side].element]?.color }" :title="ELEMENTS[battle[side].element]?.name"></i>
          <strong>{{ battle[side].name }}</strong>
          <span>ур. {{ battle[side].level }}</span>
        </div>
        <div class="pb-hp">
          <i :style="{ width: `${pct(battle[side])}%`, background: hpColor(battle[side]) }"></i>
          <span>{{ battle[side].hp }} / {{ battle[side].max }}</span>
        </div>
        <div class="pb-rage" :class="{ 'is-full': ragePct(battle[side]) >= 100 }" :title="`Ярость ${ragePct(battle[side])}%`">
          <AppIcon icon="flame" :size="11" />
          <span class="pb-rage__bar"><i :style="{ width: `${ragePct(battle[side])}%` }"></i></span>
        </div>
        <div class="pb-hud__tags">
          <span v-if="side === 'foe' && foe.temper" class="pb-tag">{{ foe.temper }}</span>
          <span v-if="side === 'foe' && foe.owner" class="pb-tag pb-tag--dim">{{ foe.owner.username }}</span>
          <span v-for="s in statuses(battle[side])" :key="s.key" class="pb-tag pb-tag--fx" :style="{ '--fx': s.color }">{{ s.name }} {{ s.left }}</span>
        </div>
      </div>

      <div v-for="side in SIDES" :key="`f-${side}`" class="pb-fighter" :class="`pb-fighter--${side}`">
        <p v-if="side === 'foe' && battle.tell && !over" class="pb-tell" :class="`pb-tell--${battle.tell.kind}`">
          {{ battle.tell.text }}
        </p>
        <span
            v-if="hit[side]"
            :key="hit[side].id"
            class="pb-dmg"
            :class="{ 'is-crit': hit[side].crit, 'is-tick': hit[side].tick }"
        >−{{ hit[side].value }}</span>
        <div class="pb-fighter__body">
          <PetFigure
              :species="battle[side].species"
              :traits="battle[side].traits"
              :size="108"
              :flip="side === 'foe'"
              :class="{ 'is-ko': battle[side].hp <= 0, 'is-hit': hit[side], 'is-stunned': (battle[side].fx?.stun ?? 0) > 0 }"
              :key="`${side}-${hit[side]?.id}`"
          />
        </div>
        <i class="pb-fighter__pad" aria-hidden="true"></i>
      </div>

      <span class="pb-vs" aria-hidden="true">VS</span>

      <ul v-if="caption.length" class="pb-caption" aria-live="polite">
        <li v-for="(entry, i) in caption" :key="`${battle.round}-${i}`" :class="`is-${entry.who}`">{{ entry.text }}</li>
      </ul>
    </div>

    <p v-if="error" class="pb__error">{{ error }}</p>

    <div v-if="!over && stunned" class="pb__stunned">
      <span>Твой питомец оглушён — этот ход он пропустит.</span>
      <button type="button" class="pb-move pb-move--attack" :disabled="busy" @click="move('attack')">Прийти в себя</button>
    </div>

    <div v-else-if="!over" class="pb__moves">
      <button type="button" class="pb-move pb-move--attack" :disabled="busy" @click="move('attack')">
        <AppIcon icon="sword" :size="18" />
        <b>Атака</b>
        <small>{{ streak >= 2 ? 'следующая — серия' : `серия ${streak}/3` }}</small>
      </button>
      <button type="button" class="pb-move pb-move--defend" :disabled="busy" @click="move('defend')">
        <AppIcon icon="shield" :size="18" />
        <b>Защита</b>
        <small>−60% урона</small>
      </button>
      <button type="button" class="pb-move pb-move--counter" :disabled="busy || me.ccd > 0" @click="move('counter')">
        <AppIcon icon="bolt" :size="18" />
        <b>Контратака</b>
        <small>{{ me.ccd > 0 ? `через ${me.ccd}` : 'ловит удар' }}</small>
      </button>
      <button
          type="button"
          class="pb-move pb-move--skill"
          :style="{ '--el': ELEMENTS[me.element]?.color }"
          :disabled="busy || me.cd > 0"
          :title="ELEMENTS[me.element]?.skill"
          @click="move('skill')"
      >
        <AppIcon icon="sparkles" :size="18" />
        <b>{{ skill.name }}</b>
        <small>{{ me.cd > 0 ? `через ${me.cd}` : 'умение' }}</small>
      </button>
      <button
          type="button"
          class="pb-move pb-move--ult"
          :class="{ 'is-ready': rageFull }"
          :style="{ '--el': ELEMENTS[me.element]?.color, '--fill': `${ragePct(me)}%` }"
          :disabled="busy || !rageFull"
          :title="ultimate.about"
          @click="move('ultimate')"
      >
        <AppIcon icon="flame" :size="18" />
        <b>{{ ultimate.name }}</b>
        <small>{{ rageFull ? 'готово!' : `ярость ${ragePct(me)}%` }}</small>
      </button>
    </div>

    <div v-else class="pb__result">
      <p>
        Рейтинг питомца: <b :class="battle.rating_change >= 0 ? 'is-up' : 'is-down'">{{ battle.rating_change >= 0 ? '+' : '' }}{{ battle.rating_change }}</b>
        <template v-if="battle.coins"> · награда <b class="is-coins">₳ {{ battle.coins }}</b></template>
        · опыт <b>+{{ battle.status === 'won' ? 4 : 1 }}</b>
      </p>
      <dl v-if="me.stats" class="pb-sum">
        <div><dt>Урон</dt><dd>{{ me.stats.dealt }} <small>/ {{ foe.stats?.dealt ?? 0 }}</small></dd></div>
        <div><dt>Лучший удар</dt><dd>{{ me.stats.best }} <small>/ {{ foe.stats?.best ?? 0 }}</small></dd></div>
        <div><dt>Криты</dt><dd>{{ me.stats.crits }} <small>/ {{ foe.stats?.crits ?? 0 }}</small></dd></div>
        <div><dt>Ультимейты</dt><dd>{{ me.stats.ults }} <small>/ {{ foe.stats?.ults ?? 0 }}</small></dd></div>
      </dl>
      <button type="button" class="pb-move pb-move--back" @click="emit('close')">К арене</button>
    </div>

    <div class="pb-log" :class="{ 'is-open': logOpen }">
      <button type="button" class="pb-log__toggle" @click="logOpen = !logOpen">
        Журнал боя · {{ battle.log?.length ?? 0 }}
        <span>{{ logOpen ? 'свернуть' : 'развернуть' }}</span>
      </button>
      <ol v-show="logOpen" ref="logEl" class="pb-log__list">
        <li v-for="(entry, i) in battle.log" :key="i" :class="[`is-${entry.who}`, entry.move ? `is-move-${entry.move}` : null]">{{ entry.text }}</li>
      </ol>
    </div>
  </section>
</template>

<style scoped>
.pb {
  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 16px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
}

.pb__head { display: flex; align-items: center; gap: 12px; }
.pb__round { color: #8a8598; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
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

/* ---------- Сцена ---------- */

.pb-scene {
  position: relative;

  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  grid-template-rows: auto auto 1fr auto;
  grid-template-areas:
      "arena arena arena"
      "hudme . hudfoe"
      "me vs foe"
      "cap cap cap";
  column-gap: 12px;

  min-height: 380px;
  padding: 12px 14px 12px;
  overflow: hidden;

  background:
      radial-gradient(ellipse 70% 40% at 50% 100%, var(--glow), transparent 70%),
      linear-gradient(180deg, transparent 58%, color-mix(in srgb, var(--ground) 70%, #000) 58%, color-mix(in srgb, var(--ground) 35%, #000) 100%),
      linear-gradient(180deg, color-mix(in srgb, var(--sky) 100%, #000), color-mix(in srgb, var(--sky) 55%, #000) 58%);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

/* Линия горизонта */
.pb-scene::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 58%;

  height: 2px;

  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.12), transparent);
}

.pb-scene.is-shaking { animation: pb-shake 0.45s linear; }

@keyframes pb-shake {
  20% { translate: -5px 2px; }
  40% { translate: 5px -2px; }
  60% { translate: -3px -1px; }
  80% { translate: 2px 2px; }
}

.pb-scene__arena {
  grid-area: arena;
  justify-self: center;

  display: flex;
  flex-direction: column;
  align-items: center;

  margin-bottom: 10px;

  text-align: center;
}

.pb-scene__arena b {
  color: #fff;

  font-family: var(--font-display);
  font-size: 13px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
}

.pb-scene__arena small { color: rgba(255, 255, 255, 0.6); font-size: 11.5px; }

/* HUD */
.pb-hud {
  z-index: 2;
  align-self: start;

  display: flex;
  flex-direction: column;
  gap: 5px;

  padding: 9px 10px;

  background: rgba(10, 8, 16, 0.62);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  backdrop-filter: blur(6px);
}

.pb-hud--me { grid-area: hudme; }
.pb-hud--foe { grid-area: hudfoe; }

.pb-hud__top { display: flex; align-items: baseline; gap: 7px; min-width: 0; }
.pb-hud__top strong { overflow: hidden; color: #fff; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }
.pb-hud__top span { flex-shrink: 0; margin-left: auto; color: #a8a3b8; font-size: 11.5px; font-weight: 700; }

.pb-hud__el {
  width: 9px;
  height: 9px;
  flex-shrink: 0;

  border-radius: 2px;
  box-shadow: 0 0 6px currentColor;
}

.pb-hp {
  position: relative;

  height: 14px;
  overflow: hidden;

  background: rgba(0, 0, 0, 0.5);
  border-radius: 5px;
}

.pb-hp i { display: block; height: 100%; transition: width 0.45s ease, background 0.45s ease; }

.pb-hp span {
  position: absolute;
  inset: 0;

  display: grid;
  place-items: center;

  color: #fff;
  text-shadow: 0 1px 2px #000;

  font-size: 10.5px;
  font-weight: 800;
}

.pb-rage { display: flex; align-items: center; gap: 5px; color: #f59e0b; }

.pb-rage__bar {
  flex: 1;

  height: 6px;
  overflow: hidden;

  background:
      repeating-linear-gradient(90deg, transparent 0 calc(10% - 1px), rgba(0, 0, 0, 0.7) calc(10% - 1px) 10%),
      rgba(255, 255, 255, 0.08);
  border-radius: 3px;
}

.pb-rage__bar i {
  display: block;
  height: 100%;

  background: linear-gradient(90deg, #f59e0b, #ef4444);
  transition: width 0.45s ease;
}

.pb-rage.is-full { color: #ef4444; }
.pb-rage.is-full .pb-rage__bar { box-shadow: 0 0 8px rgba(239, 68, 68, 0.8); animation: pb-rage 1.1s ease-in-out infinite; }

@keyframes pb-rage {
  50% { box-shadow: 0 0 14px rgba(249, 115, 22, 1); }
}

.pb-hud__tags { display: flex; flex-wrap: wrap; gap: 4px; }
.pb-hud__tags:empty { display: none; }

.pb-tag {
  padding: 1px 6px;

  color: #e6e3ee;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 5px;

  font-size: 10.5px;
  font-weight: 700;
}

.pb-tag--dim { color: #a8a3b8; }

.pb-tag--fx {
  color: var(--fx);
  background: color-mix(in srgb, var(--fx) 16%, transparent);
  border: 1px solid color-mix(in srgb, var(--fx) 45%, transparent);
}

/* Бойцы на подиумах */
.pb-fighter {
  position: relative;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;

  min-height: 190px;
  padding-top: 40px;
}

.pb-fighter--me { grid-area: me; }
.pb-fighter--foe { grid-area: foe; }

.pb-fighter__body { position: relative; z-index: 1; margin-bottom: -14px; }

.pb-fighter__pad {
  width: min(170px, 90%);
  height: 30px;

  background: radial-gradient(ellipse at 50% 50%, color-mix(in srgb, var(--ground) 80%, #fff 8%) 0 45%, color-mix(in srgb, var(--ground) 60%, #000) 46% 62%, transparent 64%);
  border-radius: 50%;
}

.pb-vs {
  grid-area: vs;
  align-self: center;

  color: rgba(255, 255, 255, 0.18);

  font-family: var(--font-display);
  font-size: 30px;
  font-style: italic;
  font-weight: 900;
}

.pb-tell {
  position: absolute;
  top: 0;
  left: 50%;
  z-index: 3;

  width: max-content;
  max-width: 100%;
  margin: 0;
  padding: 5px 10px;

  color: #fff;
  background: rgba(0, 0, 0, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;

  font-size: 11.5px;
  font-weight: 700;
  line-height: 1.3;
  text-align: center;

  translate: -50% 0;
  animation: pb-tell 0.3s ease-out;
}

/* Хвостик облачка */
.pb-tell::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: -5px;

  width: 8px;
  height: 8px;

  background: inherit;
  border-right: inherit;
  border-bottom: inherit;

  transform: translateX(-50%) rotate(45deg);
}

.pb-tell--skill { border-color: rgba(249, 115, 22, 0.7); color: #fed7aa; }
.pb-tell--ultimate { border-color: #ef4444; color: #fecaca; animation: pb-tell 0.3s ease-out, pb-warn 0.9s ease-in-out infinite; }
.pb-tell--counter { border-color: rgba(56, 189, 248, 0.7); color: #bae6fd; }
.pb-tell--stunned { border-color: rgba(250, 204, 21, 0.7); color: #fef08a; }

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
  top: 46px;
  z-index: 4;

  color: #fecaca;
  text-shadow: 0 2px 0 #7f1d1d;

  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 900;

  animation: pb-dmg 1s ease-out forwards;
}

.pb-dmg.is-crit { color: #fde047; font-size: 30px; text-shadow: 0 2px 0 #92400e; }
.pb-dmg.is-tick { color: #fdba74; font-size: 17px; }

@keyframes pb-dmg {
  from { opacity: 1; translate: 0 10px; }
  to { opacity: 0; translate: 0 -30px; }
}

/* Что случилось в раунде */
.pb-caption {
  grid-area: cap;
  z-index: 2;

  display: flex;
  flex-direction: column;
  gap: 2px;

  margin: 14px 0 0;
  padding: 8px 12px;

  background: rgba(0, 0, 0, 0.55);
  border-radius: 10px;

  font-size: 12.5px;
  line-height: 1.45;
  list-style: none;
}

.pb-caption li { animation: pb-in 0.3s ease-out both; }
.pb-caption li:nth-child(2) { animation-delay: 0.15s; }
.pb-caption li:nth-child(3) { animation-delay: 0.3s; }

@keyframes pb-in {
  from { opacity: 0; translate: 0 4px; }
}

.is-me { color: #c4b5fd; }
.is-foe { color: #fca5a5; }
.is-system { color: #fde68a; }
.is-event { color: #86efac; }
.is-move-ultimate { font-weight: 800; }

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

/* ---------- Приёмы ---------- */

.pb__moves { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }

.pb-move {
  position: relative;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;

  min-height: 74px;
  padding: 8px 6px;
  overflow: hidden;
  isolation: isolate;

  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;

  font: inherit;
  text-align: center;

  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease;
}

.pb-move b { font-size: 13.5px; line-height: 1.15; }
.pb-move small { color: #a8a3b8; font-size: 11px; }
.pb-move:hover:not(:disabled) { transform: translateY(-2px); }
.pb-move:disabled { opacity: 0.45; cursor: default; }

.pb-move--attack { color: #fecaca; }
.pb-move--defend { color: #bae6fd; }
.pb-move--counter { color: #fde68a; }
.pb-move--attack b, .pb-move--defend b, .pb-move--counter b { color: #fff; }

.pb-move--attack:hover:not(:disabled) { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.5); }
.pb-move--defend:hover:not(:disabled) { background: rgba(56, 189, 248, 0.18); border-color: rgba(56, 189, 248, 0.5); }
.pb-move--counter:hover:not(:disabled) { background: rgba(250, 204, 21, 0.14); border-color: rgba(250, 204, 21, 0.5); }

.pb-move--skill {
  color: var(--el, #8b5cf6);
  background: color-mix(in srgb, var(--el, #8b5cf6) 18%, rgba(255, 255, 255, 0.03));
  border-color: color-mix(in srgb, var(--el, #8b5cf6) 50%, transparent);
}

.pb-move--skill b { color: #fff; }

/* Ультимейт заполняется яростью снизу вверх */
.pb-move--ult { color: var(--el, #ef4444); border-style: dashed; }
.pb-move--ult b { color: #fff; }

.pb-move--ult::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: -1;

  height: var(--fill, 0%);

  background: color-mix(in srgb, var(--el, #ef4444) 22%, transparent);
  transition: height 0.45s ease;
}

.pb-move--ult.is-ready {
  background: linear-gradient(135deg, color-mix(in srgb, var(--el, #ef4444) 60%, #7f1d1d), #1c1917);
  border: 1px solid var(--el, #ef4444);
  animation: pb-ult 1.1s ease-in-out infinite;
}

.pb-move--ult.is-ready small { color: #fff; font-weight: 800; }

@keyframes pb-ult {
  50% { box-shadow: 0 0 22px color-mix(in srgb, var(--el, #ef4444) 70%, transparent); }
}

/* ---------- Итог ---------- */

.pb__result { display: flex; flex-direction: column; gap: 12px; }
.pb__result p { margin: 0; color: #d6d2e2; font-size: 14px; }
.pb__result .is-up { color: #4ade80; }
.pb__result .is-down { color: #f87171; }
.pb__result .is-coins { color: #fde68a; }

.pb-sum {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;

  margin: 0;
}

.pb-sum div {
  padding: 10px;

  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
}

.pb-sum dt { color: #8a8598; font-size: 11px; font-weight: 700; }
.pb-sum dd { margin: 3px 0 0; color: #fff; font-size: 18px; font-weight: 900; }
.pb-sum small { color: #f87171; font-size: 12px; font-weight: 700; }

.pb-move--back { align-self: flex-start; min-height: 42px; padding: 0 18px; flex-direction: row; background: rgba(139, 92, 246, 0.22); border-color: rgba(139, 92, 246, 0.5); }

/* ---------- Журнал ---------- */

.pb-log {
  background: rgba(0, 0, 0, 0.25);
  border-radius: 12px;
}

.pb-log__toggle {
  display: flex;
  justify-content: space-between;

  width: 100%;
  padding: 9px 12px;

  color: #a8a3b8;
  background: none;
  border: 0;

  font: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;
}

.pb-log__toggle span { color: #6f6a80; }

.pb-log__list {
  max-height: 180px;
  margin: 0;
  padding: 0 12px 10px;
  overflow-y: auto;

  font-size: 12.5px;
  line-height: 1.6;
  list-style: none;
}

/* ---------- Телефон ---------- */

@media (max-width: 960px) {
  .pb__moves { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .pb { padding: 10px; gap: 10px; border-radius: 16px; }

  .pb-scene {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas:
        "arena arena"
        "hudme hudfoe"
        "me foe"
        "cap cap";
    column-gap: 6px;
    row-gap: 6px;

    min-height: 0;
    padding: 10px 8px;
  }

  .pb-scene__arena small { display: none; }
  .pb-hud { padding: 7px 8px; }
  .pb-hud__top strong { font-size: 13px; }
  .pb-hud__top span { font-size: 10.5px; }
  .pb-vs { display: none; }

  .pb-fighter { min-height: 0; padding-top: 38px; }
  .pb-fighter__body { scale: 0.78; transform-origin: 50% 100%; margin-bottom: -10px; }
  .pb-tell { max-width: 96%; font-size: 10.5px; padding: 4px 7px; }
  .pb-dmg { top: 52px; font-size: 20px; }

  .pb-caption { margin-top: 8px; font-size: 11.5px; }

  .pb__moves { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pb-move { min-height: 62px; }
  .pb-move--ult { grid-column: 1 / -1; }

  .pb-sum { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (prefers-reduced-motion: reduce) {
  .pb-scene.is-shaking, .pb-rage.is-full .pb-rage__bar, .pb-move--ult.is-ready, .pb-tell--ultimate, .is-stunned, .pb-caption li { animation: none; }
}
</style>
