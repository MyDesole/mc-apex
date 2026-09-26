<script setup>
import { computed, onMounted, ref } from 'vue'
import { myClanApi } from '@/services/myClan.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  clan: { type: Object, required: true },
  permissions: { type: Object, default: () => ({}) },
})

const auth = useAuthStore()
const resources = ref([])
const loading = ref(true)
const showForm = ref(false)
const processing = ref(false)
const categoryFilter = ref('')

// Лайтбокс для галереи скриншотов
const lightbox = ref(null)

const form = ref({
  title: '',
  description: '',
  category: 'resource_pack',
  file: null,
  is_public: false,
})

const CATEGORIES = [
  { value: 'resource_pack', label: 'Ресурс-пак' },
  { value: 'screenshot', label: 'Скриншот' },
  { value: 'config', label: 'Конфиг' },
  { value: 'guide', label: 'Гайд' },
  { value: 'other', label: 'Другое' },
]

const isGalleryMode = computed(() => categoryFilter.value === 'screenshot')

async function load() {
  loading.value = true
  try {
    // не отправляем category, если он пустой
    const params = {}
    if (categoryFilter.value) {
      params.category = categoryFilter.value
    }

    const data = await myClanApi.resources(params)
    resources.value = data.data ?? []
  } catch (e) {
    console.error(e)
    resources.value = []
  } finally {
    loading.value = false
  }
}

function setCategory(value) {
  if (categoryFilter.value === value) return
  categoryFilter.value = value
  load()
}

function onFile(e) {
  form.value.file = e.target.files[0]
}

async function submit() {
  if (!form.value.file) return
  processing.value = true
  try {
    await myClanApi.uploadResource(form.value)
    showForm.value = false
    form.value = { title: '', description: '', category: 'resource_pack', file: null, is_public: false }
    await load()
  } finally {
    processing.value = false
  }
}

async function download(resource) {
  const data = await myClanApi.downloadResource(resource.id)
  window.open(data.url, '_blank')
}

async function remove(resource) {
  if (!confirm('Удалить ресурс?')) return
  await myClanApi.deleteResource(resource.id)
  await load()
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB'
  return (bytes / 1073741824).toFixed(1) + ' GB'
}

// === ЛАЙТБОКС ===

function openLightbox(resource) {
  lightbox.value = resource
}

function closeLightbox() {
  lightbox.value = null
}

function lightboxPrev() {
  if (!lightbox.value) return
  const idx = resources.value.findIndex(r => r.id === lightbox.value.id)
  if (idx === -1) return
  const prevIdx = (idx - 1 + resources.value.length) % resources.value.length
  lightbox.value = resources.value[prevIdx]
}

function lightboxNext() {
  if (!lightbox.value) return
  const idx = resources.value.findIndex(r => r.id === lightbox.value.id)
  if (idx === -1) return
  const nextIdx = (idx + 1) % resources.value.length
  lightbox.value = resources.value[nextIdx]
}

function onKey(e) {
  if (!lightbox.value) return
  if (e.key === 'Escape') closeLightbox()
  if (e.key === 'ArrowLeft') lightboxPrev()
  if (e.key === 'ArrowRight') lightboxNext()
}

onMounted(() => {
  load()
  window.addEventListener('keydown', onKey)
})

