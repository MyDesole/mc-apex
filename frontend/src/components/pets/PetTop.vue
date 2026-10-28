<script setup>
/**
 * Топ питомцев сервера: самые прокачанные, лучшие бойцы арены и самые
 * редкие экземпляры. Тройка — на пьедестале, остальные — списком.
 * Клик по питомцу — посмотреть его во всей красе.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PetViewer from '@/components/pets/PetViewer.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import { ELEMENTS } from '@/data/players/petArena.js'
import { PET_RARITY, petBackdrop } from '@/data/players/petTraits.js'
import { userLink } from '@/utils/links.js'

const by = ref('level')
const pets = ref(null)
const viewing = ref(null)

const TABS = [['level', 'Уровень'], ['rating', 'Арена'], ['rare', 'Редкость']]

// Пьедестал: второй слева, первый в центре, третий справа
const podium = computed(() => {
  const [first, second, third] = pets.value ?? []
  return [second && { pet: second, place: 2 }, first && { pet: first, place: 1 }, third && { pet: third, place: 3 }].filter(Boolean)
})
const rest = computed(() => (pets.value ?? []).slice(3))

async function load() {
  pets.value = null
  pets.value = (await api.get('/pets/top', { params: { by: by.value } })).pets ?? []
}

function chance(p) {
  if (p.chance === undefined) return ''
  return p.chance < 0.01 ? '< 0,01 %' : `${String(p.chance).replace('.', ',')} %`
}

function value(p) {
  if (by.value === 'level') return { main: `Ур. ${p.level}`, sub: `${p.xp} опыта` }
  if (by.value === 'rating') return { main: p.rating, sub: `${p.wins} – ${p.losses}` }
  return { main: chance(p), sub: PET_RARITY[p.traits?.rarity]?.label }
}

function stageStyle(p) {
  const aura = petBackdrop(p.traits)
  const rar = PET_RARITY[p.traits?.rarity]?.color ?? '#a8a3b8'
  return {
    '--rar': rar,
    background: aura
        ? `${aura}, radial-gradient(circle at 50% 60%, ${rar}22, transparent 70%)`
        : `radial-gradient(circle at 50% 60%, ${rar}33, transparent 70%)`,
  }
}

watch(by, load)
onMounted(load)
</script>

<template>
  <div class="pt">
    <div class="pt-tabs">
      <button v-for="[id, label] in TABS" :key="id" type="button" :class="{ 'is-on': by === id }" @click="by = id">{{ label }}</button>
    </div>

    <PageLoader v-if="!pets" />
    <p v-else-if="!pets.length" class="pt-empty">Пока пусто — сыграй на арене первым.</p>

    <template v-else>
      <!-- Пьедестал -->
      <div class="pt-podium">
        <button
            v-for="{ pet: p, place } in podium"
            :key="p.inventory_id"
            type="button"
            class="pt-hero"
            :class="`is-place-${place}`"
            :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }"
            @click="viewing = p"
        >
          <span class="pt-hero__medal">{{ place }}</span>
          <span class="pt-hero__stage">
            <!-- Сияние — круг по центру, меньше сцены: гаснет мягко, без обрезки краёв -->
            <span class="pt-hero__glow" :style="stageStyle(p)"></span>
            <PetFigure :species="p.species" :traits="p.traits" :size="place === 1 ? 76 : 62" :backdrop="false" class="pt-hero__pet" />
          </span>
          <strong>{{ p.name }} <em v-if="p.traits?.serial">#{{ p.traits.serial }}</em></strong>
          <small>{{ p.owner?.username }} · <span :style="{ color: ELEMENTS[p.element]?.color }">{{ ELEMENTS[p.element]?.name }}</span></small>
          <b class="pt-hero__value">{{ value(p).main }}</b>
          <small>{{ value(p).sub }}</small>
        </button>
      </div>

      <!-- Остальные -->
      <ol v-if="rest.length" class="pt-list" start="4">
        <li
            v-for="(p, i) in rest"
            :key="p.inventory_id"
            class="pt-row"
            :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }"
            @click="viewing = p"
        >
          <span class="pt-row__place">{{ i + 4 }}</span>
          <span class="pt-row__pet" :style="stageStyle(p)">
            <PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" />
          </span>
          <span class="pt-row__name">
            <strong>{{ p.name }} <em v-if="p.traits?.serial">#{{ p.traits.serial }}</em></strong>
            <small>
              <RouterLink v-if="p.owner" :to="userLink(p.owner)" @click.stop>{{ p.owner.username }}</RouterLink>
              · <span :style="{ color: ELEMENTS[p.element]?.color }">{{ ELEMENTS[p.element]?.name }}</span>
              · {{ p.traits?.model?.name }}, {{ p.traits?.backdrop?.name }}, {{ p.traits?.accessory?.name }}
            </small>
          </span>
          <span class="pt-row__value">
            <b>{{ value(p).main }}</b>
            <small>{{ value(p).sub }}</small>
          </span>
        </li>
      </ol>
    </template>

    <PetViewer v-if="viewing" :pet="viewing" @close="viewing = null" />
  </div>
</template>

<style scoped>
.pt { display: flex; flex-direction: column; gap: 14px; }

.pt-tabs {
  display: flex;
  gap: 2px;
  align-self: flex-start;

  padding: 3px;

  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 11px;
}

.pt-tabs button {
  min-height: 32px;
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

.pt-tabs button.is-on { color: #fff; background: rgba(139, 92, 246, 0.35); }

.pt-empty { margin: 0; padding: 30px; color: #8a8598; text-align: center; }

/* ---------- Пьедестал ---------- */

