<script setup>
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'

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
  <div class="events">
    <div v-if="canManage" class="actions">
      <button class="btn-create" @click="creating = !creating">
        + Создать
      </button>
    </div>

    <div v-if="creating" class="form">
      <select v-model="form.type">
        <option value="announcement">Анонс</option>
        <option value="event">Мероприятие</option>
        <option value="training">Тренировка</option>
      </select>

      <input v-model="form.title" placeholder="Заголовок" />
      <textarea v-model="form.body" rows="3" placeholder="Описание" />
      <input v-model="form.starts_at" type="datetime-local" />

      <div class="form-actions">
        <button class="btn-cancel" @click="creating = false">Отмена</button>
        <button class="btn-save" @click="submit">Создать</button>
      </div>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!events.length" class="empty">Пока нет мероприятий</div>

    <div v-else class="list">
      <article v-for="e in events" :key="e.id" class="event">
        <header>
                    <span class="type" :class="`type-${e.type}`">
                        {{ typeLabels[e.type] }}
                    </span>
          <span v-if="e.starts_at" class="date">
                        {{ new Date(e.starts_at).toLocaleString('ru-RU') }}
                    </span>
        </header>

        <h3>{{ e.title }}</h3>
        <p v-if="e.body">{{ e.body }}</p>

        <footer>
          <span>Автор: {{ e.author?.username }}</span>
          <button v-if="canManage" class="btn-del" @click="remove(e)">Удалить</button>
        </footer>
      </article>
    </div>
  </div>
</template>

<style scoped>
.events { display: flex; flex-direction: column; gap: 12px; }

.actions { display: flex; justify-content: flex-end; }

.btn-create {
  padding: 9px 16px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
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
  border-radius: 8px;
  font: inherit;
  outline: none;
}

.form-actions { display: flex; gap: 8px; justify-content: flex-end; }

.btn-cancel, .btn-save {
  padding: 9px 16px;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-cancel { color: var(--text-dim); background: transparent; border: 1px solid var(--border); }
.btn-save { color: #fff; background: var(--accent); }

.list { display: flex; flex-direction: column; gap: 12px; }

.event {
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.event header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
}

.type {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.type-announcement { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.type-event { color: #f472b6; background: rgba(244, 114, 182, 0.1); }
.type-training { color: #34d399; background: rgba(52, 211, 153, 0.1); }

.date { color: var(--text-dim); font-size: 12px; }

.event h3 { margin: 0 0 6px; font-size: 16px; }
.event p { margin: 0 0 12px; color: var(--text-dim); font-size: 13px; }

.event footer {
  display: flex;
  justify-content: space-between;
  color: var(--text-muted);
  font-size: 12px;
}

.btn-del {
  color: #f87171;
  background: transparent;
  border: 0;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.empty { padding: 40px; text-align: center; color: var(--text-dim); }
</style>