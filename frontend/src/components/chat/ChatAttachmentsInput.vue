<script setup>
/**
 * Панель прикреплённых к сообщению файлов + кнопка выбора.
 *
 * Можно выбрать несколько файлов.
 * Сначала отправляем пачкой, а при отказе сервера разбираем её
 * по одному файлу, чтобы сохранить валидные вложения.
 */
import { computed, ref } from 'vue'
import { chatApi } from '@/services/chat/chat.js'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, default: 10 },
})

const emit = defineEmits(['update:modelValue'])

const MAX_KILOBYTES = 12288 // 12 МБ

const uploading = ref(false)
const progress = ref({ done: 0, total: 0 })
const error = ref('')
const previewOpen = ref(null)

const files = computed(() => props.modelValue)
const isBusy = computed(() => uploading.value)

function toChip(attachment) {
  return {
    id: attachment.id,
    name: attachment.name,
    url: attachment.url,
    size: attachment.size,
    is_image: attachment.is_image,
  }
}

function resetInput(event) {
  // Позволяет повторно выбрать тот же файл.
  event.target.value = ''
}

async function onPick(event) {
  const picked = Array.from(event.target.files || [])
  resetInput(event)

  if (!picked.length) return

  error.value = ''

  const room = props.max - files.value.length

  if (room <= 0) {
    error.value = `Можно приложить не больше ${props.max} файлов.`
    return
  }

  const fits = []
  const tooBig = []

  picked.forEach((file) => {
    if (file.size > MAX_KILOBYTES * 1024) {
      tooBig.push(file.name)
    } else {
      fits.push(file)
    }
  })

  const accepted = fits.slice(0, room)

  const messages = []

  if (fits.length > room) {
    messages.push(`Можно приложить ещё ${room} файл(ов) — лишние пропущены.`)
  }

  if (tooBig.length) {
    messages.push(
        `${tooBig.map((name) => `«${name}»`).join(', ')} больше 12 МБ — пропущены.`
    )
  }

  if (messages.length) {
    error.value = messages.join(' ')
  }

  if (!accepted.length) return

  uploading.value = true
  progress.value = {
    done: 0,
    total: accepted.length,
  }

  try {
    const added = await uploadBatch(accepted)

    emit('update:modelValue', [...files.value, ...added])
  } catch (e) {
    error.value = describeError(e)
  } finally {
    uploading.value = false
    progress.value = {
      done: 0,
      total: 0,
    }
  }
}

/**
 * Сначала пытаемся загрузить всю пачку одним запросом.
 * Если сервер отклонил пачку — пробуем каждый файл отдельно.
 */
async function uploadBatch(batch) {
  try {
    const result = await chatApi.uploadMany(batch)

    const attachments =
        result.attachments ??
        (result.attachment ? [result.attachment] : [])

    progress.value = {
      done: batch.length,
      total: batch.length,
    }

    return attachments.map(toChip)
  } catch (batchError) {
    if (batch.length === 1) {
      throw batchError
    }

    return uploadIndividually(batch)
  }
}

async function uploadIndividually(batch) {
  const added = []
  const rejected = []

  for (const file of batch) {
    try {
      const result = await chatApi.upload(file)

      added.push(toChip(result.attachment))
    } catch {
      rejected.push(file.name)
    } finally {
      progress.value = {
        done: progress.value.done + 1,
        total: progress.value.total,
      }
    }
  }

  if (rejected.length) {
    error.value =
        `Не удалось приложить: ${rejected.map((name) => `«${name}»`).join(', ')}. ` +
        'Разрешены картинки, видео, pdf, тексты и архивы до 12 МБ.'
  }

  if (!added.length && rejected.length) {
    throw new Error('Ни один файл не удалось приложить.')
  }

  return added
}

function describeError(e) {
  if (e.status === 422) {
    return 'Такой файл приложить нельзя: разрешены картинки, видео, pdf, тексты и архивы до 12 МБ.'
  }

  return e.message || 'Не удалось загрузить файл.'
}

