<script setup>
/**
 * Просмотр картинок поверх страницы.
 *
 * Раньше картинка форума открывалась ссылкой в новой вкладке — сайт
 * терялся из виду, а листать картинки поста было нельзя.
 *
 * Листание: стрелки на экране и на клавиатуре, свайп на телефоне.
 * Увеличение: клик или двойной тап по картинке (в точку клика), колесо
 * мыши; увеличенную картинку можно двигать. Закрытие: Esc, крестик,
 * клик по фону.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  // [{ url, name }]
  images: { type: Array, required: true },
  start: { type: Number, default: 0 },
})

const emit = defineEmits(['close'])

const index = ref(Math.min(Math.max(0, props.start), Math.max(0, props.images.length - 1)))
const scale = ref(1)
const offset = ref({ x: 0, y: 0 })
const loaded = ref(false)

// Идёт перетаскивание увеличенной картинки
const panning = ref(false)

const MAX_SCALE = 4
const ZOOM_STEP = 2.2

const current = computed(() => props.images[index.value] ?? null)
const many = computed(() => props.images.length > 1)
const zoomed = computed(() => scale.value > 1.01)

const imageStyle = computed(() => ({
  transform: `translate(${offset.value.x}px, ${offset.value.y}px) scale(${scale.value})`,
  cursor: zoomed.value ? (panning.value ? 'grabbing' : 'grab') : 'zoom-in',
  transition: panning.value ? 'none' : 'transform 0.18s ease',
}))

function resetZoom() {
  scale.value = 1
  offset.value = { x: 0, y: 0 }
}

function go(step) {
  if (!many.value) return

  index.value = (index.value + step + props.images.length) % props.images.length
}

watch(index, () => {
  resetZoom()
  loaded.value = false
})

/* ----------------------------- Увеличение ----------------------------- */

const imgEl = ref(null)

/** Увеличить в точку (x, y) экрана — туда, куда кликнули. */
function zoomAt(nextScale, x, y) {
  const el = imgEl.value

  if (!el) return

  nextScale = Math.min(MAX_SCALE, Math.max(1, nextScale))

  if (nextScale === 1) {
    resetZoom()
    return
  }

  // Точка под курсором остаётся на месте
  const rect = el.getBoundingClientRect()
  const cx = rect.left + rect.width / 2
  const cy = rect.top + rect.height / 2
  const ratio = nextScale / scale.value

  offset.value = {
    x: offset.value.x - (x - cx) * (ratio - 1),
    y: offset.value.y - (y - cy) * (ratio - 1),
  }
  scale.value = nextScale
}

let moved = false

function onImageClick(event) {
  // Конец перетаскивания — не клик
  if (moved) return

  if (zoomed.value) resetZoom()
  else zoomAt(ZOOM_STEP, event.clientX, event.clientY)
}

function onWheel(event) {
  event.preventDefault()
  zoomAt(scale.value * (event.deltaY < 0 ? 1.25 : 0.8), event.clientX, event.clientY)
}

/* ----------------------------- Перетаскивание ----------------------------- */

let panStart = { x: 0, y: 0, ox: 0, oy: 0 }

function onPointerDown(event) {
  if (!zoomed.value || event.pointerType === 'touch') return

  panning.value = true
  moved = false
  panStart = { x: event.clientX, y: event.clientY, ox: offset.value.x, oy: offset.value.y }
  event.currentTarget.setPointerCapture?.(event.pointerId)
}

function onPointerMove(event) {
  if (!panning.value) return

  const dx = event.clientX - panStart.x
  const dy = event.clientY - panStart.y

  if (Math.abs(dx) + Math.abs(dy) > 4) moved = true

  offset.value = { x: panStart.ox + dx, y: panStart.oy + dy }
}

function onPointerUp() {
  panning.value = false
  // Клик после перетаскивания не должен сбрасывать увеличение
  setTimeout(() => { moved = false }, 0)
}

/* ----------------------------- Касания ----------------------------- */

let touch = null
let lastTap = 0

function onTouchStart(event) {
  if (event.touches.length !== 1) return

  const t = event.touches[0]

  touch = { x: t.clientX, y: t.clientY, ox: offset.value.x, oy: offset.value.y, at: Date.now() }
}

function onTouchMove(event) {
  if (!touch || event.touches.length !== 1) return

  const t = event.touches[0]

  // Увеличенную картинку пальцем двигают, а не листают
  if (zoomed.value) {
    event.preventDefault()
    offset.value = { x: touch.ox + t.clientX - touch.x, y: touch.oy + t.clientY - touch.y }
  }
}

function onTouchEnd(event) {
  if (!touch) return

  const t = event.changedTouches[0]
  const dx = t.clientX - touch.x
  const dy = t.clientY - touch.y
  const quick = Date.now() - touch.at < 300

  touch = null

  if (zoomed.value) return

  // Свайп в сторону — следующая картинка, вниз — закрыть
  if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
    go(dx < 0 ? 1 : -1)
  } else if (dy > 90 && Math.abs(dy) > Math.abs(dx) * 1.5) {
    emit('close')
  } else if (quick && Math.abs(dx) < 10 && Math.abs(dy) < 10) {
    // Двойной тап — увеличить
    const now = Date.now()

    if (now - lastTap < 300) zoomAt(ZOOM_STEP, t.clientX, t.clientY)

    lastTap = now
  }
}

/* ----------------------------- Клавиатура ----------------------------- */

