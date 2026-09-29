<script setup>
import { computed } from 'vue'

const props = defineProps({
  achievements: { type: Array, default: () => [] },
})

const emit = defineEmits(['open'])

const rows = computed(() => {
  const list = props.achievements
  const out = []
  for (let i = 0; i < list.length; i += 4) out.push(list.slice(i, i + 4))
  return out
})

const rarityColors = {
  common: '#7c3aed', rare: '#06b6d4', epic: '#f97316', legendary: '#facc15',
}

function color(a) {
  return a?.color || rarityColors[a?.rarity] || '#7c3aed'
}
</script>

<template>
  <aside v-if="achievements.length" class="showcase">
    <header class="showcase__head">
      <h3 class="showcase__title">Достижения</h3>
      <span class="showcase__count">{{ achievements.length }}</span>
    </header>

    <div class="showcase__rows">
      <div v-for="(row, ri) in rows" :key="ri" class="showcase__row">
        <button
            v-for="a in row"
            :key="a.id"
            type="button"
            class="showcase__cell"
            :style="{ '--color': color(a) }"
            @click="emit('open', a)"
        >
          <div class="showcase__icon">{{ a.icon }}</div>
          <div class="showcase__meta">
            <span class="showcase__name">{{ a.name }}</span>
          </div>
        </button>

        <div
            v-for="n in (4 - row.length)"
            :key="'e-' + ri + '-' + n"
            class="showcase__cell showcase__cell--empty"
        >
          <div class="showcase__icon showcase__icon--empty">?</div>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.showcase {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.showcase__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.showcase__title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: #c7d5e0;
  text-transform: uppercase;
  letter-spacing: 1.2px;
}

.showcase__count {
  font-size: 11px;
  color: #4a5568;
  font-weight: 700;
}

.showcase__rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.showcase__row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

/* === CELL === */

.showcase__cell {
  position: relative;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  font: inherit;
  color: inherit;
  border-radius: 6px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
}

.showcase__cell:hover {
  transform: translateY(-2px);
  border-color: var(--color);
  box-shadow:
      0 6px 18px color-mix(in srgb, var(--color) 35%, transparent),
      inset 0 0 0 1px color-mix(in srgb, var(--color) 60%, transparent);
}

.showcase__cell::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 50%;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), transparent);
  pointer-events: none;
}

.showcase__cell::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at center, color-mix(in srgb, var(--color) 30%, transparent), transparent 70%);
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}

.showcase__cell:hover::after {
  opacity: 1;
}

/* === ICON === */

.showcase__icon {
  font-size: 28px;
  line-height: 1;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.6));
  position: relative;
  z-index: 1;
}

/* === EMPTY CELL === */

.showcase__cell--empty {
  opacity: 0.35;
  cursor: default;
}

.showcase__cell--empty:hover {
  transform: none;
  border-color: rgba(255, 255, 255, 0.06);
  box-shadow: none;
}

.showcase__cell--empty::after {
  display: none;
}

.showcase__icon--empty {
  font-size: 16px;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.15);
}

/* === META (name on hover) === */

.showcase__meta {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 6px 8px;
  background: linear-gradient(0deg, rgba(0, 0, 0, 0.9), transparent);
  font-size: 10px;
  color: #c7d5e0;
  transform: translateY(100%);
  transition: transform 0.2s ease;
  z-index: 2;
  pointer-events: none;
}

.showcase__cell:hover .showcase__meta {
  transform: translateY(0);
}

.showcase__name {
  display: block;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* === АДАПТИВ === */

@media (max-width: 600px) {
  .showcase {
    padding: 12px;
  }

  .showcase__icon {
    font-size: 22px;
  }
}
</style>