<script setup>
/**
 * Пошаговый бой на арене: два питомца друг напротив друга, полоски
 * здоровья, три приёма и журнал раундов. Сервер считает раунд, здесь —
 * только показ: удар подсвечивается, урон всплывает над целью.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import { ELEMENTS } from '@/data/players/petArena.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const props = defineProps({
  battle: { type: Object, required: true },
})

const emit = defineEmits(['update', 'close'])

const auth = useAuthStore()
const busy = ref(false)
const error = ref('')
const hit = ref({ me: null, foe: null })
const logEl = ref(null)

const me = computed(() => props.battle.me)
const foe = computed(() => props.battle.foe)
const over = computed(() => props.battle.status !== 'active')
const skill = computed(() => props.battle.skills?.[me.value?.element] ?? { name: 'Умение' })

function pct(f) {
  return Math.max(0, Math.round((f.hp / f.max) * 100))
}

function hpColor(f) {
  const p = pct(f)
  return p > 50 ? '#4ade80' : p > 25 ? '#facc15' : '#f87171'
}

// Новые записи журнала — всплывающий урон над целью
watch(() => props.battle.log?.length, (now, before) => {
  const fresh = (props.battle.log ?? []).slice(before ?? 0)
  fresh.forEach((entry, i) => {
    if (!entry.damage) return
    const target = entry.who === 'me' ? 'foe' : 'me'
    setTimeout(() => {
      hit.value = { ...hit.value, [target]: { value: entry.damage, crit: entry.crit, id: Math.random() } }
    }, i * 450)
  })
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
  <section v-if="me && foe" class="pb" :class="{ 'is-over': over }">
    <header class="pb__head">
      <span>Раунд {{ battle.round }}</span>
      <strong v-if="!over">Твой ход</strong>
      <strong v-else-if="battle.status === 'won'" class="pb__win">Победа!</strong>
      <strong v-else class="pb__lose">Поражение</strong>
      <button v-if="!over" type="button" class="pb__forfeit" :disabled="busy" @click="forfeit">Сдаться</button>
    </header>

    <div class="pb__field">
      <div v-for="side in ['me', 'foe']" :key="side" class="pb-side" :class="`pb-side--${side}`">
        <div class="pb-side__info">
          <span class="pb-el" :style="{ '--el': ELEMENTS[battle[side].element]?.color }">{{ ELEMENTS[battle[side].element]?.name }}</span>
          <strong>{{ battle[side].name }}</strong>
          <small>Ур. {{ battle[side].level }}<template v-if="side === 'foe' && foe.owner"> · {{ foe.owner.username }}</template></small>
          <div class="pb-hp">
            <i :style="{ width: `${pct(battle[side])}%`, background: hpColor(battle[side]) }"></i>
            <span>{{ battle[side].hp }} / {{ battle[side].max }}</span>
          </div>
          <dl class="pb-stats">
            <div><dt>Атака</dt><dd>{{ battle[side].atk }}</dd></div>
            <div><dt>Защита</dt><dd>{{ battle[side].def }}</dd></div>
            <div><dt>Скорость</dt><dd>{{ battle[side].spd }}</dd></div>
          </dl>
        </div>

        <div class="pb-side__stage">
          <span
              v-if="hit[side]"
              :key="hit[side].id"
              class="pb-dmg"
              :class="{ 'is-crit': hit[side].crit }"
          >−{{ hit[side].value }}</span>
          <PetFigure
              :species="battle[side].species"
              :traits="battle[side].traits"
              :size="88"
              :flip="side === 'foe'"
              :class="{ 'is-ko': battle[side].hp <= 0, 'is-hit': hit[side] }"
              :key="`${side}-${hit[side]?.id}`"
          />
        </div>
      </div>
    </div>

    <p v-if="error" class="pb__error">{{ error }}</p>

    <div v-if="!over" class="pb__moves">
      <button type="button" class="pb-move pb-move--attack" :disabled="busy" @click="move('attack')">
        <AppIcon icon="sword" :size="16" />
        <span><b>Атака</b><small>Обычный удар</small></span>
      </button>
      <button type="button" class="pb-move pb-move--defend" :disabled="busy" @click="move('defend')">
        <AppIcon icon="shield" :size="16" />
        <span><b>Защита</b><small>−60% урона, +6% здоровья</small></span>
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
    </div>

    <div v-else class="pb__result">
      <p>
        Рейтинг питомца: <b :class="battle.rating_change >= 0 ? 'is-up' : 'is-down'">{{ battle.rating_change >= 0 ? '+' : '' }}{{ battle.rating_change }}</b>
        <template v-if="battle.coins"> · награда <b class="is-coins">₳ {{ battle.coins }}</b></template>
        · опыт <b>+{{ battle.status === 'won' ? 4 : 1 }}</b>
      </p>
      <button type="button" class="pb-move pb-move--skill" @click="emit('close')">К арене</button>
    </div>

    <ol ref="logEl" class="pb__log">
      <li v-for="(entry, i) in battle.log" :key="i" :class="`is-${entry.who}`">{{ entry.text }}</li>
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
      radial-gradient(ellipse at 50% 100%, rgba(139, 92, 246, 0.16), transparent 60%),
      var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
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

.pb__field {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.pb-side {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pb-side--foe { text-align: right; }
.pb-side--foe .pb-side__info { align-items: flex-end; }

.pb-side__info { display: flex; flex-direction: column; gap: 3px; }
.pb-side__info strong { color: #fff; font-size: 17px; }
.pb-side__info small { color: #8a8598; font-size: 12px; }

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

.pb-side--foe .pb-el { align-self: flex-end; }

.pb-hp {
  position: relative;

  width: 100%;
  height: 16px;
  margin-top: 4px;

  overflow: hidden;

  background: rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 6px;
}

.pb-hp i { display: block; height: 100%; transition: width 0.45s ease, background 0.45s ease; }
.pb-side--foe .pb-hp i { margin-left: auto; }

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

.pb-stats { display: flex; gap: 10px; margin: 2px 0 0; }
.pb-side--foe .pb-stats { justify-content: flex-end; }
.pb-stats div { display: flex; gap: 4px; }
.pb-stats dt { color: #6f6a80; font-size: 11px; }
.pb-stats dd { margin: 0; color: #d6d2e2; font-size: 11px; font-weight: 800; }

.pb-side__stage {
  position: relative;

  height: 150px;

  display: grid;
  place-items: center;

  background: radial-gradient(ellipse at 50% 85%, rgba(0, 0, 0, 0.45) 0 22%, transparent 23%);
}

.is-hit { animation: pb-hit 0.4s ease; }
.is-ko { opacity: 0.35; filter: grayscale(1); rotate: 90deg; transition: rotate 0.5s ease, opacity 0.5s ease; }

@keyframes pb-hit {
  25% { translate: -6px 0; filter: brightness(2.2); }
  50% { translate: 6px 0; }
  75% { translate: -3px 0; }
}

.pb-dmg {
  position: absolute;
  top: 6px;
  z-index: 3;

  color: #fecaca;
  text-shadow: 0 2px 0 #7f1d1d;

  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 900;

  animation: pb-dmg 1s ease-out forwards;
}

.pb-dmg.is-crit { color: #fde047; font-size: 28px; text-shadow: 0 2px 0 #92400e; }

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

.pb__moves { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }

.pb-move {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;

  min-height: 54px;
  padding: 8px 12px;

  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;

  font: inherit;
  text-align: left;

  cursor: pointer;
  transition: transform 0.12s ease, background 0.12s ease;
}

.pb-move span { display: flex; flex-direction: column; }
.pb-move b { font-size: 14px; }
.pb-move small { color: #a8a3b8; font-size: 11px; }
.pb-move:hover:not(:disabled) { transform: translateY(-2px); }
.pb-move:disabled { opacity: 0.45; cursor: default; }

.pb-move--attack:hover:not(:disabled) { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.5); }
.pb-move--defend:hover:not(:disabled) { background: rgba(56, 189, 248, 0.2); border-color: rgba(56, 189, 248, 0.5); }

.pb-move--skill {
  background: color-mix(in srgb, var(--el, #8b5cf6) 22%, rgba(255, 255, 255, 0.04));
  border-color: color-mix(in srgb, var(--el, #8b5cf6) 55%, transparent);
}

.pb__result { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.pb__result p { margin: 0; color: #d6d2e2; font-size: 14px; }
.pb__result .is-up { color: #4ade80; }
.pb__result .is-down { color: #f87171; }
.pb__result .is-coins { color: #fde68a; }
.pb__result .pb-move { min-height: 40px; }

.pb__log {
  max-height: 150px;
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

@media (max-width: 640px) {
  .pb { padding: 12px; }
  .pb__field { gap: 8px; }
  .pb-side__stage { height: 110px; }
  .pb-stats { display: none; }
  .pb__moves { grid-template-columns: minmax(0, 1fr); }
}
</style>