function remove(index) {
  const next = [...files.value]
  next.splice(index, 1)

  emit('update:modelValue', next)
}

function openPreview(file) {
  if (!file?.is_image) return
  previewOpen.value = file
}

function closePreview() {
  previewOpen.value = null
}
</script>

<template>
  <div class="attach-bar">
    <!-- Ошибка / предупреждение -->
    <Transition name="attach-fade">
      <div v-if="error" class="attach-error">
        <span class="attach-error__icon">!</span>
        <span class="attach-error__text">{{ error }}</span>

        <button
            class="attach-error__close"
            type="button"
            aria-label="Закрыть"
            @click="error = ''"
        >
          ×
        </button>
      </div>
    </Transition>

    <!-- Вложения -->
    <TransitionGroup
        v-if="files.length"
        name="attach-list"
        tag="div"
        class="attach-list"
    >
      <div
          v-for="(file, index) in files"
          :key="file.id"
          class="attach-chip"
      >
        <!-- Превью -->
        <button
            v-if="file.is_image"
            class="attach-chip__preview"
            type="button"
            :title="`Открыть ${file.name}`"
            @click="openPreview(file)"
        >
          <img
              :src="file.url"
              :alt="file.name"
              loading="lazy"
          >

          <span class="attach-chip__preview-overlay">
            <svg
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M15 3h6v6" />
              <path d="M10 14 21 3" />
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            </svg>
          </span>
        </button>

        <!-- Иконка обычного файла -->
        <span v-else class="attach-chip__file-icon">
          <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6" />
          </svg>
        </span>

        <!-- Информация -->
        <div class="attach-chip__info">
          <span
              class="attach-chip__name"
              :title="file.name"
          >
            {{ file.name }}
          </span>

          <span class="attach-chip__meta">
            {{ file.size }}
          </span>
        </div>

        <!-- Удаление -->
        <button
            class="attach-chip__remove"
            type="button"
            title="Убрать файл"
            :aria-label="`Убрать ${file.name}`"
            @click="remove(index)"
        >
          <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>

    <!-- Нижняя панель -->
    <div class="attach-footer">
      <label
          class="attach-button"
          :class="{
          'attach-button--busy': isBusy,
          'attach-button--disabled': files.length >= max && !isBusy,
        }"
      >
        <input
            type="file"
            multiple
            :disabled="isBusy || files.length >= max"
            @change="onPick"
        >

        <span class="attach-button__icon">
          <svg
              width="17"
              height="17"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
          </svg>
        </span>

        <span class="attach-button__content">
          <span v-if="!isBusy">Прикрепить файл</span>

          <span v-else-if="progress.total > 1">
            Загрузка {{ progress.done }}/{{ progress.total }}
          </span>

          <span v-else>
            Загрузка…
          </span>
        </span>

        <span
            v-if="!isBusy"
            class="attach-button__count"
        >
          {{ files.length }}/{{ max }}
        </span>

        <span
            v-else
            class="attach-button__spinner"
        />
      </label>

      <span class="attach-hint">
        До 12 МБ
      </span>
    </div>
  </div>

  <!-- Полноэкранный просмотр -->
  <Transition name="attach-preview">
    <div
        v-if="previewOpen"
        class="attach-modal"
        @click.self="closePreview"
    >
      <div class="attach-modal__backdrop" />

      <div class="attach-modal__content">
        <button
            class="attach-modal__close"
            type="button"
            aria-label="Закрыть"
            @click="closePreview"
        >
          <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
          </svg>
        </button>

        <img
            class="attach-modal__image"
            :src="previewOpen.url"
            :alt="previewOpen.name"
        >

        <div class="attach-modal__caption">
          {{ previewOpen.name }}
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.attach-bar {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ─────────────────────────────
   ERROR
───────────────────────────── */

.attach-error {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
  padding: 9px 11px;
  border: 1px solid rgba(239, 68, 68, 0.18);
  border-radius: 11px;
  background:
      linear-gradient(
          135deg,
          rgba(239, 68, 68, 0.09),
          rgba(239, 68, 68, 0.035)
      );
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
  line-height: 1.45;
}

.attach-error__icon {
  flex: 0 0 20px;
  width: 20px;
  height: 20px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.16);
  color: #f87171;
  font-size: 12px;
  font-weight: 800;
}

