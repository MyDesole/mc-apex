<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { newsApi } from '@/services/news/news.js'

const route = useRoute()

const data = ref(null)
const loading = ref(true)
const error = ref(null)

const typeLabels = {
  news: 'Новость',
  update: 'Обновление',
  event: 'Событие',
  announcement: 'Анонс',
}

const typeClasses = {
  news: 'badge--news',
  update: 'badge--update',
  event: 'badge--event',
  announcement: 'badge--announcement',
}

// Новость из визуального редактора — HTML; старые — обычный текст
const isHtml = (value) => /<[a-z][\s\S]*>/i.test(value || '')

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

async function load(id) {
  loading.value = true
  error.value = null
  data.value = null

  try {
    data.value = await newsApi.show(id)
  } catch (e) {
    error.value = e.status === 404
        ? 'Новость не найдена'
        : (e.message || 'Ошибка загрузки')
  } finally {
    loading.value = false
  }
}

watch(
    () => route.params.id,
    (newId) => {
      if (newId) load(newId)
    },
    { immediate: true }
)
</script>

<template>
  <div v-if="loading" class="state">
    <div class="spinner" />
    <span>Загрузка новости...</span>
  </div>

  <div v-else-if="error" class="state state--error">
    <div class="state__icon">
      {{ error.includes('не найдена') ? '🔎' : '⚠️' }}
    </div>

    <div class="state__code">
      {{ error.includes('не найдена') ? '404' : 'Ошибка' }}
    </div>

    <div class="state__title">
      {{ error }}
    </div>

    <RouterLink to="/news" class="back-button">
      ← Вернуться к новостям
    </RouterLink>
  </div>

  <article
      v-else-if="data?.news"
      class="news-item"
  >
    <header
        class="news-item__cover"
        :style="data.news.cover_url
        ? { backgroundImage: `url(${data.news.cover_url})` }
        : {}
      "
    >
      <div class="news-item__overlay" />

      <div class="news-item__head">
        <RouterLink
            to="/news"
            class="back-link"
        >
          ← Все новости
        </RouterLink>

        <div class="badges">
          <span
              class="badge"
              :class="typeClasses[data.news.type]"
          >
            {{ typeLabels[data.news.type] || 'Публикация' }}
          </span>

          <span
              v-if="data.news.is_pinned"
              class="badge badge--pin"
          >
            📌 Закреплено
          </span>
        </div>

        <h1 class="news-item__title">
          {{ data.news.title }}
        </h1>

        <div class="news-item__meta">
          <span class="meta-item">
            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <path
                  d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
              />
              <circle
                  cx="12"
                  cy="7"
                  r="4"
              />
            </svg>

            {{ data.news.author?.username ?? '—' }}
          </span>

          <span class="meta-item">
            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <circle
                  cx="12"
                  cy="12"
                  r="10"
              />
              <path
                  d="M12 6v6l4 2"
                  stroke-linecap="round"
              />
            </svg>

            {{ formatDate(data.news.published_at ?? data.news.created_at) }}
          </span>
        </div>
      </div>
    </header>

    <div class="news-item__body">
      <div
          v-if="data.news.excerpt"
          class="news-item__excerpt"
      >
        {{ data.news.excerpt }}
      </div>

      <!-- HTML из редактора уже очищен сервером по белому списку -->
      <div
          v-if="data.news.body && isHtml(data.news.body)"
          class="news-item__content news-prose"
          v-html="data.news.body"
      ></div>

      <div
          v-else-if="data.news.body"
          class="news-item__content news-prose"
      >
        <p v-for="(paragraph, i) in data.news.body.split(/\n{2,}/)" :key="i" class="news-item__plain">{{ paragraph }}</p>
      </div>

      <div
          v-else
          class="news-item__empty"
      >
        <span class="news-item__empty-icon">📝</span>
        Текст новости пока не добавлен
      </div>
    </div>

    <nav
        v-if="data.prev || data.next"
        class="news-nav"
    >
      <RouterLink
          v-if="data.prev"
          :to="`/news/${data.prev.id}`"
          class="nav-link"
      >
        <span class="nav-label">
          ← Предыдущая
        </span>

        <span class="nav-title">
          {{ data.prev.title }}
        </span>
      </RouterLink>

      <div
          v-else
          class="nav-link nav-link--empty"
      />

      <RouterLink
          v-if="data.next"
          :to="`/news/${data.next.id}`"
          class="nav-link nav-link--next"
      >
        <span class="nav-label">
          Следующая →
        </span>

        <span class="nav-title">
          {{ data.next.title }}
        </span>
      </RouterLink>
    </nav>
  </article>
</template>

<style scoped>
@import "@/views/news/NewsItemView.css";
</style>

<style>
/* Оформление текста новости (v-html) — не scoped, общее с редактором */
@import "@/assets/newsProse.css";
</style>
