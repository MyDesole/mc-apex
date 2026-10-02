<script setup>
import { computed } from 'vue'

const props = defineProps({
  achievements: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['open'])

const achievements = computed(() => props.achievements ?? [])

function achievementColor(achievement) {
  return achievement?.color || '#8b5cf6'
}

function openAchievement(achievement) {
  emit('open', achievement)
}
</script>

<template>
  <section class="showcase">
    <div class="showcase__top">
      <div>

        <div class="showcase__title-row">
          <h3 class="showcase__title">Трофеи</h3>

          <span class="showcase__count">
            {{ achievements.length }}
          </span>
        </div>
      </div>

      <div class="showcase__symbol">✦</div>
    </div>

    <div v-if="achievements.length" class="achievement-grid">
      <button
          v-for="achievement in achievements"
          :key="achievement.id"
          type="button"
          class="achievement"
          :style="{ '--achievement-color': achievementColor(achievement) }"
          @click="openAchievement(achievement)"
      >
        <span class="achievement__icon">
          {{ achievement.icon || '✦' }}
        </span>

        <span class="achievement__content">
          <span class="achievement__name">
            {{ achievement.name }}
          </span>

          <span
              v-if="achievement.description"
              class="achievement__description"
          >
            {{ achievement.description }}
          </span>
        </span>

        <span class="achievement__arrow">›</span>
      </button>
    </div>

    <div v-else class="showcase-empty">
      <div class="showcase-empty__icon">✦</div>

      <div>
        <strong>Пока нет достижений</strong>
        <span>Здесь появятся твои трофеи</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
@import "@/components/players/profile/PlayerProfileShowcase.css";
</style>
