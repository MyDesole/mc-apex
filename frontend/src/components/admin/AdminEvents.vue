<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/admin.js'

const events = ref([])
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

let timer = null

async function load() {
  loading.value = true
  try {
    const data = await adminApi.events({
      search: search.value,
      page: page.value,
    })
    events.value = data.data
    lastPage.value = data.last_page
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; load() }, 300)
})

watch(page, load)

async function remove(event) {
  if (!confirm(`Удалить пост «${event.title}»?`)) return
  await adminApi.deleteEvent(event.id)
  await load()
}

const typeLabels = {
  announcement: 'Анонс',
  event: 'Мероприятие',
  training: 'Тренировка',
}

onMounted(load)
</script>

<template>
  <div>
    <input
        v-model="search"
        type="text"
        placeholder="Поиск по заголовку или тексту..."
        class="search"
    />

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!events.length" class="empty">Нет постов</div>

    <div v-else class="list">
      <div v-for="e in events" :key="e.id" class="event-row">
        <div class="event-head">
                    <span class="type" :class="`type-${e.type}`">
                        {{ typeLabels[e.type] }}
                    </span>

          <RouterLink :to="`/clans/${e.clan.id}`" class="clan">
            [{{ e.clan.tag }}] {{ e.clan.name }}
          </RouterLink>

          <span class="date">
                        {{ new Date(e.created_at).toLocaleString('ru-RU') }}
                    </span>
        </div>

        <h3 class="title">{{ e.title }}</h3>

        <p v-if="e.body" class="body">{{ e.body }}</p>

        <div class="actions">
                    <span class="author">
                        Автор: {{ e.author?.username }}
                    </span>
          <button class="btn-del" @click="remove(e)">
            Удалить
          </button>
        </div>
      </div>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="page <= 1" @click="page--">←</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="page++">→</button>
    </div>
  </div>
</template>

<style scoped>
.search {
  width: 100%;
  min-height: 42px;
  padding: 0 14px;
  margin-bottom: 16px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
}

.search:focus {
  border-color: var(--accent);
}

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.event-row {
  padding: 16px 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.event-head {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
  font-size: 12px;
}

.type {
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.type-announcement {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.1);
}

.type-event {
  color: #f472b6;
  background: rgba(244, 114, 182, 0.1);
}

.type-training {
  color: #34d399;
  background: rgba(52, 211, 153, 0.1);
}

.clan {
  color: var(--accent-light);
  font-weight: 700;
}

.date {
  margin-left: auto;
  color: var(--text-muted);
}

.title {
  margin: 0 0 8px;
  font-size: 15px;
  font-weight: 800;
  color: var(--text);
}

.body {
  margin: 0 0 12px;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-height: 120px;
  overflow: hidden;
}

.actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.author {
  color: var(--text-muted);
  font-size: 12px;
}

.btn-del {
  padding: 5px 12px;
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 7px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-del:hover {
  background: rgba(239, 68, 68, 0.08);
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 24px;
  color: var(--text-dim);
  font-size: 13px;
}

.pagination button {
  min-height: 34px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>