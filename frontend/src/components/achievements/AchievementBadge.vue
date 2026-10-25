<script setup>
/**
 * Значок ачивки: пиксельная иконка на «блоке» с рамкой по редкости.
 *
 * Обычная — серая рамка, редкая — синяя, эпическая — фиолетовая,
 * легендарная — золотая с медленным бликом. Закрытая — серый силуэт.
 */
import { computed } from 'vue'
import PixelIcon from '@/components/achievements/PixelIcon.vue'

const props = defineProps({
  achievement: { type: Object, required: true },
  size: { type: Number, default: 56 },
  locked: { type: Boolean, default: false },
})

const rarity = computed(() => props.achievement?.rarity || 'common')
</script>

<template>
  <span
      class="ach-badge"
      :class="[`ach-badge--${rarity}`, { 'ach-badge--locked': locked }]"
      :style="{
        '--badge-size': `${size}px`,
        '--badge-color': achievement.color || '#a78bfa',
      }"
  >
    <PixelIcon
        :icon="achievement.icon"
        :color="achievement.color || '#a78bfa'"
        :size="Math.round(size * 0.64)"
        :muted="locked"
    />
  </span>
</template>

<style scoped>
.ach-badge {
  --frame: #4a4458;

  position: relative;

  width: var(--badge-size);
  height: var(--badge-size);
  flex-shrink: 0;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  background:
      linear-gradient(180deg, color-mix(in srgb, var(--badge-color) 16%, #1b1825), #141219);
  border: 2px solid var(--frame);
  border-radius: 6px;

  /* Грань «блока»: светлее сверху, темнее снизу */
  box-shadow:
      inset 0 2px 0 rgba(255, 255, 255, 0.08),
      inset 0 -3px 0 rgba(0, 0, 0, 0.35);
}

.ach-badge--rare {
  --frame: #3b82f6;
}

.ach-badge--epic {
  --frame: #8b5cf6;
}

.ach-badge--legendary {
  --frame: #eab308;
}

/* Легендарная: блик раз в несколько секунд */
.ach-badge--legendary::after {
  content: '';

  position: absolute;
  inset: -50%;

  background: linear-gradient(115deg, transparent 42%, rgba(255, 255, 255, 0.28) 50%, transparent 58%);

  transform: translateX(-100%);
  animation: badge-shine 4.5s ease-in-out infinite;

  pointer-events: none;
}

@keyframes badge-shine {
  0%, 60% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.ach-badge--locked {
  --frame: #2c2836;

  background: #141219;
}

.ach-badge--locked::after {
  display: none;
}

@media (prefers-reduced-motion: reduce) {
  .ach-badge--legendary::after {
    animation: none;
    display: none;
  }
}
</style>
