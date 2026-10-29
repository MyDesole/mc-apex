<script setup>
/**
 * Плеер голосового сообщения: кнопка, пиксельная «волна» и время.
 *
 * Настоящую форму волны не считаем — это лишняя работа на каждый файл.
 * Столбики строятся из id вложения: у каждого голосового свой рисунок,
 * и он не меняется между перерисовками.
 */
import { computed, onBeforeUnmount, ref } from 'vue'
import { formatDuration } from '@/utils/chatPreview.js'

const props = defineProps({
  file: { type: Object, required: true },
})

const BARS = 28

const audio = ref(null)
const playing = ref(false)
const progress = ref(0)
const current = ref(0)

const bars = computed(() => {
  let seed = Number(props.file.id) || 1

  return Array.from({ length: BARS }, () => {
    seed = (seed * 9301 + 49297) % 233280

    return 25 + Math.round((seed / 233280) * 75)
  })
})

const duration = computed(() => props.file.duration || 0)

const shown = computed(() => formatDuration(playing.value || current.value ? current.value : duration.value))

/** Только одно голосовое играет одновременно. */
function stopOthers() {
  document.querySelectorAll('audio[data-chat-voice]').forEach((el) => {
    if (el !== audio.value) el.pause()
  })
}

function toggle() {
  const el = audio.value

  if (!el) return

  if (el.paused) {
    stopOthers()
    el.play().catch(() => { playing.value = false })
  } else {
    el.pause()
  }
}

function onTime() {
  const el = audio.value

  if (!el) return

  const total = Number.isFinite(el.duration) && el.duration > 0 ? el.duration : duration.value

  current.value = el.currentTime
  progress.value = total ? Math.min(1, el.currentTime / total) : 0
}

function onEnded() {
  playing.value = false
  progress.value = 0
  current.value = 0
}

/** Клик по волне — перемотка. */
function seek(event) {
  const el = audio.value
  const rect = event.currentTarget.getBoundingClientRect()
  const total = Number.isFinite(el?.duration) && el.duration > 0 ? el.duration : duration.value

  if (!el || !total) return

  el.currentTime = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)) * total
  onTime()
}

onBeforeUnmount(() => audio.value?.pause())
</script>

<template>
  <div class="voice" :class="{ 'is-playing': playing }">
    <button type="button" class="voice__btn" :aria-label="playing ? 'Пауза' : 'Слушать'" @click="toggle">
      <svg v-if="!playing" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4v16l13-8z" /></svg>
      <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h4v16H6zM14 4h4v16h-4z" /></svg>
    </button>

    <div class="voice__wave" role="slider" :aria-valuenow="Math.round(progress * 100)" aria-valuemin="0" aria-valuemax="100" @click="seek">
      <span
          v-for="(height, index) in bars"
          :key="index"
          class="voice__bar"
          :class="{ 'is-done': index / BARS < progress }"
          :style="{ height: `${height}%` }"
      ></span>
    </div>

    <span class="voice__time">{{ shown }}</span>

    <audio
        ref="audio"
        data-chat-voice
        :src="file.url"
        preload="none"
        @play="playing = true"
        @pause="playing = false"
        @timeupdate="onTime"
        @ended="onEnded"
    ></audio>
  </div>
</template>

<style scoped>
.voice {
  display: flex;
  align-items: center;
  gap: 10px;
  width: min(260px, 100%);
  padding: 2px 0;
}

.voice__btn {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 34px;
  height: 34px;

  color: #120c1f;

  background: var(--voice-accent, #a78bfa);
  border: 0;
  border-radius: 6px;
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25);

  cursor: pointer;
}

.voice__btn:active {
  box-shadow: inset 0 2px 0 rgba(0, 0, 0, 0.25);
  transform: translateY(1px);
}

.voice__wave {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 2px;
  height: 26px;

  cursor: pointer;
}

.voice__bar {
  flex: 1;
  min-width: 2px;

  background: currentColor;
  opacity: 0.3;

  transition: opacity 0.15s ease;
}

.voice__bar.is-done {
  background: var(--voice-accent, #a78bfa);
  opacity: 1;
}

.voice__time {
  flex-shrink: 0;
  min-width: 32px;

  font-size: 11px;
  font-variant-numeric: tabular-nums;
  opacity: 0.75;
}
</style>
