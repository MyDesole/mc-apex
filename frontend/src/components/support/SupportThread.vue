<script setup>
/**
 * Переписка по обращению: общая для игрока и персонала.
 * staff — смотрим со стороны поддержки (свои сообщения справа — ответы персонала).
 */
import { nextTick, ref, watch } from 'vue'
import { formatMessageTime } from '@/components/support/supportFormat.js'

const props = defineProps({
  ticket: { type: Object, required: true },
  staff: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['send', 'close'])

const draft = ref('')
const list = ref(null)

function isMine(message) {
  return props.staff ? message.is_staff : !message.is_staff
}

function send() {
  const body = draft.value.trim()
  if (!body) return
  emit('send', body)
  draft.value = ''
}

function onKey(event) {
  if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) send()
}

watch(() => props.ticket?.messages?.length, async () => {
  await nextTick()
  if (list.value) list.value.scrollTop = list.value.scrollHeight
}, { immediate: true })
</script>

<template>
  <div class="thread">
    <div ref="list" class="thread__messages">
      <div
          v-for="m in ticket.messages"
          :key="m.id"
          class="thread__msg"
          :class="{ 'thread__msg--mine': isMine(m), 'thread__msg--staff': m.is_staff }"
      >
        <div class="thread__meta">
          <b>{{ m.is_staff ? (staff ? m.user?.username : 'Поддержка APEX') : m.user?.username }}</b>
          <small>{{ formatMessageTime(m.created_at) }}</small>
        </div>
        <p class="thread__body">{{ m.body }}</p>
      </div>
    </div>

    <form class="thread__form" @submit.prevent="send">
      <textarea
          v-model="draft"
          class="thread__input"
          rows="3"
          maxlength="4000"
          :placeholder="ticket.status === 'closed' ? 'Обращение закрыто — напишите, чтобы открыть его снова' : 'Сообщение… (Ctrl+Enter — отправить)'"
          @keydown="onKey"
      ></textarea>
      <div class="thread__actions">
        <button
            v-if="ticket.status !== 'closed'"
            type="button"
            class="btn btn-secondary"
            :disabled="busy"
            @click="emit('close')"
        >{{ staff ? 'Закрыть обращение' : 'Вопрос решён' }}</button>
        <button type="submit" class="btn btn-primary" :disabled="busy || !draft.trim()">Отправить</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.thread {
  display: grid;
  gap: 12px;
}

.thread__messages {
  display: grid;
  gap: 10px;
  max-height: min(60vh, 560px);
  overflow-y: auto;
  padding: 4px;
}

.thread__msg {
  max-width: 82%;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.05);
  justify-self: start;
}

.thread__msg--mine {
  justify-self: end;
  background: rgba(124, 58, 237, 0.18);
}

.thread__msg--staff:not(.thread__msg--mine) {
  border: 1px solid rgba(34, 197, 94, 0.35);
}

.thread__meta {
  display: flex;
  gap: 8px;
  align-items: baseline;
  font-size: 12px;
}

.thread__meta small {
  opacity: 0.6;
}

.thread__body {
  margin: 4px 0 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.thread__form {
  display: grid;
  gap: 8px;
}

.thread__input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.25);
  color: inherit;
  resize: vertical;
  font: inherit;
}

.thread__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 600px) {
  .thread__msg {
    max-width: 94%;
  }
}
</style>
