<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/admin.js'
import AdminNewsForm from './AdminNewsForm.vue'

const list = ref([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await adminApi.newsList()
    list.value = data.data
  } finally {
    loading.value = false
  }
}

async function destroy(item) {
  if (!confirm(`Удалить новость «${item.title}»?`)) return
  await adminApi.destroyNews(item.id)
  await load()
}

async function togglePublish(item) {
  await adminApi.updateNews(item.id, { is_published: !item.is_published })
  await load()
}

function openCreate() {
  editing.value = null
  showForm.value = true
}

function openEdit(item) {
  editing.value = item
  showForm.value = true
}

function onUpdated() {
  showForm.value = false
  load()
}

const typeLabels = {
  news: 'Новость',
  update: 'Обновление',
  event: 'Событие',
  announcement: 'Анонс',
}

function formatDate(date) {
  return new Date(date).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div>
    <!-- HEAD -->
    <div class="head">
      <div>
        <h2>Новости</h2>
        <p class="subtitle">
          Всего: <b>{{ list.length }}</b>
        </p>
      </div>

      <button class="btn-create" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M12 5v14M5 12h14" stroke-linecap="round" />
        </svg>
        Создать новость
      </button>
    </div>

    <!-- STATES -->
    <div v-if="loading" class="empty">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <div v-else-if="!list.length" class="empty">
      <div class="empty__icon">📰</div>
      <div class="empty__title">Новостей пока нет</div>
      <div class="empty__hint">Создай первую новость, чтобы она появилась на главной</div>
      <button class="btn-create mt" @click="openCreate">
        + Создать новость
      </button>
    </div>

    <!-- LIST -->
    <div v-else class="list">
      <article
          v-for="n in list"
          :key="n.id"
          class="row"
          :class="{
                    'row--pinned': n.is_pinned,
                    'row--draft': !n.is_published,
                }"
      >
        <!-- Обложка -->
        <div
            class="row__cover"
            :style="n.cover_url ? { backgroundImage: `url(${n.cover_url})` } : {}"
        >
                    <span v-if="!n.cover_url" class="cover-letter">
                        {{ n.title.charAt(0).toUpperCase() }}
                    </span>

          <div class="cover-badges">
                        <span
                            class="badge"
                            :class="`type-${n.type}`"
                        >
                            {{ typeLabels[n.type] }}
                        </span>
          </div>
        </div>

        <!-- Инфо -->
        <div class="row__info">
          <div class="row__head">
            <span v-if="n.is_pinned" class="pin" title="Закреплено">📌</span>
            <h3 class="row__title">{{ n.title }}</h3>
          </div>

          <p v-if="n.excerpt" class="row__excerpt">
            {{ n.excerpt }}
          </p>

          <div class="row__meta">
                        <span class="meta-item">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            {{ n.author?.username ?? '—' }}
                        </span>

            <span class="meta-item">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 6v6l4 2" stroke-linecap="round" />
                            </svg>
                            {{ formatDate(n.created_at) }}
                        </span>

            <span
                v-if="!n.is_published"
                class="meta-item draft-badge"
            >
                            <span class="dot" />
                            ЧЕРНОВИК
                        </span>
          </div>
        </div>

        <!-- Действия -->
        <div class="row__actions">
          <button
              class="btn-action"
              :title="n.is_published ? 'Снять с публикации' : 'Опубликовать'"
              @click="togglePublish(n)"
          >
            <svg v-if="n.is_published" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke-linecap="round" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke-linecap="round" />
              <path d="M1 1l22 22" stroke-linecap="round" />
            </svg>
          </button>

          <button class="btn-action" title="Редактировать" @click="openEdit(n)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>

          <button class="btn-action danger" title="Удалить" @click="destroy(n)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" stroke-linecap="round" />
            </svg>
          </button>
        </div>
      </article>
    </div>

    <AdminNewsForm
        v-if="showForm"
        :news="editing"
        @close="showForm = false"
        @updated="onUpdated"
    />
  </div>
</template>

<style scoped>
/* === HEAD === */

.head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}

.head h2 {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.3px;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 13px;
}

.subtitle b {
  color: var(--text);
  font-weight: 900;
}

.btn-create {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-create:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35);
}

.btn-create.mt {
  margin-top: 12px;
}

/* === STATES === */

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 60px 20px;
  text-align: center;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
  color: var(--text-dim);
  font-size: 13px;
}

.empty__icon {
  font-size: 42px;
  opacity: 0.6;
  margin-bottom: 4px;
}

.empty__title {
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
}

.empty__hint {
  font-size: 13px;
  color: var(--text-dim);
  max-width: 320px;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* === LIST === */

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.row {
  position: relative;
  display: grid;
  grid-template-columns: 120px 1fr auto;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.row--pinned {
  border-color: rgba(250, 204, 21, 0.3);
  background: linear-gradient(90deg, rgba(250, 204, 21, 0.04), var(--bg-card) 40%);
}

.row--draft {
  opacity: 0.7;
}

/* Обложка */

.row__cover {
  position: relative;
  height: 72px;
  border-radius: 10px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}

.cover-letter {
  font-size: 28px;
  font-weight: 900;
  color: #fff;
  opacity: 0.5;
}

.cover-badges {
  position: absolute;
  top: 6px;
  left: 6px;
  right: 6px;
  display: flex;
  gap: 4px;
}

.badge {
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #fff;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
}

.badge.type-news { color: #a78bfa; }
.badge.type-update { color: #4ade80; }
.badge.type-event { color: #f472b6; }
.badge.type-announcement { color: #fbbf24; }

/* Инфо */

.row__info {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.row__head {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.pin {
  font-size: 14px;
  flex-shrink: 0;
}

.row__title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row__excerpt {
  margin: 0;
  color: var(--text-dim);
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row__meta {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  margin-top: 2px;
  font-size: 11px;
  color: var(--text-muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.meta-item svg {
  opacity: 0.7;
}

.draft-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.1);
  border: 1px solid rgba(251, 191, 36, 0.25);
  border-radius: 999px;
  font-weight: 800;
  letter-spacing: 0.4px;
}

.draft-badge .dot {
  width: 5px;
  height: 5px;
  background: #fbbf24;
  border-radius: 50%;
  box-shadow: 0 0 6px #fbbf24;
}

/* Действия */

.row__actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.btn-action {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-action:hover {
  color: var(--text);
  border-color: var(--border-hover);
  background: rgba(255, 255, 255, 0.03);
}

.btn-action.danger:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

/* === АДАПТИВ === */

@media (max-width: 700px) {
  .head {
    flex-direction: column;
  }

  .btn-create {
    width: 100%;
    justify-content: center;
  }

  .row {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .row__cover {
    height: 120px;
  }

  .row__actions {
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid var(--border);
  }
}
</style>