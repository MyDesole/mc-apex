<script setup>
/**
 * Очередь техподдержки: дольше всех ждущие обращения сверху.
 */
import { inject, onMounted, ref, watch } from 'vue'
import { adminSupportApi } from '@/services/support/support.js'
import SupportThread from '@/components/support/SupportThread.vue'
import { SUPPORT_STATUS, formatMessageTime } from '@/components/support/supportFormat.js'

const openDossier = inject('openDossier', () => {})
const refreshCounters = inject('refreshCounters', () => {})

const FILTERS = [
  { key: 'open', label: 'Ждут ответа' },
  { key: 'answered', label: 'Ответили' },
  { key: 'closed', label: 'Закрытые' },
  { key: 'all', label: 'Все' },
]

const status = ref('open')
const tickets = ref([])
const counts = ref({ open: 0, answered: 0 })
const current = ref(null)
const busy = ref(false)
const error = ref('')

async function load() {
  const data = await adminSupportApi.list(status.value)
  tickets.value = data.tickets ?? []
  counts.value = data.counts ?? counts.value
}

async function select(ticket) {
  error.value = ''
  current.value = (await adminSupportApi.show(ticket.id)).ticket
}

async function run(fn) {
  busy.value = true
  error.value = ''
  try {
    current.value = (await fn()).ticket
    await load()
    refreshCounters()
  } catch (e) {
    error.value = e.message || 'Не удалось выполнить действие.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
watch(status, () => {
  current.value = null
  load()
})
</script>

<template>
  <div class="admin-support">
    <div class="kit-tabs">
      <button
          v-for="f in FILTERS"
          :key="f.key"
          type="button"
          class="kit-btn"
          :class="{ 'kit-btn--primary': status === f.key }"
          @click="status = f.key"
      >
        {{ f.label }}<template v-if="counts[f.key]"> · {{ counts[f.key] }}</template>
      </button>
    </div>

    <div class="admin-support__layout">
      <ul class="admin-support__list">
        <li v-if="!tickets.length" class="kit-muted">Обращений нет</li>
        <li v-for="t in tickets" :key="t.id">
          <button
              type="button"
              class="admin-support__item"
              :class="{ 'admin-support__item--active': current?.id === t.id }"
              @click="select(t)"
          >
            <b>{{ t.subject }}</b>
            <span class="kit-muted">{{ t.user?.username }} · {{ t.category_label }}</span>
            <small class="kit-muted">{{ SUPPORT_STATUS[t.status] }} · {{ formatMessageTime(t.last_message_at) }}</small>
          </button>
        </li>
      </ul>

      <section class="admin-support__main">
        <p v-if="!current" class="kit-muted">Выберите обращение слева.</p>

        <template v-else>
          <div class="admin-support__head">
            <h3>{{ current.subject }}</h3>
            <button type="button" class="kit-btn" @click="openDossier(current.user.id)">Досье {{ current.user?.username }}</button>
          </div>
          <div v-if="error" class="kit-muted" role="alert">{{ error }}</div>

          <SupportThread
              :ticket="current"
              staff
              :busy="busy"
              @send="(body) => run(() => adminSupportApi.reply(current.id, body))"
              @close="run(() => adminSupportApi.close(current.id))"
          />
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.kit-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.admin-support__layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 14px;
  align-items: start;
}

.admin-support__list {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.admin-support__item {
  display: grid;
  gap: 2px;
  width: 100%;
  padding: 10px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.admin-support__item--active,
.admin-support__item:hover {
  border-color: rgba(167, 139, 250, 0.5);
}

.admin-support__main {
  padding: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
}

.admin-support__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.admin-support__head h3 {
  margin: 0;
}

@media (max-width: 800px) {
  .admin-support__layout {
    grid-template-columns: 1fr;
  }
}
</style>
