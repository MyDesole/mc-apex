<script setup>
/**
 * 3D-модель игрока по его скину из Minecraft.
 *
 * Модель медленно вращается и «дышит»; её можно крутить мышью или
 * пальцем. Клик переключает ходьбу. three.js тяжёлый, поэтому
 * skinview3d подгружается отдельным чанком только здесь.
 * Без WebGL показываем плоскую фигуру спереди.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { renderSkinFront } from '@/utils/minecraftSkin.js'

const props = defineProps({
  // { url, model, image } из loadSkin()
  skin: { type: Object, required: true },
  // Подпись над головой
  nameTag: { type: String, default: null },
})

const host = ref(null)
const canvasRef = ref(null)
const fallbackRef = ref(null)
const failed = ref(false)
const walking = ref(false)

let viewer = null
let lib = null
let resizeObserver = null

const reducedMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

async function mount() {
  try {
    lib = await import('skinview3d')

    const { width, height } = host.value.getBoundingClientRect()

    viewer = new lib.SkinViewer({
      canvas: canvasRef.value,
      width: Math.max(1, Math.round(width)),
      height: Math.max(1, Math.round(height)),
      skin: props.skin.url,
      model: props.skin.model === 'slim' ? 'slim' : 'default',
      // Нужен, чтобы карточка игрока могла снять кадр с модели
      preserveDrawingBuffer: true,
    })

    viewer.background = null
    viewer.fov = 40
    viewer.zoom = 0.72
    viewer.globalLight.intensity = 2.6
    viewer.cameraLight.intensity = 0.7

    viewer.controls.enableZoom = false
    viewer.controls.enablePan = false

    viewer.autoRotate = !reducedMotion
    viewer.autoRotateSpeed = 0.6

    viewer.animation = reducedMotion ? null : new lib.IdleAnimation()

    if (props.nameTag) {
      viewer.nameTag = new lib.NameTagObject(props.nameTag, { textStyle: 'white' })
    }

    // Пока игрок крутит модель — авто-вращение не мешает
    viewer.controls.addEventListener('start', () => {
      viewer.autoRotate = false
    })

    resizeObserver = new ResizeObserver(([entry]) => {
      const box = entry.contentRect

      if (viewer && box.width > 0 && box.height > 0) {
        viewer.setSize(Math.round(box.width), Math.round(box.height))
      }
    })

    resizeObserver.observe(host.value)
  } catch {
    failed.value = true
    drawFallback()
  }
}

function drawFallback() {
  if (!fallbackRef.value || !props.skin?.image) return

  const figure = renderSkinFront(props.skin.image, { model: props.skin.model, scale: 8 })

  fallbackRef.value.replaceChildren(figure)
}

function toggleWalk() {
  if (!viewer || !lib || reducedMotion) return

  walking.value = !walking.value
  viewer.animation = walking.value ? new lib.WalkingAnimation() : new lib.IdleAnimation()

  if (walking.value) viewer.animation.speed = 0.7
}

/** Кадр модели для карточки игрока. */
function snapshot() {
  if (!viewer) return null

  viewer.render()

  return viewer.canvas.toDataURL('image/png')
}

defineExpose({ snapshot })

watch(() => props.skin, async (skin) => {
  if (!skin) return

  if (viewer) {
    await viewer.loadSkin(skin.url, { model: skin.model === 'slim' ? 'slim' : 'default' })
  } else if (failed.value) {
    drawFallback()
  }
})

onMounted(mount)

onUnmounted(() => {
  resizeObserver?.disconnect()
  viewer?.dispose()
  viewer = null
})
</script>

<template>
  <div
      ref="host"
      class="skin-viewer"
      :class="{ 'skin-viewer--flat': failed }"
  >
    <canvas
        v-show="!failed"
        ref="canvasRef"
        class="skin-viewer__canvas"
        :aria-label="nameTag ? `3D-модель скина ${nameTag}` : '3D-модель скина'"
        role="img"
        @dblclick="toggleWalk"
    />

    <div v-if="failed" ref="fallbackRef" class="skin-viewer__flat" />

    <span v-if="!failed && !reducedMotion" class="skin-viewer__hint">
      Тяните, чтобы повернуть · двойной клик — {{ walking ? 'стоять' : 'идти' }}
    </span>
  </div>
</template>

<style scoped>
.skin-viewer {
  position: relative;

  width: 100%;
  height: 100%;
  min-height: 280px;
}

.skin-viewer__canvas {
  display: block;

  width: 100%;
  height: 100%;

  cursor: grab;
  touch-action: none;
}

.skin-viewer__canvas:active {
  cursor: grabbing;
}

.skin-viewer__flat {
  height: 100%;

  display: flex;
  align-items: center;
  justify-content: center;
}

.skin-viewer__flat :deep(canvas) {
  height: 86%;
  width: auto;

  image-rendering: pixelated;
}

.skin-viewer__hint {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 8px;

  color: var(--text-muted);

  font-size: 12px;
  text-align: center;

  pointer-events: none;

  opacity: 0;
  transition: opacity 0.2s ease;
}

.skin-viewer:hover .skin-viewer__hint {
  opacity: 1;
}
</style>