import { onBeforeUnmount } from 'vue'
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div class="tab">
    <div class="head">
      <div class="filters">
        <button
            v-for="c in [{ value: '', label: 'Все' }, ...CATEGORIES]"
            :key="c.value"
            class="filter-btn"
            :class="{ active: categoryFilter === c.value }"
            @click="setCategory(c.value)"
        >
          {{ c.label }}
        </button>
      </div>

      <button
          v-if="permissions.resources"
          class="btn-create"
          @click="showForm = !showForm"
      >
        {{ showForm ? 'Отмена' : '+ Загрузить' }}
      </button>
    </div>

    <!-- Форма -->
    <div v-if="showForm" class="form">
      <input v-model="form.title" placeholder="Название" maxlength="120" />
      <textarea v-model="form.description" rows="3" placeholder="Описание" />

      <div class="row">
        <select v-model="form.category">
          <option v-for="c in CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>

        <label class="file-input">
          <input type="file" @change="onFile" />
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <path d="M17 8l-5-5-5 5" />
            <path d="M12 3v12" />
          </svg>
          <span>{{ form.file ? form.file.name : 'Выбрать файл' }}</span>
        </label>
      </div>

      <label class="checkbox">
        <input v-model="form.is_public" type="checkbox" />
        <span>Публичный (доступен не-членам клана)</span>
      </label>

      <div class="form-actions">
        <button class="btn-save" :disabled="processing || !form.file" @click="submit">
          {{ processing ? '...' : 'Загрузить' }}
        </button>
      </div>
    </div>

    <!-- Состояния -->
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!resources.length" class="empty">Ресурсов нет</div>

    <!-- Галерея скриншотов -->
    <div v-else-if="isGalleryMode" class="gallery">
      <div
          v-for="r in resources"
          :key="r.id"
          class="shot"
          @click="openLightbox(r)"
      >
        <div class="shot__image">
          <img
              v-if="r.file_url"
              :src="r.file_url"
              :alt="r.title"
              loading="lazy"
          />
          <div v-else class="shot__placeholder">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" />
              <circle cx="8.5" cy="8.5" r="1.5" />
              <path d="M21 15l-5-5L5 21" />
            </svg>
          </div>

          <div class="shot__overlay">
            <span class="shot__title">{{ r.title }}</span>
          </div>
        </div>

        <div class="shot__meta">
          <span class="shot__author">{{ r.author?.username ?? '—' }}</span>
          <span class="shot__size">{{ formatSize(r.file_size) }}</span>
        </div>

        <button
            v-if="permissions.resources || r.author_id === auth.user?.id"
            class="shot__delete"
            title="Удалить"
            @click.stop="remove(r)"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18" />
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Список (остальные категории) -->
    <div v-else class="list">
      <div v-for="r in resources" :key="r.id" class="resource">
        <div class="resource__icon" :class="`icon-${r.category}`">
          <svg v-if="r.category === 'resource_pack'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            <path d="M3.27 6.96 12 12.01l8.73-5.05" />
            <path d="M12 22.08V12" />
          </svg>

          <svg v-else-if="r.category === 'screenshot'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
          </svg>

          <svg v-else-if="r.category === 'config'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
          </svg>

          <svg v-else-if="r.category === 'guide'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
          </svg>

          <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6" />
          </svg>
        </div>

        <div class="resource__info">
          <div class="resource__title">
            {{ r.title }}
            <span v-if="r.is_public" class="public-badge">публичный</span>
          </div>
          <div class="resource__meta">
            {{ r.author?.username }} · {{ r.file_name }} · {{ formatSize(r.file_size) }} · {{ r.downloads }} скачиваний
          </div>
          <p v-if="r.description" class="resource__desc">{{ r.description }}</p>
        </div>

        <div class="resource__actions">
          <button class="btn-action" title="Скачать" @click="download(r)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <path d="M7 10l5 5 5-5" />
              <path d="M12 15V3" />
            </svg>
          </button>

          <button
              v-if="permissions.resources || r.author_id === auth.user?.id"
              class="btn-action danger"
              title="Удалить"
              @click="remove(r)"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18" />
              <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
              <path d="M10 11v6M14 11v6" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Лайтбокс -->
    <Teleport to="body">
      <div
          v-if="lightbox"
          class="lightbox"
          @click.self="closeLightbox"
      >
        <button class="lightbox__close" @click="closeLightbox" aria-label="Закрыть">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>

        <button class="lightbox__nav lightbox__nav--prev" @click.stop="lightboxPrev" aria-label="Назад">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </button>

        <button class="lightbox__nav lightbox__nav--next" @click.stop="lightboxNext" aria-label="Вперёд">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 6l6 6-6 6" />
          </svg>
        </button>

        <div class="lightbox__content" @click.stop>
          <img
              v-if="lightbox.file_url"
              :src="lightbox.file_url"
              :alt="lightbox.title"
              class="lightbox__img"
          />
          <div v-else class="lightbox__placeholder">
            Нет превью
          </div>

          <div class="lightbox__info">
            <div class="lightbox__title">{{ lightbox.title }}</div>
            <div class="lightbox__meta">
              {{ lightbox.author?.username ?? '—' }} · {{ formatSize(lightbox.file_size) }}
            </div>
            <p v-if="lightbox.description" class="lightbox__desc">{{ lightbox.description }}</p>

            <button class="lightbox__download" @click="download(lightbox)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <path d="M7 10l5 5 5-5" />
                <path d="M12 15V3" />
              </svg>
              Скачать
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }

.head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

.filters { display: flex; gap: 4px; flex-wrap: wrap; }

