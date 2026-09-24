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
  return Math.round((1 - (props.position - 1) / props.total) * 100)
})
</script>

<template>
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
    <div class="rank-badge__icon">
      <span v-if="medal" class="medal">{{ medal }}</span>
      <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z" stroke-linejoin="round" />
      </svg>
    </div>

    <div class="rank-badge__info">
      <div class="rank-badge__position">
        <span class="num">#{{ position }}</span>
        <span class="label">место в топе</span>
      </div>
      <div class="rank-badge__meta">
        из {{ total }} игроков
        <template v-if="percentile !== null">
          <span class="sep">·</span>
          лучше {{ percentile }}%
        </template>
      </div>
    </div>
  </div>

  <div v-else class="rank-badge rank-unranked">
    <div class="rank-badge__icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v4M12 16h.01" stroke-linecap="round" />
      </svg>
    </div>

    <div class="rank-badge__info">
      <div class="rank-badge__position">
        <span class="label">Нет места в топе</span>
      </div>
      <div class="rank-badge__meta">
        Пройди тир-тест, чтобы попасть в рейтинг
      </div>
    </div>
  </div>
</template>

<style scoped>
.rank-badge {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: all 0.2s ease;
}

.rank-badge:hover {
  border-color: var(--border-hover);
  transform: translateY(-1px);
}

.rank-badge__icon {
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(124, 58, 237, 0.1);
  border-radius: 13px;
  color: var(--accent-light);
  flex-shrink: 0;
}

.medal {
  font-size: 28px;
  line-height: 1;
  filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.5));
}

.rank-badge__info {
  flex: 1;
  min-width: 0;
}

.rank-badge__position {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 4px;
}

.rank-badge__position .num {
  font-size: 24px;
  font-weight: 900;
  letter-spacing: -1px;
  color: var(--text);
}

.rank-badge__position .label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-dim);
}

.rank-badge__meta {
  font-size: 12px;
  color: var(--text-muted);
}

.rank-badge__meta .sep {
  margin: 0 6px;
  opacity: 0.4;
}

/* === Топ-1 / 2 / 3 === */

.rank-gold {
  border-color: rgba(250, 204, 21, 0.4);
  background: linear-gradient(90deg, rgba(250, 204, 21, 0.07), var(--bg-card) 50%);
  box-shadow: 0 0 30px rgba(250, 204, 21, 0.08);
}

.rank-gold .rank-badge__icon {
  background: linear-gradient(135deg, #fde047, #f59e0b);
  color: #fff;
  box-shadow: 0 4px 20px rgba(250, 204, 21, 0.4);
}

.rank-gold .num { color: #facc15; }

.rank-silver {
  border-color: rgba(192, 192, 192, 0.35);
  background: linear-gradient(90deg, rgba(192, 192, 192, 0.05), var(--bg-card) 50%);
}

.rank-silver .rank-badge__icon {
  background: linear-gradient(135deg, #f3f4f6, #9ca3af);
  color: #fff;
  box-shadow: 0 4px 20px rgba(192, 192, 192, 0.3);
}

.rank-silver .num { color: #c0c0c0; }

.rank-bronze {
  border-color: rgba(205, 127, 50, 0.35);
  background: linear-gradient(90deg, rgba(205, 127, 50, 0.05), var(--bg-card) 50%);
}

.rank-bronze .rank-badge__icon {
  background: linear-gradient(135deg, #e09966, #a0522d);
  color: #fff;
  box-shadow: 0 4px 20px rgba(205, 127, 50, 0.3);
}

.rank-bronze .num { color: #cd7f32; }

/* === Топ-10 === */

.rank-top10 {
  border-color: rgba(124, 58, 237, 0.3);
}

.rank-top10 .rank-badge__icon {
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  box-shadow: 0 4px 20px rgba(124, 58, 237, 0.3);
}

/* === Без места === */

.rank-unranked {
  border-style: dashed;
}

.rank-unranked .rank-badge__icon {
  background: rgba(255, 255, 255, 0.03);
  color: var(--text-muted);
}

.rank-unranked .label {
  color: var(--text-dim);
  font-weight: 700;
  font-size: 14px;
}
</style>