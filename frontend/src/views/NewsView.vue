<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { newsApi } from '@/services/news.js'

const news = ref([])
const loading = ref(true)
const error = ref(null)

const typeLabels = {
  news: 'Новость',
  update: 'Обновление',
  event: 'Событие',
  announcement: 'Анонс',
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
      <div>
        <h1>Новости</h1>
        <p class="subtitle">Последние обновления APEX TIERS</p>
      </div>
    </header>

    <div v-if="loading" class="empty">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <div v-else-if="error" class="empty">
      <div class="empty__icon">⚠️</div>
      <div class="empty__title">{{ error }}</div>
    </div>

    <div v-else-if="!news.length" class="empty">
      <div class="empty__icon">📰</div>
      <div class="empty__title">Новостей пока нет</div>
      <div class="empty__hint">Заходи позже — скоро что-то будет</div>
    </div>

    <div v-else class="grid">
      <RouterLink
          v-for="n in news"
          :key="n.id"
          :to="`/news/${n.id}`"
          class="card"
          :class="{ 'card--pinned': n.is_pinned }"
      >
        <div
            class="card__cover"
            :style="n.cover_url ? { backgroundImage: `url(${n.cover_url})` } : {}"
        >
                    <span v-if="!n.cover_url" class="card__letter">
                        {{ n.title.charAt(0).toUpperCase() }}
                    </span>

          <div class="card__badges">
            <span v-if="n.is_pinned" class="badge badge--pin">📌</span>
            <span class="badge" :class="`badge--${n.type}`">
                            {{ typeLabels[n.type] }}
                        </span>
          </div>
        </div>

        <div class="card__body">
          <h3 class="card__title">{{ n.title }}</h3>

          <p v-if="n.excerpt" class="card__excerpt">
            {{ n.excerpt }}
          </p>

          <div class="card__meta">
                        <span>
                            {{ new Date(n.published_at ?? n.created_at).toLocaleDateString('ru-RU', {
                          day: '2-digit', month: 'short', year: 'numeric'
                        }) }}
                        </span>
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.news-page {
  width: min(1100px, calc(100% - 40px));
  margin: 40px auto;
}

.page-head {
  margin-bottom: 28px;
}

.page-head h1 {
  margin: 0 0 6px;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

/* Grid */
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}

.card {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.25s ease;
}

.card:hover {
  border-color: var(--border-hover);
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.card--pinned {
  border-color: rgba(250, 204, 21, 0.35);
  box-shadow: 0 0 30px rgba(250, 204, 21, 0.05);
}

.card__cover {
  position: relative;
  height: 160px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
}

.card__letter {
  font-size: 48px;
  font-weight: 900;
  color: #fff;
  opacity: 0.4;
}

.card__badges {
  position: absolute;
  top: 10px;
  left: 10px;
  right: 10px;
  display: flex;
  justify-content: space-between;
  gap: 6px;
}

.badge {
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #fff;
  background: rgba(0, 0, 0, 0.6);
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

.card__body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.card__title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  line-height: 1.3;
}

.card__excerpt {
  margin: 0;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.card__meta {
  margin-top: auto;
  padding-top: 8px;
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 600;
}

/* States */
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

@media (max-width: 600px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>