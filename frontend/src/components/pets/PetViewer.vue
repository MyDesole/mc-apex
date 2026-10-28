<script setup>
/**
 * Питомец во всей красе: крупно на своём фоне, дышит и покачивается,
 * показывает трюки. Справа — паспорт экземпляра: черты с шансами,
 * общая редкость, уровень, стихия, арена и хозяин.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import { ELEMENTS, PET_NAMES } from '@/data/players/petArena.js'
import { PET_RARITY, chanceLabel, petBackdrop } from '@/data/players/petTraits.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  pet: { type: Object, required: true },
  // Свой питомец: можно посмотреть все выученные трюки
  tricks: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const flip = ref(false)
const playing = ref(null)

const traits = computed(() => props.pet.traits ?? null)
const rarity = computed(() => PET_RARITY[traits.value?.rarity] ?? PET_RARITY.common)
const element = computed(() => ELEMENTS[props.pet.element] ?? null)
const aura = computed(() => petBackdrop(traits.value))

// Общий шанс экземпляра — произведение шансов трёх черт
const total = computed(() => {
  const t = traits.value
  if (!t?.model) return null
  const value = (t.model.chance * t.backdrop.chance * t.accessory.chance) / 10000
  if (value < 0.0001) return `1 на ${Math.round(100 / value).toLocaleString('ru-RU')}`
  return `${String(+value.toFixed(4)).replace('.', ',')} %`
})

const known = computed(() => {
  if (props.tricks.length) return props.tricks.filter((t) => (props.pet.level ?? 1) >= t.level)
  return props.pet.trick ? [{ key: props.pet.trick, name: 'Трюк' }] : []
})

const ROWS = [['model', 'Окрас'], ['backdrop', 'Фон'], ['accessory', 'Аксессуар']]

function play(trick) {
  playing.value = null
  requestAnimationFrame(() => {
    playing.value = trick
    setTimeout(() => { if (playing.value === trick) playing.value = null }, 1700)
  })
}

function onKey(event) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
  // Встречает трюком, если знает
  if (props.pet.trick) setTimeout(() => play(props.pet.trick), 500)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div class="pvw" @click.self="emit('close')">
      <div class="pvw__box" :style="{ '--rar': rarity.color }" role="dialog" aria-modal="true">
        <button type="button" class="pvw__close" title="Закрыть" @click="emit('close')">
          <AppIcon icon="close" :size="16" />
        </button>

        <!-- Сцена -->
        <section class="pvw__stage">
          <span class="pvw__glow" :style="aura ? { background: aura } : null"></span>
          <span class="pvw__grid"></span>
          <span class="pvw__floor"></span>
          <div class="pvw__pet">
            <PetFigure
                :species="pet.species"
                :traits="traits"
                :size="150"
                :backdrop="false"
                :flip="flip"
                :trick="playing"
            />
          </div>

          <div class="pvw__tools">
            <button type="button" title="Повернуть" @click="flip = !flip"><AppIcon icon="globe" :size="14" /> Повернуть</button>
            <button v-for="t in known" :key="t.key" type="button" @click="play(t.key)">
              <AppIcon icon="sparkles" :size="14" /> {{ t.name }}
            </button>
          </div>
        </section>

        <!-- Паспорт -->
        <section class="pvw__info">
          <span class="pvw__rarity">{{ rarity.label }} экземпляр</span>
          <h2>{{ pet.name }} <em v-if="traits?.serial">#{{ traits.serial }}</em></h2>
          <p class="pvw__species">
            {{ PET_NAMES[pet.species] || 'Питомец' }}
            <template v-if="pet.owner"> · хозяин <RouterLink :to="userLink(pet.owner)" @click="emit('close')">{{ pet.owner.username }}</RouterLink></template>
          </p>

          <div class="pvw__chips">
            <span class="pvw-chip pvw-chip--lvl">Ур. {{ pet.level ?? 1 }}</span>
            <span v-if="element" class="pvw-chip" :style="{ '--c': element.color }">{{ element.name }}</span>
            <span v-if="total" class="pvw-chip pvw-chip--rar">Шанс {{ total }}</span>
          </div>

          <dl v-if="traits" class="pvw__traits">
            <div v-for="[key, label] in ROWS" :key="key">
              <dt>{{ label }}</dt>
              <dd>
                <b>{{ traits[key]?.name }}</b>
                <span class="pvw__bar"><i :style="{ width: `${Math.max(3, Math.min(100, traits[key]?.chance ?? 0) * 3)}%` }"></i></span>
                <small>{{ chanceLabel(traits[key]?.chance) }}</small>
              </dd>
            </div>
          </dl>

          <dl class="pvw__stats">
            <div><dt>Рейтинг арены</dt><dd>{{ pet.rating ?? 1000 }}</dd></div>
            <div><dt>Победы</dt><dd>{{ pet.wins ?? 0 }}</dd></div>
            <div><dt>Поражения</dt><dd>{{ pet.losses ?? 0 }}</dd></div>
          </dl>

          <p v-if="element" class="pvw__skill"><AppIcon icon="sparkles" :size="13" /> Умение на арене — {{ element.skill }}</p>
        </section>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.pvw {
  position: fixed;
  inset: 0;
  z-index: 1300;

  display: grid;
  place-items: center;

  padding: 20px;
  overflow-y: auto;

  background: rgba(6, 5, 10, 0.8);
  backdrop-filter: blur(8px);
}

.pvw__box {
  position: relative;

  width: min(900px, 100%);

  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);

  overflow: hidden;

  background: #15131c;
  border: 1px solid color-mix(in srgb, var(--rar) 45%, rgba(255, 255, 255, 0.08));
  border-radius: 24px;
  box-shadow: 0 40px 90px -30px color-mix(in srgb, var(--rar) 70%, #000);

  animation: pvw-in 0.25s ease-out;
}

@keyframes pvw-in {
  from { opacity: 0; transform: translateY(14px) scale(0.98); }
}

.pvw__close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 3;

  width: 34px;
  height: 34px;

  display: grid;
  place-items: center;

  color: #d6d2e2;
  background: rgba(10, 8, 16, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;

  cursor: pointer;
}

/* ---------- Сцена ---------- */

