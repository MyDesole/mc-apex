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
import { spriteRatio, spriteUrl } from '@/data/shop/pixelSprites.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'

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

    const style = {
      left: `${(spread(i, 1) * 100).toFixed(1)}%`,
      top: `${(spread(i, 2) * 100).toFixed(1)}%`,
      width: `${size}px`,
      height: `${c.streak ? size * 4 : size}px`,
      animationDuration: `${duration.toFixed(2)}s`,
      // Отрицательная задержка — частицы уже «в пути» при открытии страницы
      animationDelay: `-${(spread(i, 5) * duration).toFixed(2)}s`,
      opacity: (0.55 + spread(i, 6) * 0.45).toFixed(2),
    }

    // Пиксельная картинка вместо цветного квадрата
    if (c.sprite) {
      style.height = `${Math.round(size / spriteRatio(c.sprite))}px`
      style.backgroundImage = spriteUrl(c.sprite)
    }

    // Следы лапок: цепочка по диагонали, левая-правая, по очереди
    if (props.effect === 'cat_paws') {
      style.left = `${6 + i * (88 / count)}%`
      style.top = `${(i % 2 ? 62 : 48) - i * (30 / count)}%`
      style.animationDelay = `${(i * duration) / count}s`
      style.opacity = 1
    }

    // Дым взрыва: клубы из одной точки, все сразу
    if (props.effect === 'creeper_boom') {
      style.left = `${50 + (spread(i, 1) - 0.5) * 30}%`
      style.top = `${55 + (spread(i, 2) - 0.5) * 30}%`
      style.animationDelay = '0s'
    }

    let text = null

    // Матрица: колонка символов, у каждой своя скорость
    if (c.glyphs) {
      const length = 8 + Math.round(spread(i, 7) * 10)
      text = Array.from({ length }, (_, k) => GLYPHS[Math.floor(spread(i * 31 + k, 8) * GLYPHS.length)]).join('')
      style.left = `${(i / count) * 100 + spread(i, 9) * 3}%`
      style.width = 'auto'
      style.height = 'auto'
      style.fontSize = `${size}px`
    }

    // Варп: звёзды разлетаются из центра под своим углом
    if (props.effect === 'warp') {
      style.left = '50%'
      style.top = '50%'
      style['--a'] = `${(spread(i, 1) * 360).toFixed(0)}deg`
    }

    // Конфетти и боке: цвет из палитры
    if (props.effect === 'confetti' || props.effect === 'bokeh') {
      style.background = PARTY_COLORS[i % PARTY_COLORS.length]
    }

    // Светлячки: каждый блуждает по своей траектории
    if (props.effect === 'fireflies') {
      style['--dx'] = `${((spread(i, 10) - 0.5) * 120).toFixed(0)}px`
      style['--dy'] = `${((spread(i, 11) - 0.5) * 80).toFixed(0)}px`
    }

    return { style, text }
  })
})

const GLYPHS = 'アイウエオカキクケコサシスセソタチツテトナニヌネノ0123456789ABCDEFΣΛΞ'
const PARTY_COLORS = ['#f472b6', '#facc15', '#22d3ee', '#a78bfa', '#4ade80', '#fb923c']
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

    <!-- Молния: ломаная из SVG, бьёт по шапке время от времени -->
    <svg v-if="effect === 'lightning'" class="fx__bolt" viewBox="0 0 60 200" preserveAspectRatio="none">
      <polyline points="34,0 22,62 36,66 14,130 30,134 6,200" />
    </svg>

    <!-- Крупная картинка на полотне: криппер перед взрывом, тотем во вспышке -->
    <span v-if="config.hero" class="fx__hero">
      <PixelSprite :sprite="config.hero" :size="34" />
    </span>

    <!-- Глитч: полосы помех с RGB-сдвигом -->
    <template v-if="effect === 'glitch'">
      <span v-for="n in 4" :key="n" class="fx__glitch" :style="{ '--n': n }"></span>
    </template>

    <span
        v-for="(p, i) in particles"
        :key="i"
        class="fx__particle"
        :class="{ 'fx__particle--sprite': config.sprite, 'fx__particle--text': p.text }"
        :style="p.style"
    >{{ p.text }}</span>
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

/* ---------- «Майнкрафт оживает» ---------- */

/* Частица-картинка: фон — пиксельный спрайт, без своего цвета */
.fx__particle--sprite {
  background-color: transparent;
  background-position: center;
  background-repeat: no-repeat;
  background-size: contain;
  image-rendering: pixelated;
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.35));
}

/* Пчёлы: перелетают через профиль, покачиваясь; половина — навстречу */
.fx--bees .fx__particle {
  opacity: 1 !important;
  animation-name: fx-fly, fx-bob;
  animation-timing-function: linear, ease-in-out;
}

