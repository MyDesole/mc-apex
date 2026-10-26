<script setup>
/**
 * Украшения рамки аватара. Слой кладётся поверх рамки и не ловит клики.
 *
 * Два вида: «россыпь» (decor.shape — цветочки, сердечки, звёздочки по
 * кругу) и живые украшения (decor.kind) в духе аватар-декораций Discord:
 *   flames    — языки пламени по кругу
 *   horns     — рога с раскалёнными кончиками
 *   shockwave — звуковой удар вардена и сенсоры скалка
 *   peek      — криппер выглядывает, рамка мигает перед взрывом
 *   orbit     — пиксельные спрайты кружат вокруг аватара
 *   gills     — жабры аксолотля покачиваются по бокам
 *   ears      — кошачьи ушки, иногда дёргаются
 *   top       — спрайт над головой (корона) с искрами
 *   halo      — светящийся нимб
 */
import { computed } from 'vue'
import { frameById } from '@/data/players/profileCustomization.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const props = defineProps({
  frame: { type: String, default: null },
  // Размер украшения в долях от размера рамки
  scale: { type: Number, default: 0.26 },
  // Размер аватара в пикселях: от него считаются пиксельные спрайты
  size: { type: Number, default: 96 },
})

const decor = computed(() => frameById(props.frame)?.decor ?? null)
const kind = computed(() => decor.value?.kind ?? (decor.value?.shape ? 'scatter' : null))

/* Россыпь: пять украшений — два крупных сверху и снизу, мелкие по бокам */
const SPOTS = [
  { angle: -128, size: 1, tilt: -18 },
  { angle: -62, size: 0.72, tilt: 12 },
  { angle: 18, size: 0.6, tilt: -8 },
  { angle: 92, size: 0.85, tilt: 20 },
  { angle: 158, size: 0.66, tilt: -14 },
]

