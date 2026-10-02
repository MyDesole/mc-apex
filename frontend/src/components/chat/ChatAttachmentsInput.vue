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
@import "@/components/chat/ChatAttachmentsInput.css";
</style>