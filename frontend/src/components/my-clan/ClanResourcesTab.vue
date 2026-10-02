<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
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
  if (!await confirmDialog('Удалить ресурс?')) return
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
/* =========================================================
   BASE
   ========================================================= */

.tab {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}

/* =========================================================
   HEADER
   ========================================================= */

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.filters {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px;
  max-width: 100%;
  overflow-x: auto;

  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 12px;

  scrollbar-width: none;
}

.filters::-webkit-scrollbar {
  display: none;
}

.filter-btn {
  position: relative;
  flex-shrink: 0;

  padding: 8px 13px;

  color: var(--text-muted);
  background: transparent;
  border: 0;
  border-radius: 8px;

  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1px;

  cursor: pointer;
  transition:
      color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.filter-btn:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.045);
}

.filter-btn.active {
  color: #fff;
  background: var(--accent);
  box-shadow:
      0 4px 14px rgba(124, 58, 237, 0.24),
      inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.btn-create {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  min-height: 38px;
  padding: 0 16px;

  color: #fff;
  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent, #7c3aed)
      );

  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;

  font-size: 12px;
  font-weight: 800;

  cursor: pointer;

  box-shadow:
      0 7px 22px rgba(124, 58, 237, 0.22),
      inset 0 1px 0 rgba(255, 255, 255, 0.12);

  transition:
      transform 0.18s ease,
      box-shadow 0.18s ease,
      filter 0.18s ease;
}

.btn-create:hover {
  transform: translateY(-1px);
  filter: brightness(1.08);

  box-shadow:
      0 10px 28px rgba(124, 58, 237, 0.3),
      inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.btn-create:active {
  transform: translateY(0);
}

/* =========================================================
   FORM
   ========================================================= */

.form {
  position: relative;

  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 20px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.045),
          rgba(255, 255, 255, 0.018)
      );

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;

  box-shadow:
      0 18px 50px rgba(0, 0, 0, 0.18),
      inset 0 1px 0 rgba(255, 255, 255, 0.035);

  overflow: hidden;
}

.form::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;

  height: 1px;

  background: linear-gradient(
      90deg,
      transparent,
      rgba(139, 92, 246, 0.65),
      transparent
  );

  opacity: 0.7;
}

.form input[type="text"],
.form input:not([type]),
.form textarea,
.form select {
  width: 100%;
  box-sizing: border-box;

  padding: 11px 13px;

  color: var(--text);
  background: rgba(7, 7, 13, 0.72);

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 10px;

  font: inherit;
  font-size: 13px;

  outline: none;
  resize: vertical;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.form input::placeholder,
.form textarea::placeholder {
  color: var(--text-muted);
}

.form input:hover,
.form textarea:hover,
.form select:hover {
  border-color: rgba(255, 255, 255, 0.12);
}

.form input:focus,
.form textarea:focus,
.form select:focus {
  background: rgba(7, 7, 13, 0.9);
  border-color: var(--accent);

  box-shadow:
      0 0 0 3px rgba(124, 58, 237, 0.1),
      0 8px 25px rgba(0, 0, 0, 0.12);
}

.form textarea {
  min-height: 80px;
}

.row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.file-input {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  min-height: 42px;
  box-sizing: border-box;
  padding: 0 14px;

  color: var(--text-dim);
  background: rgba(7, 7, 13, 0.58);

  border: 1px dashed rgba(255, 255, 255, 0.12);
  border-radius: 10px;

  cursor: pointer;
  font-size: 12px;
  font-weight: 600;

  transition:
      color 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease;
}

.file-input input {
  display: none;
}

.file-input:hover {
  color: var(--accent-light, #a78bfa);
  border-color: rgba(139, 92, 246, 0.55);
  background: rgba(124, 58, 237, 0.06);
}

.checkbox {
  display: flex;
  align-items: center;
  gap: 9px;

  padding: 4px 2px;

  color: var(--text-dim);
  font-size: 12px;

  cursor: pointer;
  user-select: none;
}

.checkbox input {
  width: 15px;
  height: 15px;
  margin: 0;

  accent-color: var(--accent);
  cursor: pointer;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  min-height: 38px;
  padding: 0 19px;

  color: #fff;
  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent, #7c3aed)
      );

  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 9px;

  font-size: 12px;
  font-weight: 800;

  cursor: pointer;

  box-shadow:
      0 6px 18px rgba(124, 58, 237, 0.2),
      inset 0 1px 0 rgba(255, 255, 255, 0.1);

  transition:
      transform 0.18s ease,
      filter 0.18s ease,
      opacity 0.18s ease;
}

.btn-save:hover:not(:disabled) {
  transform: translateY(-1px);
  filter: brightness(1.08);
}

.btn-save:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
}

