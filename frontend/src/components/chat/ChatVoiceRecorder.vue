<script setup>
/**
 * Запись голосового: нажал — пишет, ещё раз — отправил, крестик — отмена.
 *
 * Формат выбирает браузер: Chrome и Firefox пишут webm/opus, Safari — mp4.
 * Сервер принимает любой из них. Дольше VOICE_MAX секунд запись
 * останавливается и уходит сама.
 */
import { computed, onBeforeUnmount, ref } from 'vue'
import { formatDuration } from '@/utils/chatPreview.js'

const VOICE_MAX = 120

const props = defineProps({
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['recorded', 'error', 'state'])

const recording = ref(false)
const seconds = ref(0)

let recorder = null
let stream = null
let chunks = []
let startedAt = 0
let timer = null
let cancelled = false

const supported = computed(() => typeof window !== 'undefined'
    && typeof window.MediaRecorder !== 'undefined'
    && Boolean(navigator.mediaDevices?.getUserMedia))

function pickType() {
  const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']

  return types.find((type) => window.MediaRecorder.isTypeSupported?.(type)) || ''
}

function release() {
  clearInterval(timer)
  timer = null
  stream?.getTracks().forEach((track) => track.stop())
  stream = null
  recorder = null
  recording.value = false
  emit('state', false)
}

async function start() {
  if (!supported.value || props.disabled || recording.value) return

  try {
    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
  } catch {
    emit('error', 'Нет доступа к микрофону — разреши его в настройках браузера.')

    return
  }

  const type = pickType()

  chunks = []
  cancelled = false
  recorder = new MediaRecorder(stream, type ? { mimeType: type } : undefined)

  recorder.ondataavailable = (event) => {
    if (event.data?.size) chunks.push(event.data)
  }

  recorder.onstop = () => {
    const duration = Math.max(1, Math.round((Date.now() - startedAt) / 1000))
    const blob = new Blob(chunks, { type: (recorder?.mimeType || type || 'audio/webm').split(';')[0] })

    release()

    if (cancelled) return

    // Случайный тап — не отправляем пустышку
    if (duration < 1 || blob.size < 1000) {
      emit('error', 'Слишком короткая запись.')

      return
    }

    emit('recorded', { blob, duration: Math.min(VOICE_MAX, duration) })
  }

  startedAt = Date.now()
  seconds.value = 0
  recorder.start()
  recording.value = true
  emit('state', true)

  timer = setInterval(() => {
    seconds.value = Math.floor((Date.now() - startedAt) / 1000)

    if (seconds.value >= VOICE_MAX) stop()
  }, 250)
}

function stop() {
  if (recorder?.state === 'recording') recorder.stop()
}

function cancel() {
  cancelled = true
  stop()
}

onBeforeUnmount(cancel)

defineExpose({ recording, cancel })
</script>

<template>
  <div v-if="supported" class="voice-rec" :class="{ 'is-on': recording }">
    <template v-if="recording">
      <button type="button" class="voice-rec__cancel" title="Отменить запись" @click="cancel">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
      </button>

      <span class="voice-rec__live">
        <span class="voice-rec__dot"></span>
        {{ formatDuration(seconds) }}
        <small>/ {{ formatDuration(VOICE_MAX) }}</small>
      </span>
    </template>

    <button
        type="button"
        class="voice-rec__btn"
        :disabled="disabled"
        :title="recording ? 'Отправить голосовое' : 'Записать голосовое'"
        @click="recording ? stop() : start()"
    >
      <svg v-if="!recording" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="9" y="3" width="6" height="11" rx="3" />
        <path d="M5 11a7 7 0 0 0 14 0M12 18v3" />
      </svg>
      <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 2 11 13" />
        <path d="M22 2l-7 20-4-9-9-4 20-7z" />
      </svg>
    </button>
  </div>
</template>

<style scoped>
.voice-rec {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 8px;
}

.voice-rec.is-on {
  flex: 1;
  justify-content: flex-end;
}

.voice-rec__btn,
.voice-rec__cancel {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 40px;
  height: 40px;

  color: inherit;

  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;

  cursor: pointer;
}

.voice-rec__btn:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.voice-rec.is-on .voice-rec__btn {
  color: #120c1f;

  background: #f87171;
  border-color: #f87171;
}

.voice-rec__live {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 8px;

  font-size: 13px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.voice-rec__live small {
  font-weight: 500;
  opacity: 0.5;
}

.voice-rec__dot {
  width: 10px;
  height: 10px;

  background: #f87171;

  animation: voice-rec-blink 1s steps(2) infinite;
}

@keyframes voice-rec-blink {
  50% { opacity: 0.2; }
}
</style>
