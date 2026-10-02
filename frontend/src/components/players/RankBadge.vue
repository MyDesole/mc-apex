<script setup>
import { computed } from 'vue'

const props = defineProps({
  position: { type: Number, default: null },
  total: { type: Number, default: 0 },
})

const isTop10 = computed(() => props.position && props.position <= 10)

const medal = computed(() => {
  if (props.position === 1) return '🥇'
  if (props.position === 2) return '🥈'
  if (props.position === 3) return '🥉'
  return null
})

const percentile = computed(() => {
  if (!props.position || !props.total) return null

  return Math.round(
      (1 - (props.position - 1) / props.total) * 100
  )
})
</script>

<template>
  <!-- RANKED -->
  <div
      v-if="position"
      class="rank-badge"
      :class="{
      'rank-gold': position === 1,
      'rank-silver': position === 2,
      'rank-bronze': position === 3,
      'rank-top10': isTop10 && position > 3,
    }"
  >
    <div class="rank-badge__glow"></div>

    <div class="rank-badge__topline">
      <span class="rank-badge__label">
        GLOBAL RANKING
      </span>

      <span
          v-if="position <= 10"
          class="rank-badge__status"
      >
        <i></i>
        TOP {{ position <= 3 ? '3' : '10' }}
      </span>
    </div>

    <div class="rank-badge__main">
      <div class="rank-badge__icon">
        <span v-if="medal" class="medal">
          {{ medal }}
        </span>

        <svg
            v-else
            width="22"
            height="22"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >
          <path
              d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"
              stroke-linejoin="round"
          />
        </svg>
      </div>

      <div class="rank-badge__info">
        <div class="rank-badge__position">
          <span class="num">
            #{{ position }}
          </span>

          <span class="label">
            место в рейтинге
          </span>
        </div>

        <div class="rank-badge__meta">
          <span>
            из {{ total }} игроков
          </span>

          <template v-if="percentile !== null">
            <span class="sep">/</span>

            <span class="percentile">
              лучше {{ percentile }}%
            </span>
          </template>
        </div>
      </div>

      <div class="rank-badge__arrow">
        <svg
            width="17"
            height="17"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
          <path
              d="M9 18l6-6-6-6"
              stroke-linecap="round"
              stroke-linejoin="round"
          />
        </svg>
      </div>
    </div>

    <div class="rank-badge__line">
      <span></span>
    </div>
  </div>

  <!-- UNRANKED -->
  <div
      v-else
      class="rank-badge rank-unranked"
  >
    <div class="rank-badge__topline">
      <span class="rank-badge__label">
        GLOBAL RANKING
      </span>

      <span class="rank-badge__status rank-badge__status--idle">
        <i></i>
        UNRANKED
      </span>
    </div>

    <div class="rank-badge__main">
      <div class="rank-badge__icon">
        <svg
            width="21"
            height="21"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >
          <circle cx="12" cy="12" r="10" />

          <path
              d="M12 8v4M12 16h.01"
              stroke-linecap="round"
          />
        </svg>
      </div>

      <div class="rank-badge__info">
        <div class="rank-badge__position">
          <span class="label">
            Нет места в рейтинге
          </span>
        </div>

        <div class="rank-badge__meta">
          Пройди тир-тест, чтобы попасть в рейтинг
        </div>
      </div>
    </div>

    <div class="rank-badge__line">
      <span></span>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/players/RankBadge.css";
</style>
