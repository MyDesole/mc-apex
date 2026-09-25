<script setup>
import { onMounted, ref } from 'vue'
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

async function load() {
  loading.value = true
  try {
    const params = categoryFilter.value ? { category: categoryFilter.value } : {}
    const data = await myClanApi.resources(params)
    resources.value = data.data
  } finally {
    loading.value = false
  }
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

// иконки по категориям (используются в template через <component :is>)
const ICONS = {
  resource_pack: 'iconPackage',
  screenshot: 'iconImage',
  config: 'iconGear',
  guide: 'iconBook',
  other: 'iconFile',
}
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
            @click="categoryFilter = c.value; load()"
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

    <!-- Список -->
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!resources.length" class="empty">Ресурсов нет</div>

    <div v-else class="list">
      <div v-for="r in resources" :key="r.id" class="resource">
        <div class="resource__icon" :class="`icon-${r.category}`">
          <!-- Ресурс-пак -->
          <svg v-if="r.category === 'resource_pack'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            <path d="M3.27 6.96 12 12.01l8.73-5.05" />
            <path d="M12 22.08V12" />
          </svg>

          <!-- Скриншот -->
          <svg v-else-if="r.category === 'screenshot'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
          </svg>

          <!-- Конфиг -->
          <svg v-else-if="r.category === 'config'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
          </svg>

          <!-- Гайд -->
          <svg v-else-if="r.category === 'guide'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
          </svg>

          <!-- Другое -->
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

/* Цвета иконок по категориям */
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
}
</style>