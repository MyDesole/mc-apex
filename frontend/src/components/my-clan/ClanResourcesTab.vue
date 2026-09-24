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

function getFileIcon(category) {
  return {
    resource_pack: '📦',
    screenshot: '🖼',
    config: '⚙️',
    guide: '📖',
    other: '📎',
  }[category] || '📎'
}

onMounted(load)
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
        <div class="resource__icon">{{ getFileIcon(r.category) }}</div>

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
          <button class="btn-action" @click="download(r)">⬇️</button>
          <button
              v-if="permissions.resources || r.author_id === auth.user?.id"
              class="btn-action danger"
              @click="remove(r)"
          >
            🗑
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
  padding: 10px 14px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px dashed var(--border);
  border-radius: 9px;
  cursor: pointer;
  font-size: 13px;
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

.resource__icon { font-size: 28px; flex-shrink: 0; }
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
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  color: var(--text-dim);
  font-size: 14px;
}

.btn-action:hover { border-color: var(--border-hover); color: var(--text); }
.btn-action.danger:hover { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

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
}
</style>