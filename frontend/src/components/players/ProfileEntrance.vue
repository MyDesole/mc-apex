<script setup>
/**
 * Появление профиля (магазин, тип profile_entrance): короткое интро
 * поверх профиля, когда его открывают. Играет один раз и исчезает,
 * клики не ловит. В превью магазина — по кругу (loop).
 */
import { computed, onMounted, onUnmounted, ref, useId } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const props = defineProps({
  entrance: { type: String, default: null },
  // Превью в магазине: повторять бесконечно
  loop: { type: Boolean, default: false },
})

const hugGradientId = useId()

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
    <template v-if="entrance === 'rift'">
      <span class="entrance__rift-shade"></span>
      <span class="entrance__rift-seam"></span>
      <span class="entrance__rift-aperture">
        <span class="entrance__rift-core"></span>
        <img src="/images/cosmetics/singularity/frame.webp" alt="" decoding="async" />
        <span class="entrance__rift-corona"></span>
      </span>
      <span class="entrance__rift-wave"></span>
      <span v-for="n in 18" :key="n" class="entrance__rift-spark" :style="{ '--angle': `${n * 20}deg`, '--delay': `${(n % 4) * 0.035}s` }"></span>
    </template>
    <template v-else-if="entrance === 'cloud_hug'">
      <span v-for="n in 4" :key="`cloud-${n}`" class="entrance__hug-cloud" :style="{ '--side': n % 2 ? -1 : 1, '--row': n > 2 ? 1 : 0 }"></span>
      <svg class="entrance__hug-heart" viewBox="0 0 120 120">
        <defs><radialGradient :id="hugGradientId" cx="35%" cy="25%" r="90%"><stop stop-color="#fff4f8" /><stop offset=".5" stop-color="#f5bbd2" /><stop offset="1" stop-color="#c487b4" /></radialGradient></defs>
        <path :fill="`url(#${hugGradientId})`" stroke="#ffe6f0" stroke-width="1.5" d="M60 107C42 94 12 74 12 45a24 24 0 0 1 48-3 24 24 0 0 1 48 3c0 29-30 49-48 62Z" />
        <g fill="#fff5ef"><ellipse cx="44" cy="50" rx="6" ry="8" transform="rotate(-20 44 50)" /><ellipse cx="58" cy="43" rx="6" ry="8" /><ellipse cx="73" cy="48" rx="6" ry="8" transform="rotate(20 73 48)" /><path d="M43 73c0-8 10-18 17-18s17 10 17 18c0 11-12 6-17 6s-17 5-17-6Z" /></g>
      </svg>
      <span v-for="n in 14" :key="`heart-${n}`" class="entrance__hug-petal" :style="{ '--x': `${(n * 37) % 100}%`, '--drift': `${(n % 2 ? -1 : 1) * (20 + n * 3)}px`, '--delay': `${(n % 5) * .055}s` }">
        <svg viewBox="0 0 24 24"><path d="M12 21C8 18 2 14 2 8a5 5 0 0 1 10-1A5 5 0 0 1 22 8c0 6-6 10-10 13Z" /></svg>
      </span>
    </template>
    <!-- Удар молнии -->
    <template v-else-if="entrance === 'lightning'">
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

