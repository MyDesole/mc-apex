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
const pagination = ref({
  current: 1,
  last: 1,
  total: 0,
})

const activeCategory = ref('')
const search = ref('')
const sort = ref('activity')
const page = ref(1)

const SORTS = [
  { value: 'activity', label: 'Активные' },
  { value: 'new', label: 'Новые' },
  { value: 'popular', label: 'Популярные' },
  { value: 'unanswered', label: 'Без ответов' },
]

const activeCategoryData = computed(() =>
    categories.value.find((item) => item.slug === activeCategory.value) || null
)

const pageTitle = computed(() => {
  if (activeCategoryData.value) return activeCategoryData.value.name
  if (search.value.trim()) return `Поиск`
  return 'Последние обсуждения'
})

const hasFilters = computed(() =>
    Boolean(activeCategory.value || search.value || sort.value !== 'activity')
)

const visibleCategories = computed(() =>
    categories.value.filter((category) => category.topics_count > 0)
)

async function loadForum() {
  const data = await forumApi.index()

  categories.value = data.categories ?? []
  stats.value = data.stats ?? {}
}

async function loadTopics() {
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
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    await Promise.all([
      loadForum(),
      loadTopics(),
    ])
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить форум.'
  } finally {
    loading.value = false
  }
}

function selectCategory(slug) {
  activeCategory.value =
      activeCategory.value === slug ? '' : slug

  page.value = 1
}

function resetFilters() {
  activeCategory.value = ''
  search.value = ''
  sort.value = 'activity'
  page.value = 1
}