.filter-btn {
  padding: 7px 12px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.filter-btn:hover { border-color: var(--border-hover); color: var(--text); }
.filter-btn.active { color: #fff; background: var(--accent); border-color: var(--accent); }

.btn-create {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.form input[type="text"],
.form input:not([type]),
.form textarea,
.form select {
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.form input:focus, .form textarea:focus, .form select:focus { border-color: var(--accent); }

.row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.file-input {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 14px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px dashed var(--border);
  border-radius: 9px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.15s;
}

.file-input input { display: none; }
.file-input:hover { border-color: var(--accent); color: var(--accent-light); }

.checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-dim);
  cursor: pointer;
}

.checkbox input { accent-color: var(--accent); }

.form-actions { display: flex; justify-content: flex-end; }

.btn-save {
  padding: 10px 20px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 9px;
  font-weight: 700;
  cursor: pointer;
}

.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.list { display: flex; flex-direction: column; gap: 6px; }

.resource {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.resource__icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
}

.resource__icon.icon-resource_pack { color: #a78bfa; }
.resource__icon.icon-screenshot { color: #f472b6; }
.resource__icon.icon-config { color: #60a5fa; }
.resource__icon.icon-guide { color: #34d399; }
.resource__icon.icon-other { color: var(--text-dim); }

.resource__info { flex: 1; min-width: 0; }

.resource__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 4px;
}

.public-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 800;
  color: #4ade80;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.resource__meta { font-size: 11px; color: var(--text-dim); }
.resource__desc { margin: 4px 0 0; font-size: 12px; color: var(--text-muted); }

.resource__actions { display: flex; gap: 4px; flex-shrink: 0; }

.btn-action {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  color: var(--text-dim);
  transition: all 0.15s;
}

.btn-action:hover {
  border-color: var(--border-hover);
  color: var(--text);
  background: rgba(255, 255, 255, 0.03);
}

.btn-action.danger:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.06);
}

.empty {
  padding: 60px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}

/* ============================================
   GALLERY (скриншоты)
   ============================================ */

.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
}

.shot {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 6px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  cursor: pointer;
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.shot:hover {
  transform: translateY(-2px);
  border-color: var(--border-hover);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}

.shot__image {
  position: relative;
  aspect-ratio: 16 / 10;
  border-radius: 8px;
  overflow: hidden;
  background: #0d0d14;
}

.shot__image img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.shot__placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(6, 182, 212, 0.05));
}

.shot__overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px 10px 8px;
  background: linear-gradient(0deg, rgba(0, 0, 0, 0.85), transparent);
  opacity: 0;
  transition: opacity 0.15s ease;
}

.shot:hover .shot__overlay { opacity: 1; }

.shot__title {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}

.shot__meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 2px 4px;
  font-size: 11px;
  color: var(--text-dim);
}

.shot__author {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 700;
}

.shot__size {
  color: var(--text-muted);
  flex-shrink: 0;
}

.shot__delete {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(13, 13, 20, 0.85);
  border: 1px solid rgba(239, 68, 68, 0.4);
  border-radius: 7px;
  color: #f87171;
  cursor: pointer;
  opacity: 0;
  transition: all 0.15s;
  backdrop-filter: blur(6px);
}

.shot:hover .shot__delete { opacity: 1; }

.shot__delete:hover {
  background: rgba(239, 68, 68, 0.9);
  color: #fff;
  border-color: rgba(239, 68, 68, 1);
}

/* ============================================
   LIGHTBOX
   ============================================ */

.lightbox {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: rgba(6, 6, 10, 0.92);
  backdrop-filter: blur(10px);
  animation: lbFade 0.15s ease;
}

@keyframes lbFade {
  from { opacity: 0; }
  to { opacity: 1; }
}

.lightbox__close {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #e5e7eb;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  z-index: 3;
}

.lightbox__close:hover {
  background: rgba(255, 255, 255, 0.12);
}

.lightbox__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 48px;
  height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #e5e7eb;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.15s;
  z-index: 3;
}

.lightbox__nav:hover {
  background: rgba(255, 255, 255, 0.12);
}

.lightbox__nav--prev { left: 16px; }
.lightbox__nav--next { right: 16px; }

.lightbox__content {
  position: relative;
  max-width: 1100px;
  width: 100%;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--bg-card, #12121a);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.7);
}

.lightbox__img {
  width: 100%;
  max-height: 70vh;
  object-fit: contain;
  background: #0a0a10;
  display: block;
}

.lightbox__placeholder {
  padding: 60px;
  text-align: center;
  color: var(--text-muted);
  background: #0a0a10;
}

.lightbox__info {
  padding: 16px 20px 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lightbox__title {
  font-size: 17px;
  font-weight: 800;
  color: var(--text, #fff);
}

.lightbox__meta {
  font-size: 12px;
  color: var(--text-dim, #8a8a97);
}

.lightbox__desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--text-dim, #8a8a97);
  line-height: 1.5;
  white-space: pre-wrap;
}

.lightbox__download {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
  padding: 10px 18px;
  color: #fff;
  background: var(--accent, #7c3aed);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.lightbox__download:hover {
  background: var(--accent-light, #8b5cf6);
  transform: translateY(-1px);
}

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 600px) {
  .row { grid-template-columns: 1fr; }

  .resource {
    flex-wrap: wrap;
    gap: 10px;
  }

  .resource__actions {
    width: 100%;
    justify-content: flex-end;
  }

  .gallery {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px;
  }

  .lightbox {
    padding: 60px 12px 20px;
  }

  .lightbox__nav {
    width: 40px;
    height: 40px;
  }

  .lightbox__img {
    max-height: 50vh;
  }
}
</style>