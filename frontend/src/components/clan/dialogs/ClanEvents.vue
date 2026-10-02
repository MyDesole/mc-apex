<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'
import ClanEventComments from '@/components/clan/dialogs/ClanEventComments.vue'

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
@import "@/components/clan/dialogs/ClanEvents.css";
</style>
