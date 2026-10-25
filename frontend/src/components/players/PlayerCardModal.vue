<script setup>
/**
 * Карточка игрока для соцсетей и Discord.
 *
 * Тир-блок, процент, место в рейтинге, фигура по скину Minecraft и
 * пять аспектов. Карточку можно скачать PNG или скопировать картинкой
 * прямо в буфер — вставляется в Discord и Telegram одним Ctrl+V.
 */
import { computed, onUnmounted, ref, watch } from 'vue'

import { loadSkin } from '@/utils/minecraftSkin.js'
import { ensureCardFonts, FIGURE_FRAME, renderPlayerCard } from '@/utils/playerCardRenderer.js'
import { renderSkin3d } from '@/utils/skinRender3d.js'
import { playerAspects } from '@/data/players/aspects.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  user: { type: Object, required: true },
  rank: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const preview = ref(null)
const status = ref('')
const rendering = ref(false)

let canvas = null
let statusTimer = null

const canCopyImage = typeof window !== 'undefined'
    && 'ClipboardItem' in window
    && Boolean(navigator.clipboard?.write)

const fileName = computed(() =>
    `apex-${String(props.user?.username || 'player').replace(/[^\w.-]+/g, '_')}.png`
)

function close() {
  emit('update:modelValue', false)
}

function loadAvatar(url) {
  if (!url) return Promise.resolve(null)

  return new Promise((resolve) => {
    const image = new Image()
    // Без CORS картинка «испачкает» холст, и PNG не сохранится
    image.crossOrigin = 'anonymous'
    image.onload = () => resolve(image)
    image.onerror = () => resolve(null)
    image.src = url
  })
}

async function render() {
  rendering.value = true
  preview.value = null

  try {
    const [skin, avatar] = await Promise.all([
      loadSkin(props.user.id),
      loadAvatar(props.user.avatar_url),
      ensureCardFonts(),
    ])

    // 3D-кадр со вторым слоем; без WebGL карточка нарисует плоскую фигуру
    const figure = skin ? await renderSkin3d(skin, FIGURE_FRAME) : null

    canvas = renderPlayerCard({
      user: props.user,
      rank: props.rank,
      aspects: playerAspects(props.user),
      skin,
      figure,
      avatar,
    })

    preview.value = canvas.toDataURL('image/png')
  } catch {
    flash('Не удалось собрать карточку')
  } finally {
    rendering.value = false
  }
}

function toBlob() {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('empty'))), 'image/png')
  })
}

async function download() {
  if (!canvas) return

  const url = URL.createObjectURL(await toBlob())
  const link = document.createElement('a')

  link.href = url
  link.download = fileName.value
  link.click()

  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

async function copyImage() {
  if (!canvas || !canCopyImage) return

  try {
    await navigator.clipboard.write([new ClipboardItem({ 'image/png': toBlob() })])
    flash('Картинка скопирована — вставьте её в Discord или Telegram')
  } catch {
    flash('Браузер не дал скопировать картинку — скачайте PNG')
  }
}

async function copyLink() {
  const url = new URL(userLink(props.user), window.location.origin).toString()

  try {
    await navigator.clipboard.writeText(url)
    flash('Ссылка на профиль скопирована')
  } catch {
    flash(url)
  }
}

function flash(message) {
  status.value = message
  clearTimeout(statusTimer)
  statusTimer = setTimeout(() => {
    status.value = ''
  }, 3500)
}

function onKeydown(event) {
  if (event.key === 'Escape') close()
}

watch(() => props.modelValue, (open) => {
  if (open) {
    render()
    window.addEventListener('keydown', onKeydown)
  } else {
    window.removeEventListener('keydown', onKeydown)
  }
}, { immediate: true })

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(statusTimer)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="card-modal">
      <div
          v-if="modelValue"
          class="card-modal"
          @mousedown.self="close"
      >
        <div
            class="card-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-label="Карточка игрока"
        >
          <header class="card-modal__head">
            <h2>Карточка игрока</h2>

            <button
                type="button"
                class="card-modal__close"
                aria-label="Закрыть"
                @click="close"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round" />
              </svg>
            </button>
          </header>

          <div class="card-modal__body">
            <div class="card-modal__preview">
              <img
                  v-if="preview"
                  :src="preview"
                  :alt="`Карточка игрока ${user.username}`"
              />
              <div v-else class="card-modal__placeholder">
                {{ rendering ? 'Собираем карточку…' : '' }}
              </div>
            </div>

            <div class="card-modal__side">
              <p class="card-modal__text">
                Тир, место в рейтинге, аспекты и скин — одной картинкой.
                Подходит для Discord, Telegram и сторис.
              </p>

              <button
                  type="button"
                  class="btn btn-primary card-modal__action"
                  :disabled="!preview"
                  @click="download"
              >
                Скачать PNG
              </button>

              <button
                  v-if="canCopyImage"
                  type="button"
                  class="btn btn-secondary card-modal__action"
                  :disabled="!preview"
                  @click="copyImage"
              >
                Скопировать картинку
              </button>

              <button
                  type="button"
                  class="btn btn-secondary card-modal__action"
                  @click="copyLink"
              >
                Скопировать ссылку на профиль
              </button>

              <p
                  class="card-modal__status"
                  role="status"
                  aria-live="polite"
              >{{ status }}</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.card-modal {
  position: fixed;
  inset: 0;
  z-index: 2500;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 16px;

  background: rgba(5, 4, 8, 0.78);
}

.card-modal__dialog {
  width: min(820px, 100%);
  max-height: calc(100vh - 32px);

  display: flex;
  flex-direction: column;

  overflow: hidden;

  background: var(--bg-card);
  border: 1px solid var(--border-hover);
  border-radius: 8px;

  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.6);
}

.card-modal__head {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 16px 20px;

  border-bottom: 1px solid var(--border);
}

.card-modal__head h2 {
  margin: 0;

  color: #fff;

  font-size: 18px;
}

.card-modal__close {
  width: 34px;
  height: 34px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--text-dim);
  background: transparent;
  border-radius: 6px;

  cursor: pointer;
}

.card-modal__close:hover {
  color: #fff;
  background: var(--bg-card-hover);
}

.card-modal__close svg {
  width: 18px;
  height: 18px;
}

.card-modal__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 260px;
  gap: 24px;

  padding: 20px;

  overflow-y: auto;
}

.card-modal__preview {
  display: flex;
  justify-content: center;
}

.card-modal__preview img {
  width: 100%;
  max-width: 420px;
  height: auto;

  border-radius: 12px;
}

.card-modal__placeholder {
  width: 100%;
  max-width: 420px;
  aspect-ratio: 600 / 840;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--text-muted);
  background: var(--bg);
  border-radius: 12px;

  font-size: 14px;
}

.card-modal__side {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.card-modal__text {
  margin: 0 0 8px;

  color: var(--text-dim);

  font-size: 14px;
}

.card-modal__action {
  width: 100%;
  min-height: 42px;
}

.card-modal__action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.card-modal__status {
  min-height: 40px;
  margin: 4px 0 0;

  color: #4ade80;

  font-size: 13px;
}

.card-modal-enter-active,
.card-modal-leave-active {
  transition: opacity 0.15s ease;
}

.card-modal-enter-from,
.card-modal-leave-to {
  opacity: 0;
}

@media (max-width: 700px) {
  .card-modal__body {
    grid-template-columns: 1fr;
  }
}
</style>