function onKey(event) {
  if (event.key === 'Escape') emit('close')
  else if (event.key === 'ArrowRight') go(1)
  else if (event.key === 'ArrowLeft') go(-1)
}

const closeBtn = ref(null)

onMounted(() => {
  window.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
  nextTick(() => closeBtn.value?.focus())
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
        class="lightbox"
        role="dialog"
        aria-modal="true"
        :aria-label="current?.name || 'Просмотр картинки'"
        @click.self="emit('close')"
    >
      <header class="lightbox__top">
        <span class="lightbox__name">{{ current?.name }}</span>
        <span v-if="many" class="lightbox__count">{{ index + 1 }} / {{ images.length }}</span>

        <a
            v-if="current"
            class="lightbox__btn"
            :href="current.url"
            target="_blank"
            rel="noopener"
            title="Открыть оригинал"
            aria-label="Открыть оригинал"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /></svg>
        </a>

        <button ref="closeBtn" type="button" class="lightbox__btn" title="Закрыть (Esc)" aria-label="Закрыть" @click="emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </header>

      <div
          class="lightbox__stage"
          @click.self="emit('close')"
          @wheel="onWheel"
          @touchstart.passive="onTouchStart"
          @touchmove="onTouchMove"
          @touchend="onTouchEnd"
      >
        <span v-if="!loaded" class="lightbox__spinner" aria-hidden="true"></span>

        <img
            v-if="current"
            ref="imgEl"
            :key="current.url"
            class="lightbox__img"
            :class="{ 'is-loaded': loaded }"
            :src="current.url"
            :alt="current.name"
            :style="imageStyle"
            draggable="false"
            @load="loaded = true"
            @error="loaded = true"
            @click.stop="onImageClick"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
        >
      </div>

      <template v-if="many">
        <button type="button" class="lightbox__nav lightbox__nav--prev" aria-label="Предыдущая" @click.stop="go(-1)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        </button>
        <button type="button" class="lightbox__nav lightbox__nav--next" aria-label="Следующая" @click.stop="go(1)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
        </button>

        <div class="lightbox__thumbs">
          <button
              v-for="(image, i) in images"
              :key="image.url + i"
              type="button"
              class="lightbox__thumb"
              :class="{ 'is-on': i === index }"
              :aria-label="`Картинка ${i + 1}`"
              @click.stop="index = i"
          >
            <img :src="image.url" :alt="image.name" loading="lazy">
          </button>
        </div>
      </template>

      <p class="lightbox__hint">
        {{ zoomed ? 'Перетащи, чтобы рассмотреть · клик — уменьшить' : 'Клик — увеличить · Esc — закрыть' }}
      </p>
    </div>
  </Teleport>
</template>

<style scoped>
.lightbox {
  position: fixed;
  inset: 0;
  z-index: 2000;

  display: flex;
  flex-direction: column;

  color: #ede9f6;

  background: rgba(6, 4, 12, 0.94);

  animation: lightbox-in 0.16s ease;
}

@keyframes lightbox-in {
  from { opacity: 0; }
}

.lightbox__top {
  position: relative;
  z-index: 2;

  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
}

.lightbox__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;

  font-size: 13px;
  white-space: nowrap;
  text-overflow: ellipsis;
  opacity: 0.75;
}

.lightbox__count {
  font-size: 13px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.lightbox__btn {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 40px;
  height: 40px;

  color: inherit;

  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 8px;

  cursor: pointer;
}

.lightbox__btn:hover {
  background: rgba(255, 255, 255, 0.16);
}

.lightbox__stage {
  position: relative;

  display: grid;
  flex: 1;
  place-items: center;
  min-height: 0;
  overflow: hidden;
  padding: 0 64px;

  touch-action: none;
}

.lightbox__img {
  max-width: 100%;
  max-height: 100%;

  object-fit: contain;
  opacity: 0;
  user-select: none;
  -webkit-user-drag: none;

  will-change: transform;
}

.lightbox__img.is-loaded {
  opacity: 1;
}

.lightbox__spinner {
  position: absolute;

  width: 32px;
  height: 32px;

  border: 3px solid rgba(255, 255, 255, 0.15);
  border-top-color: #a78bfa;
  border-radius: 50%;

  animation: lightbox-spin 0.8s linear infinite;
}

@keyframes lightbox-spin {
  to { transform: rotate(360deg); }
}

.lightbox__nav {
  position: absolute;
  top: 50%;
  z-index: 2;

  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;

  color: inherit;

  background: rgba(20, 14, 32, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 8px;

  cursor: pointer;
  transform: translateY(-50%);
}

.lightbox__nav:hover {
  background: rgba(124, 58, 237, 0.4);
}

.lightbox__nav--prev { left: 12px; }
.lightbox__nav--next { right: 12px; }

.lightbox__thumbs {
  display: flex;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px 0;
  overflow-x: auto;
}

.lightbox__thumb {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
  padding: 0;
  overflow: hidden;

  background: none;
  border: 2px solid transparent;
  border-radius: 6px;
  opacity: 0.55;

  cursor: pointer;
}

.lightbox__thumb.is-on {
  border-color: #a78bfa;
  opacity: 1;
}

.lightbox__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.lightbox__hint {
  margin: 0;
  padding: 10px 16px 14px;

  font-size: 12px;
  text-align: center;
  opacity: 0.5;
}

@media (max-width: 640px) {
  .lightbox__stage {
    padding: 0 8px;
  }

  .lightbox__nav {
    top: auto;
    bottom: 96px;
    transform: none;
  }

  .lightbox__hint {
    display: none;
  }
}
</style>