/* =========================================================
   EMPTY
   ========================================================= */

.empty {
  display: flex;
  align-items: center;
  justify-content: center;

  min-height: 180px;
  padding: 30px;

  color: var(--text-muted);

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.055),
          transparent 50%
      ),
      rgba(255, 255, 255, 0.018);

  border: 1px dashed rgba(255, 255, 255, 0.08);
  border-radius: 14px;

  font-size: 12px;
}

/* =========================================================
   RESOURCE LIST
   ========================================================= */

.list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.resource {
  position: relative;

  display: flex;
  align-items: center;
  gap: 14px;

  padding: 13px 15px;

  background:
      linear-gradient(
          100deg,
          rgba(255, 255, 255, 0.035),
          rgba(255, 255, 255, 0.018)
      );

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 13px;

  transition:
      transform 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.resource:hover {
  transform: translateY(-1px);

  background:
      linear-gradient(
          100deg,
          rgba(255, 255, 255, 0.052),
          rgba(255, 255, 255, 0.022)
      );

  border-color: rgba(255, 255, 255, 0.11);

  box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.18),
      inset 0 1px 0 rgba(255, 255, 255, 0.025);
}

.resource__icon {
  width: 44px;
  height: 44px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  border-radius: 11px;

  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.07);

  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035);
}

.resource__icon.icon-resource_pack {
  color: #a78bfa;
  background: rgba(139, 92, 246, 0.09);
  border-color: rgba(139, 92, 246, 0.18);
}

.resource__icon.icon-screenshot {
  color: #f472b6;
  background: rgba(236, 72, 153, 0.08);
  border-color: rgba(236, 72, 153, 0.16);
}

.resource__icon.icon-config {
  color: #60a5fa;
  background: rgba(59, 130, 246, 0.08);
  border-color: rgba(59, 130, 246, 0.16);
}

.resource__icon.icon-guide {
  color: #34d399;
  background: rgba(16, 185, 129, 0.08);
  border-color: rgba(16, 185, 129, 0.16);
}

.resource__icon.icon-other {
  color: var(--text-dim);
}

.resource__info {
  flex: 1;
  min-width: 0;
}

.resource__title {
  display: flex;
  align-items: center;
  gap: 8px;

  min-width: 0;

  margin-bottom: 4px;

  color: var(--text);

  font-size: 13px;
  font-weight: 800;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.public-badge {
  flex-shrink: 0;

  padding: 3px 7px;

  color: #4ade80;
  background: rgba(34, 197, 94, 0.08);

  border: 1px solid rgba(34, 197, 94, 0.2);
  border-radius: 999px;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.resource__meta {
  color: var(--text-muted);

  font-size: 10px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.resource__desc {
  margin: 5px 0 0;

  color: var(--text-dim);

  font-size: 11px;
  line-height: 1.45;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.resource__actions {
  display: flex;
  gap: 5px;
  flex-shrink: 0;
}

.btn-action {
  width: 34px;
  height: 34px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 8px;

  cursor: pointer;

  transition:
      color 0.16s ease,
      border-color 0.16s ease,
      background 0.16s ease,
      transform 0.16s ease;
}

.btn-action:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.14);
  transform: translateY(-1px);
}

.btn-action.danger:hover {
  color: #f87171;
  background: rgba(239, 68, 68, 0.07);
  border-color: rgba(239, 68, 68, 0.28);
}

/* =========================================================
   GALLERY
   ========================================================= */

.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(205px, 1fr));
  gap: 12px;
}

