<script setup>
/**
 * След курсора (магазин, тип cursor_trail): гость водит мышью по
 * профилю — за курсором остаются искры, сердечки, огонь и прочее.
 *
 * Слой кладётся поверх родителя и не ловит клики; частицы создаются
 * напрямую в DOM и удаляются по окончании анимации — так быстрее,
 * чем реактивный список. В превью магазина (auto) курсор «водит» сам.
 */
import { onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  trail: { type: String, default: null },
  // Превью: виртуальный курсор описывает восьмёрку
  auto: { type: Boolean, default: false },
})

const layer = ref(null)

const GLYPHS = 'アイウエオカキクケコ0123456789ΣΛ'
const RAINBOW = ['#ff3d81', '#ffb03d', '#f6ff3d', '#3dffa0', '#3dd9ff', '#a855f7']

let host = null
let last = { x: -999, y: -999, t: 0 }
let frame = null
let hue = 0
let count = 0

function spawn(x, y) {
  const el = document.createElement('span')
  el.className = `trail trail--${props.trail}`

  const size = 6 + Math.random() * 8
  el.style.left = `${x}px`
  el.style.top = `${y}px`
  el.style.setProperty('--size', `${size}px`)
  el.style.setProperty('--dx', `${(Math.random() - 0.5) * 40}px`)
  el.style.setProperty('--dy', `${(Math.random() - 0.2) * 40}px`)
  el.style.setProperty('--rot', `${Math.random() * 360}deg`)

  if (props.trail === 'rainbow') {
    el.style.setProperty('--c', RAINBOW[hue++ % RAINBOW.length])
  }

  if (props.trail === 'matrix') {
    el.textContent = GLYPHS[Math.floor(Math.random() * GLYPHS.length)]
  }

  if (props.trail === 'snow') {
    el.textContent = '❄'
  }

  el.addEventListener('animationend', () => el.remove(), { once: true })
  layer.value?.appendChild(el)

  // Не даём частицам копиться, если мышь носится как бешеная
  if (++count % 60 === 0) {
    const nodes = layer.value?.children
    while (nodes && nodes.length > 80) nodes[0].remove()
  }
}

function onMove(event) {
  if (!layer.value) return

  const rect = layer.value.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  const now = performance.now()

  // Частица не чаще раза в 16 мс и не ближе 6 px к прошлой
  if (now - last.t < 16 || Math.hypot(x - last.x, y - last.y) < 6) return

  last = { x, y, t: now }
  spawn(x, y)
}

/** Превью: курсор сам рисует восьмёрку по превью. */
function autoLoop(time) {
  if (!layer.value) return

  const { width, height } = layer.value.getBoundingClientRect()
  const t = time / 900
  const x = width / 2 + Math.sin(t) * width * 0.36
  const y = height / 2 + Math.sin(t * 2) * height * 0.28

  if (time - last.t > 45) {
    last = { x, y, t: time }
    spawn(x, y)
  }

  frame = requestAnimationFrame(autoLoop)
}

onMounted(() => {
  if (!props.trail) return
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return

  if (props.auto) {
    frame = requestAnimationFrame(autoLoop)
    return
  }

  host = layer.value?.parentElement
  host?.addEventListener('pointermove', onMove)
})

onUnmounted(() => {
  host?.removeEventListener('pointermove', onMove)
  cancelAnimationFrame(frame)
})
</script>

<template>
  <div v-if="trail" ref="layer" class="trail-layer" aria-hidden="true"></div>
</template>

<style>
/* Без scoped: частицы создаются вручную и не получают scoped-атрибут */
.trail-layer {
  position: absolute;
  inset: 0;
  z-index: 35;

  overflow: hidden;
  border-radius: inherit;

  pointer-events: none;
}

.trail {
  position: absolute;

  width: var(--size);
  height: var(--size);

  margin: calc(var(--size) / -2) 0 0 calc(var(--size) / -2);

  animation: trail-fade 0.9s ease-out forwards;
}

@keyframes trail-fade {
  0% { opacity: 1; transform: translate(0, 0) scale(1) rotate(0); }
  100% { opacity: 0; transform: translate(var(--dx), var(--dy)) scale(0.2) rotate(var(--rot)); }
}

