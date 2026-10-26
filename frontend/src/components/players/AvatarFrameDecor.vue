<script setup>
/**
 * Украшения милых рамок аватара: цветочки, сердечки или звёздочки,
 * разложенные по кругу. Слой кладётся поверх рамки и не ловит клики.
 */
import { computed } from 'vue'
import { frameById } from '@/data/players/profileCustomization.js'

const props = defineProps({
  frame: { type: String, default: null },
  // Размер украшения в долях от размера рамки
  scale: { type: Number, default: 0.26 },
})

const decor = computed(() => frameById(props.frame)?.decor ?? null)

/* Пять украшений: два крупных сверху и снизу, мелкие — по бокам */
const SPOTS = [
  { angle: -128, size: 1, tilt: -18 },
  { angle: -62, size: 0.72, tilt: 12 },
  { angle: 18, size: 0.6, tilt: -8 },
  { angle: 92, size: 0.85, tilt: 20 },
  { angle: 158, size: 0.66, tilt: -14 },
]

const items = computed(() => {
  const d = decor.value
  if (!d) return []

  return SPOTS.map((spot, i) => {
    const rad = (spot.angle * Math.PI) / 180
    const size = props.scale * spot.size * 100

    return {
      shape: d.shape,
      color: d.colors[i % d.colors.length],
      center: d.center,
      style: {
        left: `${50 + Math.cos(rad) * 50 - size / 2}%`,
        top: `${50 + Math.sin(rad) * 50 - size / 2}%`,
        width: `${size}%`,
        height: `${size}%`,
        rotate: `${spot.tilt}deg`,
        animationDelay: `${-i * 0.7}s`,
      },
    }
  })
})
</script>

<template>
  <span v-if="decor" class="decor" aria-hidden="true">
    <span
        v-for="(item, i) in items"
        :key="i"
        class="decor__item"
        :style="item.style"
    >
      <!-- Цветок: пять лепестков-кругов и серединка -->
      <svg v-if="item.shape === 'flower'" viewBox="0 0 24 24">
        <g :fill="item.color" stroke="rgba(0,0,0,0.12)" stroke-width="0.6">
          <circle cx="12" cy="5.5" r="4.6" />
          <circle cx="18.2" cy="10" r="4.6" />
          <circle cx="15.8" cy="17.3" r="4.6" />
          <circle cx="8.2" cy="17.3" r="4.6" />
          <circle cx="5.8" cy="10" r="4.6" />
        </g>
        <circle cx="12" cy="12" r="3.4" :fill="item.center || '#facc15'" />
      </svg>

      <svg v-else-if="item.shape === 'heart'" viewBox="0 0 24 24">
        <path
            d="M12 21s-7.5-4.6-10-9.3C.3 8.4 2.2 4.5 6 4.5c2.2 0 3.6 1.2 4.5 2.6.9-1.4 2.3-2.6 4.5-2.6 3.8 0 5.7 3.9 4 7.2C19.5 16.4 12 21 12 21z"
            :fill="item.color"
            stroke="rgba(255,255,255,0.7)"
            stroke-width="1.2"
        />
      </svg>

      <svg v-else viewBox="0 0 24 24">
        <path
            d="M12 0c.8 6.6 5.4 11.2 12 12-6.6.8-11.2 5.4-12 12-.8-6.6-5.4-11.2-12-12C6.6 11.2 11.2 6.6 12 0z"
            :fill="item.color"
        />
      </svg>
    </span>
  </span>
</template>

<style scoped>
.decor {
  position: absolute;
  inset: 0;
  z-index: 3;

  pointer-events: none;
}

.decor__item {
  position: absolute;

  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));

  animation: decor-bob 3.2s ease-in-out infinite;
}

.decor__item svg {
  display: block;
  width: 100%;
  height: 100%;
}

@keyframes decor-bob {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-6%) scale(1.06); }
}

@media (prefers-reduced-motion: reduce) {
  .decor__item {
    animation: none;
  }
}
</style>
