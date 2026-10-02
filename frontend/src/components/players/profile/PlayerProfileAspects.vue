<script setup>
import { computed } from 'vue'

const props = defineProps({
  aspects: {
    type: Object,
    default: () => ({}),
  },
})

/**
 * Аспекты по режимам.
 *
 * Наборы разные: в PvP это аим и понимание боя, в BedWars — PvP, игра
 * на кровати, командная игра и строительство. Раньше здесь был один
 * общий список из шести полей, в котором не было бедварсных pvp,
 * teamplay и building: они не показывались и не попадали в сумму.
 */
const modes = {
  pvp: {
    title: 'PvP',
    icon: '⚔',
    color: '#8b5cf6',
    stats: [
      { key: 'block_placing', label: 'Block Placement' },
      { key: 'rotka', label: 'Rod' },
      { key: 'movement', label: 'Movement' },
      { key: 'aim', label: 'Aim' },
      { key: 'game_sense', label: 'Game Sense' },
    ],
  },
  bedwars: {
    title: 'BedWars',
    icon: '◆',
    color: '#facc15',
    stats: [
      { key: 'pvp', label: 'PvP' },
      { key: 'game_sense', label: 'Game Sense' },
      { key: 'bed_play', label: 'Bed Play' },
      { key: 'teamplay', label: 'Teamplay' },
      { key: 'building', label: 'Building' },
    ],
  },
}

const availableModes = computed(() =>
    Object.entries(props.aspects)
        .filter(([, value]) => value && typeof value === 'object')
        .map(([key, value]) => {
          const mode = modes[key]

          return {
            key,
            data: value,
            title: mode?.title ?? key,
            icon: mode?.icon ?? '◆',
            color: mode?.color ?? '#8b5cf6',
            // Неизвестный режим показываем по полям, которые реально пришли
            stats: mode?.stats ?? Object.keys(value).map((field) => ({
              key: field,
              label: field,
            })),
          }
        }),
)

function percentage(value) {
  return Math.min(100, Math.max(0, (Number(value) / 20) * 100))
}

function totalScore(data, stats) {
  const values = stats
      .map(stat => Number(data?.[stat.key]))
      .filter(value => Number.isFinite(value))

  if (!values.length) return 0

  return values.reduce((sum, value) => sum + value, 0)
}

function maxScore(data, stats) {
  const count = stats.filter(stat =>
      Number.isFinite(Number(data?.[stat.key]))
  ).length

  return count * 20
}
</script>

<template>
  <section v-if="availableModes.length" class="aspects">
    <div class="section-head">


      <span class="section-line"></span>
    </div>

    <div class="aspects-grid">
      <article
          v-for="mode in availableModes"
          :key="mode.key"
          class="aspect-card"
          :style="{ '--mode-color': mode.color }"
      >
        <div class="aspect-card__glow"></div>

        <header class="aspect-header">
          <div class="aspect-icon">
            {{ mode.icon }}
          </div>

          <div class="aspect-info">
            <span class="aspect-label">РЕЖИМ</span>
            <h4>{{ mode.title }}</h4>
          </div>

          <div class="aspect-total">
            <strong>{{ totalScore(mode.data, mode.stats) }}</strong>
            <span>/{{ maxScore(mode.data, mode.stats) }}</span>
          </div>
        </header>

        <div class="stats">
          <div
              v-for="stat in mode.stats"
              :key="stat.key"
              v-show="mode.data?.[stat.key] !== undefined && mode.data?.[stat.key] !== null"
              class="stat"
          >
            <div class="stat-top">
              <span>{{ stat.label }}</span>
              <strong>
                {{ mode.data[stat.key] }}
                <small>/20</small>
              </strong>
            </div>

            <div class="stat-track">
              <div
                  class="stat-fill"
                  :style="{ width: `${percentage(mode.data[stat.key])}%` }"
              ></div>
            </div>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
@import "@/components/players/profile/PlayerProfileAspects.css";
</style>