.fx--bees .fx__particle:nth-child(even) {
  animation-direction: reverse, normal;
  scale: -1 1;
}

@keyframes fx-fly {
  from { left: -12%; }
  to { left: 108%; }
}

@keyframes fx-bob {
  0%, 100% { translate: 0 0; }
  25% { translate: 0 -10px; }
  75% { translate: 0 8px; }
}

/* Алмазы падают и крутятся, как выпавший предмет */
.fx--diamond_rain .fx__particle {
  animation-name: fx-fall, fx-flip;
  animation-timing-function: linear, linear;
}

@keyframes fx-flip {
  0%, 100% { scale: 1 1; }
  50% { scale: -1 1; }
}

/* Пиксельные сердца поднимаются */
.fx--pixel_hearts .fx__particle {
  top: auto !important;
  bottom: -8%;
  animation-name: fx-rise, fx-sway;
  animation-timing-function: linear, ease-in-out;
}

/* Следы лапок: появляются по очереди и тают */
.fx--cat_paws .fx__particle {
  rotate: -28deg;
  animation-name: fx-paw;
  animation-timing-function: ease-out;
}

.fx--cat_paws .fx__particle:nth-child(even) {
  rotate: -8deg;
}

@keyframes fx-paw {
  0% { opacity: 0; transform: scale(0.4); }
  6% { opacity: 0.9; transform: scale(1.1); }
  10% { transform: scale(1); }
  45% { opacity: 0.8; }
  60%, 100% { opacity: 0; }
}

/* Инферно: угли и пепел поднимаются над жаром снизу */
.fx--inferno .fx__particle {
  top: auto !important;
  bottom: -6%;
  background: linear-gradient(var(--fx-color-2), var(--fx-color));
  box-shadow: 0 0 4px var(--fx-color);
  animation-name: fx-rise, fx-sway;
  animation-timing-function: linear, ease-in-out;
}

.fx--inferno .fx__particle:nth-child(3n) {
  background: #78716c;
  box-shadow: none;
}

.fx--inferno .fx__sheet {
  background:
      radial-gradient(ellipse at 50% 120%, color-mix(in srgb, var(--fx-color) 55%, transparent), transparent 60%),
      linear-gradient(transparent 55%, rgba(127, 29, 29, 0.35));
  animation: fx-heat 2.4s ease-in-out infinite alternate;
}

@keyframes fx-heat {
  from { opacity: 0.65; transform: scaleY(1); }
  to { opacity: 1; transform: scaleY(1.05); }
}

/* Гроза: тёмное небо, дождь и удар молнии раз в несколько секунд */
.fx--lightning .fx__sheet {
  background: linear-gradient(rgba(15, 23, 42, 0.45), transparent 70%);
  animation: fx-storm 5.5s linear infinite;
}

.fx--lightning .fx__particle {
  animation-name: fx-fall;
  opacity: 0.45 !important;
  background: linear-gradient(transparent, var(--fx-color));
}

@keyframes fx-storm {
  0%, 58%, 66%, 100% { background-color: transparent; }
  60% { background-color: rgba(224, 231, 255, 0.35); }
  62% { background-color: transparent; }
  64% { background-color: rgba(224, 231, 255, 0.25); }
}

.fx__bolt {
  position: absolute;
  top: 0;
  left: 62%;

  width: 12%;
  height: 92%;

  opacity: 0;

  animation: fx-bolt 5.5s linear infinite;
}

.fx__bolt polyline {
  fill: none;
  stroke: #eef2ff;
  stroke-width: 4;
  stroke-linejoin: bevel;
  filter: drop-shadow(0 0 6px var(--fx-color)) drop-shadow(0 0 14px var(--fx-color-2));
  vector-effect: non-scaling-stroke;
}

@keyframes fx-bolt {
  0%, 58%, 63%, 100% { opacity: 0; }
  59%, 62% { opacity: 1; }
  60.5% { opacity: 0.3; }
}

/* Криппер: подкрадывается, мигает, вспышка — и клубы дыма */
.fx__hero {
  position: absolute;
  left: 50%;
  top: 50%;

  transform: translate(-50%, -50%);
}

.fx--creeper_boom .fx__hero {
  left: 80%;
  top: 72%;
  animation: fx-creeper 6s linear infinite;
}

@keyframes fx-creeper {
  0% { opacity: 0; transform: translate(40%, 30%); }
  12%, 40% { opacity: 1; transform: translate(-50%, -50%); filter: none; }
  44%, 52% { filter: brightness(3); transform: translate(-50%, -50%) scale(1.15); }
  48%, 56% { filter: none; transform: translate(-50%, -50%) scale(1); }
  58% { filter: brightness(3); transform: translate(-50%, -50%) scale(1.3); opacity: 1; }
  60%, 100% { opacity: 0; transform: translate(-50%, -50%) scale(1.6); }
}