const scatter = computed(() => {
  const d = decor.value
  if (kind.value !== 'scatter') return []

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

/* Пламя: языки по кругу, каждый со своим ритмом */
const flames = computed(() => Array.from({ length: 14 }, (_, i) => ({
  '--angle': `${i * (360 / 14)}deg`,
  '--len': `${[34, 26, 40, 30, 22, 36, 28][i % 7]}%`,
  animationDelay: `${-(i * 0.37) % 1.1}s`,
  animationDuration: `${0.7 + (i % 4) * 0.15}s`,
})))

/* Орбита: спрайты равномерно по кругу */
const orbit = computed(() => {
  const d = decor.value
  if (kind.value !== 'orbit') return []

  const count = d.count ?? 3
  return Array.from({ length: count }, (_, i) => ({ '--angle': `${(360 / count) * i}deg` }))
})

const spriteSize = computed(() => Math.max(10, Math.round(props.size * 0.24)))

/* Планетарий: размер орбиты, скорость, наклон и сама планета */
const PLANETS = [
  {
    orbit: { inset: '-10%', animationDuration: '6s' },
    body: { width: '14%', height: '14%', background: 'radial-gradient(circle at 30% 30%, #fef3c7, #f59e0b 55%, #92400e)', boxShadow: '0 0 10px #f59e0b' },
  },
  {
    orbit: { inset: '-24%', animationDuration: '11s', animationDirection: 'reverse' },
    body: { width: '18%', height: '18%', background: 'radial-gradient(circle at 30% 30%, #bfdbfe, #3b82f6 55%, #1e3a8a)', boxShadow: '0 0 12px #3b82f6' },
  },
  {
    orbit: { inset: '-36%', animationDuration: '17s' },
    body: { width: '11%', height: '11%', background: 'radial-gradient(circle at 30% 30%, #fbcfe8, #db2777 55%, #500724)', boxShadow: '0 0 10px #ec4899', outline: '2px solid rgba(251,207,232,.5)', outlineOffset: '2px' },
  },
]
</script>

<template>
  <span
      v-if="kind"
      class="decor"
      :class="`decor--${kind}`"
      :style="{
        '--decor-color': decor.color || decor.colors?.[0],
        '--decor-color-2': decor.color2 || decor.inner || decor.tip || decor.color,
        '--orbit-duration': `${decor.duration || 8}s`,
        '--radius': `${size / 2}px`,
        '--hue': `${decor.hue || 0}deg`,
      }"
      aria-hidden="true"
  >
    <!-- Россыпь -->
    <template v-if="kind === 'scatter'">
      <span v-for="(item, i) in scatter" :key="i" class="decor__item" :style="item.style">
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
    </template>

    <!-- Пламя Незера -->
    <template v-else-if="kind === 'flames'">
      <span class="decor__heat"></span>
      <span v-for="(style, i) in flames" :key="i" class="decor__flame" :style="style"></span>
    </template>

    <!-- Рога -->
    <template v-else-if="kind === 'horns'">
      <span class="decor__horn decor__horn--left">
        <PixelSprite sprite="horn" :size="Math.round(size * 0.46)" anim="glow" glow="#f97316" />
      </span>
      <span class="decor__horn decor__horn--right">
        <PixelSprite sprite="horn" :size="Math.round(size * 0.46)" anim="glow" glow="#f97316" />
      </span>
    </template>

    <!-- Варден: ударная волна и сенсоры скалка -->
    <template v-else-if="kind === 'shockwave'">
      <span v-for="n in 3" :key="`w${n}`" class="decor__wave" :style="{ animationDelay: `${(n - 1) * 0.55}s` }"></span>
      <span v-for="n in 6" :key="`s${n}`" class="decor__sensor" :style="{ '--angle': `${n * 60 - 30}deg`, animationDelay: `${n * 0.3}s` }"></span>
    </template>

    <!-- Криппер выглядывает -->
    <template v-else-if="kind === 'peek'">
      <span class="decor__fuse"></span>
      <span class="decor__peek">
        <PixelSprite :sprite="decor.sprite" :size="Math.round(size * 0.36)" />
      </span>
    </template>

    <!-- Орбита -->
    <span v-else-if="kind === 'orbit'" class="decor__orbit">
      <span v-for="(style, i) in orbit" :key="i" class="decor__satellite" :style="style">
        <span class="decor__satellite-inner">
          <PixelSprite :sprite="decor.sprite" :size="spriteSize" />
        </span>
      </span>
    </span>

    <!-- Жабры аксолотля -->
    <template v-else-if="kind === 'gills'">
      <span v-for="side in ['left', 'right']" :key="side" class="decor__gills" :class="`decor__gills--${side}`">
        <i v-for="n in 3" :key="n" :style="{ '--n': n }"></i>
      </span>
    </template>

    <!-- Кошачьи ушки -->
    <template v-else-if="kind === 'ears'">
      <span class="decor__ear decor__ear--left"><i></i></span>
      <span class="decor__ear decor__ear--right"><i></i></span>
    </template>

    <!-- Корона и прочее над головой -->
    <template v-else-if="kind === 'top'">
      <span class="decor__top">
        <PixelSprite :sprite="decor.sprite" :size="Math.round(size * 0.34)" anim="glow" glow="#fde047" />
      </span>
      <span v-for="n in 3" :key="n" class="decor__spark" :style="{ '--n': n }"></span>
    </template>

    <!-- Нимб -->
    <template v-else-if="kind === 'halo'">
      <span class="decor__halo"></span>
      <span class="decor__halo-glow"></span>
    </template>

    <!-- Голограмма: радужное кольцо переливается, по нему пробегает блик -->
    <template v-else-if="kind === 'holo'">
      <span class="decor__ring decor__ring--holo-glow"></span>
      <span class="decor__ring decor__ring--holo"></span>
      <span class="decor__ring decor__ring--shine"></span>
    </template>

    <!-- Неон: две трубки светятся и иногда моргают -->
    <template v-else-if="kind === 'neon'">
      <span class="decor__neon decor__neon--outer"></span>
      <span class="decor__neon decor__neon--inner"></span>
    </template>

    <!-- Чёрная дыра: аккреционный диск вращается вокруг аватара -->
    <template v-else-if="kind === 'blackhole'">
      <span class="decor__lens"></span>
      <span class="decor__disk-wrap"><span class="decor__disk"></span></span>
      <span class="decor__photon"></span>
    </template>

    <!-- Глитч: красный и бирюзовый контуры дёргаются вразнобой -->
    <template v-else-if="kind === 'glitch'">
      <span class="decor__glitch decor__glitch--red"></span>
      <span class="decor__glitch decor__glitch--cyan"></span>
      <span class="decor__glitch-bar"></span>
    </template>

    <!-- Кибер-прицел: сегменты вращаются навстречу, уголки рамки -->
    <template v-else-if="kind === 'hud'">
      <span class="decor__hud decor__hud--outer"></span>
      <span class="decor__hud decor__hud--inner"></span>
      <span v-for="corner in ['tl', 'tr', 'bl', 'br']" :key="corner" class="decor__bracket" :class="`decor__bracket--${corner}`"></span>
    </template>

    <!-- Плазма: размытое кольцо энергии быстро вращается -->
    <template v-else-if="kind === 'plasma'">
      <span class="decor__ring decor__ring--plasma-glow"></span>
      <span class="decor__ring decor__ring--plasma"></span>
    </template>

    <!-- Планетарий: три планеты на разных орбитах -->
    <template v-else-if="kind === 'planets'">
      <span v-for="(planet, i) in PLANETS" :key="i" class="decor__planet-orbit" :style="planet.orbit">
        <i class="decor__planet" :style="planet.body"></i>
      </span>
    </template>

    <!-- Жидкий металл: хромированное кольцо с бегущими бликами -->
    <template v-else-if="kind === 'chrome'">
      <span class="decor__ring decor__ring--chrome"></span>
    </template>
  </span>
</template>

<style scoped>
.decor {
  position: absolute;
  inset: 0;
  z-index: 3;

  pointer-events: none;
}

/* ---------- Россыпь ---------- */

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

/* ---------- Пламя ---------- */

.decor__heat {
  position: absolute;
  inset: -6%;

  border-radius: 50%;
  box-shadow:
      0 0 18px 4px rgba(249, 115, 22, 0.55),
      inset 0 0 14px rgba(253, 224, 71, 0.35);
  filter: hue-rotate(var(--hue, 0deg));

  animation: heat 1.6s ease-in-out infinite alternate;
}

@keyframes heat {
  from { opacity: 0.6; }
  to { opacity: 1; }
}

.decor__flame {
  position: absolute;
  left: 50%;
  top: 50%;

  width: 16%;
  height: var(--len);

  background: radial-gradient(ellipse at 50% 100%, #fef9c3 0%, #fde047 22%, #f97316 55%, rgba(220, 38, 38, 0.85) 75%, transparent 78%);
  border-radius: 50% 50% 40% 40% / 70% 70% 30% 30%;
  /* --hue перекрашивает пламя: синее «пламя душ» — тот же огонь со сдвигом тона */
  filter: blur(0.3px) drop-shadow(0 0 3px rgba(249, 115, 22, 0.8)) hue-rotate(var(--hue, 0deg));

  /* Основание языка — на окружности рамки, кончик смотрит наружу */
  margin-left: -8%;
  margin-top: calc(-1 * var(--len));
  transform-origin: 50% 100%;
  transform: rotate(var(--angle)) translateY(calc(-0.86 * var(--radius)));

  animation: flicker 0.9s ease-in-out infinite alternate;
  mix-blend-mode: screen;
}

@keyframes flicker {
  0% { scale: 0.75 0.6; opacity: 0.75; }
  40% { scale: 1.05 1.1; opacity: 1; }
  100% { scale: 0.9 0.85; opacity: 0.85; }
}

/* ---------- Рога ---------- */

/* Пиксельные рога: основание у макушки, кончики смотрят вверх и в стороны */
.decor__horn {
  position: absolute;
  top: -30%;

  filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.5));
}

