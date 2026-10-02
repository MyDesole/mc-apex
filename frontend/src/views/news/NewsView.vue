<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { newsApi } from '@/services/news/news.js'

const news = ref([])
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

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

onMounted(async () => {
  try {
    const data = await newsApi.list()
    news.value = data.data
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить новости'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="news-page">
    <header class="page-head">
      <div class="page-head__content">
        <div class="page-head__label">
          <span class="page-head__dot" />
          Новости
        </div>

        <h1>Последние события</h1>

        <p class="subtitle">
          Новости, обновления и важные события APEX TIERS
        </p>
      </div>
    </header>

    <div v-if="loading" class="state">
      <div class="spinner" />
      <span>Загрузка новостей...</span>
    </div>

    <div v-else-if="error" class="state state--error">
      <div class="state__icon">⚠️</div>
      <div class="state__title">{{ error }}</div>
      <div class="state__hint">
        Попробуйте обновить страницу
      </div>
    </div>

    <div v-else-if="!news.length" class="state">
      <div class="state__icon">📰</div>
      <div class="state__title">Новостей пока нет</div>
      <div class="state__hint">
        Загляните позже — здесь скоро появятся новые публикации
      </div>
    </div>

    <div v-else class="grid">
      <RouterLink
          v-for="item in news"
          :key="item.id"
          :to="`/news/${item.id}`"
          class="card"
          :class="{ 'card--pinned': item.is_pinned }"
      >
        <div
            class="card__cover"
            :style="item.cover_url
            ? { backgroundImage: `url(${item.cover_url})` }
            : {}
          "
        >
          <span v-if="!item.cover_url" class="card__letter">
            {{ item.title.charAt(0).toUpperCase() }}
          </span>

          <div class="card__overlay" />

          <div class="card__badges">
            <span
                v-if="item.is_pinned"
                class="badge badge--pin"
                title="Закреплено"
            >
              📌
            </span>

            <span
                class="badge"
                :class="typeClasses[item.type]"
            >
              {{ typeLabels[item.type] || 'Публикация' }}
            </span>
          </div>
        </div>

        <div class="card__body">
          <h3 class="card__title">
            {{ item.title }}
          </h3>

          <p v-if="item.excerpt" class="card__excerpt">
            {{ item.excerpt }}
          </p>

          <div class="card__meta">
            <span>
              {{ formatDate(item.published_at ?? item.created_at) }}
            </span>

            <span class="card__arrow">→</span>
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
@import "@/views/news/NewsView.css";
</style>
