<script setup>
/**
 * Зимнее оформление: падающий снег и ёлочки по углам.
 *
 * Включается само с 1 декабря по 2 января включительно. Игрок может выключить
 * украшения: выбор хранится в браузере (apex:winter = off). Снег рисуется на
 * canvas поверх страницы, не мешает кликам и не крутится, когда вкладка
 * скрыта или включено «уменьшить анимацию» в системе.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'

const STORAGE_KEY = 'apex:winter'

function inSeason(now = new Date()) {
  const m = now.getMonth() + 1
  const d = now.getDate()
  // с 1 декабря по 2 января включительно
  return m === 12 || (m === 1 && d <= 2)
}

function allowed() {
  try {
    return localStorage.getItem(STORAGE_KEY) !== 'off'
  } catch {
    return true
  }
}

const active = ref(inSeason() && allowed())
const canvas = ref(null)

let ctx = null
let flakes = []
let raf = 0
let w = 0
let h = 0
let reduced = false

function makeFlake(randomY = true) {
  return {
    x: Math.random() * w,
    y: randomY ? Math.random() * h : -10,
    r: 0.8 + Math.random() * 2.2,
    v: 0.35 + Math.random() * 0.9,
    sway: Math.random() * Math.PI * 2,
    swaySpeed: 0.004 + Math.random() * 0.01,
    alpha: 0.35 + Math.random() * 0.5,
  }
}

function resize() {
  if (!canvas.value) return

  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  w = window.innerWidth
  h = window.innerHeight
  canvas.value.width = Math.floor(w * dpr)
  canvas.value.height = Math.floor(h * dpr)
  canvas.value.style.width = `${w}px`
  canvas.value.style.height = `${h}px`
  ctx = canvas.value.getContext('2d')
  if (!ctx) return   // canvas недоступен (старый браузер, тестовая среда): без снега, ёлочки остаются
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)

  // на узком экране снега меньше: не нагружаем телефон
  const count = Math.round(Math.min(110, Math.max(30, (w * h) / 16000)))
  while (flakes.length < count) flakes.push(makeFlake())
  flakes.length = count
}

function frame() {
  raf = requestAnimationFrame(frame)

  if (document.hidden || !ctx) return

  ctx.clearRect(0, 0, w, h)
  ctx.fillStyle = '#ffffff'

  for (const f of flakes) {
    f.sway += f.swaySpeed
    f.y += f.v
    f.x += Math.sin(f.sway) * 0.35

    if (f.y > h + 6) Object.assign(f, makeFlake(false))
    if (f.x < -6) f.x = w + 6
    if (f.x > w + 6) f.x = -6

    ctx.globalAlpha = f.alpha
    ctx.beginPath()
    ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2)
    ctx.fill()
  }

  ctx.globalAlpha = 1
}

onMounted(() => {
  if (!active.value) return

  reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

  resize()
  window.addEventListener('resize', resize)

  if (reduced) {
    // без анимации: один статичный кадр со снежинками
    frame()
    cancelAnimationFrame(raf)
  } else {
    raf = requestAnimationFrame(frame)
  }
})

onBeforeUnmount(() => {
  cancelAnimationFrame(raf)
  window.removeEventListener('resize', resize)
})
</script>

<template>
  <div v-if="active" class="winter" aria-hidden="true">
    <canvas ref="canvas" class="winter__snow" />

    <!-- ёлочки по нижним углам -->
    <svg class="winter__tree winter__tree--left" viewBox="0 0 120 180" fill="none">
      <rect x="54" y="150" width="12" height="26" rx="2" fill="#5b3a29" />
      <path d="M60 8 L92 56 H76 L104 98 H84 L116 148 H4 L36 98 H16 L44 56 H28 Z" fill="#14532d" />
      <path d="M60 8 L92 56 H76 L104 98 H84 L116 148 H60 Z" fill="#166534" opacity=".75" />
      <path d="M44 56 H76 L66 70 L58 62 L50 70 Z" fill="#fff" opacity=".9" />
      <path d="M32 98 H84 L74 112 L62 104 L50 112 L40 104 Z" fill="#fff" opacity=".9" />
      <path d="M14 148 H106 L96 160 L84 152 L70 160 L58 152 L44 160 L32 152 L22 160 Z" fill="#fff" opacity=".9" />
      <circle cx="60" cy="8" r="5" fill="#fde047" />
      <circle cx="46" cy="76" r="3.2" fill="#ef4444" />
      <circle cx="74" cy="92" r="3.2" fill="#60a5fa" />
      <circle cx="40" cy="124" r="3.2" fill="#fbbf24" />
      <circle cx="82" cy="132" r="3.2" fill="#ef4444" />
    </svg>

    <svg class="winter__tree winter__tree--right" viewBox="0 0 120 180" fill="none">
      <rect x="54" y="150" width="12" height="26" rx="2" fill="#5b3a29" />
      <path d="M60 8 L92 56 H76 L104 98 H84 L116 148 H4 L36 98 H16 L44 56 H28 Z" fill="#14532d" />
      <path d="M60 8 L92 56 H76 L104 98 H84 L116 148 H60 Z" fill="#166534" opacity=".75" />
      <path d="M44 56 H76 L66 70 L58 62 L50 70 Z" fill="#fff" opacity=".9" />
      <path d="M32 98 H84 L74 112 L62 104 L50 112 L40 104 Z" fill="#fff" opacity=".9" />
      <path d="M14 148 H106 L96 160 L84 152 L70 160 L58 152 L44 160 L32 152 L22 160 Z" fill="#fff" opacity=".9" />
      <circle cx="60" cy="8" r="5" fill="#fde047" />
      <circle cx="74" cy="76" r="3.2" fill="#60a5fa" />
      <circle cx="46" cy="92" r="3.2" fill="#ef4444" />
      <circle cx="80" cy="124" r="3.2" fill="#fbbf24" />
      <circle cx="38" cy="132" r="3.2" fill="#60a5fa" />
    </svg>
  </div>
</template>

<style scoped>
.winter {
  position: fixed;
  inset: 0;
  z-index: 30;
  pointer-events: none;
  overflow: hidden;
}

.winter__snow {
  position: absolute;
  inset: 0;
}

.winter__tree {
  position: absolute;
  bottom: 0;
  width: 92px;
  height: auto;
  opacity: 0.92;
  filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.35));
}

.winter__tree--left {
  left: 6px;
}

.winter__tree--right {
  right: 6px;
  transform: scaleX(-1);
}

/* на телефоне ёлочки не нужны: экран и так тесный */
@media (max-width: 720px) {
  .winter__tree {
    display: none;
  }
}
</style>