function startTopic() {
  if (!auth.isAuthenticated) {
    router.push({
      name: 'login',
      query: { redirect: '/forum/new' },
    })
    return
  }

  router.push({
    name: 'forum-new',
    query: activeCategory.value
        ? { category: activeCategory.value }
        : {},
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

  return date.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function categoryColor(category) {
  return category?.color || 'var(--accent)'
}

function categoryIcon(category) {
  return category?.icon || 'globe'
}

function topicAuthor(topic) {
  const author = topic?.author
  const username = topic?.user?.username

  if (typeof author === 'string' && author.trim()) {
    return author.trim()
  }

  // Автор приходит объектом карточки игрока
  if (author && typeof author === 'object' && author.username) {
    return author.username
  }

  if (typeof topic?.author_username === 'string' && topic.author_username.trim()) {
    return topic.author_username.trim()
  }

  if (typeof username === 'string' && username.trim()) {
    return username.trim()
  }

  return 'Игрок'
}
function topicReplyCount(topic) {
  return topic.replies_count ?? topic.replies ?? 0
}

watch([activeCategory, sort], () => {
  page.value = 1
  loadTopics().catch((e) => {
    error.value = e.message || 'Не удалось загрузить темы.'
  })
})

watch(page, () => {
  loadTopics().catch((e) => {
    error.value = e.message || 'Не удалось загрузить темы.'
  })
})

let searchTimer = null

watch(search, () => {
  clearTimeout(searchTimer)

  searchTimer = setTimeout(() => {
    page.value = 1

    loadTopics().catch((e) => {
      error.value = e.message || 'Не удалось загрузить темы.'
    })
  }, 350)
})

onMounted(load)
</script>

<template>
  <main class="forum-page">
    <!-- HERO -->
    <section class="forum-hero">
      <div class="forum-hero__noise"></div>
      <div class="forum-hero__orb forum-hero__orb--one"></div>
      <div class="forum-hero__orb forum-hero__orb--two"></div>

      <div class="forum-hero__inner">
        <div class="hero-copy">
          <div class="hero-kicker">
            <span></span>
            APEX COMMUNITY
          </div>

          <h1>
            Место, где
            <strong>игроки говорят.</strong>
          </h1>

          <p>
            Обсуждай сервер, делись опытом, находи тиммейтов
            и задавай вопросы другим игрокам APEX.
          </p>
        </div>

        <div class="hero-side">
          <div class="hero-stat">
            <strong>{{ stats.topics ?? 0 }}</strong>
            <span>тем</span>
          </div>

          <div class="hero-stat">
            <strong>{{ stats.replies ?? 0 }}</strong>
            <span>ответов</span>
          </div>

          <div class="hero-stat">
            <strong>{{ stats.players ?? 0 }}</strong>
            <span>игроков</span>
          </div>
        </div>
      </div>
    </section>

    <!-- TOOLBAR -->
    <section class="forum-toolbar">
      <div class="toolbar-search">
        <AppIcon icon="target" :size="16" />

        <input
            v-model="search"
            type="search"
            placeholder="Найти тему..."
        >

        <kbd>⌘ K</kbd>
      </div>

      <button
          class="create-topic"
          type="button"
          @click="startTopic"
      >
        <AppIcon icon="send" :size="15" />
        Новая тема
      </button>
    </section>

    <!-- ERROR -->
    <div v-if="error" class="error-box">
      <AppIcon icon="alert" :size="15" />
      {{ error }}
    </div>

    <!-- CATEGORIES -->
    <section class="categories-section">
      <div class="section-heading">
        <div>
          <span class="section-eyebrow">НАВИГАЦИЯ</span>
          <h2>Разделы</h2>
        </div>

        <span class="section-count">
          {{ categories.length }} разделов
        </span>
      </div>

      <div class="category-list">
        <button
            v-for="category in visibleCategories"
            :key="category.id"
            class="category-card"
            :class="{
            'category-card--active':
              activeCategory === category.slug,
          }"
            :style="{ '--category-color': categoryColor(category) }"
            type="button"
            @click="selectCategory(category.slug)"
        >
          <span class="category-card__icon">
            <AppIcon
                :icon="categoryIcon(category)"
                :size="19"
            />
          </span>

          <span class="category-card__main">
            <strong>{{ category.name }}</strong>
            <span>{{ category.description }}</span>
          </span>

          <span class="category-card__count">
            <strong>{{ category.topics_count }}</strong>
            <small>тем</small>
          </span>

          <span class="category-card__arrow">
            →
          </span>
        </button>
      </div>
    </section>

    <!-- TOPICS -->
    <section class="topics-section">
      <div class="topics-heading">
        <div>
          <span class="section-eyebrow">СООБЩЕСТВО</span>

          <h2>
            {{ pageTitle }}

            <span v-if="pagination.total">
              {{ pagination.total }}
            </span>
          </h2>
        </div>

        <button
            v-if="hasFilters"
            class="reset-btn"
            type="button"
            @click="resetFilters"
        >
          Сбросить
        </button>
      </div>

      <!-- SORT -->
      <div class="topic-controls">
        <div class="sort-tabs">
          <button
              v-for="item in SORTS"
              :key="item.value"
              type="button"
              :class="{ active: sort === item.value }"
              @click="sort = item.value"
          >
            {{ item.label }}
          </button>
        </div>

        <div
            v-if="activeCategoryData"
            class="active-filter"
            :style="{
            '--category-color': categoryColor(activeCategoryData),
          }"
        >
          <span></span>
          {{ activeCategoryData.name }}
        </div>
      </div>

      <!-- LOADING -->
      <div v-if="loading" class="topic-list topic-list--loading">
        <div
            v-for="n in 6"
            :key="n"
            class="topic-skeleton"
        >
          <span></span>
          <div>
            <i></i>
            <i></i>
          </div>
          <em></em>
        </div>
      </div>

      <!-- EMPTY -->
      <div
          v-else-if="!topics.length"
          class="empty"
      >
        <div class="empty__icon">
          <AppIcon icon="search" :size="22" />
        </div>

        <h3>Здесь пока тихо</h3>

        <p>
          {{ search
            ? 'По вашему запросу ничего не найдено.'
            : 'Создай первую тему и начни обсуждение.' }}
        </p>

        <button
            v-if="search || activeCategory"
            type="button"
            class="empty__button"
            @click="resetFilters"
        >
          Показать все темы
        </button>
      </div>

      <!-- TOPIC LIST -->
      <div v-else class="topic-list">
        <RouterLink
            v-for="(topic, index) in topics"
            :key="topic.id"
            class="topic"
            :to="{
            name: 'forum-topic',
            params: { id: topic.id },
          }"
        >
          <div class="topic__index">
            {{ String((pagination.current - 1) * 20 + index + 1).padStart(2, '0') }}
          </div>

          <div class="topic__main">
            <div class="topic__meta">
              <span
                  class="topic__category"
                  :style="{
                  '--category-color': categoryColor(topic.category),
                }"
              >
                {{ topic.category?.name || topic.category_name || 'Форум' }}
              </span>

              <span v-if="topic.is_pinned" class="topic__badge">
                Закреплено
              </span>

              <span v-if="topic.is_locked" class="topic__badge topic__badge--muted">
                Закрыто
              </span>
            </div>

            <h3>{{ topic.title }}</h3>

            <div class="topic__author">
              <span class="topic-avatar">
                <img
                    v-if="topic.author_avatar"
                    :src="topic.author_avatar"
                    :alt="topicAuthor(topic)"
                >
                <template v-else>
                  {{ topicAuthor(topic).charAt(0).toUpperCase() }}
                </template>
              </span>

              <span>
                {{ topicAuthor(topic) }}
              </span>

              <i>·</i>

              <span>
                {{ formatWhen(topic.created_at) }}
              </span>
            </div>
          </div>

          <div class="topic__activity">
            <strong>{{ topicReplyCount(topic) }}</strong>
            <span>ответов</span>
          </div>

          <div class="topic__last">
            <span>Последняя активность</span>
            <strong>
              {{ formatWhen(topic.last_reply_at || topic.updated_at) }}
            </strong>
            <small>
              {{ topic.last_reply_user.username || topicAuthor(topic) }}
            </small>
          </div>

          <span class="topic__arrow">→</span>
        </RouterLink>
      </div>

      <!-- PAGINATION -->
      <div
          v-if="pagination.last > 1"
          class="pagination"
      >
        <button
            type="button"
            :disabled="pagination.current <= 1"
            @click="page--"
        >
          ←
        </button>

        <span>
          {{ pagination.current }}
          <i>/</i>
          {{ pagination.last }}
        </span>

        <button
            type="button"
            :disabled="pagination.current >= pagination.last"
            @click="page++"
        >
          →
        </button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.forum-page {
  width: min(1180px, calc(100% - 40px));
  margin: 28px auto 90px;
}

