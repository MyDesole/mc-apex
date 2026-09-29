<script setup>
import { computed } from 'vue'
import  {aspectColor, percentColor} from '@/composables/useTier'

const props = defineProps({
  aspects: { type: Object, default: () => ({}) },
})

const ASPECT_LABELS = {
  pvp: { block_placing: 'БП', rotka: 'Ротка', movement: 'Мувмент', aim: 'Аим', game_sense: 'Понимание боя' },
  bedwars: { pvp: 'PvP', game_sense: 'Понимание игры', bed_play: 'Игра на кровати', teamplay: 'Командная игра', building: 'Строительство' },
}
const ASPECT_KEYS = {
  pvp: ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'],
  bedwars: ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'],
}
const EMPTY_PVP = { block_placing: 0, rotka: 0, movement: 0, aim: 0, game_sense: 0 }
const EMPTY_BEDWARS = { pvp: 0, game_sense: 0, bed_play: 0, teamplay: 0, building: 0 }

const allAspects = computed(() => {
  const ua = props.aspects ?? {}
  const pvp = ua.pvp ?? EMPTY_PVP
  const bw = ua.bedwars ?? EMPTY_BEDWARS

  const pvpSum = pvp.block_placing + pvp.rotka + pvp.movement + pvp.aim + pvp.game_sense
  const bwSum = bw.pvp + bw.game_sense + bw.bed_play + bw.teamplay + bw.building

  return [
    { mode: 'pvp', ...pvp, percent: pvpSum, hasData: !!ua.pvp },
    { mode: 'bedwars', ...bw, percent: bwSum, hasData: !!ua.bedwars },
  ]
})

function totalScore(a) {
  if (a.mode === 'bedwars') return a.pvp + a.game_sense + a.bed_play + a.teamplay + a.building
  return a.block_placing + a.rotka + a.movement + a.aim + a.game_sense
}
</script>

<template>
  <section class="aspects">
    <div class="aspects__head">
      <h3 class="aspects__title">Аспекты игрока</h3>
      <span class="aspects__sub">Оценка по 5 критериям · макс. 100</span>
    </div>

    <div class="aspects__list">
      <article
          v-for="aspect in allAspects"
          :key="aspect.mode"
          class="aspect-card"
          :class="{
          'aspect-card--empty': !aspect.hasData,
          [`aspect-card--${aspect.mode}`]: true,
        }"
      >
        <header class="aspect-card__head">
          <div class="aspect-card__head-left">
            <span class="aspect-card__mode">{{ aspect.mode === 'pvp' ? 'PvP' : 'BedWars' }}</span>
            <span class="aspect-card__sub">{{ aspect.mode === 'pvp' ? 'p-ранг' : 'b-ранг' }}</span>
            <span v-if="!aspect.hasData" class="badge-empty">не тестирован</span>
          </div>

          <div class="aspect-card__head-right">
            <div class="total">
              <span class="total__value">{{ totalScore(aspect) }}</span>
              <span class="total__max">/100</span>
            </div>
            <div
                class="percent-pill"
                :style="{
                color: percentColor(aspect.percent),
                borderColor: percentColor(aspect.percent) + '55',
                background: percentColor(aspect.percent) + '12',
              }"
            >
              {{ aspect.percent }}%
            </div>
          </div>
        </header>

        <div class="aspect-card__grid">
          <div v-for="key in ASPECT_KEYS[aspect.mode]" :key="key" class="aspect">
            <div class="aspect__top">
              <span class="aspect__label">{{ ASPECT_LABELS[aspect.mode][key] }}</span>
              <span class="aspect__value" :class="{ 'aspect__value--zero': !aspect[key] }">
                {{ aspect[key] ?? 0 }}
              </span>
            </div>
            <div class="aspect__bar">
              <div
                  class="aspect__fill"
                  :style="{
                  width: ((aspect[key] ?? 0) / 20 * 100) + '%',
                  background: aspectColor(aspect[key] ?? 0),
                }"
              />
            </div>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.aspects {
  margin-top: 4px;
}

.aspects__head {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 14px;
  padding: 0 2px;
}

.aspects__title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.aspects__sub {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.aspects__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* === CARD === */

.aspect-card {
  position: relative;
  padding: 16px 18px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s ease;
}

.aspect-card:hover {
  border-color: var(--border-hover);
}

.aspect-card--empty {
  opacity: 0.75;
}

.aspect-card--pvp {
  background: linear-gradient(180deg, rgba(124, 58, 237, 0.04), transparent 40%), #0d0d14;
}

.aspect-card--bedwars {
  background: linear-gradient(180deg, rgba(6, 182, 212, 0.04), transparent 40%), #0d0d14;
}

/* === CARD HEAD === */

.aspect-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  gap: 12px;
}

.aspect-card__head-left {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
  flex-wrap: wrap;
}

.aspect-card__mode {
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.aspect-card__sub {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.badge-empty {
  display: inline-block;
  padding: 2px 8px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.aspect-card__head-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

/* === TOTAL === */

.total {
  display: flex;
  align-items: baseline;
  gap: 2px;
  font-weight: 900;
}

.total__value {
  font-size: 16px;
  color: var(--text);
}

.total__max {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 700;
}

.percent-pill {
  padding: 4px 10px;
  border: 1px solid;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
}

/* === GRID === */

.aspect-card__grid {
  display: grid;
  gap: 10px;
}

.aspect {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.aspect__top {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.aspect__label {
  color: var(--text-dim);
  font-size: 12px;
  font-weight: 600;
}

.aspect__value {
  color: var(--text);
  font-size: 12px;
  font-weight: 800;
}

.aspect__value--zero {
  color: var(--text-muted);
}

.aspect__bar {
  height: 6px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 999px;
  overflow: hidden;
}

.aspect__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

/* === АДАПТИВ === */

@media (max-width: 600px) {
  .aspect-card {
    padding: 14px;
  }

  .aspect-card__head {
    flex-wrap: wrap;
  }

  .aspect-card__head-right {
    width: 100%;
    justify-content: space-between;
  }
}
</style>