.decor__horn--left {
  left: 0;
}

.decor__horn--right {
  right: 0;
  transform: scaleX(-1);
}

/* ---------- Варден ---------- */

.decor__wave {
  position: absolute;
  inset: 0;

  border: 3px solid var(--decor-color);
  border-radius: 50%;
  box-shadow: 0 0 12px var(--decor-color), inset 0 0 8px var(--decor-color);

  animation: wave 2.4s cubic-bezier(0.2, 0.6, 0.3, 1) infinite;
}

@keyframes wave {
  0% { transform: scale(0.95); opacity: 0.9; }
  100% { transform: scale(1.7); opacity: 0; }
}

.decor__sensor {
  position: absolute;
  left: 50%;
  top: 50%;

  width: 9%;
  height: 9%;

  background: var(--decor-color);
  border-radius: 2px;
  box-shadow: 0 0 8px var(--decor-color);

  transform: translate(-50%, -50%) rotate(var(--angle)) translateY(calc(-1 * var(--radius) - 6px)) rotate(45deg);

  animation: sensor 1.8s ease-in-out infinite;
}

@keyframes sensor {
  0%, 100% { opacity: 0.35; }
  50% { opacity: 1; }
}

/* ---------- Криппер ---------- */

.decor__peek {
  position: absolute;
  right: -18%;
  bottom: -8%;

  filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.5));

  animation: peek 6s ease-in-out infinite;
}

