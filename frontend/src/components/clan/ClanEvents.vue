<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'
import ClanEventComments from './ClanEventComments.vue'

const props = defineProps({
  clan: Object,
  canManage: Boolean,
})

const events = ref([])
const loading = ref(true)
const creating = ref(false)
const form = ref({ type: 'announcement', title: '', body: '', starts_at: '' })

async function load() {
  loading.value = true
  try {
    const data = await clansApi.events(props.clan.id)
    events.value = data.data
  } finally {
    loading.value = false
  }
}
const openComments = ref({})

function toggleComments(id) {
  openComments.value[id] = !openComments.value[id]
}
async function submit() {
  await clansApi.createEvent(props.clan.id, {
    ...form.value,
    starts_at: form.value.starts_at || null,
  })
  creating.value = false
  form.value = { type: 'announcement', title: '', body: '', starts_at: '' }
  await load()
}

async function remove(event) {
  if (!await confirmDialog('Удалить?')) return
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
  <div class="events">

    <div class="section-head">
      <div>
        <div class="section-title">Мероприятия</div>
        <div class="section-subtitle">
          Анонсы, тренировки и события клана
        </div>
      </div>

      <button
          v-if="canManage"
          class="btn-create"
          @click="creating = !creating"
      >
        <span>+</span>
        Создать
      </button>
    </div>

    <div v-if="creating" class="create-card">
      <div class="create-title">Новое мероприятие</div>

      <div class="form-grid">
        <div class="field">
          <label>Тип</label>
          <select v-model="form.type">
            <option value="announcement">Анонс</option>
            <option value="event">Мероприятие</option>
            <option value="training">Тренировка</option>
          </select>
        </div>

        <div class="field">
          <label>Дата</label>
          <input v-model="form.starts_at" type="datetime-local">
        </div>
      </div>

      <div class="field">
        <label>Заголовок</label>
        <input v-model="form.title" placeholder="Например: Тренировка состава">
      </div>

      <div class="field">
        <label>Описание</label>
        <textarea
            v-model="form.body"
            rows="4"
            placeholder="Что нужно знать участникам?"
        />
      </div>

      <div class="form-actions">
        <button class="btn-cancel" @click="creating = false">
          Отмена
        </button>

        <button class="btn-save" @click="submit">
          Создать мероприятие
        </button>
      </div>
    </div>

    <div v-if="loading" class="state-card">
      <div class="loader"></div>
      Загружаем мероприятия...
    </div>

    <div v-else-if="!events.length" class="state-card">
      <div class="empty-icon">◈</div>
      <strong>Пока ничего нет</strong>
      <span>Создай первое мероприятие для своего клана</span>
    </div>

    <div v-else class="events-list">
      <article
          v-for="e in events"
          :key="e.id"
          class="event-card"
      >
        <div class="event-accent" :class="`accent-${e.type}`"></div>

        <header class="event-head">
          <span class="type" :class="`type-${e.type}`">
            {{ typeLabels[e.type] }}
          </span>

          <span v-if="e.starts_at" class="date">
            <span>◷</span>
            {{ new Date(e.starts_at).toLocaleString('ru-RU') }}
          </span>
        </header>

        <h3>{{ e.title }}</h3>

        <p v-if="e.body">{{ e.body }}</p>

        <footer>
          <div class="author">
            <span class="author-dot"></span>
            {{ e.author?.username || 'Администратор' }}
          </div>

          <button
              v-if="canManage"
              class="btn-delete"
              @click="remove(e)"
          >
            Удалить
          </button>
        </footer>

        <button
            class="comments-toggle"
            @click="toggleComments(e.id)"
        >
          <span>💬</span>
          Комментарии

          <svg
              width="13"
              height="13"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              :style="{
              transform: openComments[e.id]
                ? 'rotate(180deg)'
                : 'none'
            }"
          >
            <path
                d="m6 9 6 6 6-6"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
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
.events {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.section-title {
  font-size: 17px;
  font-weight: 850;
}

.section-subtitle {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 12px;
}

.btn-create {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 36px;
  padding: 0 13px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  transition: .18s;
}

.btn-create:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.btn-create span {
  font-size: 17px;
  line-height: 0;
}

.create-card {
  padding: 17px;
  background:
      linear-gradient(135deg, rgba(124,58,237,.05), transparent),
      var(--bg-card);
  border: 1px solid rgba(139,92,246,.2);
  border-radius: 14px;
}

.create-title {
  margin-bottom: 14px;
  font-size: 14px;
  font-weight: 850;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.field {
  margin-bottom: 11px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-muted);
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .6px;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 12px;
  color: var(--text);
  background: #0c0c13;
  border: 1px solid var(--border);
  border-radius: 9px;
  outline: none;
  font: inherit;
  font-size: 12px;
}

.field textarea {
  resize: vertical;
  line-height: 1.5;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: rgba(139,92,246,.6);
  box-shadow: 0 0 0 3px rgba(124,58,237,.1);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.btn-cancel,
.btn-save {
  min-height: 35px;
  padding: 0 13px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-save {
  color: #fff;
  background: var(--accent);
  border: 0;
}

.events-list {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.event-card {
  position: relative;
  overflow: hidden;
  padding: 17px 18px 0;
  background:
      linear-gradient(135deg, rgba(255,255,255,.018), transparent 45%),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: .2s;
}

.event-card:hover {
  border-color: rgba(139,92,246,.25);
}

.event-accent {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 2px;
}

.accent-announcement { background: #60a5fa; }
.accent-event { background: #ec4899; }
.accent-training { background: #22c55e; }

.event-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.type {
  padding: 5px 8px;
  border-radius: 6px;
  font-size: 9px;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .5px;
}

.type-announcement {
  color: #93c5fd;
  background: rgba(96,165,250,.1);
}

.type-event {
  color: #f9a8d4;
  background: rgba(236,72,153,.1);
}

.type-training {
  color: #86efac;
  background: rgba(34,197,94,.1);
}

.date {
  display: flex;
  align-items: center;
  gap: 5px;
  color: var(--text-muted);
  font-size: 10px;
}

.event-card h3 {
  margin: 12px 0 6px;
  font-size: 16px;
  font-weight: 850;
}

.event-card p {
  margin: 0 0 14px;
  color: var(--text-dim);
  font-size: 12px;
  line-height: 1.65;
  white-space: pre-wrap;
  word-break: break-word;
}

.event-card footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 0;
  border-top: 1px solid rgba(255,255,255,.045);
}

.author {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
  font-size: 10px;
}

.author-dot {
  width: 5px;
  height: 5px;
  background: var(--accent);
  border-radius: 50%;
}

.btn-delete {
  color: #f87171;
  background: transparent;
  border: 0;
  font-size: 10px;
  font-weight: 750;
  cursor: pointer;
}

.comments-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 9px 0;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  border-top: 1px solid rgba(255,255,255,.045);
  font-size: 11px;
  font-weight: 750;
  text-align: left;
  cursor: pointer;
}

.comments-toggle:hover {
  color: var(--accent-light);
}

.comments-toggle svg {
  margin-left: auto;
  transition: .2s;
}

.state-card {
  min-height: 150px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 7px;
  color: var(--text-muted);
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
  font-size: 12px;
}

.state-card strong {
  color: var(--text-dim);
}

.empty-icon {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent-light);
  background: rgba(124,58,237,.08);
  border-radius: 11px;
  font-size: 19px;
}

.loader {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(139,92,246,.18);
  border-top-color: var(--accent-light);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 600px) {
  .section-head {
    align-items: flex-start;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .event-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .date {
    margin-top: -5px;
  }
}
</style>