/* ───────────────────────── HERO ───────────────────────── */

.forum-hero {
  position: relative;
  min-height: 300px;
  overflow: hidden;
  background:
      radial-gradient(
          circle at 75% 20%,
          rgba(124, 58, 237, 0.2),
          transparent 35%
      ),
      linear-gradient(
          135deg,
          var(--bg-card),
          var(--bg)
      );
  border: 1px solid var(--border);
  border-radius: 24px;
}

.forum-hero::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
      linear-gradient(
          120deg,
          transparent 0%,
          rgba(255, 255, 255, 0.025) 50%,
          transparent 100%
      );
}

.forum-hero__noise {
  position: absolute;
  inset: 0;
  opacity: 0.04;
  background-image:
      radial-gradient(#fff 0.6px, transparent 0.6px);
  background-size: 7px 7px;
}

.forum-hero__orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(2px);
  pointer-events: none;
}

.forum-hero__orb--one {
  width: 300px;
  height: 300px;
  right: -100px;
  top: -150px;
  background: rgba(124, 58, 237, 0.12);
}

.forum-hero__orb--two {
  width: 180px;
  height: 180px;
  right: 250px;
  bottom: -130px;
  background: rgba(168, 85, 247, 0.08);
}

.forum-hero__inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  min-height: 300px;
  padding: 48px;
}

.hero-copy {
  max-width: 650px;
}

