<script setup>
/**
 * Чейнджлог и роадмап в админке: посты об обновлениях (со ссылкой
 * для Telegram-канала) и цели проекта с прогрессом.
 */
import { computed, onMounted, ref } from 'vue'
import { adminProjectApi } from '@/services/project/project.js'
import { alert, confirm } from '@/utils/dialog.js'

const tab = ref('changelog')
const busy = ref(false)

/* ---------------- Чейнджлог ---------------- */

const entries = ref([])
const categories = ref({ feature: 'Новое', improvement: 'Улучшение', fix: 'Исправление' })
const entryForm = ref(null)
const copiedId = ref(null)

function emptyEntry() {
  return { id: null, title: '', version: '', category: 'feature', body: '', is_published: true, regenerate_slug: false }
}

async function loadChangelog() {
  const data = await adminProjectApi.changelog()
  entries.value = data.entries ?? []
  if (data.categories) categories.value = data.categories
}

function editEntry(entry) {
  entryForm.value = entry ? { ...entry, version: entry.version ?? '', regenerate_slug: false } : emptyEntry()
}

async function saveEntry() {
  const form = entryForm.value
  const payload = {
    title: form.title,
    version: form.version || null,
    category: form.category,
    body: form.body,
    is_published: form.is_published,
    regenerate_slug: form.regenerate_slug,
  }

  await run(async () => {
    form.id ? await adminProjectApi.updateEntry(form.id, payload) : await adminProjectApi.createEntry(payload)
    entryForm.value = null
    await loadChangelog()
  })
}

async function removeEntry(entry) {
  if (!await confirm(`Удалить запись «${entry.title}»? Ссылка на неё перестанет работать.`, { title: 'Удаление', danger: true })) return
  await run(async () => {
    await adminProjectApi.deleteEntry(entry.id)
    await loadChangelog()
  })
}

async function copyLink(entry) {
  const link = `${window.location.origin}/changelog/${entry.slug}`

  try {
    await navigator.clipboard.writeText(link)
  } catch {
    await alert(link, { title: 'Ссылка на пост' })
    return
  }

  copiedId.value = entry.id
  setTimeout(() => { copiedId.value = null }, 1600)
}

/* ---------------- Роадмап ---------------- */

const items = ref([])
const statuses = ref({ planned: 'В планах', in_progress: 'В работе', done: 'Готово' })
const metrics = ref({})
const itemForm = ref(null)

function emptyItem() {
  return { id: null, title: '', description: '', status: 'planned', metric: null, progress_current: null, progress_target: null, eta: '', sort_order: null }
}

async function loadRoadmap() {
  const data = await adminProjectApi.roadmap()
  items.value = data.items ?? []
  if (data.statuses) statuses.value = data.statuses
  metrics.value = data.metrics ?? {}
}

function editItem(item) {
  itemForm.value = item
      ? {
        ...item,
        // В форме — ручное значение, а не посчитанное автоматически
        progress_current: item.progress_manual,
        eta: item.eta ?? '',
      }
      : emptyItem()
}

const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v))

async function saveItem() {
  const form = itemForm.value
  const payload = {
    title: form.title,
    description: form.description || null,
    status: form.status,
    metric: form.metric || null,
    progress_current: form.metric ? null : num(form.progress_current),
    progress_target: num(form.progress_target),
    eta: form.eta || null,
    sort_order: num(form.sort_order),
  }

  await run(async () => {
    form.id ? await adminProjectApi.updateItem(form.id, payload) : await adminProjectApi.createItem(payload)
    itemForm.value = null
    await loadRoadmap()
  })
}

async function removeItem(item) {
  if (!await confirm(`Удалить цель «${item.title}»?`, { title: 'Удаление', danger: true })) return
  await run(async () => {
    await adminProjectApi.deleteItem(item.id)
    await loadRoadmap()
  })
}

/* ---------------- Общее ---------------- */

async function run(fn) {
  busy.value = true
  try {
    await fn()
  } catch (e) {
    await alert(e.message || 'Не удалось сохранить', { title: 'Ошибка' })
  } finally {
    busy.value = false
  }
}

const sortedItems = computed(() => [...items.value].sort((a, b) => a.sort_order - b.sort_order))

function date(iso) {
  return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' }) : ''
}

onMounted(() => Promise.all([loadChangelog(), loadRoadmap()]))
</script>

