<script setup>
/**
 * Появление профиля (магазин, тип profile_entrance): короткое интро
 * поверх профиля, когда его открывают. Играет один раз и исчезает,
 * клики не ловит. В превью магазина — по кругу (loop).
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const props = defineProps({
  entrance: { type: String, default: null },
  // Превью в магазине: повторять бесконечно
  loop: { type: Boolean, default: false },
})

const DURATION = 2200

const visible = ref(false)
const runKey = ref(0)
let timer = null
let loopTimer = null

/** Детерминированный разброс для конфетти. */
function spread(i, salt) {
  const x = Math.sin(i * 12.9898 + salt * 78.233) * 43758.5453
  return x - Math.floor(x)
}

const COLORS = ['#f472b6', '#facc15', '#22d3ee', '#a78bfa', '#4ade80', '#fb923c']

const confetti = computed(() => Array.from({ length: 46 }, (_, i) => ({
  '--x': `${(spread(i, 1) - 0.5) * 140}%`,
  '--y': `${40 + spread(i, 2) * 70}%`,
  '--r': `${spread(i, 3) * 720 - 360}deg`,
  '--d': `${spread(i, 4) * 0.25}s`,
  background: COLORS[i % COLORS.length],
})))

function play() {
  visible.value = true
  runKey.value++

  clearTimeout(timer)
  timer = setTimeout(() => { visible.value = false }, DURATION)
}

onMounted(() => {
  if (!props.entrance) return

  // Уважаем «уменьшить движение»: интро не показываем
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches && !props.loop) return

  play()

  if (props.loop) {
    loopTimer = setInterval(play, DURATION + 900)
  }
})

onUnmounted(() => {
  clearTimeout(timer)
  clearInterval(loopTimer)
})
</script>

<template>
  <div
      v-if="entrance && visible"
      :key="runKey"
      class="entrance"
      :class="`entrance--${entrance}`"
      aria-hidden="true"
  >
    <!-- Удар молнии -->
    <template v-if="entrance === 'lightning'">
      <span class="entrance__dark"></span>
      <svg class="entrance__bolt" viewBox="0 0 60 200" preserveAspectRatio="none">
        <polyline points="34,0 22,62 36,66 14,130 30,134 6,200" />
      </svg>
      <span class="entrance__flash"></span>
    </template>

    <!-- Портал -->
    <template v-else-if="entrance === 'portal'">
      <span class="entrance__portal"></span>
      <span class="entrance__portal entrance__portal--inner"></span>
    </template>

    <!-- Взлом -->
    <template v-else-if="entrance === 'glitch'">
      <span v-for="n in 7" :key="n" class="entrance__slice" :style="{ '--n': n }"></span>
      <span class="entrance__scan"></span>
    </template>

    <!-- Хлопушка -->
    <template v-else-if="entrance === 'confetti'">
      <span v-for="(style, i) in confetti" :key="i" class="entrance__confetti" :style="style"></span>
    </template>

    <!-- Из тумана -->
    <template v-else-if="entrance === 'smoke'">
      <span v-for="n in 6" :key="n" class="entrance__cloud" :style="{ '--n': n }"></span>
    </template>

    <!-- Коронация -->
    <template v-else-if="entrance === 'royal'">
      <span class="entrance__rays"></span>
      <span class="entrance__crown"><PixelSprite sprite="crown" :size="64" /></span>
      <span class="entrance__flash entrance__flash--gold"></span>
    </template>

    <!-- Горизонт событий -->
    <template v-else-if="entrance === 'blackhole'">
      <span class="entrance__hole"></span>
      <span class="entrance__hole-ring"></span>
    </template>
  </div>
</template>

<style scoped>
.entrance {
  position: absolute;
  inset: 0;
  z-index: 40;

  overflow: hidden;
  border-radius: inherit;

  pointer-events: none;
}

.entrance > * {
  position: absolute;
}

/* ---------- Молния ---------- */

.entrance__dark {
  inset: 0;
  background: rgba(2, 6, 23, 0.85);
  animation: ent-dark 2.2s ease-out forwards;
}

