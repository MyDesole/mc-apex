<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { forumApi } from '@/services/forum.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const error = ref('')

const categories = ref([])
const stats = ref({})
const topics = ref([])
const pagination = ref({ current: 1, last: 1, total: 0 })

const tab = ref('forum')          // forum | all
const activeCategory = ref('')    // slug раздела
const search = ref('')
const sort = ref('activity')
const page = ref(1)

const SORTS = [
  { value: 'activity', label: 'По активности' },
  { value: 'new', label: 'Новые' },
  { value: 'popular', label: 'Популярные' },
  { value: 'unanswered', label: 'Без ответов' },
]

const activeCategoryData = computed(() =>
    categories.value.find((c) => c.slug === activeCategory.value) || null
)

async function loadForum() {
  try {
    const data = await forumApi.index()

    categories.value = data.categories ?? []
    stats.value = data.stats ?? {}
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить форум.'
  }
}

async function loadTopics() {
  try {
    const data = await forumApi.topics({
      category: activeCategory.value,
      search: search.value,
      sort: sort.value,
      page: page.value,
      perPage: 20,
    })

    topics.value = data.data ?? []
    pagination.value = {
      current: data.current_page ?? 1,
      last: data.last_page ?? 1,
      total: data.total ?? 0,
    }
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить темы.'
  }
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    await Promise.all([loadForum(), loadTopics()])
  } finally {
    loading.value = false
  }
}

watch([activeCategory, sort], () => {
  page.value = 1
  loadTopics()
})

watch(page, loadTopics)

let searchTimer = null

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadTopics()
  }, 350)
})

function openCategory(slug) {
  activeCategory.value = activeCategory.value === slug ? '' : slug
  tab.value = 'forum'
}

function resetFilters() {
  activeCategory.value = ''
  search.value = ''
  sort.value = 'activity'
  page.value = 1
}

function startTopic() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: '/forum/new' } })
    return
  }

  router.push({
    name: 'forum-new',
    query: activeCategory.value ? { category: activeCategory.value } : {},
  })
}

