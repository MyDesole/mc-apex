<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/admin.js'

const comments = ref([])
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

let timer = null

async function load() {
  loading.value = true
  try {
    const data = await adminApi.comments({
      search: search.value,
      page: page.value,
    })
    comments.value = data.data
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

async function remove(comment) {
  if (!confirm('Удалить комментарий?')) return
  await adminApi.deleteComment(comment.id)
  await load()
}

function formatDate(date) {
  return new Date(date).toLocaleString('ru-RU')
}

onMounted(load)
</script>

<template>
  <div>
    <input
        v-model="search"
        type="text"
        placeholder="Поиск по тексту..."
        class="search"
    />

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!comments.length" class="empty">Нет комментариев</div>

    <div v-else class="list">
      <div v-for="c in comments" :key="c.id" class="comment-row">
        <div class="comment-main">
          <RouterLink :to="`/players/${c.user.id}`" class="author">
            {{ c.user.username }}
          </RouterLink>

          <span class="where">
                        в
                        <RouterLink :to="`/clans/${c.event.clan_id}`">
                            [{{ c.event.clan?.tag }}] {{ c.event.clan?.name }}
                        </RouterLink>
                        · «{{ c.event.title }}»
                    </span>

          <span class="date">{{ formatDate(c.created_at) }}</span>
        </div>

        <p class="body">{{ c.body }}</p>

        <div class="actions">
          <button class="btn-del" @click="remove(c)">
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

.comment-row {
  padding: 14px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.comment-main {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: baseline;
  margin-bottom: 8px;
  font-size: 12px;
}

.author {
  color: var(--accent-light);
  font-weight: 800;
}

.where {
  color: var(--text-dim);
}

.where a {
  color: var(--text);
  font-weight: 700;
}

.where a:hover {
  color: var(--accent-light);
}

.date {
  color: var(--text-muted);
  margin-left: auto;
}

.body {
  margin: 0 0 10px;
  color: var(--text);
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.actions {
  display: flex;
  justify-content: flex-end;
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