.attach-error__text {
  min-width: 0;
  flex: 1;
}

.attach-error__close {
  flex: 0 0 auto;
  width: 24px;
  height: 24px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: rgba(255, 255, 255, 0.35);
  cursor: pointer;
  transition:
      color 0.18s ease,
      background 0.18s ease;
}

.attach-error__close:hover {
  color: rgba(255, 255, 255, 0.8);
  background: rgba(255, 255, 255, 0.06);
}

/* ─────────────────────────────
   FILE LIST
───────────────────────────── */

.attach-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
}

/* ─────────────────────────────
   FILE CHIP
───────────────────────────── */

.attach-chip {
  position: relative;
  min-width: 0;
  max-width: 280px;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 6px 8px 6px 6px;
  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 12px;
  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.055),
          rgba(255, 255, 255, 0.025)
      );
  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.035),
      0 4px 16px rgba(0, 0, 0, 0.12);
  backdrop-filter: blur(12px);
  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease,
      box-shadow 0.18s ease;
}

.attach-chip:hover {
  border-color: rgba(255, 255, 255, 0.12);
  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.075),
          rgba(255, 255, 255, 0.035)
      );
  transform: translateY(-1px);
  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.045),
      0 7px 20px rgba(0, 0, 0, 0.18);
}

/* IMAGE PREVIEW */

.attach-chip__preview {
  position: relative;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.25);
  cursor: pointer;
}

.attach-chip__preview img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  transition: transform 0.22s ease;
}

.attach-chip__preview-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(0, 0, 0, 0.48);
  color: #fff;
  opacity: 0;
  transition: opacity 0.18s ease;
}

.attach-chip__preview:hover img {
  transform: scale(1.06);
}

.attach-chip__preview:hover .attach-chip__preview-overlay {
  opacity: 1;
}

/* FILE ICON */

.attach-chip__file-icon {
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(124, 58, 237, 0.16);
  border-radius: 8px;
  background:
      linear-gradient(
          135deg,
          rgba(124, 58, 237, 0.15),
          rgba(124, 58, 237, 0.055)
      );
  color: rgba(167, 139, 250, 0.9);
}

/* INFO */

.attach-chip__info {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.attach-chip__name {
  display: block;
  min-width: 0;
  overflow: hidden;
  color: rgba(255, 255, 255, 0.82);
  font-size: 12px;
  font-weight: 600;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attach-chip__meta {
  color: rgba(255, 255, 255, 0.34);
  font-size: 10px;
  line-height: 1.2;
}

/* REMOVE */

.attach-chip__remove {
  flex: 0 0 auto;
  width: 25px;
  height: 25px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: rgba(255, 255, 255, 0.28);
  cursor: pointer;
  transition:
      color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease;
}

.attach-chip__remove:hover {
  color: #f87171;
  background: rgba(239, 68, 68, 0.1);
  transform: scale(1.04);
}

/* ─────────────────────────────
   FOOTER
───────────────────────────── */

.attach-footer {
  display: flex;
  align-items: center;
  gap: 9px;
}

.attach-button {
  position: relative;
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  gap: 9px;
  padding: 0 10px 0 11px;
  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 10px;
  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.055),
          rgba(255, 255, 255, 0.025)
      );
  color: rgba(255, 255, 255, 0.72);
  cursor: pointer;
  user-select: none;
  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      color 0.18s ease,
      transform 0.18s ease;
}

.attach-button:hover {
  border-color: rgba(124, 58, 237, 0.28);
  background:
      linear-gradient(
          135deg,
          rgba(124, 58, 237, 0.12),
          rgba(255, 255, 255, 0.035)
      );
  color: rgba(255, 255, 255, 0.92);
  transform: translateY(-1px);
}

.attach-button:active {
  transform: translateY(0);
}