@keyframes ent-dark {
  0%, 35% { opacity: 1; }
  100% { opacity: 0; }
}

.entrance__bolt {
  top: 0;
  left: 46%;

  width: 14%;
  height: 100%;

  opacity: 0;
  animation: ent-bolt 2.2s linear forwards;
}

.entrance__bolt polyline {
  fill: none;
  stroke: #eef2ff;
  stroke-width: 5;
  vector-effect: non-scaling-stroke;
  filter: drop-shadow(0 0 8px #a5b4fc) drop-shadow(0 0 22px #6366f1);
}

@keyframes ent-bolt {
  0%, 18% { opacity: 0; }
  20%, 24% { opacity: 1; }
  22% { opacity: 0.3; }
  34%, 100% { opacity: 0; }
}

.entrance__flash {
  inset: 0;
  background: #e0e7ff;
  opacity: 0;
  animation: ent-flash 2.2s ease-out forwards;
}

@keyframes ent-flash {
  0%, 21% { opacity: 0; }
  23% { opacity: 0.85; }
  45%, 100% { opacity: 0; }
}

/* ---------- Портал ---------- */

.entrance__portal {
  left: 50%;
  top: 50%;

  width: 40px;
  height: 40px;

  border-radius: 50%;
  background: conic-gradient(#a855f7, #ec4899, #6366f1, #22d3ee, #a855f7);
  -webkit-mask: radial-gradient(circle, transparent 42%, #000 46%);
  mask: radial-gradient(circle, transparent 42%, #000 46%);
  filter: drop-shadow(0 0 20px #a855f7);
  transform: translate(-50%, -50%);

  animation: ent-portal 2.2s cubic-bezier(0.6, 0, 0.4, 1) forwards;
}

.entrance__portal--inner {
  -webkit-mask: none;
  mask: none;
  background: radial-gradient(circle, rgba(30, 10, 60, 0.95) 0, rgba(76, 29, 149, 0.8) 45%, transparent 70%);
  animation-name: ent-portal-core;
}

@keyframes ent-portal {
  0% { transform: translate(-50%, -50%) scale(0) rotate(0); opacity: 1; }
  40% { transform: translate(-50%, -50%) scale(30) rotate(540deg); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(60) rotate(900deg); opacity: 0; }
}

@keyframes ent-portal-core {
  0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
  40% { transform: translate(-50%, -50%) scale(40); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(40); opacity: 0; }
}

/* ---------- Взлом ---------- */

.entrance__slice {
  left: -10%;
  right: -10%;
  top: calc((var(--n) - 1) * 14.3%);

  height: 14.3%;

  background: linear-gradient(90deg, rgba(255, 42, 109, 0.75), rgba(10, 10, 20, 0.95) 30%, rgba(10, 10, 20, 0.95) 70%, rgba(5, 217, 232, 0.75));

  animation: ent-slice 2.2s steps(6) forwards;
  animation-delay: calc(var(--n) * 0.05s);
}

@keyframes ent-slice {
  0% { transform: translateX(0); opacity: 1; }
  30% { transform: translateX(calc((var(--n) - 4) * 6%)); opacity: 1; }
  60% { transform: translateX(calc((4 - var(--n)) * 12%)); opacity: 0.8; }
  100% { transform: translateX(120%); opacity: 0; }
}

.entrance__scan {
  inset: 0;
  background: repeating-linear-gradient(transparent 0 3px, rgba(5, 217, 232, 0.15) 3px 4px);
  animation: ent-dark 2.2s linear forwards;
}

/* ---------- Хлопушка ---------- */

.entrance__confetti {
  left: 50%;
  top: 102%;

  width: 9px;
  height: 5px;

  border-radius: 1px;
  opacity: 0;

  animation: ent-confetti 2.2s cubic-bezier(0.2, 0.7, 0.3, 1) forwards;
  animation-delay: var(--d);
}

@keyframes ent-confetti {
  0% { opacity: 1; transform: translate(0, 0) rotate(0); }
  45% { opacity: 1; transform: translate(var(--x), calc(-1 * var(--y) * 6)) rotate(var(--r)); }
  100% { opacity: 0; transform: translate(calc(var(--x) * 1.2), calc(-1 * var(--y) * 3)) rotate(calc(var(--r) * 2)); }
}

/* ---------- Туман ---------- */

.entrance__cloud {
  left: calc((var(--n) - 1) * 18% - 10%);
  top: calc(10% + (var(--n) % 3) * 22%);

  width: 60%;
  aspect-ratio: 1;

  background: radial-gradient(circle, rgba(214, 211, 209, 0.95), rgba(168, 162, 158, 0.6) 45%, transparent 70%);
  filter: blur(8px);

  animation: ent-cloud 2.2s ease-out forwards;
}

@keyframes ent-cloud {
  0%, 25% { opacity: 1; transform: scale(1.4); }
  100% { opacity: 0; transform: scale(2.4) translateX(calc((var(--n) - 3.5) * 10%)); }
}

/* ---------- Коронация ---------- */

.entrance__rays {
  left: 50%;
  top: 40%;

  width: 260%;
  aspect-ratio: 1;

  background: repeating-conic-gradient(rgba(250, 204, 21, 0.35) 0 10deg, transparent 10deg 24deg);
  -webkit-mask: radial-gradient(circle, #000 10%, transparent 55%);
  mask: radial-gradient(circle, #000 10%, transparent 55%);
  transform: translate(-50%, -50%);

  animation: ent-rays 2.2s ease-out forwards;
}

@keyframes ent-rays {
  0% { opacity: 0; transform: translate(-50%, -50%) rotate(0) scale(0.6); }
  25% { opacity: 1; }
  100% { opacity: 0; transform: translate(-50%, -50%) rotate(60deg) scale(1.1); }
}

.entrance__crown {
  left: 50%;
  top: 40%;

  filter: drop-shadow(0 0 16px #facc15);
  transform: translate(-50%, -50%);

  animation: ent-crown 2.2s cubic-bezier(0.3, 1.4, 0.5, 1) forwards;
}

@keyframes ent-crown {
  0% { opacity: 0; transform: translate(-50%, -300%) scale(0.6); }
  35% { opacity: 1; transform: translate(-50%, -50%) scale(1.3); }
  70% { opacity: 1; transform: translate(-50%, -50%) scale(1.1); }
  100% { opacity: 0; transform: translate(-50%, -80%) scale(1); }
}

.entrance__flash--gold {
  background: radial-gradient(circle at 50% 40%, rgba(254, 249, 195, 0.9), rgba(250, 204, 21, 0.3) 40%, transparent 70%);
  animation-name: ent-flash-gold;
}

@keyframes ent-flash-gold {
  0%, 30% { opacity: 0; }
  36% { opacity: 1; }
  70%, 100% { opacity: 0; }
}

/* ---------- Горизонт событий ---------- */

.entrance__hole {
  left: 50%;
  top: 50%;

  width: 30px;
  aspect-ratio: 1;

  border-radius: 50%;
  background: #000;
  box-shadow: 0 0 30px 10px rgba(249, 115, 22, 0.6), 0 0 80px 30px rgba(0, 0, 0, 0.8);
  transform: translate(-50%, -50%);

  animation: ent-hole 2.2s cubic-bezier(0.7, 0, 0.3, 1) forwards;
}

@keyframes ent-hole {
  0% { transform: translate(-50%, -50%) scale(0); }
  45% { transform: translate(-50%, -50%) scale(50); }
  55% { transform: translate(-50%, -50%) scale(50); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
}

.entrance__hole-ring {
  left: 50%;
  top: 50%;

  width: 30px;
  aspect-ratio: 1;

  border-radius: 50%;
  border: 2px solid #fde68a;
  box-shadow: 0 0 20px #f97316;
  transform: translate(-50%, -50%);
  opacity: 0;

  animation: ent-ring 2.2s ease-out forwards;
}

@keyframes ent-ring {
  0%, 55% { opacity: 0; transform: translate(-50%, -50%) scale(0); }
  60% { opacity: 1; }
  100% { opacity: 0; transform: translate(-50%, -50%) scale(40); }
}
</style>
