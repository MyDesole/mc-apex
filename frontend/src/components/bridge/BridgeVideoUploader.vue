<script setup>
/**
 * Загрузка видео бриджа частями.
 *
 * Ролик на две минуты может весить сотни мегабайт — одним запросом он не
 * пройдёт. Режем файл на части, шлём по очереди и показываем прогресс.
 * Незавершённую загрузку можно прервать.
 */
import { computed, onBeforeUnmount, ref } from 'vue'
import { bridgeVideoApi } from '@/services/bridge/bridge.js'

const props = defineProps({
  // Идентификатор завершённой загрузки
  modelValue: { type: String, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const MAX_SIZE = 300 * 1024 * 1024

const file = ref(null)
const status = ref('idle')
const progress = ref(0)
const error = ref('')
const uploadedBytes = ref(0)

let controller = null

const fileName = computed(() => file.value?.name ?? '')
const fileSize = computed(() => file.value?.size ?? 0)
const sizeLabel = computed(() => formatSize(fileSize.value))

const statusLabel = computed(() => {
  if (status.value === 'uploading') return `Загрузка ${progress.value}%`
  if (status.value === 'done') return 'Видео загружено'

  return 'Видео не выбрано'
})

function formatSize(bytes) {
  if (!bytes) return '0 МБ'
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} КБ`

  return `${(bytes / 1024 / 1024).toFixed(1)} МБ`
}

function onFileChange(event) {
  const picked = event.target.files?.[0]

  event.target.value = ''

  if (!picked) return

  error.value = ''

  if (picked.size > MAX_SIZE) {
    error.value = 'Видео больше 300 МБ — сожми ролик.'

    return
  }

  if (!picked.type.startsWith('video/')) {
    error.value = 'Нужен видеофайл: MP4, WebM или MOV.'

    return
  }

  file.value = picked
  status.value = 'idle'
  progress.value = 0
  uploadedBytes.value = 0

  emit('update:modelValue', null)

  start()
}

/** Шлёт файл частями и сообщает наверх идентификатор загрузки. */
async function start() {
  if (!file.value) return

  status.value = 'uploading'
  error.value = ''

  controller = new AbortController()

  try {
    const session = await bridgeVideoApi.init({
      file_name: file.value.name,
      size: file.value.size,
      mime: file.value.type,
    })

    const chunkSize = session.chunk_size
    const total = Math.ceil(file.value.size / chunkSize)

    for (let index = 0; index < total; index++) {
      if (controller.signal.aborted) throw new Error('cancelled')

      const start = index * chunkSize
      const blob = file.value.slice(start, start + chunkSize)

      await bridgeVideoApi.chunk(session.uuid, index, blob)

      uploadedBytes.value = Math.min(file.value.size, start + chunkSize)
      progress.value = Math.round((uploadedBytes.value / file.value.size) * 100)
    }

    await bridgeVideoApi.complete(session.uuid)

    status.value = 'done'
    progress.value = 100

    emit('update:modelValue', session.uuid)
  } catch (e) {
    if (e?.message === 'cancelled') {
      status.value = 'idle'
      progress.value = 0

      return
    }

    status.value = 'error'
    error.value = e?.message || 'Не удалось загрузить видео'
  } finally {
    controller = null
  }
}

/** Прерывает загрузку: части на сервере подчистятся сами. */
function cancel() {
  controller?.abort()
  file.value = null
  status.value = 'idle'
  progress.value = 0
  uploadedBytes.value = 0

  emit('update:modelValue', null)
}

function reset() {
  cancel()
  error.value = ''
}

onBeforeUnmount(() => controller?.abort())

defineExpose({ reset })
</script>

<template>
  <div class="uploader" :class="{ 'uploader--disabled': disabled }">
    <div
        v-if="!file"
        class="uploader__drop"
    >
      <label class="uploader__pick">
        <input
            type="file"
            accept="video/mp4,video/webm,video/quicktime,video/x-matroska"
            :disabled="disabled"
            @change="onFileChange"
        />

        <span class="uploader__pick-title">Загрузить видео</span>
        <span class="uploader__pick-hint">
          MP4, WebM или MOV · до 300 МБ · грузится частями
        </span>
      </label>
    </div>

    <div v-else class="uploader__file">
      <div class="uploader__row">
        <span class="uploader__name" :title="fileName">{{ fileName }}</span>
        <span class="uploader__size">{{ sizeLabel }}</span>
      </div>

      <div class="uploader__bar">
        <div
            class="uploader__fill"
            :class="{
              'uploader__fill--done': status === 'done',
              'uploader__fill--error': status === 'error',
            }"
            :style="{ width: `${progress}%` }"
        />
      </div>

      <div class="uploader__row">
        <span class="uploader__status">{{ statusLabel }}</span>

        <button
            v-if="status !== 'done'"
            type="button"
            class="uploader__cancel"
            @click="cancel"
        >
          Отменить
        </button>

        <button
            v-else
            type="button"
            class="uploader__cancel"
            @click="reset"
        >
          Заменить
        </button>
      </div>
    </div>

    <p v-if="error" class="uploader__error">{{ error }}</p>
  </div>
</template>

<style scoped>
@import "@/components/bridge/BridgeVideoUploader.css";
</style>
