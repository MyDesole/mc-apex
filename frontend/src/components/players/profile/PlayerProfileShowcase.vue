<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AchievementBadge from '@/components/achievements/AchievementBadge.vue'

const props = defineProps({
  achievements: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['open'])

const achievements = computed(() => props.achievements ?? [])

/** Сколько ачивок видно сразу: при десятках ачивок профиль становился бесконечным. */
const LIMIT = 6

const expanded = ref(false)

const visible = computed(() =>
    expanded.value ? achievements.value : achievements.value.slice(0, LIMIT)
)

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

      <RouterLink to="/achievements" class="showcase__all">Все ачивки →</RouterLink>
    </div>

    <div v-if="achievements.length" class="achievement-grid">
      <button
          v-for="achievement in visible"
          :key="achievement.id"
          type="button"
          class="achievement"
          :style="{ '--achievement-color': achievementColor(achievement) }"
          @click="openAchievement(achievement)"
      >
        <AchievementBadge :achievement="achievement" :size="40" />

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

    <button
        v-if="achievements.length > LIMIT"
        type="button"
        class="showcase__more"
        @click="expanded = !expanded"
    >
      {{ expanded ? 'Свернуть' : `Показать все (${achievements.length})` }}
    </button>

    <div v-else class="showcase-empty">
      <AchievementBadge :achievement="{ icon: 'trophy', rarity: 'common' }" :size="40" locked />

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