.pt-podium {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: end;
  gap: 12px;
}

.pt-hero {
  --medal: #94a3b8;

  position: relative;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;

  padding: 16px 14px 16px;

  color: inherit;
  background:
      linear-gradient(180deg, color-mix(in srgb, var(--medal) 14%, transparent), transparent 55%),
      var(--bg-card, #15131c);
  border: 1px solid color-mix(in srgb, var(--medal) 45%, rgba(255, 255, 255, 0.06));
  border-radius: 20px;

  font: inherit;
  text-align: center;

  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.pt-hero:hover { transform: translateY(-4px); box-shadow: 0 18px 40px -22px var(--medal); }

.pt-hero.is-place-1 { --medal: #facc15; padding-top: 22px; }
.pt-hero.is-place-2 { --medal: #cbd5e1; }
.pt-hero.is-place-3 { --medal: #d97706; }

.pt-hero__medal {
  position: absolute;
  top: 10px;
  left: 12px;

  width: 28px;
  height: 28px;

  display: grid;
  place-items: center;

  color: #1a1206;
  background: var(--medal);
  border-radius: 8px;

  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 900;
}

.pt-hero__stage {
  position: relative;

  width: 100%;
  height: 120px;
  margin-bottom: 8px;

  display: grid;
  place-items: center;
}

.is-place-1 .pt-hero__stage { height: 140px; }

.pt-hero__glow {
  position: absolute;
  top: 50%;
  left: 50%;

  width: 118px;
  height: 118px;

  border-radius: 50%;
  transform: translate(-50%, -50%);
}

.is-place-1 .pt-hero__glow { width: 138px; height: 138px; }

.pt-hero__pet { position: relative; z-index: 1; }

.pt-hero__pet { animation: pt-bob 3s ease-in-out infinite; }
.is-place-2 .pt-hero__pet { animation-delay: -1s; }
.is-place-3 .pt-hero__pet { animation-delay: -2s; }

@keyframes pt-bob { 50% { translate: 0 -6px; } }

.pt-hero strong { max-width: 100%; overflow: hidden; color: #fff; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }
.pt-hero strong em { color: #8a8598; font-size: 12px; font-style: normal; }
.pt-hero small { color: #8a8598; font-size: 12px; }
.pt-hero__value { margin-top: 6px; color: var(--rar, #fff); font-size: 20px; font-weight: 900; }

/* ---------- Список ---------- */

.pt-list { display: flex; flex-direction: column; gap: 6px; margin: 0; padding: 0; list-style: none; }

.pt-row {
  display: grid;
  grid-template-columns: 28px 56px minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;

  padding: 8px 16px 8px 10px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 14px;

  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.pt-row:hover { border-color: color-mix(in srgb, var(--rar) 55%, transparent); background: color-mix(in srgb, var(--rar) 5%, var(--bg-card, #15131c)); }

.pt-row__place { color: #6f6a80; font-family: var(--font-display); font-size: 15px; font-weight: 900; text-align: center; }

/* Квадратный слот на фоне экземпляра — все питомцы одного размера */
.pt-row__pet {
  width: 56px;
  height: 56px;

  display: grid;
  place-items: center;

  overflow: hidden;

  background-repeat: no-repeat;
  background-position: center;
  border: 1px solid color-mix(in srgb, var(--rar) 30%, rgba(255, 255, 255, 0.05));
  border-radius: 12px;
}

.pt-row__name { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.pt-row__name strong { overflow: hidden; color: #fff; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.pt-row__name em { color: #8a8598; font-size: 12px; font-style: normal; }
.pt-row__name small { overflow: hidden; color: #8a8598; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.pt-row__name a { color: #c4b5fd; text-decoration: none; }

.pt-row__value { display: flex; flex-direction: column; align-items: flex-end; }
.pt-row__value b { color: var(--rar, #fff); font-size: 15px; }
.pt-row__value small { color: #8a8598; font-size: 11.5px; }

@media (max-width: 640px) {
  .pt-podium { gap: 6px; }
  .pt-hero { padding: 12px 6px; }
  .pt-hero__stage { height: 90px; }
  .is-place-1 .pt-hero__stage { height: 104px; }
  .pt-hero__glow { width: 88px; height: 88px; }
  .is-place-1 .pt-hero__glow { width: 102px; height: 102px; }
  .pt-hero__value { font-size: 16px; }
  .pt-row { grid-template-columns: 22px 48px minmax(0, 1fr) auto; gap: 10px; }
  .pt-row__pet { width: 48px; height: 48px; }
}
</style>
