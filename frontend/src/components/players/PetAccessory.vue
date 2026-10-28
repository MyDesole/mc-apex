<script setup>
/**
 * Аксессуар питомца — пиксель-арт в том же стиле, что и сами питомцы
 * (раньше были эмодзи и смотрелись чужеродно). Карта: строки одинаковой
 * длины, точка — пусто, буква — цвет из палитры аксессуара.
 */
import { computed } from 'vue'
import { PET_ACCESSORY_ART } from '@/data/players/petTraits.js'

const props = defineProps({
  id: { type: String, required: true },
  // Ширина в пикселях экрана
  width: { type: Number, default: 24 },
})

const art = computed(() => PET_ACCESSORY_ART[props.id] ?? null)

const cells = computed(() => {
  if (!art.value) return []
  const out = []
  art.value.map.forEach((row, y) => {
    [...row].forEach((ch, x) => {
      const color = art.value.palette[ch]
      if (color) out.push({ x, y, color })
    })
  })
  return out
})

const cols = computed(() => art.value?.map[0]?.length ?? 1)
const rows = computed(() => art.value?.map.length ?? 1)
</script>

<template>
  <svg
      v-if="art"
      class="pet-accessory"
      :class="art.glow ? 'pet-accessory--glow' : null"
      :width="width"
      :height="Math.round(width * rows / cols)"
      :viewBox="`0 0 ${cols} ${rows}`"
      shape-rendering="crispEdges"
      aria-hidden="true"
  >
    <rect v-for="(c, i) in cells" :key="i" :x="c.x" :y="c.y" width="1" height="1" :fill="c.color" />
  </svg>
</template>

<style>
.pet-accessory {
  display: block;
  image-rendering: pixelated;
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.45));
}

.pet-accessory--glow {
  filter: drop-shadow(0 0 3px rgba(253, 224, 71, 0.9));
}
</style>
