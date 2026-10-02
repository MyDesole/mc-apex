<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AdminNewsForm from '@/components/admin/AdminNewsForm.vue'

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
  if (!await confirmDialog(`Удалить новость «${item.title}»?`)) return
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
@import "@/components/admin/AdminNews.css";
</style>