.entrance--rift { max-height:min(75vh,700px); }
.entrance__rift-shade { inset:0; background:radial-gradient(ellipse at center,#08121be8,#05080eb0); animation:rift-shade 2.2s ease-out both; }
.entrance__rift-seam { left:50%; top:12%; width:2px; height:76%; background:linear-gradient(transparent,#b9fbff 35%,#fff 50%,#99eaff 65%,transparent); box-shadow:0 0 16px #3cdbff; transform-origin:center; animation:rift-seam 2.2s ease-in-out both; }
.entrance__rift-aperture { left:50%; top:50%; width:min(48%,280px); aspect-ratio:1; border-radius:50%; animation:rift-open 2.2s cubic-bezier(.22,.7,.3,1) both; }
.entrance__rift-aperture img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; image-rendering:auto; }
.entrance__rift-core { position:absolute; inset:15%; border-radius:50%; background:radial-gradient(ellipse at 35% 30%,#8deaffaa,transparent 35%),conic-gradient(from 30deg,#040a18,#334568,#100a26,#3b9bb2,#060b17); box-shadow:inset 0 0 28px #0a031f,0 0 22px #43dafa55; animation:rift-core 2.2s ease-out both; }
.entrance__rift-corona { position:absolute; inset:5%; border:2px solid #9af5ff; border-radius:50%; mask-image:conic-gradient(transparent 0deg 35deg,#000 60deg 135deg,transparent 160deg 210deg,#000 240deg 300deg,transparent 330deg); animation:rift-corona 2.2s ease-out both; box-shadow:0 0 12px #4fd5ff; }
.entrance__rift-wave { left:50%; top:50%; width:min(48%,280px); aspect-ratio:1; border-radius:50%; border:2px solid #b7faff; box-shadow:0 0 14px #80dcff,inset 0 0 12px #b096ff; animation:rift-wave 2.2s ease-out both; }
.entrance__rift-spark { left:50%; top:50%; width:min(35%,250px); height:2px; transform-origin:left center; background:linear-gradient(90deg,transparent 65%,#7ce6ffcc 93%,#efffff); border-radius:50%; animation:rift-spark 1.25s ease-out both; animation-delay:calc(.7s + var(--delay)); }
@keyframes rift-shade { 0% { opacity:0; } 15%,42% { opacity:1; } 100% { opacity:0; } }
@keyframes rift-seam { 0% { opacity:0; transform:scaleY(0); } 14%,23% { opacity:1; transform:scaleY(1); } 42%,100% { opacity:0; transform:scaleY(.1) scaleX(12); } }
@keyframes rift-open { 0%,12% { opacity:0; transform:translate(-50%,-50%) scale(.02) rotate(-85deg); } 35% { opacity:1; transform:translate(-50%,-50%) scale(1) rotate(0); } 56% { opacity:1; transform:translate(-50%,-50%) scale(1.06) rotate(15deg); } 100% { opacity:0; transform:translate(-50%,-50%) scale(3.8) rotate(55deg); } }
@keyframes rift-core { 0%,20% { opacity:0; transform:rotate(0); } 35% { opacity:1; } 100% { opacity:0; transform:rotate(160deg); } }
@keyframes rift-corona { from { transform:rotate(0); } to { transform:rotate(460deg); } }
@keyframes rift-wave { 0%,36% { opacity:0; transform:translate(-50%,-50%) scale(.7); } 44% { opacity:.8; } 100% { opacity:0; transform:translate(-50%,-50%) scale(5); } }
@keyframes rift-spark { 0% { opacity:0; transform:rotate(var(--angle)) scaleX(.12); } 18% { opacity:.85; } 100% { opacity:0; transform:rotate(var(--angle)) scaleX(1.8); } }
@media(prefers-reduced-motion:reduce) { .entrance--rift { display:none; } }

.entrance--cloud_hug { max-height:min(75vh,700px); }
.entrance__hug-cloud { left:calc(var(--side) * 25%); right:calc(var(--side) * -25%); top:calc(var(--row) * 48% - 20%); height:85%; border-radius:45%; filter:blur(8px); background:radial-gradient(ellipse at 45% 55%,#fff1f9e8 15%,#f0d8efcc 40%,#e6dcf380 60%,transparent 72%); animation:hug-cloud 2.2s ease-in-out both; }
.entrance__hug-heart { left:50%; top:48%; width:min(30%,180px); height:auto; filter:drop-shadow(0 8px 18px #c67caa55); animation:hug-heart 2.2s ease-out both; }
.entrance__hug-petal { left:var(--x); top:60%; width:clamp(8px,2.8%,22px); height:auto; animation:hug-petal 1.6s ease-out both; animation-delay:calc(.3s + var(--delay)); }
.entrance__hug-petal svg { display:block; width:100%; fill:#f1a5c8; stroke:#fff3f8; stroke-width:1; }
@keyframes hug-cloud { 0% { opacity:0; transform:scale(.8); } 18% { opacity:.75; } 100% { opacity:0; transform:translateX(calc(var(--side) * 55%)) scale(1.25); } }
@keyframes hug-heart { 0%,12% { opacity:0; transform:translate(-50%,-50%) scale(.5) rotate(-8deg); } 38% { opacity:1; transform:translate(-50%,-50%) scale(1.05) rotate(3deg); } 57% { opacity:1; transform:translate(-50%,-50%) scale(1) rotate(0); } 100% { opacity:0; transform:translate(-50%,-65%) scale(1.1); } }
@keyframes hug-petal { 0% { opacity:0; transform:translate(0,30px) scale(.4) rotate(-12deg); } 25% { opacity:.9; } 100% { opacity:0; transform:translate(var(--drift),-150px) scale(1) rotate(18deg); } }
@media(prefers-reduced-motion:reduce) { .entrance--cloud_hug { display:none; } }
</style>
