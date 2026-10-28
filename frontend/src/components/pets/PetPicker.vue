<script setup>
/**
 * Лента выбора питомца: карточки одного размера в один ряд с прокруткой,
 * а не стена кнопок. Подпись под именем — уровень или рейтинг.
 */
import PetFigure from '@/components/pets/PetFigure.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

defineProps({
  pets: { type: Array, required: true },
  modelValue: { type: Number, default: null },
  // level | rating — что писать под именем
  show: { type: String, default: 'level' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="pp" role="listbox">
    <button
        v-for="p in pets"
        :key="p.inventory_id"
        type="button"
        role="option"
        class="pp__pet"
        :class="{ 'is-on': modelValue === p.inventory_id }"
        :aria-selected="modelValue === p.inventory_id"
        :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color || '#a8a3b8' }"
        @click="$emit('update:modelValue', p.inventory_id)"
    >
      <span class="pp__slot"><PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" /></span>
      <strong>{{ p.name }}</strong>
      <small>{{ show === 'rating' ? `рейтинг ${p.rating}` : `ур. ${p.level}` }}</small>
    </button>
  </div>
</template>

<style scoped>
.pp {
  display: flex;
  gap: 8px;

  padding: 2px 2px 8px;
  overflow-x: auto;

  scroll-snap-type: x proximity;
  scrollbar-width: thin;
}

.pp__pet {
  flex: 0 0 104px;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;

  padding: 10px 6px 8px;

  color: inherit;
  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;

  font: inherit;

  cursor: pointer;
  scroll-snap-align: start;
  transition: border-color 0.15s ease, transform 0.15s ease;
}

.pp__pet:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--rar) 50%, transparent); }

.pp__pet.is-on {
  background: linear-gradient(180deg, color-mix(in srgb, var(--rar) 18%, transparent), transparent 70%), var(--bg-card, #15131c);
  border-color: var(--rar);
  box-shadow: 0 0 0 1px var(--rar);
}

.pp__slot {
  width: 56px;
  height: 46px;
  margin-bottom: 4px;

  display: grid;
  place-items: center;

  background: radial-gradient(circle, color-mix(in srgb, var(--rar) 18%, transparent), transparent 70%);
}

.pp__pet strong { max-width: 100%; overflow: hidden; color: #fff; font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; }
.pp__pet small { color: #8a8598; font-size: 11px; }
</style>