function formatWhen(value) {
  if (!value) return ''

  const date = new Date(value)
  const diff = (Date.now() - date.getTime()) / 1000

  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`
  if (diff < 604800) return `${Math.floor(diff / 86400)} дн назад`

  return date.toLocaleDateString('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' })
}

function categoryColor(category) {
  return category?.color || 'var(--accent)'
}

onMounted(load)
</script>

<template>
  <main class="forum">
    <header class="forum__head">
      <div class="forum__title-wrap">
        <h1 class="forum__title">Форум</h1>
        <p class="forum__subtitle">
          Общение игроков APEX: вопросы, гайды, кланы и турниры
        </p>
      </div>

      <div class="forum__actions">
        <label class="forum__search">
          <AppIcon icon="target" :size="15" />
          <input
              v-model="search"
              type="search"
              placeholder="Поиск по темам и ответам"
          >
        </label>

        <button class="btn btn-primary forum__new" type="button" @click="startTopic">
          <AppIcon icon="send" :size="15" />
          Новая тема
        </button>
      </div>
    </header>

    <!-- Статистика -->
    <section class="forum__stats">
      <div class="stat">
        <span class="stat__value">{{ stats.topics ?? 0 }}</span>
        <span class="stat__label">Тем</span>
      </div>
      <div class="stat">
        <span class="stat__value">{{ stats.replies ?? 0 }}</span>
        <span class="stat__label">Ответов</span>
      </div>
      <div class="stat">
        <span class="stat__value">{{ stats.players ?? 0 }}</span>
        <span class="stat__label">Игроков</span>
      </div>

      <RouterLink
          v-if="stats.latest_topic"
          class="stat stat--latest"
          :to="{ name: 'forum-topic', params: { id: stats.latest_topic.id } }"
      >
        <span class="stat__label">Последняя тема</span>
        <span class="stat__latest-title">{{ stats.latest_topic.title }}</span>
        <span class="stat__latest-meta">
          {{ stats.latest_topic.author }} · {{ stats.latest_topic.category }}
        </span>
      </RouterLink>
    </section>

    <p v-if="error" class="alert">{{ error }}</p>

    <!-- Разделы -->
    <section class="categories">
      <h2 class="section-title">Разделы</h2>

      <div v-if="loading" class="state">Загружаем разделы…</div>

      <div v-else class="categories__grid">
        <button
            v-for="category in categories"
            :key="category.id"
            type="button"
            class="category"
            :class="{ 'category--active': activeCategory === category.slug }"
            :style="{ '--cat-color': categoryColor(category) }"
            @click="openCategory(category.slug)"
        >
          <span class="category__icon">
            <AppIcon :icon="category.icon" :size="20" />
          </span>

          <span class="category__body">
            <span class="category__name">{{ category.name }}</span>
            <span class="category__desc">{{ category.description }}</span>
            <span class="category__meta">
              {{ category.topics_count }} тем · {{ category.replies_count }} ответов
              <template v-if="category.policy_label !== 'Все игроки'">
                · {{ category.policy_label }}
              </template>
            </span>
          </span>

          <span v-if="category.last_topic" class="category__last">
            <span class="category__last-title">{{ category.last_topic.title }}</span>
            <span class="category__last-meta">
              {{ category.last_topic.last_reply_user }} · {{ formatWhen(category.last_topic.last_reply_at) }}
            </span>
          </span>
        </button>
      </div>
    </section>

    <!-- Фильтры тем -->
    <section class="topics">
      <div class="topics__head">
        <h2 class="section-title">
          <template v-if="activeCategoryData">
            {{ activeCategoryData.name }}
          </template>
          <template v-else-if="search">
            Поиск: «{{ search }}»
          </template>
          <template v-else>
            Все темы
          </template>
        </h2>

        <div class="topics__controls">
          <select v-model="sort" class="select">
            <option v-for="option in SORTS" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <button
              v-if="activeCategory || search || sort !== 'activity'"
              class="btn btn-secondary btn-sm"
              type="button"
              @click="resetFilters"
          >
            Сбросить
          </button>
        </div>
      </div>

      <p v-if="activeCategoryData && !activeCategoryData.can_post" class="notice">
        В этом разделе темы создаёт только персонал. Отвечать можно всем.
      </p>

      <div v-if="loading" class="state">Загружаем темы…</div>

      <p v-else-if="!topics.length" class="state">
        Тем пока нет.
        <button class="link" type="button" @click="startTopic">Создать первую</button>
      </p>

      <div v-else class="topics__list">
        <RouterLink
            v-for="topic in topics"
            :key="topic.id"
            class="topic-row"
            :class="{ 'topic-row--pinned': topic.is_pinned }"
            :to="{ name: 'forum-topic', params: { id: topic.id } }"
        >
          <span class="topic-row__avatar">
            <img v-if="topic.author?.avatar_url" :src="topic.author.avatar_url" :alt="topic.author.username">
            <template v-else>
              {{ (topic.author?.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </span>

          <span class="topic-row__body">
            <span class="topic-row__flags">
              <span v-if="topic.is_pinned" class="flag flag--pin">
                <AppIcon icon="star" :size="11" /> Закреплено
              </span>
              <span v-if="topic.is_locked" class="flag flag--lock">
                <AppIcon icon="shield" :size="11" /> Закрыто
              </span>
              <span
                  v-if="topic.category"
                  class="flag"
                  :style="{ '--flag-color': topic.category.color || 'var(--accent)' }"
              >
                {{ topic.category.name }}
              </span>
            </span>

            <span class="topic-row__title">{{ topic.title }}</span>

            <span class="topic-row__meta">
              {{ topic.author?.username }}
              <template v-if="topic.author?.is_verified"> ✓</template>
              · {{ formatWhen(topic.created_at) }}
              <template v-if="topic.last_reply_user && topic.replies_count">
                · последний ответ: {{ topic.last_reply_user }}
              </template>
            </span>
          </span>

          <span class="topic-row__counters">
            <span class="counter">
              <AppIcon icon="send" :size="13" /> {{ topic.replies_count }}
            </span>
            <span class="counter">
              <AppIcon icon="target" :size="13" /> {{ topic.views }}
            </span>
            <span class="counter">
              <AppIcon icon="heart" :size="13" /> {{ topic.likes_count }}
            </span>
          </span>
        </RouterLink>
      </div>

      <div v-if="pagination.last > 1" class="pager">
        <button
            class="btn btn-secondary btn-sm"
            type="button"
            :disabled="pagination.current <= 1"
            @click="page = pagination.current - 1"
        >
          Назад
        </button>
        <span class="pager__info">
          {{ pagination.current }} / {{ pagination.last }} · всего {{ pagination.total }}
        </span>
        <button
            class="btn btn-secondary btn-sm"
            type="button"
            :disabled="pagination.current >= pagination.last"
            @click="page = pagination.current + 1"
        >
          Вперёд
        </button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.forum {
  width: min(1160px, calc(100% - 40px));
  margin: 40px auto 80px;
}

.forum__head {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 24px;
}

.forum__title {
  margin: 0 0 6px;
  font-size: 30px;
  font-weight: 900;
  letter-spacing: -0.6px;
}

.forum__subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

.forum__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.forum__search {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  color: var(--text-muted);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
  min-height: 38px;
}

.forum__search input {
  width: 210px;
  color: var(--text);
  background: transparent;
  border: 0;
  outline: none;
  font-size: 13px;
}

.forum__new {
  gap: 7px;
}

/* Статистика */
.forum__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin-bottom: 30px;
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 14px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.stat__value {
  font-size: 22px;
  font-weight: 900;
  color: var(--accent-light);
}

.stat__label {
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.stat--latest {
  grid-column: span 2;
  transition: border-color 0.2s ease;
}

.stat--latest:hover {
  border-color: var(--border-hover);
}

.stat__latest-title {
  color: var(--text);
  font-size: 14px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stat__latest-meta {
  color: var(--text-muted);
  font-size: 12px;
}

/* Разделы */
.section-title {
  margin: 0 0 14px;
  font-size: 18px;
  font-weight: 800;
}

.categories__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
  margin-bottom: 36px;
}

.category {
  display: flex;
  gap: 13px;
  align-items: flex-start;
  padding: 15px;
  text-align: left;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-left: 3px solid var(--cat-color, var(--accent));
  border-radius: 12px;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
}

.category:hover {
  background: var(--bg-card-hover);
  transform: translateY(-1px);
}

.category--active {
  border-color: var(--cat-color, var(--accent));
  background: var(--bg-card-hover);
}

.category__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  color: var(--cat-color, var(--accent-light));
  background: rgba(0, 0, 0, 0.3);
  border-radius: 10px;
  flex-shrink: 0;
}

.category__body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  flex: 1;
}

.category__name {
  color: var(--text);
  font-size: 15px;
  font-weight: 700;
}

.category__desc {
  color: var(--text-dim);
  font-size: 12px;
  line-height: 1.45;
}

.category__meta {
  color: var(--text-muted);
  font-size: 11px;
}

.category__last {
  display: none;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  max-width: 190px;
  text-align: right;
}

@media (min-width: 900px) {
  .category__last {
    display: flex;
  }
}

.category__last-title {
  color: var(--text-dim);
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 190px;
}

.category__last-meta {
  color: var(--text-muted);
  font-size: 11px;
}

/* Темы */
.topics__head {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.topics__controls {
  display: flex;
  gap: 8px;
  align-items: center;
}

.select {
  padding: 9px 12px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 13px;
  cursor: pointer;
}

.btn-sm {
  min-height: 34px;
  padding: 0 12px;
  font-size: 12px;
}

.notice {
  margin: 0 0 14px;
  padding: 10px 14px;
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.1);
  border: 1px solid rgba(99, 102, 241, 0.3);
  border-radius: 10px;
  font-size: 13px;
}

.topics__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.topic-row {
  display: flex;
  gap: 13px;
  align-items: center;
  padding: 12px 15px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 11px;
  transition: background 0.18s ease, border-color 0.18s ease;
}

.topic-row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.topic-row--pinned {
  border-left: 3px solid #fbbf24;
}

.topic-row__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  overflow: hidden;
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 9px;
  font-size: 14px;
  font-weight: 800;
  flex-shrink: 0;
}

.topic-row__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.topic-row__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.topic-row__flags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.flag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  color: var(--flag-color, var(--text-dim));
  background: rgba(255, 255, 255, 0.05);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.flag--pin {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.12);
}

.flag--lock {
  color: #9ca3af;
  background: rgba(156, 163, 175, 0.12);
}

.topic-row__title {
  color: var(--text);
  font-size: 14px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.topic-row__meta {
  color: var(--text-muted);
  font-size: 12px;
}

.topic-row__counters {
  display: flex;
  gap: 12px;
  flex-shrink: 0;
}

.counter {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--text-dim);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

/* Общее */
.state {
  padding: 40px 0;
  color: var(--text-dim);
  text-align: center;
}

.link {
  color: var(--accent-light);
  background: transparent;
  border: 0;
  font: inherit;
  cursor: pointer;
}

.link:hover {
  text-decoration: underline;
}

.alert {
  margin: 0 0 18px;
  padding: 12px 16px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.35);
  border-radius: 10px;
  font-size: 13px;
}

.pager {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
  margin-top: 18px;
}

.pager__info {
  color: var(--text-muted);
  font-size: 12px;
}

@media (max-width: 720px) {
  .forum__search input {
    width: 150px;
  }

  .topic-row__counters {
    display: none;
  }
}
</style>