.shot {
  position: relative;

  display: flex;
  flex-direction: column;
  gap: 7px;

  padding: 6px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.045),
          rgba(255, 255, 255, 0.018)
      );

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 14px;

  cursor: pointer;

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.shot:hover {
  transform: translateY(-3px);

  border-color: rgba(139, 92, 246, 0.32);

  box-shadow:
      0 16px 38px rgba(0, 0, 0, 0.3),
      0 0 0 1px rgba(124, 58, 237, 0.06);
}

.shot__image {
  position: relative;

  aspect-ratio: 16 / 10;

  overflow: hidden;

  border-radius: 9px;

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.08),
          transparent 60%
      ),
      #0a0a10;
}

.shot__image::after {
  content: '';

  position: absolute;
  inset: 0;

  background: linear-gradient(
      180deg,
      rgba(255, 255, 255, 0.035),
      transparent 28%,
      transparent 65%,
      rgba(0, 0, 0, 0.35)
  );

  pointer-events: none;
}

.shot__image img {
  position: absolute;
  inset: 0;

  width: 100%;
  height: 100%;

  object-fit: cover;
  display: block;

  transition: transform 0.35s ease;
}

.shot:hover .shot__image img {
  transform: scale(1.035);
}

.shot__placeholder {
  position: absolute;
  inset: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--text-muted);

  background:
      radial-gradient(
          circle at center,
          rgba(124, 58, 237, 0.08),
          transparent 60%
      );
}

.shot__overlay {
  position: absolute;
  z-index: 2;

  right: 0;
  bottom: 0;
  left: 0;

  padding: 30px 10px 9px;

  background: linear-gradient(
      0deg,
      rgba(0, 0, 0, 0.85),
      transparent
  );

  opacity: 0;

  transition: opacity 0.2s ease;
}

.shot:hover .shot__overlay {
  opacity: 1;
}

.shot__title {
  display: block;

  color: #fff;

  font-size: 11px;
  font-weight: 800;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.shot__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 8px;

  padding: 2px 4px;

  font-size: 10px;
}

.shot__author {
  min-width: 0;

  color: var(--text-dim);

  font-weight: 700;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.shot__size {
  flex-shrink: 0;
  color: var(--text-muted);
}

.shot__delete {
  position: absolute;
  top: 11px;
  right: 11px;
  z-index: 4;

  width: 29px;
  height: 29px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  color: #f87171;
  background: rgba(7, 7, 13, 0.78);

  border: 1px solid rgba(239, 68, 68, 0.32);
  border-radius: 8px;

  cursor: pointer;

  opacity: 0;

  backdrop-filter: blur(8px);

  transition:
      opacity 0.18s ease,
      background 0.18s ease,
      color 0.18s ease,
      transform 0.18s ease;
}

.shot:hover .shot__delete {
  opacity: 1;
}

.shot__delete:hover {
  color: #fff;
  background: rgba(239, 68, 68, 0.88);
  border-color: rgba(239, 68, 68, 0.95);
  transform: scale(1.04);
}

/* =========================================================
   LIGHTBOX
   ========================================================= */

.lightbox {
  position: fixed;
  inset: 0;
  z-index: 3000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 42px 22px;

  background: rgba(3, 3, 7, 0.93);

  backdrop-filter: blur(14px);

  animation: lbFade 0.18s ease;
}

@keyframes lbFade {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

.lightbox__close,
.lightbox__nav {
  color: #e5e7eb;

  background: rgba(255, 255, 255, 0.055);

  border: 1px solid rgba(255, 255, 255, 0.1);

  cursor: pointer;

  transition:
      background 0.18s ease,
      border-color 0.18s ease,
      transform 0.18s ease;
}

.lightbox__close {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 5;

  width: 40px;
  height: 40px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  border-radius: 11px;
}

.lightbox__close:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.16);
  transform: rotate(3deg);
}

