<script setup>
const props = defineProps({
  achievement: { type: Object, required: true },
})
const emit = defineEmits(['close'])

const rarityColors = {
  common: '#7c3aed', rare: '#06b6d4', epic: '#f97316', legendary: '#facc15',
}
const rarityLabels = {
  common: 'Обычное', rare: 'Редкое', epic: 'Эпическое', legendary: 'Легендарное',
}

function color(a) {
  return a?.color || rarityColors[a?.rarity] || '#7c3aed'
}

function formatEarnedAt(a) {
  const raw = a?.pivot?.earned_at ?? a?.earned_at
  if (!raw) return null
  try {
    return new Date(raw).toLocaleDateString('ru-RU', {
      day: '2-digit', month: 'long', year: 'numeric',
    })
  } catch { return null }
}
</script>

<template>
  <div class="ach-modal-bg" @click.self="emit('close')">
    <div class="ach-modal" :style="{ '--color': color(achievement) }">
      <button class="ach-modal__close" type="button" aria-label="Закрыть" @click="emit('close')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18M6 6l12 12" />
        </svg>
      </button>

      <div class="ach-modal__glow" />
      <div class="ach-modal__icon">{{ achievement.icon }}</div>
      <h3 class="ach-modal__name">{{ achievement.name }}</h3>

      <div class="ach-modal__meta">
        <span v-if="achievement.rarity" class="ach-chip ach-chip--rarity">
          {{ rarityLabels[achievement.rarity] ?? achievement.rarity }}
        </span>
        <span v-if="achievement.points" class="ach-chip ach-chip--points">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
          </svg>
          {{ achievement.points }} очков
        </span>
      </div>

      <p v-if="achievement.description" class="ach-modal__desc">
        {{ achievement.description }}
      </p>

      <div v-if="formatEarnedAt(achievement)" class="ach-modal__date">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <path d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        Получено {{ formatEarnedAt(achievement) }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.ach-modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(6, 6, 10, 0.72);
  backdrop-filter: blur(8px);
  animation: achFade 0.15s ease;
}

@keyframes achFade {
  from { opacity: 0; }
  to { opacity: 1; }
}

.ach-modal {
  position: relative;
  width: 100%;
  max-width: 380px;
  padding: 32px 26px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid color-mix(in srgb, var(--color) 45%, var(--border));
  border-radius: 18px;
  box-shadow:
      0 30px 80px -20px rgba(0, 0, 0, 0.7),
      inset 0 0 0 1px color-mix(in srgb, var(--color) 20%, transparent);
  animation: achPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}

@keyframes achPop {
  from { opacity: 0; transform: scale(0.94) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.ach-modal__glow {
  position: absolute;
  top: -80px;
  left: 50%;
  width: 320px;
  height: 320px;
  transform: translateX(-50%);
  background: radial-gradient(circle, color-mix(in srgb, var(--color) 40%, transparent) 0%, transparent 65%);
  pointer-events: none;
  opacity: 0.65;
  z-index: 0;
}

.ach-modal__close {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 3;
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}

.ach-modal__close:hover {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text);
}

.ach-modal__icon {
  position: relative;
  z-index: 1;
  width: 104px;
  height: 104px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 56px;
  line-height: 1;
  border-radius: 24px;
  background:
      radial-gradient(circle at 50% 30%, color-mix(in srgb, var(--color) 30%, transparent) 0%, transparent 70%),
      rgba(255, 255, 255, 0.03);
  border: 1px solid color-mix(in srgb, var(--color) 55%, transparent);
  box-shadow:
      0 12px 40px color-mix(in srgb, var(--color) 35%, transparent),
      inset 0 0 0 1px rgba(255, 255, 255, 0.04);
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
}

.ach-modal__name {
  position: relative;
  z-index: 1;
  margin: 6px 0 0;
  font-size: 20px;
  font-weight: 900;
  color: #f3f4f6;
  letter-spacing: -0.3px;
  text-align: center;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
}

.ach-modal__meta {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 2px;
}

.ach-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.ach-chip--rarity {
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color) 45%, transparent);
}

.ach-chip--points {
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border: 1px solid rgba(250, 204, 21, 0.35);
}

.ach-modal__desc {
  position: relative;
  z-index: 1;
  margin: 6px 0 0;
  color: #b8c2cf;
  font-size: 13.5px;
  line-height: 1.6;
  text-align: center;
  max-width: 300px;
}

.ach-modal__date {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding-top: 14px;
  width: 100%;
  justify-content: center;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  color: #6b7280;
  font-size: 11.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Адаптив для мобильных */
@media (max-width: 600px) {
  .ach-modal {
    padding: 26px 18px 18px;
    max-width: 100%;
  }

  .ach-modal__icon {
    width: 88px;
    height: 88px;
    font-size: 46px;
    border-radius: 20px;
  }

  .ach-modal__name {
    font-size: 18px;
  }
}
</style>