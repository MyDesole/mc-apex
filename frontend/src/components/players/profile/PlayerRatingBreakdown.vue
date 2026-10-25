<script setup>
import { computed } from 'vue'

/**
 * Из чего сложились очки общего рейтинга: среднее трёх тиров,
 * турниры и клановые войны. Формула — RatingService на бэкенде.
 */
const props = defineProps({
  rating: { type: Object, default: null },
  titleClass: { type: String, default: 'block-title' },
})

const rows = computed(() => {
  const r = props.rating
  if (!r) return []

  return [
    {
      label: 'Тиры',
      hint: `PvP ${r.pvp}% · BedWars ${r.bedwars}% · бридж ${r.bridge}% — среднее`,
      points: r.tiers_points,
    },
    {
      label: 'Матчи турниров',
      hint: `${r.match_wins} побед`,
      points: r.match_points,
    },
    {
      label: 'Победы в турнирах',
      hint: `${r.tournament_wins} турниров`,
      points: r.tournament_points,
    },
    {
      label: 'Клановые войны',
      hint: `${r.wars} участий, ${r.wars_won} побед`,
      points: r.war_points + r.war_win_points,
    },
  ]
})
</script>

<template>
  <section v-if="rating" class="fact rating-breakdown">
    <h3 :class="titleClass">
      Очки рейтинга
      <strong class="rating-breakdown__total">{{ rating.total }}</strong>
    </h3>

    <ul class="rating-breakdown__list">
      <li v-for="row in rows" :key="row.label" :title="row.hint">
        <span class="rating-breakdown__label">
          {{ row.label }}
          <small>{{ row.hint }}</small>
        </span>
        <b :class="{ 'is-zero': !row.points }">+{{ row.points }}</b>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.rating-breakdown h3 {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.rating-breakdown__total {
  font-family: var(--font-display);
  font-size: 20px;
  color: var(--accent-light);
}

.rating-breakdown__list {
  display: flex;
  flex-direction: column;
  gap: 8px;

  margin: 0;
  padding: 0;
  list-style: none;
}

.rating-breakdown__list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.rating-breakdown__label {
  display: flex;
  flex-direction: column;
  min-width: 0;
  font-size: 14px;
}

.rating-breakdown__label small {
  overflow: hidden;
  font-size: 12px;
  color: var(--text-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rating-breakdown__list b {
  font-weight: 700;
  color: #4ade80;
}

.rating-breakdown__list b.is-zero {
  color: var(--text-muted);
}
</style>