.lightbox__nav {
  position: absolute;
  top: 50%;
  z-index: 5;

  width: 48px;
  height: 48px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  border-radius: 13px;

  transform: translateY(-50%);
}

.lightbox__nav:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.16);
}

.lightbox__nav--prev {
  left: 18px;
}

.lightbox__nav--next {
  right: 18px;
}

.lightbox__content {
  position: relative;

  width: 100%;
  max-width: 1100px;
  max-height: calc(100vh - 70px);

  display: flex;
  flex-direction: column;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(24, 24, 34, 0.98),
          rgba(12, 12, 19, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 18px;

  box-shadow:
      0 40px 100px rgba(0, 0, 0, 0.72),
      0 0 0 1px rgba(124, 58, 237, 0.04);
}

.lightbox__img {
  width: 100%;
  max-height: 68vh;

  object-fit: contain;

  display: block;

  background: #08080d;
}

.lightbox__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;

  min-height: 300px;

  color: var(--text-muted);
  background: #08080d;
}

.lightbox__info {
  display: flex;
  flex-direction: column;
  gap: 7px;

  padding: 17px 20px 20px;

  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.lightbox__title {
  color: var(--text, #fff);

  font-size: 17px;
  font-weight: 850;
  letter-spacing: -0.2px;
}

.lightbox__meta {
  color: var(--text-dim, #8a8a97);

  font-size: 11px;
}

.lightbox__desc {
  margin: 4px 0 0;

  color: var(--text-dim, #8a8a97);

  font-size: 12px;
  line-height: 1.55;

  white-space: pre-wrap;
}

.lightbox__download {
  align-self: flex-start;

  display: inline-flex;
  align-items: center;
  gap: 8px;

  margin-top: 6px;
  padding: 9px 15px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent, #7c3aed)
      );

  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 9px;

  font-size: 12px;
  font-weight: 800;

  cursor: pointer;

  box-shadow:
      0 7px 20px rgba(124, 58, 237, 0.22),
      inset 0 1px 0 rgba(255, 255, 255, 0.1);

  transition:
      transform 0.18s ease,
      filter 0.18s ease;
}

.lightbox__download:hover {
  transform: translateY(-1px);
  filter: brightness(1.08);
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 760px) {
  .head {
    align-items: stretch;
  }

  .filters {
    width: 100%;
  }

  .btn-create {
    width: 100%;
  }

  .row {
    grid-template-columns: 1fr;
  }

  .gallery {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px;
  }

  .resource {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .resource__info {
    min-width: calc(100% - 60px);
  }

  .resource__actions {
    width: 100%;
    justify-content: flex-end;
    padding-top: 2px;
  }

  .lightbox {
    padding: 58px 10px 16px;
  }

  .lightbox__nav {
    width: 38px;
    height: 38px;
  }

  .lightbox__nav--prev {
    left: 8px;
  }

  .lightbox__nav--next {
    right: 8px;
  }

  .lightbox__content {
    border-radius: 14px;
  }

  .lightbox__img {
    max-height: 55vh;
  }
}

@media (max-width: 460px) {
  .form {
    padding: 15px;
  }

  .gallery {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .shot {
    border-radius: 11px;
  }

  .shot__delete {
    opacity: 1;
  }

  .resource {
    padding: 12px;
  }

  .resource__icon {
    width: 40px;
    height: 40px;
  }

  .resource__title {
    font-size: 12px;
  }

  .resource__meta {
    white-space: normal;
    line-height: 1.4;
  }
}
</style>
