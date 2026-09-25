<script setup>
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'
import ClanEventComments from "@/components/clan/ClanEventComments.vue";
const props = defineProps({
  clan: { type: Object, required: true },
  permissions: { type: Object, default: () => ({}) },
})

const auth = useAuthStore()
const events = ref([])
const loading = ref(true)
const showForm = ref(false)
const form = ref({ type: 'announcement', title: '', body: '', starts_at: '' })
const processing = ref(false)

// какие комментарии раскрыты
const openComments = ref({})

function toggleComments(id) {
  openComments.value[id] = !openComments.value[id]
}

async function load() {
  loading.value = true
  try {
    const data = await clansApi.events(props.clan.id)
    events.value = data.data
  } finally {
    loading.value = false
  }
}

async function submit() {
  processing.value = true
  try {
    await clansApi.createEvent(props.clan.id, {
      ...form.value,
      starts_at: form.value.starts_at || null,
    })
    showForm.value = false
    form.value = { type: 'announcement', title: '', body: '', starts_at: '' }
    await load()
  } finally {
    processing.value = false
  }
}

async function remove(event) {
  if (!confirm('Удалить?')) return
  await clansApi.deleteEvent(props.clan.id, event.id)
  await load()
}

const typeLabels = {
  announcement: 'Анонс',
  event: 'Мероприятие',
  training: 'Тренировка',
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <div v-if="permissions.news" class="actions">
      <button class="btn-create" @click="showForm = !showForm">
        {{ showForm ? 'Отмена' : '+ Создать' }}
      </button>
    </div>

    <!-- Форма -->
    <div v-if="showForm" class="form">
      <select v-model="form.type">
        <option value="announcement">Анонс</option>
        <option value="event">Мероприятие</option>
        <option value="training">Тренировка</option>
      </select>
      <input v-model="form.title" placeholder="Заголовок" maxlength="120" />
      <textarea v-model="form.body" rows="5" placeholder="Текст..." />
      <input v-model="form.starts_at" type="datetime-local" />
      <div class="form-actions">
        <button class="btn-save" :disabled="processing || !form.title" @click="submit">
          {{ processing ? '...' : 'Опубликовать' }}
        </button>
      </div>
    </div>

    <!-- Список -->
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!events.length" class="empty">Новостей клана пока нет</div>

    <div v-else class="list">
      <article v-for="e in events" :key="e.id" class="event">
        <header class="event__head">
          <span class="type" :class="`type-${e.type}`">
            {{ typeLabels[e.type] }}
          </span>
          <span v-if="e.starts_at" class="date">
            {{ new Date(e.starts_at).toLocaleString('ru-RU') }}
          </span>
        </header>

        <h3 class="event__title">{{ e.title }}</h3>
        <pre v-if="e.body" class="event__body">{{ e.body }}</pre>

        <footer class="event__foot">
          <span>Автор: {{ e.author?.username }}</span>
          <button
              v-if="permissions.news"
              class="btn-del"
              @click="remove(e)"
          >
            Удалить
          </button>
        </footer>

        <!-- Комментарии -->
        <button
            class="comments-toggle"
            :class="{ 'comments-toggle--open': openComments[e.id] }"
            type="button"
            @click="toggleComments(e.id)"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Комментарии
          <svg
              class="comments-toggle__chev"
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
          >
            <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>

        <ClanEventComments
            v-if="openComments[e.id]"
            :clan-id="clan.id"
            :event-id="e.id"
        />
      </article>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }

.actions { display: flex; justify-content: flex-end; }

.btn-create {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-create:hover { background: var(--accent-light); }

.form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.form input,
.form select,
.form textarea {
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.form input:focus,
.form select:focus,
.form textarea:focus { border-color: var(--accent); }

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

.list { display: flex; flex-direction: column; gap: 12px; }

.event {
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.event__head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
}

.type {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.type-announcement { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.type-event { color: #f472b6; background: rgba(244, 114, 182, 0.1); }
.type-training { color: #34d399; background: rgba(52, 211, 153, 0.1); }

.date { color: var(--text-dim); font-size: 12px; }

.event__title {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
}

.event__body {
  margin: 0 0 12px;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
  font-family: inherit;
}

.event__foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: var(--text-muted);
  font-size: 12px;
}

.btn-del {
  color: #f87171;
  background: transparent;
  border: 0;
  font-weight: 700;
  cursor: pointer;
  font-size: 12px;
}

/* === COMMENTS TOGGLE === */

.comments-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding: 6px 0;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: color 0.2s;
}

.comments-toggle:hover {
  color: var(--accent-light);
}

.comments-toggle--open {
  color: var(--accent-light);
}

.comments-toggle__chev {
  transition: transform 0.2s;
}

.comments-toggle--open .comments-toggle__chev {
  transform: rotate(180deg);
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}
</style>