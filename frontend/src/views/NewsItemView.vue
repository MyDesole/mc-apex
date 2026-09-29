<script setup>
import { onMounted, ref, watch } from 'vue'
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

const typeClasses = {
  news: 'badge--news',
  update: 'badge--update',
  event: 'badge--event',
  announcement: 'badge--announcement',
}

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

      <div
          v-if="data.news.body"
          class="news-item__content"
      >
        {{ data.news.body }}
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
/* Состояния */

.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: min(820px, calc(100% - 40px));
  margin: 40px auto;
  padding: 70px 20px;
  border: 1px dashed var(--border);
  border-radius: 16px;
  background: var(--bg-card);
  color: var(--text-dim);
  font-size: 13px;
  text-align: center;
}

.state--error {
  border-color: rgba(248, 113, 113, 0.2);
}

.state__icon {
  margin-bottom: 4px;
  font-size: 42px;
  opacity: 0.6;
}

.state__code {
  margin-top: 2px;
  color: var(--danger);
  font-size: 48px;
  font-weight: 900;
  line-height: 1;
  letter-spacing: -2px;
}

.state__title {
  color: var(--text);
  font-size: 16px;
  font-weight: 800;
}

.spinner {
  width: 30px;
  height: 30px;
  margin-bottom: 4px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.back-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 12px;
  padding: 10px 18px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--bg-card);
  color: var(--text);
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  transition:
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.back-button:hover {
  border-color: var(--accent);
  background: var(--bg-card-hover);
  transform: translateY(-1px);
}

/* Новость */

.news-item {
  width: min(820px, calc(100% - 40px));
  margin: 40px auto;
}

/* Обложка */

.news-item__cover {
  position: relative;
  min-height: 330px;
  margin-bottom: 20px;
  padding: 28px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 18px;
  background:
      radial-gradient(
          circle at 20% 20%,
          rgba(255, 255, 255, 0.1),
          transparent 30%
      ),
      linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
}

.news-item__overlay {
  position: absolute;
  inset: 0;
  background:
      linear-gradient(
          to top,
          rgba(10, 10, 15, 0.96),
          rgba(10, 10, 15, 0.45)
      );
  pointer-events: none;
}

.news-item__head {
  position: relative;
  z-index: 1;
  width: 100%;
}

.back-link {
  display: inline-flex;
  align-items: center;
  margin-bottom: 18px;
  color: #d1d1db;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: #fff;
}

/* Метки */

.badges {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  margin-bottom: 12px;
}

.badge {
  padding: 4px 9px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.58);
  backdrop-filter: blur(8px);
  color: #fff;
  font-size: 10px;
  font-weight: 900;
  line-height: 1;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.badge--pin {
  border-color: transparent;
  background: rgba(250, 204, 21, 0.92);
  color: #000;
}

.badge--news {
  color: #a78bfa;
}

.badge--update {
  color: #4ade80;
}

.badge--event {
  color: #f472b6;
}

.badge--announcement {
  color: #fbbf24;
}

/* Заголовок */

.news-item__title {
  max-width: 720px;
  margin: 0 0 14px;
  color: #fff;
  font-size: 34px;
  font-weight: 900;
  line-height: 1.15;
  letter-spacing: -1px;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
}

/* Метаданные */

.news-item__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  color: #d1d1db;
  font-size: 12px;
  font-weight: 600;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.meta-item svg {
  opacity: 0.75;
}

/* Содержимое */

.news-item__body {
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--bg-card);
}

.news-item__excerpt {
  margin-bottom: 24px;
  padding: 14px 16px;
  border-left: 3px solid var(--accent);
  border-radius: 8px;
  background: rgba(124, 58, 237, 0.05);
  color: var(--text-dim);
  font-size: 14px;
  font-style: italic;
  line-height: 1.6;
}

.news-item__content {
  color: var(--text);
  font-size: 14px;
  line-height: 1.8;
  white-space: pre-wrap;
  overflow-wrap: break-word;
}

.news-item__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 36px 20px;
  color: var(--text-muted);
  font-size: 13px;
  font-style: italic;
  text-align: center;
}

.news-item__empty-icon {
  font-size: 30px;
  opacity: 0.5;
}

/* Навигация */

.news-nav {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}

.nav-link {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 5px;
  padding: 15px 18px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: var(--bg-card);
  color: inherit;
  text-decoration: none;
  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease;
}

.nav-link:hover {
  border-color: var(--border-hover);
  background: var(--bg-card-hover);
  transform: translateY(-2px);
}

.nav-link--next {
  text-align: right;
}

.nav-link--empty {
  opacity: 0;
  pointer-events: none;
}

.nav-label {
  color: var(--accent-light);
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.nav-title {
  overflow: hidden;
  color: var(--text);
  font-size: 13px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Мобильная версия */

@media (max-width: 600px) {
  .news-item,
  .state {
    width: min(100% - 24px, 820px);
    margin: 24px auto;
  }

  .news-item__cover {
    min-height: 260px;
    margin-bottom: 16px;
    padding: 20px;
    border-radius: 16px;
  }

  .news-item__title {
    font-size: 24px;
    letter-spacing: -0.5px;
  }

  .news-item__meta {
    gap: 10px;
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

  .nav-link--empty {
    display: none;
  }
}
</style>