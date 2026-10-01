<script setup>
import { computed } from 'vue'

const props = defineProps({
  aspects: {
    type: Object,
    default: () => ({}),
  },
})

const modes = {
  pvp: {
    title: 'PvP',
    icon: '⚔',
    color: '#8b5cf6',
  },
  bedwars: {
    title: 'BedWars',
    icon: '◆',
    color: '#facc15',
  },
}

const stats = [
  { key: 'block_placing', label: 'Block Placing' },
  { key: 'rotka', label: 'Rotka' },
  { key: 'movement', label: 'Movement' },
  { key: 'aim', label: 'Aim' },
  { key: 'game_sense', label: 'Game Sense' },
  { key: 'bed_play', label: 'Bed Play' },
]

const availableModes = computed(() =>
    Object.entries(props.aspects)
        .filter(([, value]) => value && typeof value === 'object')
        .map(([key, value]) => ({
          key,
          data: value,
          ...(modes[key] ?? {
            title: key,
            icon: '◆',
            color: '#8b5cf6',
          }),
        })),
)

function percentage(value) {
  return Math.min(100, Math.max(0, (Number(value) / 20) * 100))
}

function totalScore(data) {
  const values = stats
      .map(stat => Number(data?.[stat.key]))
      .filter(value => Number.isFinite(value))

  if (!values.length) return 0

  return values.reduce((sum, value) => sum + value, 0)
}

function maxScore(data) {
  const count = stats.filter(stat =>
      Number.isFinite(Number(data?.[stat.key]))
  ).length

  return count * 20
}
</script>

<template>
  <section v-if="availableModes.length" class="aspects">
    <div class="section-head">
      <div>
        <span class="section-kicker">ХАРАКТЕРИСТИКИ</span>
        <h3 class="section-title">Игровые аспекты</h3>
      </div>

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
            <strong>{{ totalScore(mode.data) }}</strong>
            <span>/{{ maxScore(mode.data) }}</span>
          </div>
        </header>

        <div class="stats">
          <div
              v-for="stat in stats"
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
.aspects {
  position: relative;
  margin-top: 18px;
  padding: 22px;
  border: 1px solid rgba(139, 92, 246, 0.16);
  border-radius: 18px;
  background:
      radial-gradient(
          circle at 100% 0%,
          rgba(124, 58, 237, 0.11),
          transparent 35%
      ),
      rgba(7, 8, 20, 0.88);
  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 12px 40px rgba(0, 0, 0, 0.28);
}

.section-head {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 18px;
}

.section-kicker {
  display: block;
  margin-bottom: 3px;
  color: #8b5cf6;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.18em;
}

.section-title {
  margin: 0;
  color: #f5f3ff;
  font-size: 19px;
  font-weight: 900;
  letter-spacing: -0.02em;
}

.section-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(
      90deg,
      rgba(139, 92, 246, 0.28),
      transparent
  );
}

.aspects-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.aspect-card {
  position: relative;
  overflow: hidden;
  padding: 17px;
  border: 1px solid color-mix(
      in srgb,
      var(--mode-color) 20%,
      transparent
  );
  border-radius: 14px;
  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--mode-color) 7%, transparent),
          rgba(9, 10, 24, 0.92)
      );
  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 8px 25px rgba(0, 0, 0, 0.2);
}

.aspect-card__glow {
  position: absolute;
  top: -80px;
  right: -80px;
  width: 170px;
  height: 170px;
  border-radius: 50%;
  background: var(--mode-color);
  opacity: 0.07;
  filter: blur(45px);
  pointer-events: none;
}

.aspect-header {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 20px;
}

.aspect-icon {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border: 1px solid color-mix(
      in srgb,
      var(--mode-color) 30%,
      transparent
  );
  border-radius: 11px;
  background: color-mix(
      in srgb,
      var(--mode-color) 9%,
      rgba(0, 0, 0, 0.3)
  );
  color: var(--mode-color);
  font-size: 19px;
  box-shadow: 0 0 20px color-mix(
      in srgb,
      var(--mode-color) 12%,
      transparent
  );
}

.aspect-info {
  min-width: 0;
  flex: 1;
}

.aspect-label {
  display: block;
  margin-bottom: 2px;
  color: #66677d;
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.16em;
}

.aspect-info h4 {
  margin: 0;
  color: #f3f1ff;
  font-size: 15px;
  font-weight: 900;
}

.aspect-total {
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.aspect-total strong {
  color: var(--mode-color);
  font-size: 19px;
  font-weight: 900;
}

.aspect-total span {
  color: #66677d;
  font-size: 11px;
  font-weight: 700;
}

.stats {
  position: relative;
  z-index: 1;
  display: grid;
  gap: 12px;
}

.stat-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.stat-top span {
  color: #aaa8bb;
  font-size: 11px;
  font-weight: 600;
}

.stat-top strong {
  color: #eeeafc;
  font-size: 11px;
  font-weight: 800;
}

.stat-top small {
  color: #5f6075;
  font-size: 9px;
}

.stat-track {
  position: relative;
  height: 5px;
  overflow: hidden;
  border-radius: 99px;
  background: rgba(255, 255, 255, 0.055);
}

.stat-fill {
  height: 100%;
  min-width: 2px;
  border-radius: inherit;
  background: linear-gradient(
      90deg,
      color-mix(in srgb, var(--mode-color) 55%, transparent),
      var(--mode-color)
  );
  box-shadow: 0 0 10px color-mix(
      in srgb,
      var(--mode-color) 40%,
      transparent
  );
  transition: width 0.4s ease;
}

@media (max-width: 700px) {
  .aspects {
    padding: 16px;
  }

  .aspects-grid {
    grid-template-columns: 1fr;
  }
}
</style>