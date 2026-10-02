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
@import "@/components/achievements/AchievementsGrid.css";
</style>
