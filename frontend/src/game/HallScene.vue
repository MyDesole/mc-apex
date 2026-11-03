<script setup>
/**
 * Доска почёта: лучшие питомцы сервера. Тройка стоит на ступенях
 * пьедестала, остальные висят табличками. Три доски — по уровню,
 * по арене и по редкости.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import { ELEMENTS } from '@/data/players/petArena.js'
import { PET_RARITY } from '@/data/players/petTraits.js'

const BOARDS = [['level', 'Уровень'], ['rating', 'Арена'], ['rare', 'Редкость']]

const by = ref('level')
const pets = ref(null)
const error = ref('')

const top = computed(() => (pets.value ?? []).slice(0, 3))
const rest = computed(() => (pets.value ?? []).slice(3))
// Пьедестал: второй слева, первый в центре, третий справа
const podium = computed(() => [top.value[1] && { pet: top.value[1], place: 2 }, top.value[0] && { pet: top.value[0], place: 1 }, top.value[2] && { pet: top.value[2], place: 3 }].filter(Boolean))

function value(p) {
  if (by.value === 'level') return `ур. ${p.level}`
  if (by.value === 'rating') return `${p.rating} · ${p.wins}–${p.losses}`
  return p.chance === undefined ? PET_RARITY[p.traits?.rarity]?.label : p.chance < 0.01 ? '< 0,01 %' : `${String(p.chance).replace('.', ',')} %`
}

async function load() {
  pets.value = null
  try {
    pets.value = (await api.get('/pets/top', { params: { by: by.value } })).pets ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить доску.'
    pets.value = []
  }
}

watch(by, load)
onMounted(load)
</script>

<template>
  <div class="g-scene hl">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>

    <!-- Три доски на стене -->
    <nav class="hl-boards">
      <button v-for="[id, label] in BOARDS" :key="id" type="button" class="g-plank hl-sign" :class="{ 'is-on': by === id }" @click="by = id">{{ label }}</button>
    </nav>

    <p v-if="pets && !pets.length" class="g-hint">Пока пусто — сыграй на арене первым.</p>

    <template v-else-if="pets">
      <div class="hl-stage">
        <div v-for="{ pet: p, place } in podium" :key="p.inventory_id" class="hl-step" :class="`is-${place}`" :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }">
          <span class="hl-step__pet"><PetFigure :species="p.species" :traits="p.traits" :size="place === 1 ? 86 : 70" :backdrop="false" trick="sparkle" /></span>
          <span class="g-plank hl-step__plank">{{ p.name }}<small>{{ p.owner?.username }} · <span :style="{ color: ELEMENTS[p.element]?.color }">{{ ELEMENTS[p.element]?.name }}</span></small></span>
          <span class="hl-step__block"><b>{{ place }}</b><small>{{ value(p) }}</small></span>
        </div>
      </div>

      <ol class="hl-list" start="4">
        <li v-for="p in rest" :key="p.inventory_id" class="g-stone">
          <PetFigure :species="p.species" :traits="p.traits" :size="32" :backdrop="false" />
          <span><b>{{ p.name }}</b><small>{{ p.owner?.username }}</small></span>
          <i>{{ value(p) }}</i>
        </li>
      </ol>
    </template>
    <p v-else class="g-hint">Доска загружается…</p>
  </div>
</template>

<style scoped>
.hl { background: linear-gradient(rgba(0, 0, 0, 0.1), transparent 160px); }
.hl-boards { display: flex; gap: 8px; margin-bottom: 14px; }
.hl-sign { padding: 5px 18px; font-size: 13px; font-family: inherit; cursor: pointer; }
.hl-sign.is-on { background: #3f9a2f; border-color: #1f4d17; box-shadow: inset 0 3px 0 rgba(255, 255, 255, 0.25); }
.hl-stage { display: flex; align-items: flex-end; justify-content: center; gap: 10px; min-height: 270px; padding: 12px; background: radial-gradient(60% 70% at 50% 100%, rgba(255, 212, 92, 0.18), transparent 70%), #20150c; border: 4px solid #1b0f06; }
.hl-step { display: flex; flex-direction: column; align-items: center; gap: 4px; width: 150px; }
.hl-step__plank { max-width: 150px; }
.hl-step__block { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; background: #8f6a2a; border: 4px solid #4a2a10; box-shadow: inset 0 4px 0 #b8892f; }
.hl-step__block b { font-size: 26px; text-shadow: 2px 2px 0 #4a2a10; }
.hl-step__block small { font-size: 11px; color: #ffe9a8; }
.hl-step.is-1 .hl-step__block { height: 90px; background: #b8860b; }
.hl-step.is-2 .hl-step__block { height: 64px; background: #8a8f99; border-color: #3b3f47; box-shadow: inset 0 4px 0 #b6bbc4; }
.hl-step.is-3 .hl-step__block { height: 46px; background: #9a5a2a; }
.hl-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 6px; margin: 12px 0 0; padding: 0; list-style-position: inside; font-size: 12px; }
.hl-list li { display: flex; align-items: center; gap: 8px; padding: 4px 8px; }
.hl-list li span { display: flex; flex-direction: column; margin-right: auto; min-width: 0; }
.hl-list b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hl-list small { color: #c9b9e8; }
.hl-list i { font-style: normal; font-weight: 800; color: #ffd45c; }
@media (max-width: 640px) { .hl-step { width: 104px; } }
</style>
