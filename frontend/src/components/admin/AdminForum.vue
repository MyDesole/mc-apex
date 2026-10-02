<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AppIcon from '@/components/core/AppIcon.vue'

const tab = ref('categories') // categories | topics

const loading = ref(true)
const error = ref('')
const notice = ref('')

const stats = ref({})
const categories = ref([])
const policies = ref([])
const topics = ref([])
const topicsPage = ref(1)
const topicsLastPage = ref(1)
const onlyDeleted = ref(false)
const search = ref('')

// Форма раздела
const editing = ref(null)

const blankCategory = () => ({
  id: null,
  name: '',
  slug: '',
  description: '',
  icon: 'doc',
  color: '#7c3aed',
  sort_order: 100,
  post_policy: 'all',
  is_active: true,
})

async function loadStats() {
  const data = await adminApi.forumStats()
  stats.value = data
}

async function loadCategories() {
  const data = await adminApi.forumCategories()
  categories.value = data.categories ?? []
  policies.value = data.policies ?? []
}

async function loadTopics(page = 1) {
  const params = { page }

  if (onlyDeleted.value) params.only_deleted = '1'
  if (search.value) params.search = search.value

  const data = await adminApi.forumTopics(params)

  topics.value = data.data ?? []
  topicsPage.value = data.current_page ?? 1
  topicsLastPage.value = data.last_page ?? 1
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    await Promise.all([loadStats(), loadCategories(), loadTopics(1)])
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить данные форума.'
  } finally {
    loading.value = false
  }
}

function createCategory() {
  editing.value = blankCategory()
}

function editCategory(category) {
  editing.value = { ...category }
}

async function saveCategory() {
  if (!editing.value) return

  error.value = ''
  notice.value = ''

  try {
    const payload = { ...editing.value }

    delete payload.id
    delete payload.created_at
    delete payload.updated_at
    delete payload.topics
    if (!payload.slug) delete payload.slug

    if (editing.value.id) {
      await adminApi.updateForumCategory(editing.value.id, payload)
      notice.value = 'Раздел обновлён.'
    } else {
      await adminApi.createForumCategory(payload)
      notice.value = 'Раздел создан.'
    }

    editing.value = null
    await Promise.all([loadCategories(), loadStats()])
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить раздел.'
  }
}

async function removeCategory(category) {
  const hasTopics = (category.topics_count ?? 0) > 0

  if (hasTopics && !await confirmDialog(`В разделе есть темы. Удалить раздел ВМЕСТЕ с темами «${category.name}»?`)) {
    return
  }

  if (!hasTopics && !await confirmDialog(`Удалить раздел «${category.name}»?`)) {
    return
  }

  error.value = ''

  try {
    await adminApi.deleteForumCategory(category.id, hasTopics)
    notice.value = 'Раздел удалён.'
    await Promise.all([loadCategories(), loadStats()])
  } catch (e) {
    error.value = e.message || 'Не удалось удалить раздел.'
  }
}

async function togglePin(topic) {
  try {
    const result = await adminApi.pinForumTopic(topic.id)
    topic.is_pinned = result.topic.is_pinned
  } catch (e) {
    error.value = e.message || 'Не удалось закрепить тему.'
  }
}

async function toggleLock(topic) {
  try {
    const result = await adminApi.lockForumTopic(topic.id)
    topic.is_locked = result.topic.is_locked
  } catch (e) {
    error.value = e.message || 'Не удалось закрыть тему.'
  }
}

async function removeTopic(topic) {
  if (await confirmDialog(`Удалить тему «${topic.title}»?`)) {
    await adminApi.deleteForumTopic(topic.id)
    await loadTopics(topicsPage.value)
    await loadStats()
  }
}

