<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { myClanApi } from '@/services/clan/myClan.js'
import { useAuthStore } from '@/stores/core/auth.js'

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
@import "@/components/clan/tabs/ClanResourcesTab.css";
</style>
