<script setup>
/**
 * Окно чата заявки на тир-тест.
 *
 * Одно и то же окно у тестируемого и у тестера. Правила приходят с сервера:
 * can_send и reason — пока тестер не написал первым, игрок писать не может.
 * Новые сообщения подтягиваются опросом, пока окно открыто.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { tierChatApi } from '@/services/tiers/tierChat.js'

const props = defineProps({
  testId: { type: Number, required: true },
})

const emit = defineEmits(['unread-cleared'])

const POLL_MS = 5000

const thread = ref(null)
const messages = ref([])
const draft = ref('')
const sending = ref(false)
const error = ref('')
const loading = ref(true)
const listEl = ref(null)

let timer = null

const lastId = computed(() => messages.value.at(-1)?.id ?? 0)
const canSend = computed(() => Boolean(thread.value?.can_send))
const other = computed(() => (thread.value?.role === 'tester' ? thread.value?.player : thread.value?.tester))
const maxLength = computed(() => thread.value?.max_length ?? 1000)

function scrollDown() {
  nextTick(() => {
    if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight
  })
}

async function load(initial = false) {
  try {
    const data = await tierChatApi.thread(props.testId, initial ? 0 : lastId.value)
    const fresh = data.messages ?? []

    thread.value = data

    if (initial) {
      messages.value = fresh
    } else if (fresh.length) {
      const known = new Set(messages.value.map((m) => m.id))
      messages.value.push(...fresh.filter((m) => !known.has(m.id)))
    }

    if (initial || fresh.length) {
      scrollDown()
      emit('unread-cleared')
    }
  } catch (e) {
    if (initial) error.value = e.message || 'Не удалось загрузить чат.'
  } finally {
    loading.value = false
  }
}

async function send() {
  const text = draft.value.trim()

  if (!text || sending.value || !canSend.value) return

  sending.value = true
  error.value = ''

  try {
    const { message } = await tierChatApi.send(props.testId, text)

    draft.value = ''

    if (!messages.value.some((m) => m.id === message.id)) messages.value.push(message)

    scrollDown()
  } catch (e) {
    error.value = e.message || 'Сообщение не отправилось. Попробуйте ещё раз.'
    // Правила могли поменяться (тестер отпустил заявку) — обновим состояние
    load(false)
  } finally {
    sending.value = false
  }
}

function onKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

function time(iso) {
  return iso ? new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) : ''
}

onMounted(() => {
  load(true)
  timer = setInterval(() => load(false), POLL_MS)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <section class="ttchat">
    <header class="ttchat__head">
      <div class="ttchat__who">
        <span class="ttchat__dot" :class="{ 'is-on': thread?.status === 'in_progress' }" />
        <div>
          <div class="ttchat__title">
            Чат тир-теста
          </div>
          <div class="ttchat__sub">
            <template v-if="other">
              {{ thread?.role === 'tester' ? 'Тестируемый' : 'Тестер' }}: <b>{{ other.username }}</b>
            </template>
            <template v-else>
              Тестер ещё не назначен
            </template>
          </div>
        </div>
      </div>
    </header>

    <div ref="listEl" class="ttchat__list">
      <p v-if="loading" class="ttchat__hint">
        Загрузка…
      </p>

      <p v-else-if="!messages.length" class="ttchat__hint">
        {{ thread?.reason || 'Сообщений пока нет.' }}
      </p>

      <div
        v-for="m in messages"
        :key="m.id"
        class="ttchat__msg"
        :class="{ 'is-mine': m.mine }"
      >
        <div class="ttchat__bubble">
          <div class="ttchat__name">
            {{ m.mine ? 'Вы' : m.username }}
          </div>
          <div class="ttchat__text">
            {{ m.body }}
          </div>
          <div class="ttchat__time">
            {{ time(m.created_at) }}
          </div>
        </div>
      </div>
    </div>

    <p v-if="error" class="ttchat__error">
      {{ error }}
    </p>

    <p v-if="!canSend && messages.length && thread?.reason" class="ttchat__lock">
      {{ thread.reason }}
    </p>

    <form class="ttchat__form" @submit.prevent="send">
      <textarea
        v-model="draft"
        class="ttchat__input"
        rows="2"
        :maxlength="maxLength"
        :disabled="!canSend || sending"
        :placeholder="canSend ? 'Напишите сообщение… (Enter — отправить)' : 'Писать пока нельзя'"
        @keydown="onKey"
      />
      <button class="ttchat__send" type="submit" :disabled="!canSend || sending || !draft.trim()">
        Отправить
      </button>
    </form>
  </section>
</template>

<style scoped>
@import "@/components/tiers/TierTestChat.css";
</style>