<template>
  <div class="project">
    <div class="project__tabs">
      <button type="button" class="kit-btn" :class="{ 'kit-btn--primary': tab === 'changelog' }" @click="tab = 'changelog'">Чейнджлог</button>
      <button type="button" class="kit-btn" :class="{ 'kit-btn--primary': tab === 'roadmap' }" @click="tab = 'roadmap'">Роадмап</button>
      <a class="kit-btn project__open" :href="tab === 'changelog' ? '/changelog' : '/roadmap'" target="_blank" rel="noopener">Открыть страницу ↗</a>
    </div>

    <!-- ================= ЧЕЙНДЖЛОГ ================= -->
    <section v-if="tab === 'changelog'">
      <form v-if="entryForm" class="project__form" @submit.prevent="saveEntry">
        <h3>{{ entryForm.id ? 'Правка записи' : 'Новая запись' }}</h3>

        <div class="project__row">
          <label class="project__grow">Заголовок
            <input v-model="entryForm.title" class="kit-input" maxlength="160" required placeholder="Например: Макеты профиля в магазине">
          </label>
          <label>Версия
            <input v-model="entryForm.version" class="kit-input" maxlength="32" placeholder="1.4">
          </label>
          <label>Тип
            <select v-model="entryForm.category" class="kit-select">
              <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
        </div>

        <label>Текст
          <textarea
              v-model="entryForm.body"
              class="kit-input"
              rows="9"
              maxlength="20000"
              required
              placeholder="Абзацы — через пустую строку. Список — строки с «- » в начале."
          ></textarea>
        </label>

        <div class="project__row project__row--center">
          <label class="project__check">
            <input v-model="entryForm.is_published" type="checkbox"> Опубликовано
          </label>
          <label v-if="entryForm.id" class="project__check" title="Старая ссылка перестанет работать">
            <input v-model="entryForm.regenerate_slug" type="checkbox"> Обновить ссылку по новому заголовку
          </label>
        </div>

        <div class="project__actions">
          <button type="button" class="kit-btn" @click="entryForm = null">Отмена</button>
          <button type="submit" class="kit-btn kit-btn--primary" :disabled="busy">Сохранить</button>
        </div>
      </form>

      <button v-else type="button" class="kit-btn kit-btn--primary" @click="editEntry(null)">+ Новая запись</button>

      <p v-if="!entries.length" class="kit-muted">Записей пока нет.</p>

      <ul class="project__list">
        <li v-for="e in entries" :key="e.id" class="project__item">
          <div class="project__item-main">
            <b>{{ e.title }}</b>
            <small class="kit-muted">
              {{ e.category_label }}<template v-if="e.version"> · v{{ e.version }}</template> · {{ date(e.published_at) }}
              <template v-if="!e.is_published"> · <span class="kit-tag">черновик</span></template>
            </small>
            <code class="project__slug">/changelog/{{ e.slug }}</code>
          </div>
          <div class="project__item-actions">
            <button type="button" class="kit-btn" @click="copyLink(e)">{{ copiedId === e.id ? 'Скопировано ✓' : 'Ссылка' }}</button>
            <button type="button" class="kit-btn" @click="editEntry(e)">Править</button>
            <button type="button" class="kit-btn kit-btn--danger" :disabled="busy" @click="removeEntry(e)">Удалить</button>
          </div>
        </li>
      </ul>
    </section>

    <!-- ================= РОАДМАП ================= -->
    <section v-else>
      <form v-if="itemForm" class="project__form" @submit.prevent="saveItem">
        <h3>{{ itemForm.id ? 'Правка цели' : 'Новая цель' }}</h3>

        <div class="project__row">
          <label class="project__grow">Цель
            <input v-model="itemForm.title" class="kit-input" maxlength="160" required placeholder="Например: Провести турнир">
          </label>
          <label>Статус
            <select v-model="itemForm.status" class="kit-select">
              <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <label>Срок
            <input v-model="itemForm.eta" class="kit-input" maxlength="60" placeholder="ноябрь">
          </label>
        </div>

        <label>Описание
          <textarea v-model="itemForm.description" class="kit-input" rows="3" maxlength="2000"></textarea>
        </label>

        <div class="project__row">
          <label class="project__grow">Прогресс считается
            <select v-model="itemForm.metric" class="kit-select">
              <option :value="null">Вручную</option>
              <option v-for="(label, key) in metrics" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <label v-if="!itemForm.metric">Сейчас
            <input v-model="itemForm.progress_current" class="kit-input" type="number" min="0">
          </label>
          <label>Цель (число)
            <input v-model="itemForm.progress_target" class="kit-input" type="number" min="1" placeholder="без шкалы">
          </label>
          <label>Порядок
            <input v-model="itemForm.sort_order" class="kit-input" type="number" min="0">
          </label>
        </div>

        <div class="project__actions">
          <button type="button" class="kit-btn" @click="itemForm = null">Отмена</button>
          <button type="submit" class="kit-btn kit-btn--primary" :disabled="busy">Сохранить</button>
        </div>
      </form>

      <button v-else type="button" class="kit-btn kit-btn--primary" @click="editItem(null)">+ Новая цель</button>

      <ul class="project__list">
        <li v-for="i in sortedItems" :key="i.id" class="project__item">
          <div class="project__item-main">
            <b>{{ i.title }}</b>
            <small class="kit-muted">
              <span class="kit-tag" :class="{ 'kit-tag--green': i.status === 'done', 'kit-tag--yellow': i.status === 'in_progress' }">{{ i.status_label }}</span>
              <template v-if="i.progress_target"> · {{ i.progress_current ?? 0 }} / {{ i.progress_target }} ({{ i.progress_percent }}%)</template>
              <template v-if="i.metric"> · авто</template>
              <template v-if="i.eta"> · {{ i.eta }}</template>
            </small>
          </div>
          <div class="project__item-actions">
            <button type="button" class="kit-btn" @click="editItem(i)">Править</button>
            <button type="button" class="kit-btn kit-btn--danger" :disabled="busy" @click="removeItem(i)">Удалить</button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.project {
  display: grid;
  gap: 14px;
}

.project__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.project__open {
  margin-left: auto;
}

.project__form {
  display: grid;
  gap: 12px;

  padding: 16px;

  border: 1px solid var(--border);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.02);
}

.project__form h3 {
  margin: 0;
}

.project__form label {
  display: grid;
  gap: 5px;

  font-size: 12px;
  color: var(--text-dim);
}

.project__row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.project__row--center {
  align-items: center;
}

.project__grow {
  flex: 1 1 260px;
}

.project__check {
  display: flex !important;
  align-items: center;
  gap: 6px !important;
  font-size: 13px !important;
}

.project__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.project__list {
  display: grid;
  gap: 8px;

  margin: 0;
  padding: 0;
  list-style: none;
}

.project__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;

  padding: 12px 14px;

  border: 1px solid var(--border);
  border-radius: 8px;
}

.project__item-main {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.project__item-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.project__slug {
  color: var(--text-muted);
  font-size: 11.5px;
  word-break: break-all;
}
</style>
