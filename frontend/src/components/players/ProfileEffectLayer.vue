<script setup>
/**
 * Анимированный слой эффекта профиля в стиле Minecraft: частицы-пиксели
 * (снег, искры, пыль редстоуна, частицы Края, опыт) и «полотна»
 * (блеск зачарования, северное сияние, пульс скалка).
 *
 * Заполняет родителя (position: absolute) и не ловит клики. Один и тот
 * же слой рисуется в шапке профиля и в превью товара в магазине.
 */
import { computed } from 'vue'
import { layerEffect } from '@/data/players/profileCustomization.js'

const props = defineProps({
  effect: { type: String, default: null },
  // Множитель числа частиц: в маленьком превью их меньше
  density: { type: Number, default: 1 },
})

const config = computed(() => layerEffect(props.effect))

/** Детерминированный «случайный» разброс: одинаковый при каждом рендере. */
function spread(index, salt) {
  const x = Math.sin(index * 12.9898 + salt * 78.233) * 43758.5453
  return x - Math.floor(x)
}

const particles = computed(() => {
  const c = config.value
  if (!c?.particles) return []

  const count = Math.max(4, Math.round(c.particles * props.density))

  return Array.from({ length: count }, (_, i) => {
    const size = c.size[0] + Math.round(spread(i, 3) * (c.size[1] - c.size[0]))
    const duration = c.duration[0] + spread(i, 4) * (c.duration[1] - c.duration[0])

    return {
      left: `${(spread(i, 1) * 100).toFixed(1)}%`,
      top: `${(spread(i, 2) * 100).toFixed(1)}%`,
      width: `${size}px`,
      height: `${c.streak ? size * 4 : size}px`,
      animationDuration: `${duration.toFixed(2)}s`,
      // Отрицательная задержка — частицы уже «в пути» при открытии страницы
      animationDelay: `-${(spread(i, 5) * duration).toFixed(2)}s`,
      opacity: (0.55 + spread(i, 6) * 0.45).toFixed(2),
    }
  })
})
</script>

<template>
  <div
      v-if="config"
      class="fx"
      :class="`fx--${effect}`"
      :style="{ '--fx-color': config.color, '--fx-color-2': config.color2 || config.color }"
      aria-hidden="true"
  >
    <div v-if="config.sheet" class="fx__sheet" />

    <span
        v-for="(p, i) in particles"
        :key="i"
        class="fx__particle"
        :style="p"
    />
  </div>
</template>

<style scoped>
.fx {
  position: absolute;
  inset: 0;
  z-index: 4;

  overflow: hidden;
  pointer-events: none;
}

.fx__particle {
  position: absolute;
  display: block;

  background: var(--fx-color);

  animation-timing-function: linear;
  animation-iteration-count: infinite;
}

/* ---------- Падающие: дождь, снег ---------- */

/* Старт задаёт сама анимация: !important у top её бы перебил */
.fx--rain .fx__particle,
.fx--snow .fx__particle {
  animation-name: fx-fall;
}

.fx--rain .fx__particle {
  opacity: 0.75 !important;
  background: linear-gradient(transparent, var(--fx-color));
}

.fx--snow .fx__particle {
  animation-name: fx-fall, fx-sway;
  animation-timing-function: linear, ease-in-out;
}

@keyframes fx-fall {
  from { top: -10%; }
  to { top: 110%; }
}

@keyframes fx-sway {
  0%, 100% { translate: 0 0; }
  50% { translate: 10px 0; }
}

/* ---------- Поднимающиеся: искры, частицы Края, опыт ---------- */

.fx--embers .fx__particle,
.fx--ender .fx__particle,
.fx--xp .fx__particle,
.fx--sculk .fx__particle {
  top: auto !important;
  bottom: -6%;
  animation-name: fx-rise;
}

.fx--embers .fx__particle {
  background: linear-gradient(var(--fx-color-2), var(--fx-color));
}

.fx--ender .fx__particle {
  animation-name: fx-rise, fx-sway;
  animation-timing-function: linear, ease-in-out;
}

.fx--xp .fx__particle {
  border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, #ecfccb, var(--fx-color) 55%, var(--fx-color-2));
}

@keyframes fx-rise {
  0% { bottom: -6%; opacity: 0; }
  12% { opacity: 1; }
  85% { opacity: 1; }
  100% { bottom: 106%; opacity: 0; }
}

/* ---------- Мерцающие: пыль редстоуна ---------- */

.fx--redstone .fx__particle {
  animation-name: fx-twinkle;
  animation-timing-function: ease-in-out;
}

@keyframes fx-twinkle {
  0%, 100% { opacity: 0; transform: scale(0.4); }
  50% { opacity: 1; transform: scale(1); }
}

/* ---------- Полотна ---------- */

.fx__sheet {
  position: absolute;
  inset: 0;
}

