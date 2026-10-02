<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { forumApi } from '@/services/forum/forum.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'

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
@import "@/views/forum/ForumView.css";
</style>
