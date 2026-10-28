<script setup>
/**
 * Топ питомцев сервера: самые прокачанные, лучшие бойцы арены и самые
 * редкие экземпляры.
 */
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import { ELEMENTS } from '@/data/players/petArena.js'
import { PET_RARITY } from '@/data/players/petTraits.js'
import { userLink } from '@/utils/links.js'

const by = ref('level')
const pets = ref(null)

const TABS = [['level', 'Уровень'], ['rating', 'Арена'], ['rare', 'Редкость']]

async function load() {
  pets.value = null
  pets.value = (await api.get('/pets/top', { params: { by: by.value } })).pets ?? []
}

function chance(p) {
  return p.chance < 0.01 ? '< 0,01 %' : `${String(p.chance).replace('.', ',')} %`
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

    <ol v-else class="pt-list">
      <li v-for="(p, i) in pets" :key="p.inventory_id" class="pt-row" :class="`is-place-${i + 1}`" :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }">
        <span class="pt-row__place">{{ i + 1 }}</span>
        <PetFigure :species="p.species" :traits="p.traits" :size="38" />
        <span class="pt-row__name">
          <strong>{{ p.name }} <em v-if="p.traits?.serial">#{{ p.traits.serial }}</em></strong>
          <small>
            <RouterLink v-if="p.owner" :to="userLink(p.owner)">{{ p.owner.username }}</RouterLink>
            · <span :style="{ color: ELEMENTS[p.element]?.color }">{{ ELEMENTS[p.element]?.name }}</span>
          </small>
        </span>
        <span class="pt-row__value">
          <template v-if="by === 'level'"><b>Ур. {{ p.level }}</b><small>{{ p.xp }} опыта</small></template>
          <template v-else-if="by === 'rating'"><b>{{ p.rating }}</b><small>{{ p.wins }} – {{ p.losses }}</small></template>
          <template v-else><b>{{ chance(p) }}</b><small>{{ PET_RARITY[p.traits?.rarity]?.label }}</small></template>
        </span>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.pt { display: flex; flex-direction: column; gap: 12px; }

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

.pt-list { display: flex; flex-direction: column; gap: 6px; margin: 0; padding: 0; list-style: none; }

.pt-row {
  display: grid;
  grid-template-columns: 32px auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;

  padding: 8px 14px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
}

.pt-row.is-place-1 { border-color: rgba(250, 204, 21, 0.55); }
.pt-row.is-place-2 { border-color: rgba(203, 213, 225, 0.45); }
.pt-row.is-place-3 { border-color: rgba(217, 119, 6, 0.45); }

.pt-row__place { color: #6f6a80; font-family: var(--font-display); font-size: 16px; font-weight: 900; text-align: center; }
.is-place-1 .pt-row__place { color: #facc15; }
.is-place-2 .pt-row__place { color: #cbd5e1; }
.is-place-3 .pt-row__place { color: #d97706; }

.pt-row__name { display: flex; flex-direction: column; min-width: 0; }
.pt-row__name strong { overflow: hidden; color: #fff; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.pt-row__name em { color: #8a8598; font-size: 12px; font-style: normal; }
.pt-row__name small { color: #8a8598; font-size: 12px; }
.pt-row__name a { color: #c4b5fd; text-decoration: none; }

.pt-row__value { display: flex; flex-direction: column; align-items: flex-end; }
.pt-row__value b { color: var(--rar, #fff); font-size: 15px; }
.pt-row__value small { color: #8a8598; font-size: 11.5px; }
</style>
