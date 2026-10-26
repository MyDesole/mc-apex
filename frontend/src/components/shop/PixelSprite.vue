<script setup>
/**
 * Пиксельная картинка из pixelSprites.js с необязательной анимацией:
 * bounce — подпрыгивает, glow — пульсирует свечением, shake — трясётся
 * (TNT), spin — вращается, как предмет на земле, wiggle — покачивается,
 * pulse — мерцает (сенсоры скалка).
 */
import { computed } from 'vue'
import { spriteSvg, spriteRatio } from '@/data/shop/pixelSprites.js'

const props = defineProps({
  sprite: { type: String, required: true },
  size: { type: Number, default: 16 },
  anim: { type: String, default: null },
  // Цвет свечения для anim="glow"
  glow: { type: String, default: null },
})

const svg = computed(() => spriteSvg(props.sprite))
const width = computed(() => Math.round(props.size * Math.min(1.4, spriteRatio(props.sprite))))
</script>

<template>
  <span
      v-if="svg"
      class="pixel-sprite"
      :class="anim ? `pixel-sprite--${anim}` : null"
      :style="{ width: `${width}px`, height: `${size}px`, '--glow': glow || 'rgba(255,255,255,.6)' }"
      aria-hidden="true"
      v-html="svg"
  ></span>
</template>

<style scoped>
.pixel-sprite {
  display: inline-flex;
  flex-shrink: 0;

  image-rendering: pixelated;
}

.pixel-sprite :deep(svg) {
  width: 100%;
  height: 100%;
}

.pixel-sprite--bounce {
  animation: px-bounce 1.4s cubic-bezier(0.3, 0, 0.3, 1) infinite;
}

.pixel-sprite--glow {
  animation: px-glow 2.2s ease-in-out infinite;
}

.pixel-sprite--shake {
  animation: px-shake 2.4s linear infinite;
}

.pixel-sprite--spin {
  animation: px-spin 3s linear infinite;
}

.pixel-sprite--wiggle {
  animation: px-wiggle 2.6s ease-in-out infinite;
}

.pixel-sprite--pulse {
  animation: px-pulse 1.8s ease-in-out infinite;
}

@keyframes px-bounce {
  0%, 100% { transform: translateY(0); }
  40% { transform: translateY(-22%); }
  60% { transform: translateY(-18%); }
}

@keyframes px-glow {
  0%, 100% { filter: drop-shadow(0 0 1px var(--glow)); }
  50% { filter: drop-shadow(0 0 5px var(--glow)) brightness(1.2); }
}

/* TNT шипит: тишина, затем дрожь и белые вспышки */
@keyframes px-shake {
  0%, 60%, 100% { transform: translate(0, 0); filter: none; }
  64% { transform: translate(-1px, 0); filter: brightness(2.2); }
  68% { transform: translate(1px, -1px); filter: none; }
  72% { transform: translate(-1px, 1px); filter: brightness(2.2); }
  76% { transform: translate(1px, 0); filter: none; }
  80% { transform: translate(0, -1px); filter: brightness(2.2); }
}

/* Предмет на земле: вращение вокруг вертикальной оси */
@keyframes px-spin {
  0% { transform: scaleX(1); }
  25% { transform: scaleX(0.15); }
  50% { transform: scaleX(-1); }
  75% { transform: scaleX(-0.15); }
  100% { transform: scaleX(1); }
}

@keyframes px-wiggle {
  0%, 100% { transform: rotate(0deg); }
  20% { transform: rotate(-10deg); }
  40% { transform: rotate(8deg); }
  60% { transform: rotate(0deg); }
}

@keyframes px-pulse {
  0%, 100% { filter: brightness(1); }
  50% { filter: brightness(1.5) drop-shadow(0 0 4px #5eead4); }
}

@media (prefers-reduced-motion: reduce) {
  .pixel-sprite {
    animation: none !important;
  }
}
</style>