@keyframes peek {
  0%, 12% { transform: translate(-30%, 40%) rotate(10deg); opacity: 0; }
  22%, 70% { transform: translate(0, 0) rotate(0deg); opacity: 1; }
  80%, 100% { transform: translate(-30%, 40%) rotate(10deg); opacity: 0; }
}

/* Фитиль: за пару секунд до «взрыва» рамка мигает белым */
.decor__fuse {
  position: absolute;
  inset: -3%;

  border: 4px solid #fff;
  border-radius: 50%;
  opacity: 0;

  animation: fuse 6s linear infinite;
}

@keyframes fuse {
  0%, 46% { opacity: 0; }
  48% { opacity: 0.9; }
  51% { opacity: 0; }
  54% { opacity: 0.9; }
  57% { opacity: 0; }
  59% { opacity: 1; }
  61% { opacity: 0; }
  63% { opacity: 1; box-shadow: 0 0 22px 6px #fff; }
  68%, 100% { opacity: 0; }
}

/* ---------- Орбита ---------- */

.decor__orbit {
  position: absolute;
  inset: -16%;

  animation: orbit var(--orbit-duration) linear infinite;
}

@keyframes orbit {
  to { transform: rotate(360deg); }
}

/* Спутник: поворот на свой угол, спрайт — у верхнего края орбиты */
.decor__satellite {
  position: absolute;
  inset: 0;

  transform: rotate(var(--angle));
}

/* Спрайт держит горизонт, пока орбита вращается, и покачивается */
.decor__satellite-inner {
  position: absolute;
  left: 50%;
  top: 0;

  display: block;

  transform: translate(-50%, -50%) rotate(calc(-1 * var(--angle)));

  animation: counter var(--orbit-duration) linear infinite;
}

.decor__satellite-inner > * {
  animation: px-hover 0.9s ease-in-out infinite alternate;
}

@keyframes counter {
  to { rotate: -360deg; }
}

@keyframes px-hover {
  from { translate: 0 -2px; }
  to { translate: 0 2px; }
}

/* ---------- Жабры ---------- */

.decor__gills {
  position: absolute;
  top: 18%;

  width: 30%;
  height: 50%;
}

.decor__gills--left {
  left: -20%;
}

.decor__gills--right {
  right: -20%;
  transform: scaleX(-1);
}

.decor__gills i {
  position: absolute;
  right: 0;
  top: calc(var(--n) * 24%);

  width: 100%;
  height: 16%;

  background:
      repeating-linear-gradient(90deg, var(--decor-color) 0 4px, var(--decor-color-2) 4px 7px);
  border-radius: 999px 4px 4px 999px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);

  transform-origin: 100% 50%;
  rotate: calc((var(--n) - 2) * 22deg);

  animation: gill 1.6s ease-in-out infinite alternate;
  animation-delay: calc(var(--n) * -0.3s);
}

@keyframes gill {
  from { transform: rotate(-7deg) scaleX(0.92); }
  to { transform: rotate(7deg) scaleX(1); }
}

/* ---------- Ушки ---------- */

.decor__ear {
  position: absolute;
  top: -16%;

  width: 34%;
  height: 34%;

  background: var(--decor-color);
  clip-path: polygon(50% 0, 100% 100%, 0 100%);
  filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.3));

  transform-origin: 50% 100%;
}

.decor__ear i {
  position: absolute;
  inset: 32% 24% 0;

  background: var(--decor-color-2);
  clip-path: polygon(50% 0, 100% 100%, 0 100%);
}

