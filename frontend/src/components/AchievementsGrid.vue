<script setup>
const props = defineProps({
  achievements: { type: Array, default: () => [] },
  showLocked: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

const filtered = computed(() => {
  if (props.showLocked) return props.achievements
  return props.achievements.filter(a => a.earned !== false)
})

import { computed } from 'vue'

const rarityLabels = {
  common: 'Обычная',
  rare: 'Редкая',
  epic: 'Эпическая',
  legendary: 'Легендарная',
}

const rarityGlow = {
  common: 'rgba(124, 58, 237, 0.15)',
  rare: 'rgba(6, 182, 212, 0.25)',
  epic: 'rgba(249, 115, 22, 0.3)',
  legendary: 'rgba(250, 204, 21, 0.4)',
}
</script>

<template>
  <div v-if="filtered.length" class="achievements" :class="{ 'achievements--compact': compact }">
    <div
        v-for="a in filtered"
        :key="a.id"
        class="achievement"
        :class="[
                `achievement--${a.rarity}`,
                { 'achievement--locked': a.earned === false },
            ]"
        :style="{
                '--color': a.color,
                '--glow': rarityGlow[a.rarity],
            }"
        :title="a.description"
    >
      <div class="achievement__icon">{{ a.icon }}</div>

      <div class="achievement__info">
        <div class="achievement__name">{{ a.name }}</div>
        <div v-if="!compact" class="achievement__desc">{{ a.description }}</div>
      </div>

      <div v-if="!compact" class="achievement__points">+{{ a.points }}</div>
    </div>
  </div>

  <div v-else class="empty">Ачивок пока нет</div>
</template>

<style scoped>
.achievements {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}

.achievements--compact {
  grid-template-columns: repeat(auto-fill, minmax(56px, 1fr));
  gap: 8px;
}

.achievement {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s ease;
  overflow: hidden;
}

.achievement::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 0% 50%, var(--glow), transparent 60%);
  pointer-events: none;
  opacity: 0.6;
}

.achievement:hover {
  transform: translateY(-2px);
  border-color: var(--color);
  box-shadow: 0 8px 30px var(--glow);
}

.achievement__icon {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--color);
  border-radius: 10px;
  font-size: 22px;
  box-shadow: 0 0 20px var(--glow);
}

.achievement__info {
  flex: 1;
  min-width: 0;
  position: relative;
}

.achievement__name {
  font-size: 13px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.achievement__desc {
  font-size: 11px;
  color: var(--text-dim);
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.achievement__points {
  position: relative;
  flex-shrink: 0;
  padding: 4px 8px;
  color: var(--color);
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--color);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
}

/* Заблокированные */
.achievement--locked {
  opacity: 0.35;
  filter: grayscale(0.7);
}

.achievement--locked .achievement__icon {
  background: rgba(10, 10, 15, 0.4);
  border-color: var(--border);
  box-shadow: none;
}

/* Compact — только иконки */
.achievements--compact .achievement {
  padding: 8px;
  justify-content: center;
}

.achievements--compact .achievement__info {
  display: none;
}

.achievements--compact .achievement__icon {
  width: 40px;
  height: 40px;
  font-size: 20px;
}

.empty {
  padding: 24px;
  text-align: center;
  color: var(--text-muted);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}

@media (max-width: 600px) {
  .achievements {
    grid-template-columns: 1fr;
  }
}
</style>