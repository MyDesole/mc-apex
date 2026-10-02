<script setup>
const props = defineProps({
  achievement: { type: Object, required: true },
})
const emit = defineEmits(['close'])

const rarityColors = {
  common: '#8b5cf6',
  rare: '#06b6d4',
  epic: '#f97316',
  legendary: '#facc15',
}
const rarityLabels = {
  common: 'Обычное',
  rare: 'Редкое',
  epic: 'Эпическое',
  legendary: 'Легендарное',
}

function accent(a) {
  return a?.color || rarityColors[a?.rarity] || '#8b5cf6'
}

function formatEarnedAt(a) {
  const raw = a?.pivot?.earned_at ?? a?.earned_at
  if (!raw) return null
  try {
    return new Date(raw).toLocaleDateString('ru-RU', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    })
  } catch {
    return null
  }
}
</script>

<template>
  <div class="modal-bg" @click.self="emit('close')">
    <div class="modal" :style="{ '--color': accent(achievement) }">
      <button
          class="modal__close"
          type="button"
          aria-label="Закрыть"
          @click="emit('close')"
      >
        <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
          <path d="M18 6 6 18M6 6l12 12" />
        </svg>
      </button>

      <div class="modal__glow" />

      <div class="modal__icon">{{ achievement.icon }}</div>

      <h3 class="modal__name">{{ achievement.name }}</h3>

      <div class="modal__chips">
        <span v-if="achievement.rarity" class="chip chip--rarity">
          {{ rarityLabels[achievement.rarity] ?? achievement.rarity }}
        </span>
        <span v-if="achievement.points" class="chip chip--points">
          <svg
              width="11"
              height="11"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path
                d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z"
            />
          </svg>
          {{ achievement.points }} очков
        </span>
      </div>

      <p v-if="achievement.description" class="modal__desc">
        {{ achievement.description }}
      </p>

      <div v-if="formatEarnedAt(achievement)" class="modal__date">
        <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <path d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        Получено {{ formatEarnedAt(achievement) }}
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/players/profile/AchievementModal.css";
</style>