.decor__ear--left {
  left: 4%;
  rotate: -18deg;
  animation: ear-twitch 5s ease-in-out infinite;
}

.decor__ear--right {
  right: 4%;
  rotate: 18deg;
  animation: ear-twitch 5s ease-in-out infinite 1.3s;
}

@keyframes ear-twitch {
  0%, 86%, 100% { transform: rotate(0); }
  89% { transform: rotate(-14deg); }
  92% { transform: rotate(6deg); }
  95% { transform: rotate(-8deg); }
}

/* ---------- Корона ---------- */

.decor__top {
  position: absolute;
  left: 50%;
  top: -34%;

  transform: translateX(-50%) rotate(-4deg);

  animation: crown-float 2.8s ease-in-out infinite;
}

@keyframes crown-float {
  0%, 100% { translate: 0 0; }
  50% { translate: 0 -8%; }
}

.decor__spark {
  position: absolute;
  top: calc(-30% + var(--n) * 6%);
  left: calc(20% + var(--n) * 18%);

  width: 10%;
  height: 10%;

  background: #fff;
  clip-path: polygon(50% 0, 62% 38%, 100% 50%, 62% 62%, 50% 100%, 38% 62%, 0 50%, 38% 38%);
  filter: drop-shadow(0 0 3px #fde047);

  animation: spark 1.8s ease-in-out infinite;
  animation-delay: calc(var(--n) * 0.6s);
}

@keyframes spark {
  0%, 100% { transform: scale(0); opacity: 0; }
  50% { transform: scale(1) rotate(45deg); opacity: 1; }
}

/* ---------- Нимб ---------- */

.decor__halo {
  position: absolute;
  left: 18%;
  right: 18%;
  top: -22%;

  height: 18%;

  border: 4px solid var(--decor-color);
  border-radius: 50%;
  box-shadow: 0 0 12px var(--decor-color), inset 0 0 6px var(--decor-color);

  animation: halo-float 3s ease-in-out infinite;
}

.decor__halo-glow {
  position: absolute;
  inset: -8%;

  border-radius: 50%;
  background: radial-gradient(circle, transparent 55%, color-mix(in srgb, var(--decor-color) 35%, transparent) 70%, transparent 76%);

  animation: halo-pulse 3s ease-in-out infinite;
}

@keyframes halo-float {
  0%, 100% { transform: translateY(0) rotateX(0); }
  50% { transform: translateY(-14%); }
}

@keyframes halo-pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

/* ---------- Кольца: голограмма, плазма, хром ---------- */

/* Кольцо вокруг аватара: заливка видна только в тонкой полосе маски */
.decor__ring {
  position: absolute;
  inset: -7%;

  border-radius: 50%;
  -webkit-mask: radial-gradient(circle, transparent 60%, #000 61.5%, #000 70%, transparent 71.5%);
  mask: radial-gradient(circle, transparent 60%, #000 61.5%, #000 70%, transparent 71.5%);
}

.decor__ring--holo,
.decor__ring--holo-glow {
  background: conic-gradient(#ff3d81, #ffb03d, #f6ff3d, #3dffa0, #3dd9ff, #6b3dff, #ff3dd4, #ff3d81);
  animation: spin 4s linear infinite;
}

.decor__ring--holo-glow {
  filter: blur(8px);
  opacity: 0.85;
  -webkit-mask: radial-gradient(circle, transparent 52%, #000 60%, #000 72%, transparent 82%);
  mask: radial-gradient(circle, transparent 52%, #000 60%, #000 72%, transparent 82%);
}

/* Блик: белая полоса пробегает по кольцу */
.decor__ring--shine {
  background: conic-gradient(from 0deg, transparent 0 80%, rgba(255, 255, 255, 0.95) 88%, transparent 96%);
  animation: spin 2.2s linear infinite reverse;
}

.decor__ring--plasma,
.decor__ring--plasma-glow {
  background: conic-gradient(from 0deg, #7c3aed, #ec4899, #22d3ee, #7c3aed, #f472b6, #6366f1, #7c3aed);
  animation: spin 1.6s linear infinite;
}

.decor__ring--plasma {
  filter: blur(1.5px) saturate(1.4);
  animation-duration: 1.6s, 0.9s;
  animation-name: spin, plasma-wobble;
  animation-timing-function: linear, ease-in-out;
  animation-direction: normal, alternate;
}

.decor__ring--plasma-glow {
  inset: -14%;
  filter: blur(12px);
  -webkit-mask: radial-gradient(circle, transparent 45%, #000 58%, #000 70%, transparent 85%);
  mask: radial-gradient(circle, transparent 45%, #000 58%, #000 70%, transparent 85%);
  animation-direction: reverse;
}

@keyframes plasma-wobble {
  from { scale: 1; }
  to { scale: 1.05 0.97; }
}

.decor__ring--chrome {
  background: conic-gradient(#f8fafc, #64748b, #e2e8f0, #1e293b, #cbd5e1, #f8fafc, #475569, #e2e8f0, #f8fafc);
  filter: drop-shadow(0 0 6px rgba(226, 232, 240, 0.5));
  animation: spin 7s ease-in-out infinite;
}

@keyframes spin {
  to { rotate: 360deg; }
}

/* ---------- Неон ---------- */

.decor__neon {
  position: absolute;

  border-radius: 50%;
  border: 3px solid #fff;
}

.decor__neon--outer {
  inset: -9%;
  border-color: #fdf4ff;
  box-shadow: 0 0 4px #f0abfc, 0 0 10px #e879f9, 0 0 22px #d946ef, inset 0 0 8px #d946ef;
  animation: neon-flicker 5s linear infinite;
}

.decor__neon--inner {
  inset: -3%;
  border-width: 2px;
  border-color: #ecfeff;
  box-shadow: 0 0 4px #67e8f9, 0 0 10px #22d3ee, 0 0 20px #06b6d4;
  animation: neon-flicker 7s linear infinite 1.7s;
}

@keyframes neon-flicker {
  0%, 18%, 22%, 62%, 64%, 100% { opacity: 1; }
  19%, 21%, 63% { opacity: 0.25; }
}

/* ---------- Чёрная дыра ---------- */

/* Гравитационная линза: тёмное кольцо и тусклый ореол */
.decor__lens {
  position: absolute;
  inset: -12%;

  border-radius: 50%;
  background: radial-gradient(circle, transparent 50%, rgba(0, 0, 0, 0.85) 56%, rgba(249, 115, 22, 0.25) 64%, transparent 72%);
}

.decor__disk-wrap {
  position: absolute;
  inset: -42% -42%;

  perspective: 400px;
  pointer-events: none;
}

.decor__disk {
  position: absolute;
  inset: 0;

  border-radius: 50%;
  background: conic-gradient(from 0deg, transparent 0deg, #7c2d12 40deg, #f97316 90deg, #fde68a 150deg, #fff 175deg, #fde68a 200deg, #f97316 260deg, #7c2d12 320deg, transparent 360deg);
  -webkit-mask: radial-gradient(circle, transparent 38%, #000 41%, #000 60%, transparent 70%);
  mask: radial-gradient(circle, transparent 38%, #000 41%, #000 60%, transparent 70%);
  filter: blur(1px) drop-shadow(0 0 10px rgba(249, 115, 22, 0.8));

  transform: rotateX(74deg);
  animation: disk 3s linear infinite;
}

@keyframes disk {
  from { transform: rotateX(74deg) rotateZ(0deg); }
  to { transform: rotateX(74deg) rotateZ(360deg); }
}

/* Фотонное кольцо: тонкая яркая окружность прямо по краю аватара */
.decor__photon {
  position: absolute;
  inset: -2%;

  border-radius: 50%;
  border: 2px solid rgba(253, 230, 138, 0.9);
  box-shadow: 0 0 10px rgba(249, 115, 22, 0.9), 0 0 24px rgba(249, 115, 22, 0.5);

  animation: photon 2.4s ease-in-out infinite alternate;
}

@keyframes photon {
  from { opacity: 0.6; }
  to { opacity: 1; }
}

/* ---------- Глитч ---------- */

.decor__glitch {
  position: absolute;
  inset: -5%;

  border-radius: 50%;
  border: 3px solid;
  mix-blend-mode: screen;
}

.decor__glitch--red {
  border-color: #ff2a6d;
  animation: glitch-a 2.6s steps(1) infinite;
}

.decor__glitch--cyan {
  border-color: #05d9e8;
  animation: glitch-b 2.6s steps(1) infinite;
}

@keyframes glitch-a {
  0%, 100% { transform: translate(0, 0); clip-path: inset(0 0 0 0); }
  10% { transform: translate(-4px, 1px); clip-path: inset(10% 0 55% 0); }
  12% { transform: translate(3px, -2px); clip-path: inset(60% 0 5% 0); }
  14% { transform: translate(0, 0); clip-path: inset(0 0 0 0); }
  52% { transform: translate(-2px, 0); }
  54% { transform: translate(5px, 2px); clip-path: inset(30% 0 40% 0); }
  56% { transform: translate(0, 0); clip-path: inset(0 0 0 0); }
}

@keyframes glitch-b {
  0%, 100% { transform: translate(0, 0); clip-path: inset(0 0 0 0); }
  10% { transform: translate(4px, -1px); clip-path: inset(50% 0 20% 0); }
  12% { transform: translate(-3px, 2px); clip-path: inset(5% 0 70% 0); }
  14% { transform: translate(0, 0); clip-path: inset(0 0 0 0); }
  54% { transform: translate(-5px, -2px); clip-path: inset(15% 0 60% 0); }
  56% { transform: translate(0, 0); clip-path: inset(0 0 0 0); }
}

/* Полоса помех пробегает по аватару */
.decor__glitch-bar {
  position: absolute;
  left: -8%;
  right: -8%;
  top: 0;

  height: 8%;

  background: linear-gradient(90deg, transparent, rgba(5, 217, 232, 0.7), rgba(255, 42, 109, 0.7), transparent);
  mix-blend-mode: screen;
  opacity: 0;

  animation: glitch-bar 2.6s steps(1) infinite;
}

@keyframes glitch-bar {
  0%, 9%, 15%, 51%, 57%, 100% { opacity: 0; }
  10% { opacity: 1; top: 22%; }
  12% { opacity: 1; top: 64%; }
  54% { opacity: 1; top: 40%; }
}

/* ---------- Кибер-прицел ---------- */

.decor__hud {
  position: absolute;

  border-radius: 50%;
  -webkit-mask: radial-gradient(circle, transparent 66%, #000 67.5%, #000 70%, transparent 71.5%);
  mask: radial-gradient(circle, transparent 66%, #000 67.5%, #000 70%, transparent 71.5%);
}

.decor__hud--outer {
  inset: -16%;
  background: repeating-conic-gradient(#22d3ee 0 34deg, transparent 34deg 60deg);
  filter: drop-shadow(0 0 4px #22d3ee);
  animation: spin 9s linear infinite;
}

.decor__hud--inner {
  inset: -6%;
  background: repeating-conic-gradient(#a5f3fc 0 4deg, transparent 4deg 12deg);
  opacity: 0.7;
  animation: spin 14s linear infinite reverse;
}

.decor__bracket {
  position: absolute;

  width: 22%;
  height: 22%;

  border: 0 solid #22d3ee;
  filter: drop-shadow(0 0 4px #22d3ee);

  animation: bracket 2.2s ease-in-out infinite;
}

.decor__bracket--tl { top: -18%; left: -18%; border-top-width: 3px; border-left-width: 3px; }
.decor__bracket--tr { top: -18%; right: -18%; border-top-width: 3px; border-right-width: 3px; }
.decor__bracket--bl { bottom: -18%; left: -18%; border-bottom-width: 3px; border-left-width: 3px; }
.decor__bracket--br { bottom: -18%; right: -18%; border-bottom-width: 3px; border-right-width: 3px; }

@keyframes bracket {
  0%, 100% { scale: 1; opacity: 0.9; }
  50% { scale: 0.88; opacity: 1; }
}

/* ---------- Планетарий ---------- */

.decor__planet-orbit {
  position: absolute;

  border: 1px dashed rgba(255, 255, 255, 0.12);
  border-radius: 50%;

  animation: spin linear infinite;
}

.decor__planet {
  position: absolute;
  left: 50%;
  top: 0;

  border-radius: 50%;
  transform: translate(-50%, -50%);
}

@media (prefers-reduced-motion: reduce) {
  .decor *,
  .decor {
    animation: none !important;
  }
}
</style>
