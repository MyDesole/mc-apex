<script setup>
import { ref, watch } from 'vue'
import { seasonsApi } from '@/services/seasons/seasons.js'
import { tierColors } from '@/data/players/profileCustomization.js'

/** Места игрока в прошедших сезонах. Пока сезонов нет — блок скрыт. */
const props = defineProps({
  userId: { type: Number, required: true },
  titleClass: { type: String, default: 'block-title' },
})

const results = ref([])

async function load(id) {
  try {
    results.value = (await seasonsApi.player(id)).results ?? []
  } catch {
    results.value = []
  }
}

watch(() => props.userId, load, { immediate: true })
</script>

<template>
  <section v-if="results.length" class="fact seasons">
    <h3 :class="titleClass">Сезоны</h3>

    <ul class="seasons__list">
      <li v-for="r in results" :key="r.season.id" class="seasons__row">
        <span class="seasons__name">{{ r.season.name }}</span>
        <span class="tier-block seasons__tier" :style="{ '--tier': tierColors[r.tier] }">{{ r.tier }}</span>
        <span class="seasons__rank">#{{ r.rank }}<small> из {{ r.players }}</small></span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.seasons__list {
  display: flex;
  flex-direction: column;
  gap: 8px;

  margin: 0;
  padding: 0;
  list-style: none;
}

.seasons__row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;
  align-items: center;
}

.seasons__name {
  overflow: hidden;
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.seasons__tier {
  width: 24px;
  height: 24px;
  font-size: 12px;
}

.seasons__rank {
  min-width: 64px;
  font-weight: 700;
  text-align: right;
}

.seasons__rank small {
  font-weight: 400;
  color: var(--text-muted);
}
</style>