.attach-button input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
}

.attach-button__icon {
  width: 26px;
  height: 26px;
  display: grid;
  place-items: center;
  border-radius: 7px;
  background: rgba(124, 58, 237, 0.12);
  color: #a78bfa;
}

.attach-button__content {
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.attach-button__count {
  min-width: 28px;
  padding: 3px 6px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.055);
  color: rgba(255, 255, 255, 0.36);
  font-size: 10px;
  font-weight: 700;
  text-align: center;
}

.attach-button--busy {
  cursor: wait;
  border-color: rgba(124, 58, 237, 0.2);
  background:
      linear-gradient(
          135deg,
          rgba(124, 58, 237, 0.1),
          rgba(255, 255, 255, 0.025)
      );
}

.attach-button--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.attach-button__spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.12);
  border-top-color: #a78bfa;
  border-radius: 50%;
  animation: attach-spin 0.75s linear infinite;
}

.attach-hint {
  color: rgba(255, 255, 255, 0.25);
  font-size: 10px;
}

@keyframes attach-spin {
  to {
    transform: rotate(360deg);
  }
}

/* ─────────────────────────────
   IMAGE MODAL
───────────────────────────── */

.attach-modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  padding: 30px;
}

.attach-modal__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(3, 3, 8, 0.82);
  backdrop-filter: blur(14px);
}

.attach-modal__content {
  position: relative;
  z-index: 1;
  max-width: min(1100px, 94vw);
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.attach-modal__image {
  display: block;
  max-width: 100%;
  max-height: calc(92vh - 50px);
  object-fit: contain;
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 14px;
  background: rgba(0, 0, 0, 0.25);
  box-shadow:
      0 25px 80px rgba(0, 0, 0, 0.5),
      0 0 50px rgba(124, 58, 237, 0.08);
}

.attach-modal__close {
  position: absolute;
  top: -13px;
  right: -13px;
  z-index: 2;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  background: rgba(20, 20, 28, 0.9);
  color: rgba(255, 255, 255, 0.65);
  cursor: pointer;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  transition:
      color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease;
}

.attach-modal__close:hover {
  color: #fff;
  background: rgba(124, 58, 237, 0.7);
  transform: scale(1.04);
}

.attach-modal__caption {
  max-width: 80vw;
  overflow: hidden;
  color: rgba(255, 255, 255, 0.55);
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ─────────────────────────────
   TRANSITIONS
───────────────────────────── */

.attach-fade-enter-active,
.attach-fade-leave-active {
  transition:
      opacity 0.18s ease,
      transform 0.18s ease;
}

.attach-fade-enter-from,
.attach-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.attach-list-enter-active,
.attach-list-leave-active,
.attach-list-move {
  transition:
      opacity 0.2s ease,
      transform 0.2s ease;
}

.attach-list-enter-from,
.attach-list-leave-to {
  opacity: 0;
  transform: translateY(5px) scale(0.97);
}

.attach-preview-enter-active,
.attach-preview-leave-active {
  transition: opacity 0.2s ease;
}

.attach-preview-enter-from,
.attach-preview-leave-to {
  opacity: 0;
}

.attach-preview-enter-active .attach-modal__content,
.attach-preview-leave-active .attach-modal__content {
  transition: transform 0.2s ease;
}

.attach-preview-enter-from .attach-modal__content {
  transform: scale(0.96);
}

.attach-preview-leave-to .attach-modal__content {
  transform: scale(0.98);
}

/* ─────────────────────────────
   MOBILE
───────────────────────────── */

@media (max-width: 560px) {
  .attach-chip {
    max-width: 100%;
    width: 100%;
  }

  .attach-list {
    flex-direction: column;
  }

  .attach-footer {
    justify-content: space-between;
  }

  .attach-hint {
    display: none;
  }

  .attach-button {
    flex: 1;
    justify-content: center;
  }

  .attach-modal {
    padding: 16px;
  }

  .attach-modal__close {
    top: -8px;
    right: -8px;
  }
}
</style>