.hero-kicker,
.section-eyebrow {
  display: flex;
  gap: 8px;
  align-items: center;
  color: var(--accent-light);
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 1.6px;
}

.hero-kicker span {
  width: 24px;
  height: 1px;
  background: var(--accent);
}

.hero-copy h1 {
  margin: 18px 0 12px;
  color: var(--text);
  font-size: clamp(34px, 5vw, 58px);
  line-height: 0.98;
  letter-spacing: -2.5px;
  font-weight: 900;
}

.hero-copy h1 strong {
  display: block;
  color: transparent;
  background: linear-gradient(
      100deg,
      #fff,
      var(--accent-light)
  );
  -webkit-background-clip: text;
  background-clip: text;
}

.hero-copy p {
  max-width: 530px;
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
  line-height: 1.7;
}

.hero-side {
  display: flex;
  gap: 34px;
  padding-bottom: 3px;
}

.hero-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.hero-stat strong {
  color: var(--text);
  font-size: 22px;
  font-weight: 900;
}

.hero-stat span {
  color: var(--text-muted);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

/* ───────────────────────── TOOLBAR ───────────────────────── */

.forum-toolbar {
  display: flex;
  gap: 12px;
  margin: 18px 0 48px;
}

.toolbar-search {
  display: flex;
  flex: 1;
  gap: 10px;
  align-items: center;
  min-width: 0;
  height: 46px;
  padding: 0 14px;
  color: var(--text-muted);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s ease;
}

.toolbar-search:focus-within {
  border-color: var(--accent);
}

.toolbar-search input {
  flex: 1;
  min-width: 0;
  color: var(--text);
  background: transparent;
  border: 0;
  outline: 0;
  font: inherit;
  font-size: 13px;
}

.toolbar-search input::placeholder {
  color: var(--text-muted);
}

.toolbar-search kbd {
  padding: 3px 6px;
  color: var(--text-muted);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 5px;
  font-size: 10px;
}

.create-topic {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  justify-content: center;
  padding: 0 18px;
  color: #fff;
  background: var(--accent);
  border: 1px solid transparent;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  transition:
      transform 0.2s ease,
      box-shadow 0.2s ease;
}

.create-topic:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 25px rgba(124, 58, 237, 0.25);
}

/* ───────────────────────── SECTIONS ───────────────────────── */

.section-heading,
.topics-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 18px;
}

.section-eyebrow {
  margin-bottom: 6px;
  color: var(--text-muted);
}

.section-heading h2,
.topics-heading h2 {
  display: flex;
  gap: 9px;
  align-items: center;
  margin: 0;
  color: var(--text);
  font-size: 21px;
  font-weight: 900;
  letter-spacing: -0.5px;
}

.topics-heading h2 span {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 7px;
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 10px;
}

.section-count {
  color: var(--text-muted);
  font-size: 11px;
}

/* ───────────────────────── CATEGORIES ───────────────────────── */

.categories-section {
  margin-bottom: 52px;
}

.category-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 9px;
}

.category-card {
  position: relative;
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto 16px;
  gap: 13px;
  align-items: center;
  min-width: 0;
  padding: 14px;
  color: var(--text);
  text-align: left;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
  cursor: pointer;
  transition:
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.category-card:hover {
  transform: translateY(-1px);
  border-color: var(--border-hover);
  background: var(--bg-card-hover);
}

.category-card--active {
  border-color: var(--category-color);
  box-shadow:
      inset 3px 0 0 var(--category-color),
      0 8px 30px rgba(0, 0, 0, 0.12);
}

.category-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  color: var(--category-color);
  background: color-mix(
      in srgb,
      var(--category-color) 10%,
      transparent
  );
  border: 1px solid color-mix(
      in srgb,
      var(--category-color) 22%,
      transparent
  );
  border-radius: 11px;
}

.category-card__main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 3px;
}