.fx--creeper_boom .fx__sheet {
  background: radial-gradient(circle at 80% 72%, #fff 0, rgba(255, 255, 255, 0.85) 12%, rgba(254, 240, 138, 0.4) 30%, transparent 55%);
  opacity: 0;
  animation: fx-boom 6s linear infinite;
}

@keyframes fx-boom {
  0%, 58% { opacity: 0; }
  60% { opacity: 1; }
  75%, 100% { opacity: 0; }
}

.fx--creeper_boom .fx__particle {
  left: 80% !important;
  top: 72% !important;
  background: radial-gradient(circle, #f5f5f4, #a8a29e 60%, transparent 70%);
  border-radius: 50%;
  opacity: 0 !important;
  animation-name: fx-smoke;
  animation-timing-function: ease-out;
}

@keyframes fx-smoke {
  0%, 59% { opacity: 0; transform: translate(0, 0) scale(0.3); }
  62% { opacity: 0.9; }
  100% { opacity: 0; transform: translate(var(--dx, 0), -60px) scale(2.4); }
}

.fx--creeper_boom .fx__particle:nth-child(3n) { --dx: -50px; }
.fx--creeper_boom .fx__particle:nth-child(3n + 1) { --dx: 40px; }
.fx--creeper_boom .fx__particle:nth-child(3n + 2) { --dx: -10px; }

/* Тотем: вспышка, тотем взлетает, вихрь золотисто-зелёных частиц */
.fx--totem .fx__sheet {
  background: radial-gradient(circle at 50% 50%, rgba(250, 204, 21, 0.55), rgba(34, 197, 94, 0.2) 35%, transparent 60%);
  opacity: 0;
  animation: fx-totem-flash 5s ease-out infinite;
}

@keyframes fx-totem-flash {
  0%, 100% { opacity: 0; }
  4% { opacity: 1; }
  30% { opacity: 0; }
}

.fx--totem .fx__hero {
  animation: fx-totem 5s ease-out infinite;
}

@keyframes fx-totem {
  0% { opacity: 0; transform: translate(-50%, -20%) scale(0.3); }
  6% { opacity: 1; transform: translate(-50%, -50%) scale(1.4) rotate(-8deg); }
  20% { opacity: 1; transform: translate(-50%, -70%) scale(1.2) rotate(6deg); }
  34%, 100% { opacity: 0; transform: translate(-50%, -110%) scale(0.9); }
}

.fx--totem .fx__particle {
  top: auto !important;
  bottom: -6%;
  animation-name: fx-rise, fx-swirl;
  animation-timing-function: linear, ease-in-out;
}

.fx--totem .fx__particle:nth-child(even) {
  background: var(--fx-color-2);
}

@keyframes fx-swirl {
  0%, 100% { translate: -14px 0; }
  50% { translate: 14px 0; }
}

/* ---------- Премиум-эффекты ---------- */

/* Матрица: зелёные колонки символов стекают вниз, первый символ ярче */
.fx__particle--text {
  background: none;
  color: var(--fx-color);

  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-weight: 700;
  line-height: 1;
  text-shadow: 0 0 6px var(--fx-color);
  white-space: nowrap;
  writing-mode: vertical-rl;

  -webkit-mask-image: linear-gradient(transparent, #000 70%);
  mask-image: linear-gradient(transparent, #000 70%);
}

.fx--matrix .fx__particle {
  top: 0 !important;
  animation-name: fx-matrix;
}

.fx--matrix .fx__particle::after {
  content: '';
}

@keyframes fx-matrix {
  from { transform: translateY(-110%); }
  to { transform: translateY(260%); }
}

/* Варп: звёзды вытягиваются в лучи и улетают от центра */
.fx--warp .fx__sheet {
  background: radial-gradient(circle at 50% 50%, rgba(129, 140, 248, 0.35), transparent 45%);
  animation: fx-warp-core 2s ease-in-out infinite alternate;
}

@keyframes fx-warp-core {
  from { opacity: 0.5; transform: scale(0.9); }
  to { opacity: 1; transform: scale(1.1); }
}

.fx--warp .fx__particle {
  width: 2px !important;
  height: 2px !important;

  background: linear-gradient(90deg, transparent, var(--fx-color));
  border-radius: 2px;

  transform-origin: 0 50%;
  animation-name: fx-warp;
  animation-timing-function: cubic-bezier(0.5, 0, 1, 1);
}

@keyframes fx-warp {
  0% { transform: rotate(var(--a)) translateX(4px) scaleX(1); opacity: 0; }
  20% { opacity: 1; }
  100% { transform: rotate(var(--a)) translateX(380px) scaleX(70); opacity: 0.2; }
}

/* Неоновый дождь: розовые и бирюзовые струи над светящимся полом */
.fx--neon_rain .fx__particle {
  animation-name: fx-fall;
  opacity: 0.85 !important;
  background: linear-gradient(transparent, var(--fx-color));
  box-shadow: 0 0 6px var(--fx-color);
}

.fx--neon_rain .fx__particle:nth-child(even) {
  background: linear-gradient(transparent, var(--fx-color-2));
  box-shadow: 0 0 6px var(--fx-color-2);
}

.fx--neon_rain .fx__sheet {
  background:
      linear-gradient(transparent 70%, color-mix(in srgb, var(--fx-color) 30%, transparent)),
      repeating-linear-gradient(90deg, transparent 0 38px, color-mix(in srgb, var(--fx-color-2) 25%, transparent) 38px 39px);
  -webkit-mask-image: linear-gradient(transparent 60%, #000);
  mask-image: linear-gradient(transparent 60%, #000);
}

/* Галактика: туманность дрейфует, звёзды мерцают */
.fx--galaxy .fx__sheet {
  inset: -20%;
  background:
      radial-gradient(ellipse at 30% 40%, rgba(167, 139, 250, 0.35), transparent 45%),
      radial-gradient(ellipse at 70% 60%, rgba(236, 72, 153, 0.25), transparent 45%),
      radial-gradient(ellipse at 55% 30%, rgba(34, 211, 238, 0.2), transparent 40%);
  filter: blur(6px);
  animation: fx-nebula 16s ease-in-out infinite alternate;
}

@keyframes fx-nebula {
  from { transform: rotate(-4deg) scale(1); }
  to { transform: rotate(6deg) scale(1.12); }
}

.fx--galaxy .fx__particle {
  border-radius: 50%;
  box-shadow: 0 0 4px #fff;
  animation-name: fx-twinkle;
  animation-timing-function: ease-in-out;
}

/* Сбой системы: полосы помех с RGB-сдвигом время от времени */
.fx__glitch {
  position: absolute;
  left: 0;
  right: 0;

  height: calc(4% + var(--n) * 2%);

  background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--fx-color) 60%, transparent) 30%, color-mix(in srgb, var(--fx-color-2) 60%, transparent) 70%, transparent);
  mix-blend-mode: screen;
  opacity: 0;

  animation: fx-glitch 3.2s steps(1) infinite;
  animation-delay: calc(var(--n) * -0.7s);
}

@keyframes fx-glitch {
  0%, 30%, 34%, 70%, 73%, 100% { opacity: 0; }
  31% { opacity: 1; top: 18%; transform: translateX(-12px); }
  33% { opacity: 1; top: 56%; transform: translateX(16px); }
  71% { opacity: 0.8; top: 80%; transform: translateX(-6px); }
}

.fx--glitch .fx__sheet {
  background: repeating-linear-gradient(transparent 0 3px, rgba(255, 255, 255, 0.035) 3px 4px);
}

/* Конфетти: кусочки бумаги кувыркаются в воздухе */
.fx--confetti .fx__particle {
  height: auto !important;
  aspect-ratio: 1 / 0.5;
  opacity: 0.95 !important;
  border-radius: 1px;
  animation-name: fx-fall, fx-tumble;
  animation-timing-function: linear, linear;
}

@keyframes fx-tumble {
  0% { transform: rotate3d(1, 0.4, 0.2, 0deg); }
  100% { transform: rotate3d(1, 0.4, 0.2, 720deg); }
}

/* Светлячки: мягкий свет, блуждают и мигают */
.fx--fireflies .fx__particle {
  border-radius: 50%;
  background: radial-gradient(circle, #fff, var(--fx-color) 40%, transparent 70%);
  box-shadow: 0 0 10px 3px color-mix(in srgb, var(--fx-color) 60%, transparent);
  animation-name: fx-wander, fx-blink;
  animation-timing-function: ease-in-out, ease-in-out;
  animation-direction: alternate, normal;
}

@keyframes fx-wander {
  from { translate: 0 0; }
  to { translate: var(--dx) var(--dy); }
}

@keyframes fx-blink {
  0%, 100% { opacity: 0.2; }
  40%, 60% { opacity: 1; }
}

/* Боке: большие размытые огни медленно плывут */
.fx--bokeh .fx__particle {
  height: auto !important;
  aspect-ratio: 1;
  border-radius: 50%;
  filter: blur(6px);
  opacity: 0.35 !important;
  mix-blend-mode: screen;
  animation-name: fx-rise;
}

@media (prefers-reduced-motion: reduce) {
  .fx__particle,
  .fx__sheet,
  .fx__hero,
  .fx__bolt,
  .fx__glitch {
    animation: none !important;
  }
}
</style>
