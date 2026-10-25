<script setup>
/**
 * Техподдержка: обращения игрока и переписка с персоналом.
 * Открытая переписка обновляется раз в 15 секунд.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { supportApi } from '@/services/support/support.js'
import SupportThread from '@/components/support/SupportThread.vue'
import { SUPPORT_STATUS, formatMessageTime } from '@/components/support/supportFormat.js'

const route = useRoute()
const router = useRouter()

const tickets = ref([])
const categories = ref({})
const current = ref(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')

const form = ref({ subject: '', category: 'other', body: '' })

const currentId = computed(() => (route.params.id ? Number(route.params.id) : null))
const creating = computed(() => !currentId.value)

let timer = null

async function loadList() {
  const data = await supportApi.list()
  tickets.value = data.tickets ?? []
  categories.value = data.categories ?? {}
}

async function loadTicket() {
  if (!currentId.value) {
    current.value = null
    return
  }

  try {
    current.value = (await supportApi.show(currentId.value)).ticket
  } catch (e) {
    error.value = e.message || 'Обращение не найдено.'
    current.value = null
  }
}

async function run(fn) {
  busy.value = true
  error.value = ''
  try {
    await fn()
  } catch (e) {
    error.value = e.message || 'Не удалось выполнить действие.'
  } finally {
    busy.value = false
  }
}

function submit() {
  return run(async () => {
    const { ticket } = await supportApi.open(form.value)
    form.value = { subject: '', category: 'other', body: '' }
    await loadList()
    router.push(`/support/${ticket.id}`)
  })
}

function send(body) {
  return run(async () => {
    current.value = (await supportApi.reply(current.value.id, body)).ticket
    await loadList()
  })
}

function close() {
  return run(async () => {
    current.value = (await supportApi.close(current.value.id)).ticket
    await loadList()
  })
}

onMounted(async () => {
  try {
    await Promise.all([loadList(), loadTicket()])
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить обращения.'
  } finally {
    loading.value = false
  }

  timer = setInterval(() => {
    if (currentId.value && !busy.value && document.visibilityState === 'visible') loadTicket()
  }, 15000)
})

onUnmounted(() => clearInterval(timer))

watch(currentId, () => {
  error.value = ''
  loadTicket()
})
</script>

<template>
  <div class="support-page">
    <header class="support-page__head">
      <h1>Техподдержка</h1>
      <p>Опишите проблему — модераторы ответят здесь же, а вы получите уведомление.</p>
    </header>

    <div v-if="loading" class="support-page__muted">Загружаем…</div>

    <div v-else class="support-page__layout">
      <aside class="support-list">
        <button type="button" class="btn btn-primary support-list__new" @click="router.push('/support')">
          Новое обращение
        </button>

        <p v-if="!tickets.length" class="support-page__muted">Обращений пока нет.</p>

        <RouterLink
            v-for="t in tickets"
            :key="t.id"
            :to="`/support/${t.id}`"
            class="support-list__item"
            :class="{ 'support-list__item--active': t.id === currentId }"
        >
          <span class="support-list__subject">{{ t.subject }}</span>
          <span class="support-list__meta">
            <span class="support-status" :class="`support-status--${t.status}`">{{ SUPPORT_STATUS[t.status] }}</span>
            <small>{{ formatMessageTime(t.last_message_at) }}</small>
          </span>
        </RouterLink>
      </aside>

      <section class="support-main">
        <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

        <form v-if="creating" class="support-form" @submit.prevent="submit">
          <h2>Новое обращение</h2>

          <label>Тема
            <input v-model="form.subject" class="support-form__input" maxlength="120" required minlength="3" placeholder="Коротко: что случилось">
          </label>

          <label>Раздел
            <select v-model="form.category" class="support-form__input">
              <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>

          <label>Сообщение
            <textarea
                v-model="form.body"
                class="support-form__input"
                rows="6"
                maxlength="4000"
                required
                minlength="5"
                placeholder="Опишите подробно: что делали, что ожидали, что получилось. Ник, ссылки и время помогут ответить быстрее."
            ></textarea>
          </label>

          <button type="submit" class="btn btn-primary" :disabled="busy">{{ busy ? 'Отправляем…' : 'Отправить' }}</button>
        </form>

        <template v-else-if="current">
          <div class="support-main__head">
            <h2>{{ current.subject }}</h2>
            <span class="support-status" :class="`support-status--${current.status}`">{{ SUPPORT_STATUS[current.status] }}</span>
            <small class="support-page__muted">{{ current.category_label }}</small>
          </div>

          <SupportThread :ticket="current" :busy="busy" @send="send" @close="close" />
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.support-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 16px 48px;
}

.support-page__head h1 {
  margin: 0 0 6px;
}

.support-page__head p,
.support-page__muted {
  margin: 0 0 16px;
  opacity: 0.7;
}

.support-page__layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 16px;
  align-items: start;
}

.support-list,
.support-main {
  padding: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.02);
}

.support-list {
  display: grid;
  gap: 8px;
}

.support-list__new {
  width: 100%;
}

.support-list__item {
  display: grid;
  gap: 4px;
  padding: 10px;
  border-radius: 10px;
  color: inherit;
  text-decoration: none;
}

.support-list__item:hover,
.support-list__item--active {
  background: rgba(124, 58, 237, 0.14);
}

.support-list__subject {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.support-list__meta {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12px;
}

.support-status {
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 11px;
  background: rgba(250, 204, 21, 0.14);
  color: #fde68a;
}

.support-status--answered {
  background: rgba(34, 197, 94, 0.14);
  color: #86efac;
}

.support-status--closed {
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.6);
}

.support-main__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.support-main__head h2,
.support-form h2 {
  margin: 0;
  font-size: 18px;
}

.support-form {
  display: grid;
  gap: 12px;
}

.support-form label {
  display: grid;
  gap: 6px;
  font-size: 13px;
}

.support-form__input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.25);
  color: inherit;
  font: inherit;
}

@media (max-width: 800px) {
  .support-page__layout {
    grid-template-columns: 1fr;
  }
}
</style>
