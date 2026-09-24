<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { newsApi } from '@/services/news.js'

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

async function load() {
  loading.value = true
  error.value = null

  try {
    data.value = await newsApi.show(route.params.id)
  } catch (e) {
    error.value = e.status === 404 ? 'Новость не найдена' : (e.message || 'Ошибка загрузки')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="loading">
    <div class="spinner" />
    <span>Загрузка...</span>
  </div>

  <div v-else-if="error" class="error-page">
    <div class="error-page__icon">🚫</div>
    <h1>{{ error.includes('не найдена') ? '404' : 'Ошибка' }}</h1>
    <p>{{ error }}</p>
    <RouterLink to="/news" class="btn-back">← Все новости</RouterLink>
  </div>

  <article v-else-if="data?.news" class="news-item">
    <!-- HEADER -->
    <header
        class="news-item__cover"
        :style="data.news.cover_url ? { backgroundImage: `url(${data.news.cover_url})` } : {}"
    >
      <div class="news-item__overlay" />

      <div class="news-item__head">
        <RouterLink to="/news" class="back-link">
          ← Все новости
        </RouterLink>

        <div class="badges">
                    <span class="badge" :class="`badge--${data.news.type}`">
                        {{ typeLabels[data.news.type] }}
                    </span>
          <span v-if="data.news.is_pinned" class="badge badge--pin">
                        📌 Закреплено
                    </span>
        </div>

        <h1 class="news-item__title">{{ data.news.title }}</h1>

        <div class="news-item__meta">
                    <span class="meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        {{ data.news.author?.username ?? '—' }}
                    </span>
          <span class="meta-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 6v6l4 2" stroke-linecap="round" />
                        </svg>
                        {{ new Date(data.news.published_at ?? data.news.created_at).toLocaleDateString('ru-RU', {
            day: '2-digit', month: 'long', year: 'numeric'
          }) }}
                    </span>
        </div>
      </div>
    </header>

    <!-- BODY -->
    <div class="news-item__body">
      <div v-if="data.news.excerpt" class="news-item__excerpt">
        {{ data.news.excerpt }}
      </div>

      <div v-if="data.news.body" class="news-item__content">
        {{ data.news.body }}
      </div>

      <div v-else class="news-item__empty">
        Текст новости пуст
      </div>
    </div>

    <!-- NAV -->
    <nav v-if="data.prev || data.next" class="news-nav">
      <RouterLink
          v-if="data.prev"
          :to="`/news/${data.prev.id}`"
          class="nav-link nav-link--prev"
      >
        <span class="nav-label">← Предыдущая</span>
        <span class="nav-title">{{ data.prev.title }}</span>
      </RouterLink>

      <div v-else class="nav-link nav-link--empty" />

      <RouterLink
          v-if="data.next"
          :to="`/news/${data.next.id}`"
          class="nav-link nav-link--next"
      >
        <span class="nav-label">Следующая →</span>
        <span class="nav-title">{{ data.next.title }}</span>
      </RouterLink>
    </nav>
  </article>
</template>

<style scoped>
/* === STATES === */

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
  font-size: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 100px 20px;
  text-align: center;
}

.error-page__icon {
  font-size: 56px;
  opacity: 0.5;
  margin-bottom: 8px;
}

.error-page h1 {
  font-size: 72px;
  color: var(--danger);
  margin: 0;
  letter-spacing: -2px;
}

.error-page p {
  color: var(--text-dim);
  margin: 0 0 20px;
  font-size: 15px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  padding: 11px 22px;
  color: #fff;
  background: var(--accent);
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.2s;
}

.btn-back:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

/* === NEWS ITEM === */

.news-item {
  width: min(820px, calc(100% - 40px));
  margin: 40px auto;
}

.news-item__cover {
  position: relative;
  min-height: 320px;
  padding: 32px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  border-radius: 20px;
  overflow: hidden;
  display: flex;
  align-items: flex-end;
  margin-bottom: 28px;
}

.news-item__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(10, 10, 15, 0.95), rgba(10, 10, 15, 0.35));
  pointer-events: none;
}

.news-item__head {
  position: relative;
  width: 100%;
}

.back-link {
  display: inline-block;
  margin-bottom: 20px;
  color: #d1d1db;
  font-size: 13px;
  font-weight: 700;
  transition: color 0.2s;
}

.back-link:hover {
  color: #fff;
}

.badges {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}

.badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #fff;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
}

.badge--pin {
  background: rgba(250, 204, 21, 0.9);
  color: #000;
}

.badge--news { color: #a78bfa; }
.badge--update { color: #4ade80; }
.badge--event { color: #f472b6; }
.badge--announcement { color: #fbbf24; }

.news-item__title {
  margin: 0 0 14px;
  font-size: 34px;
  font-weight: 900;
  color: #fff;
  line-height: 1.15;
  letter-spacing: -1px;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
}

.news-item__meta {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  color: #d1d1db;
  font-size: 13px;
  font-weight: 600;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.meta-item svg {
  opacity: 0.8;
}

/* === BODY === */

.news-item__body {
  padding: 32px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 20px;
}

.news-item__excerpt {
  margin-bottom: 28px;
  padding: 16px 20px;
  color: var(--text-dim);
  font-size: 15px;
  line-height: 1.6;
  background: rgba(124, 58, 237, 0.05);
  border-left: 3px solid var(--accent);
  border-radius: 8px;
  font-style: italic;
}

.news-item__content {
  color: var(--text);
  font-size: 15px;
  line-height: 1.8;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.news-item__empty {
  padding: 40px;
  text-align: center;
  color: var(--text-muted);
  font-style: italic;
  font-size: 14px;
}

/* === NAV === */

.news-nav {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 24px;
}

.nav-link {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: all 0.2s;
  min-width: 0;
}

.nav-link:hover {
  border-color: var(--accent);
  background: var(--bg-card-hover);
  transform: translateY(-2px);
}

.nav-link--empty {
  pointer-events: none;
  opacity: 0;
}

.nav-link--next {
  text-align: right;
}

.nav-label {
  font-size: 11px;
  color: var(--accent-light);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.nav-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* === MOBILE === */

@media (max-width: 600px) {
  .news-item__cover {
    min-height: 240px;
    padding: 20px;
    border-radius: 16px;
  }

  .news-item__title {
    font-size: 22px;
  }

  .news-item__body {
    padding: 20px;
    border-radius: 16px;
  }

  .news-nav {
    grid-template-columns: 1fr;
  }

  .nav-link--next {
    text-align: left;
  }
}
</style>