async function restoreTopic(topic) {
  await adminApi.restoreForumTopic(topic.id)
  await loadTopics(topicsPage.value)
  await loadStats()
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

onMounted(load)
</script>

<template>
  <div class="aforum">
    <p v-if="error" class="alert alert--error">{{ error }}</p>
    <p v-if="notice" class="alert alert--ok">{{ notice }}</p>

    <!-- Статистика -->
    <div class="stats">
      <div class="stat"><span>{{ stats.topics ?? 0 }}</span>Тем</div>
      <div class="stat"><span>{{ stats.replies ?? 0 }}</span>Ответов</div>
      <div class="stat"><span>{{ stats.categories ?? 0 }}</span>Разделов</div>
      <div class="stat"><span>{{ stats.pinned ?? 0 }}</span>Закреплено</div>
      <div class="stat"><span>{{ stats.locked ?? 0 }}</span>Закрыто</div>
      <div class="stat stat--warn"><span>{{ stats.deleted_topics ?? 0 }}</span>Удалено</div>
      <div class="stat"><span>{{ stats.attachments ?? 0 }}</span>Файлов</div>
    </div>

    <nav class="subnav">
      <button :class="{ active: tab === 'categories' }" @click="tab = 'categories'">
        Разделы
      </button>
      <button :class="{ active: tab === 'topics' }" @click="tab = 'topics'">
        Темы
      </button>
    </nav>

    <p v-if="loading" class="state">Загружаем…</p>

    <!-- Разделы -->
    <section v-else-if="tab === 'categories'">
      <div class="toolbar">
        <h2>Разделы форума ({{ categories.length }})</h2>
        <button class="btn btn-primary" type="button" @click="createCategory">+ Раздел</button>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Кто может писать</th>
              <th class="num">Тем</th>
              <th>Порядок</th>
              <th>Статус</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="category in categories" :key="category.id">
              <td>
                <span class="cell-icon" :style="{ color: category.color || 'var(--accent)' }">
                  <AppIcon :icon="category.icon" :size="16" />
                </span>
                <b>{{ category.name }}</b>
                <div class="dim">{{ category.slug }}</div>
              </td>
              <td>{{ category.post_policy }}</td>
              <td class="num">{{ category.topics_count ?? 0 }}</td>
              <td>{{ category.sort_order }}</td>
              <td>
                <span class="pill" :class="category.is_active ? 'pill--on' : 'pill--off'">
                  {{ category.is_active ? 'активен' : 'скрыт' }}
                </span>
              </td>
              <td class="actions">
                <button class="btn btn-secondary btn-sm" type="button" @click="editCategory(category)">
                  Изменить
                </button>
                <button class="btn btn-danger btn-sm" type="button" @click="removeCategory(category)">
                  Удалить
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Темы -->
    <section v-else>
      <div class="toolbar">
        <h2>Темы ({{ topics.length }})</h2>

        <div class="toolbar__controls">
          <input v-model="search" class="input input--sm" placeholder="Поиск по заголовку" @keyup.enter="loadTopics(1)">
          <label class="checkbox">
            <input v-model="onlyDeleted" type="checkbox" @change="loadTopics(1)">
            <span>Только удалённые</span>
          </label>
          <button class="btn btn-secondary btn-sm" type="button" @click="loadTopics(1)">Найти</button>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Тема</th>
              <th>Автор</th>
              <th>Раздел</th>
              <th class="num">Ответов</th>
              <th>Статус</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="topic in topics" :key="topic.id" :class="{ 'row--deleted': topic.deleted_at }">
              <td>
                <a :href="`/forum/${topic.id}`" target="_blank" rel="noopener" class="title-link">
                  {{ topic.title }}
                </a>
                <div class="dim">{{ formatDate(topic.created_at) }}</div>
              </td>
              <td>{{ topic.author?.username || '—' }}</td>
              <td>{{ topic.category?.name || '—' }}</td>
              <td class="num">{{ topic.replies_count }}</td>
              <td>
                <span v-if="topic.deleted_at" class="pill pill--off">удалена</span>
                <span v-else-if="topic.is_locked" class="pill pill--warn">закрыта</span>
                <span v-else-if="topic.is_pinned" class="pill pill--on">закреплена</span>
                <span v-else class="dim">обычная</span>
              </td>
              <td class="actions">
                <button
                    v-if="!topic.deleted_at"
                    class="btn btn-secondary btn-sm"
                    type="button"
                    @click="togglePin(topic)"
                >
                  {{ topic.is_pinned ? 'Открепить' : 'Закрепить' }}
                </button>
                <button
                    v-if="!topic.deleted_at"
                    class="btn btn-secondary btn-sm"
                    type="button"
                    @click="toggleLock(topic)"
                >
                  {{ topic.is_locked ? 'Открыть' : 'Закрыть' }}
                </button>
                <button
                    v-if="!topic.deleted_at"
                    class="btn btn-danger btn-sm"
                    type="button"
                    @click="removeTopic(topic)"
                >
                  Удалить
                </button>
                <button
                    v-else
                    class="btn btn-secondary btn-sm"
                    type="button"
                    @click="restoreTopic(topic)"
                >
                  Восстановить
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="topicsLastPage > 1" class="pager">
        <button class="btn btn-secondary btn-sm" :disabled="topicsPage <= 1" @click="loadTopics(topicsPage - 1)">
          Назад
        </button>
        <span class="dim">{{ topicsPage }} / {{ topicsLastPage }}</span>
        <button
            class="btn btn-secondary btn-sm"
            :disabled="topicsPage >= topicsLastPage"
            @click="loadTopics(topicsPage + 1)"
        >
          Вперёд
        </button>
      </div>
    </section>

    <!-- Форма раздела -->
    <div v-if="editing" class="modal" @click.self="editing = null">
      <div class="modal__box">
        <h3>{{ editing.id ? 'Изменить раздел' : 'Новый раздел' }}</h3>

        <label class="field">
          <span class="field__label">Название</span>
          <input v-model="editing.name" class="input" type="text" maxlength="96">
        </label>

        <label class="field">
          <span class="field__label">Slug (необязательно)</span>
          <input v-model="editing.slug" class="input" type="text" placeholder="general">
        </label>

        <label class="field">
          <span class="field__label">Описание</span>
          <textarea v-model="editing.description" class="textarea" rows="2" maxlength="255" />
        </label>

        <div class="row">
          <label class="field">
            <span class="field__label">Иконка (имя SVG)</span>
            <input v-model="editing.icon" class="input" type="text" placeholder="doc">
          </label>

          <label class="field">
            <span class="field__label">Цвет</span>
            <input v-model="editing.color" class="input" type="text" placeholder="#7c3aed">
          </label>
        </div>

        <div class="row">
          <label class="field">
            <span class="field__label">Кто может создавать темы</span>
            <select v-model="editing.post_policy" class="select">
              <option v-for="policy in policies" :key="policy.value" :value="policy.value">
                {{ policy.label }}
              </option>
            </select>
          </label>

          <label class="field">
            <span class="field__label">Порядок</span>
            <input v-model.number="editing.sort_order" class="input" type="number" min="0">
          </label>
        </div>

        <label class="checkbox">
          <input v-model="editing.is_active" type="checkbox">
          <span>Показывать на форуме</span>
        </label>

        <div class="modal__actions">
          <button class="btn btn-secondary" type="button" @click="editing = null">Отмена</button>
          <button class="btn btn-primary" type="button" @click="saveCategory">Сохранить</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminForum.css";
</style>