/* Блеск зачарования: полоса проходит по диагонали, как по зачарованному мечу */
.fx--enchant .fx__sheet {
  inset: -50%;
  background: linear-gradient(
      115deg,
      transparent 40%,
      color-mix(in srgb, var(--fx-color) 30%, transparent) 46%,
      color-mix(in srgb, var(--fx-color-2) 40%, transparent) 50%,
      color-mix(in srgb, var(--fx-color) 30%, transparent) 54%,
      transparent 60%
  );
  animation: fx-glint 4.5s linear infinite;
}

@keyframes fx-glint {
  from { transform: translateX(-35%); }
  to { transform: translateX(35%); }
}

/* Северное сияние: широкие полосы плавно переливаются */
.fx--aurora .fx__sheet {
  inset: -30% -10%;
  background:
      linear-gradient(170deg, transparent 20%, color-mix(in srgb, var(--fx-color) 28%, transparent) 38%, transparent 55%),
      linear-gradient(190deg, transparent 35%, color-mix(in srgb, var(--fx-color-2) 24%, transparent) 52%, transparent 70%);
  filter: blur(14px);
  animation: fx-aurora 9s ease-in-out infinite alternate;
}

@keyframes fx-aurora {
  from { transform: translateX(-6%) skewX(-6deg); opacity: 0.7; }
  to { transform: translateX(6%) skewX(6deg); opacity: 1; }
}

/* Скалк: кольца расходятся от нижнего края */
.fx--sculk .fx__sheet {
  inset: auto -20% -60%;
  height: 120%;
  border-radius: 50%;
  border: 3px solid color-mix(in srgb, var(--fx-color) 70%, transparent);
  animation: fx-sculk 3.2s ease-out infinite;
}

@keyframes fx-sculk {
  from { transform: scale(0.3); opacity: 1; }
  to { transform: scale(1.2); opacity: 0; }
}

/* ---------- Милые: лепестки, сердечки, блёстки, пузыри ---------- */

/* Лепесток: овал с острым краем, падает, качается и вращается */
.fx--sakura_petals .fx__particle {
  height: auto !important;
  aspect-ratio: 1 / 0.7;

  background: linear-gradient(135deg, var(--fx-color-2), var(--fx-color));
  border-radius: 80% 0 80% 0;

  animation-name: fx-fall, fx-sway, fx-spin;
  animation-timing-function: linear, ease-in-out, linear;
}

@keyframes fx-spin {
  to { rotate: 360deg; }
}

/* Сердечко и звёздочка — маска из SVG, цвет берётся из фона */
.fx--hearts .fx__particle,
.fx--sparkles .fx__particle {
  height: auto !important;
  aspect-ratio: 1;

  -webkit-mask: var(--fx-shape) center / contain no-repeat;
  mask: var(--fx-shape) center / contain no-repeat;
}

.fx--hearts .fx__particle {
  --fx-shape: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 21s-7.5-4.6-10-9.3C.3 8.4 2.2 4.5 6 4.5c2.2 0 3.6 1.2 4.5 2.6.9-1.4 2.3-2.6 4.5-2.6 3.8 0 5.7 3.9 4 7.2C19.5 16.4 12 21 12 21z'/%3E%3C/svg%3E");

  top: auto !important;
  bottom: -8%;

  background: linear-gradient(var(--fx-color-2), var(--fx-color));

  animation-name: fx-rise, fx-sway;
  animation-timing-function: linear, ease-in-out;
}

.fx--sparkles .fx__particle {
  --fx-shape: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 0c.8 6.6 5.4 11.2 12 12-6.6.8-11.2 5.4-12 12-.8-6.6-5.4-11.2-12-12C6.6 11.2 11.2 6.6 12 0z'/%3E%3C/svg%3E");

  background: linear-gradient(135deg, #fff, var(--fx-color) 45%, var(--fx-color-2));

  animation-name: fx-twinkle;
  animation-timing-function: ease-in-out;
}

/* Пузырь: прозрачный круг с бликом, поднимается и покачивается */
.fx--bubbles .fx__particle {
  height: auto !important;
  aspect-ratio: 1;

  top: auto !important;
  bottom: -10%;

  background:
      radial-gradient(circle at 32% 30%, rgba(255, 255, 255, 0.9) 0 12%, transparent 16%),
      radial-gradient(circle, transparent 58%, color-mix(in srgb, var(--fx-color) 70%, transparent) 70%, color-mix(in srgb, var(--fx-color-2) 60%, transparent) 100%);
  border-radius: 50%;

  animation-name: fx-rise, fx-sway;
  animation-timing-function: linear, ease-in-out;
}

@media (prefers-reduced-motion: reduce) {
  .fx__particle,
  .fx__sheet {
    animation: none !important;
  }
}
</style>
