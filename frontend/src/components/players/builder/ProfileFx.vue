<script setup>
/**
 * Живой эффект поверх профиля: снег, угольки, светлячки, звездопад,
 * пиксели. Частицы — span'ы на CSS-анимациях, без canvas и таймеров;
 * случайность детерминированная, чтобы не прыгать при перерисовке.
 */
import { computed } from 'vue'

const props = defineProps({
  fx: { type: String, default: 'none' },
  count: { type: Number, default: 28 },
})

// Простой генератор: одинаковые частицы при каждом рендере
function rand(seed) {
  const x = Math.sin(seed * 9301 + 49297) * 233280
  return x - Math.floor(x)
}

const particles = computed(() => {
  if (!props.fx || props.fx === 'none') return []
  return Array.from({ length: props.count }, (_, i) => ({
    '--x': `${(rand(i + 1) * 100).toFixed(2)}%`,
    '--y': `${(rand(i + 101) * 100).toFixed(2)}%`,
    '--s': (0.5 + rand(i + 201)).toFixed(2),
    '--d': `${(6 + rand(i + 301) * 8).toFixed(2)}s`,
    '--delay': `${(-rand(i + 401) * 14).toFixed(2)}s`,
    '--drift': `${((rand(i + 501) - 0.5) * 80).toFixed(0)}px`,
  }))
})
</script>

<template>
  <div v-if="particles.length" class="profile-fx" :class="`profile-fx--${fx}`" aria-hidden="true">
    <i v-for="(style, i) in particles" :key="i" :style="style"></i>
  </div>
</template>

<style>
.profile-fx {
  position: absolute;
  inset: 0;
  z-index: 4;

  overflow: hidden;

  border-radius: inherit;

  pointer-events: none;
}

.profile-fx i {
  position: absolute;
  left: var(--x);
  top: -5%;

  width: calc(6px * var(--s));
  height: calc(6px * var(--s));

  border-radius: 50%;

  animation: var(--d) linear var(--delay) infinite;
  will-change: transform, top;
}

/* Снег: падает, покачиваясь */
.profile-fx--snow i {
  background: #fff;
  opacity: 0.8;
  filter: blur(0.4px);
  animation-name: fx-fall;
}

/* Угольки: поднимаются снизу и гаснут */
.profile-fx--embers i {
  top: auto;
  bottom: -5%;
  width: calc(4px * var(--s));
  height: calc(4px * var(--s));
  background: #fb923c;
  box-shadow: 0 0 8px #f97316, 0 0 16px rgba(239, 68, 68, 0.6);
  animation-name: fx-rise;
}

/* Светлячки: плавают на месте и мерцают */
.profile-fx--fireflies i {
  top: var(--y);
  width: calc(5px * var(--s));
  height: calc(5px * var(--s));
  background: #fef08a;
  box-shadow: 0 0 10px #facc15, 0 0 22px rgba(250, 204, 21, 0.5);
  animation-name: fx-float;
  animation-timing-function: ease-in-out;
}

/* Звездопад: косые росчерки */
.profile-fx--starfall i {
  width: calc(70px * var(--s));
  height: 1.5px;
  border-radius: 2px;
  background: linear-gradient(90deg, transparent, #fff);
  opacity: 0;
  animation-name: fx-star;
  animation-duration: calc(var(--d) * 0.9);
}

/* Пиксели: квадратики цвета акцента взлетают и вертятся */
.profile-fx--pixels i {
  top: auto;
  bottom: -5%;
  border-radius: 1px;
  background: var(--accent-color, #8b5cf6);
  box-shadow: 0 0 10px color-mix(in srgb, var(--accent-color, #8b5cf6) 60%, transparent);
  animation-name: fx-pixel;
}

@keyframes fx-fall {
  0% { top: -5%; transform: translateX(0); }
  50% { transform: translateX(var(--drift)); }
  100% { top: 105%; transform: translateX(0); }
}

@keyframes fx-rise {
  0% { bottom: -5%; opacity: 0; transform: translateX(0); }
  15% { opacity: 1; }
  100% { bottom: 105%; opacity: 0; transform: translateX(var(--drift)); }
}

@keyframes fx-float {
  0%, 100% { opacity: 0.2; transform: translate(0, 0); }
  25% { opacity: 1; }
  50% { opacity: 0.4; transform: translate(var(--drift), calc(var(--drift) * -0.6)); }
  75% { opacity: 1; }
}

@keyframes fx-star {
  0% { top: var(--y); opacity: 0; transform: translateX(0) rotate(-30deg); }
  8% { opacity: 1; }
  20% { opacity: 0; transform: translateX(-220px) translateY(120px) rotate(-30deg); }
  100% { opacity: 0; transform: translateX(-220px) translateY(120px) rotate(-30deg); }
}

@keyframes fx-pixel {
  0% { bottom: -5%; opacity: 0; transform: rotate(0); }
  15% { opacity: 0.9; }
  100% { bottom: 105%; opacity: 0; transform: translateX(var(--drift)) rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  .profile-fx { display: none; }
}
</style>