/* Искры: золотые четырёхлучевые звёздочки */
.trail--sparkles {
  background: linear-gradient(135deg, #fff, #fde047 50%, #f59e0b);
  clip-path: polygon(50% 0, 62% 38%, 100% 50%, 62% 62%, 50% 100%, 38% 62%, 0 50%, 38% 38%);
  filter: drop-shadow(0 0 3px #fde047);
}

/* Сердечки: всплывают вверх */
.trail--hearts {
  --size: 14px !important;

  background: linear-gradient(#fda4af, #f43f5e);
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 21s-7.5-4.6-10-9.3C.3 8.4 2.2 4.5 6 4.5c2.2 0 3.6 1.2 4.5 2.6.9-1.4 2.3-2.6 4.5-2.6 3.8 0 5.7 3.9 4 7.2C19.5 16.4 12 21 12 21z'/%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 21s-7.5-4.6-10-9.3C.3 8.4 2.2 4.5 6 4.5c2.2 0 3.6 1.2 4.5 2.6.9-1.4 2.3-2.6 4.5-2.6 3.8 0 5.7 3.9 4 7.2C19.5 16.4 12 21 12 21z'/%3E%3C/svg%3E") center / contain no-repeat;
  animation-name: trail-rise;
  animation-duration: 1.1s;
}

@keyframes trail-rise {
  0% { opacity: 1; transform: translate(0, 0) scale(0.6); }
  30% { transform: translate(calc(var(--dx) * 0.3), -10px) scale(1); }
  100% { opacity: 0; transform: translate(var(--dx), -50px) scale(0.8); }
}

/* Огонь: язычки пламени поднимаются и гаснут */
.trail--fire {
  --size: 16px !important;

  background: radial-gradient(circle at 50% 70%, #fef9c3 0, #fde047 25%, #f97316 55%, rgba(220, 38, 38, 0) 75%);
  border-radius: 50% 50% 45% 45% / 60% 60% 40% 40%;
  mix-blend-mode: screen;
  animation-name: trail-flame;
  animation-duration: 0.7s;
}

@keyframes trail-flame {
  0% { opacity: 1; transform: translate(0, 0) scale(1); }
  100% { opacity: 0; transform: translate(calc(var(--dx) * 0.3), -36px) scale(0.3, 0.8); }
}

/* Радуга: круглые мазки, цвет по кругу */
.trail--rainbow {
  --size: 14px !important;

  background: var(--c);
  border-radius: 50%;
  box-shadow: 0 0 10px var(--c);
  animation-name: trail-shrink;
  animation-duration: 0.8s;
}

@keyframes trail-shrink {
  0% { opacity: 0.95; transform: scale(1); }
  100% { opacity: 0; transform: scale(0.1); }
}

/* Звездопад: крупные звёзды разлетаются и вращаются */
.trail--stars {
  --size: 16px !important;

  background: linear-gradient(135deg, #fff, #c4b5fd 60%, #818cf8);
  clip-path: polygon(50% 0, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
  filter: drop-shadow(0 0 4px #c4b5fd);
  animation-duration: 1.2s;
}

/* Пузыри: прозрачные кольца всплывают */
.trail--bubbles {
  --size: 14px !important;

  background: radial-gradient(circle at 32% 30%, rgba(255, 255, 255, 0.9) 0 14%, transparent 18%);
  border: 1.5px solid rgba(186, 230, 253, 0.85);
  border-radius: 50%;
  animation-name: trail-rise;
  animation-duration: 1.3s;
}

/* Код: зелёные символы осыпаются вниз */
.trail--matrix,
.trail--snow {
  width: auto;
  height: auto;

  font-family: ui-monospace, monospace;
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
}

.trail--matrix {
  color: #4ade80;
  text-shadow: 0 0 6px #22c55e;
  animation-name: trail-drop;
  animation-duration: 1s;
}

@keyframes trail-drop {
  0% { opacity: 1; transform: translateY(0); }
  100% { opacity: 0; transform: translateY(40px); }
}

/* Снежинки: кружатся и тают */
.trail--snow {
  color: #e0f2fe;
  text-shadow: 0 0 6px #7dd3fc;
  animation-name: trail-snow;
  animation-duration: 1.4s;
}

@keyframes trail-snow {
  0% { opacity: 1; transform: translate(0, 0) rotate(0); }
  100% { opacity: 0; transform: translate(var(--dx), 40px) rotate(var(--rot)); }
}
</style>