.category-card__main strong {
  overflow: hidden;
  color: var(--text);
  font-size: 13px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.category-card__main span {
  overflow: hidden;
  color: var(--text-muted);
  font-size: 11px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.category-card__count {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.category-card__count strong {
  color: var(--text-dim);
  font-size: 13px;
}

.category-card__count small {
  color: var(--text-muted);
  font-size: 9px;
}

.category-card__arrow {
  color: var(--text-muted);
  transition: transform 0.2s ease;
}

.category-card:hover .category-card__arrow {
  transform: translateX(3px);
  color: var(--text);
}

/* ───────────────────────── TOPICS ───────────────────────── */

.topics-section {
  margin-bottom: 30px;
}

.reset-btn {
  padding: 7px 10px;
  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 11px;
  cursor: pointer;
}

.reset-btn:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.topic-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 12px;
}

.sort-tabs {
  display: flex;
  gap: 3px;
  padding: 3px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
}

.sort-tabs button {
  padding: 7px 11px;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.sort-tabs button:hover {
  color: var(--text);
}

.sort-tabs button.active {
  color: var(--text);
  background: var(--bg);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

.active-filter {
  display: inline-flex;
  gap: 7px;
  align-items: center;
  color: var(--text-dim);
  font-size: 11px;
}

.active-filter span {
  width: 6px;
  height: 6px;
  background: var(--category-color);
  border-radius: 50%;
  box-shadow: 0 0 8px var(--category-color);
}

/* ───────────────────────── TOPIC ROW ───────────────────────── */

.topic-list {
  overflow: hidden;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 15px;
}

.topic {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) 70px 145px 20px;
  gap: 18px;
  align-items: center;
  min-height: 88px;
  padding: 13px 17px;
  color: inherit;
  text-decoration: none;
  border-bottom: 1px solid var(--border);
  transition: background 0.18s ease;
}

.topic:last-child {
  border-bottom: 0;
}

.topic:hover {
  background: var(--bg-card-hover);
}

.topic__index {
  color: var(--text-muted);
  font-family: ui-monospace, monospace;
  font-size: 10px;
}

.topic__main {
  min-width: 0;
}

.topic__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  margin-bottom: 4px;
}

.topic__category {
  color: var(--category-color, var(--accent-light));
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.topic__badge {
  padding: 2px 6px;
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 4px;
  font-size: 8px;
  font-weight: 800;
  text-transform: uppercase;
}

.topic__badge--muted {
  color: var(--text-muted);
  background: var(--bg);
  border-color: var(--border);
}

.topic h3 {
  overflow: hidden;
  margin: 0 0 6px;
  color: var(--text);
  font-size: 13px;
  font-weight: 750;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.topic__author {
  display: flex;
  gap: 6px;
  align-items: center;
  color: var(--text-muted);
  font-size: 10px;
}

.topic__author i {
  font-style: normal;
  opacity: 0.5;
}

.topic-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  overflow: hidden;
  color: #fff;
  background: linear-gradient(135deg, var(--accent-light), var(--accent));
  border-radius: 6px;
  font-size: 8px;
  font-weight: 900;
}

.topic-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.topic__activity {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.topic__activity strong {
  color: var(--text);
  font-size: 14px;
}

.topic__activity span {
  color: var(--text-muted);
  font-size: 9px;
}

.topic__last {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 3px;
  padding-left: 15px;
  border-left: 1px solid var(--border);
}

.topic__last span {
  color: var(--text-muted);
  font-size: 8px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.topic__last strong {
  color: var(--text-dim);
  font-size: 10px;
  font-weight: 700;
}

.topic__last small {
  overflow: hidden;
  color: var(--text-muted);
  font-size: 9px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.topic__arrow {
  color: var(--text-muted);
  transition: transform 0.2s ease;
}

.topic:hover .topic__arrow {
  color: var(--accent-light);
  transform: translateX(3px);
}

/* ───────────────────────── EMPTY / LOADING ───────────────────────── */

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 70px 20px;
  text-align: center;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 15px;
}

.empty__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  margin-bottom: 14px;
  color: var(--text-muted);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 13px;
}

.empty h3 {
  margin: 0 0 6px;
  color: var(--text);
  font-size: 15px;
}

.empty p {
  margin: 0;
  color: var(--text-muted);
  font-size: 12px;
}

.empty__button {
  margin-top: 17px;
  padding: 8px 12px;
  color: var(--accent-light);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 11px;
  cursor: pointer;
}

.topic-list--loading {
  padding: 0 16px;
}

.topic-skeleton {
  display: grid;
  grid-template-columns: 42px 1fr 100px;
  gap: 15px;
  align-items: center;
  height: 76px;
  border-bottom: 1px solid var(--border);
}

.topic-skeleton > span,
.topic-skeleton > div i,
.topic-skeleton > em {
  display: block;
  background: var(--bg);
  border-radius: 6px;
  animation: pulse 1.4s infinite ease-in-out;
}

.topic-skeleton > span {
  width: 20px;
  height: 10px;
}

.topic-skeleton > div {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.topic-skeleton > div i:first-child {
  width: 55%;
  height: 12px;
}

.topic-skeleton > div i:last-child {
  width: 30%;
  height: 8px;
}

.topic-skeleton > em {
  width: 70px;
  height: 8px;
}

@keyframes pulse {
  50% {
    opacity: 0.45;
  }
}

/* ───────────────────────── OTHER ───────────────────────── */

.error-box {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 20px;
  padding: 11px 14px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 10px;
  font-size: 12px;
}

.pagination {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
  margin-top: 20px;
}

.pagination button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
}

.pagination button:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--border-hover);
}

.pagination button:disabled {
  opacity: 0.35;
  cursor: default;
}

.pagination span {
  color: var(--text-dim);
  font-size: 11px;
}

.pagination i {
  margin: 0 5px;
  color: var(--text-muted);
  font-style: normal;
}

/* ───────────────────────── MOBILE ───────────────────────── */

@media (max-width: 760px) {
  .forum-page {
    width: min(100% - 24px, 1180px);
    margin-top: 14px;
  }

  .forum-hero {
    min-height: 0;
    border-radius: 18px;
  }

  .forum-hero__inner {
    display: block;
    min-height: 0;
    padding: 32px 24px 25px;
  }

  .hero-copy h1 {
    font-size: 37px;
    letter-spacing: -1.8px;
  }

  .hero-copy p {
    font-size: 13px;
  }

  .hero-side {
    gap: 25px;
    margin-top: 28px;
  }

  .forum-toolbar {
    margin-bottom: 34px;
  }

  .toolbar-search kbd {
    display: none;
  }

  .create-topic {
    width: 46px;
    padding: 0;
    font-size: 0;
  }

  .create-topic svg {
    margin: 0;
  }

  .category-list {
    grid-template-columns: 1fr;
  }

  .category-card {
    grid-template-columns: 40px minmax(0, 1fr) auto;
  }

  .category-card__arrow {
    display: none;
  }

  .category-card__count {
    display: none;
  }

  .topics-heading {
    align-items: center;
  }

  .topic-controls {
    display: block;
  }

  .sort-tabs {
    width: 100%;
    overflow-x: auto;
  }

  .sort-tabs button {
    flex: 1;
    white-space: nowrap;
  }

  .active-filter {
    margin-top: 10px;
  }

  .topic {
    grid-template-columns: 1fr 25px;
    gap: 8px;
    min-height: 0;
    padding: 15px;
  }

  .topic__index,
  .topic__activity,
  .topic__last {
    display: none;
  }

  .topic h3 {
    font-size: 13px;
    white-space: normal;
    line-height: 1.4;
  }

  .topic__arrow {
    align-self: center;
  }
}

@media (max-width: 430px) {
  .hero-copy h1 {
    font-size: 32px;
  }

  .hero-side {
    justify-content: space-between;
  }

  .section-heading h2,
  .topics-heading h2 {
    font-size: 18px;
  }

  .category-card {
    padding: 11px;
  }
}
</style>