.pvw__stage {
  position: relative;

  min-height: 420px;

  display: grid;
  place-items: center;

  overflow: hidden;

  background:
      radial-gradient(circle at 50% 45%, color-mix(in srgb, var(--rar) 22%, transparent), transparent 65%),
      linear-gradient(180deg, #1b1726, #0e0c14);
}

.pvw__glow {
  position: absolute;
  top: 44%;
  left: 50%;

  width: 340px;
  height: 340px;

  border-radius: 50%;
  opacity: 0.9;
  transform: translate(-50%, -50%);

  animation: pvw-glow 6s ease-in-out infinite;
}

@keyframes pvw-glow {
  50% { transform: translate(-50%, -50%) scale(1.08) rotate(20deg); }
}

/* Пиксельная сетка — чтобы фон читался как мир Minecraft */
.pvw__grid {
  position: absolute;
  inset: 0;

  background:
      linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px) 0 0 / 16px 16px,
      linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px) 0 0 / 16px 16px;
  mask-image: radial-gradient(circle at 50% 50%, #000, transparent 75%);
}

.pvw__floor {
  position: absolute;
  bottom: 92px;
  left: 50%;

  width: 170px;
  height: 22px;

  background: radial-gradient(ellipse, rgba(0, 0, 0, 0.6), transparent 70%);
  transform: translateX(-50%);

  animation: pvw-shadow 3s ease-in-out infinite;
}

.pvw__pet {
  position: relative;
  z-index: 1;

  margin-bottom: 40px;

  animation: pvw-bob 3s ease-in-out infinite;
}

@keyframes pvw-bob {
  50% { transform: translateY(-10px); }
}

@keyframes pvw-shadow {
  50% { transform: translateX(-50%) scale(0.82); opacity: 0.7; }
}

.pvw__tools {
  position: absolute;
  left: 50%;
  bottom: 18px;
  z-index: 2;

  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;

  width: calc(100% - 32px);

  transform: translateX(-50%);
}

.pvw__tools button {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 7px 12px;

  color: #e9e5f5;
  background: rgba(10, 8, 16, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  backdrop-filter: blur(6px);

  font: inherit;
  font-size: 12.5px;
  font-weight: 700;

  cursor: pointer;
}

.pvw__tools button:hover { color: #fff; border-color: color-mix(in srgb, var(--rar) 70%, transparent); }

/* ---------- Паспорт ---------- */

.pvw__info {
  display: flex;
  flex-direction: column;
  gap: 14px;

  padding: 26px 24px 24px;
}

.pvw__rarity {
  color: var(--rar);

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.pvw__info h2 {
  margin: -6px 0 0;

  color: #fff;

  font-family: var(--font-display);
  font-size: 30px;
  line-height: 1.1;
}

.pvw__info h2 em { color: #8a8598; font-size: 18px; font-style: normal; }
.pvw__species { margin: -8px 0 0; color: #8a8598; font-size: 13px; }
.pvw__species a { color: #c4b5fd; }

.pvw__chips { display: flex; flex-wrap: wrap; gap: 6px; }

.pvw-chip {
  --c: #a8a3b8;

  padding: 4px 10px;

  color: var(--c);
  background: color-mix(in srgb, var(--c) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--c) 40%, transparent);
  border-radius: 999px;

  font-size: 12px;
  font-weight: 800;
}

.pvw-chip--lvl { --c: #fde047; }
.pvw-chip--rar { --c: var(--rar); }

.pvw__traits { display: flex; flex-direction: column; gap: 10px; margin: 0; }
.pvw__traits dt { color: #6f6a80; font-size: 10.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }

.pvw__traits dd {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 90px 56px;
  align-items: center;
  gap: 10px;

  margin: 3px 0 0;
}

.pvw__traits dd b { overflow: hidden; color: #fff; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.pvw__traits dd small { color: var(--rar); font-size: 12px; font-weight: 800; text-align: right; }

.pvw__bar { height: 6px; overflow: hidden; background: rgba(255, 255, 255, 0.06); border-radius: 4px; }
.pvw__bar i { display: block; height: 100%; background: color-mix(in srgb, var(--rar) 80%, #fff); }

.pvw__stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;

  margin: 0;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.06);
  border-radius: 12px;
}

.pvw__stats div { padding: 10px 12px; background: #100e16; }
.pvw__stats dt { color: #6f6a80; font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
.pvw__stats dd { margin: 2px 0 0; color: #fff; font-size: 18px; font-weight: 900; }

.pvw__skill { display: flex; align-items: center; gap: 6px; margin: 0; color: #a8a3b8; font-size: 12.5px; }

@media (max-width: 760px) {
  .pvw { place-items: start center; padding: 10px; }
  .pvw__box { grid-template-columns: minmax(0, 1fr); }
  .pvw__stage { min-height: 320px; }
  .pvw__info { padding: 18px; }
}
</style>
