<script setup>
/**
 * Мероприятия клана: лента анонсов, мероприятий и тренировок.
 * Слева — плитка с датой, справа — тип, заголовок, текст, автор и комментарии.
 */
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'
import ClanEventComments from '@/components/clan/dialogs/ClanEventComments.vue'
import AppIcon from '@/components/core/AppIcon.vue'

const props = defineProps({
  clan: Object,
  canManage: Boolean,
})

const events = ref([])
const loading = ref(true)
const creating = ref(false)
const saving = ref(false)
const error = ref('')
const filter = ref('all')
const form = ref({ type: 'announcement', title: '', body: '', starts_at: '' })
const openComments = ref({})

const TYPES = {
  announcement: { label: 'Анонс', icon: 'chat' },
  event: { label: 'Мероприятие', icon: 'star' },
  training: { label: 'Тренировка', icon: 'sword' },
}

const shown = computed(() => (filter.value === 'all' ? events.value : events.value.filter((e) => e.type === filter.value)))

function count(type) {
  return events.value.filter((e) => e.type === type).length
}

function isPast(e) {
  return e.starts_at && new Date(e.starts_at).getTime() < Date.now()
}

function fullDate(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const data = await clansApi.events(props.clan.id)
    events.value = data.data ?? []
  } catch {
    // Список мероприятий отдаётся только авторизованным — гостям показываем то, что пришло со страницей клана
    events.value = props.clan.events ?? []
  } finally {
    loading.value = false
  }
}

function toggleComments(id) {
  openComments.value[id] = !openComments.value[id]
}

async function submit() {
  if (!form.value.title.trim()) {
    error.value = 'Укажите заголовок.'
    return
  }

  saving.value = true
  error.value = ''

  try {
    await clansApi.createEvent(props.clan.id, {
      ...form.value,
      starts_at: form.value.starts_at || null,
    })
    creating.value = false
    form.value = { type: 'announcement', title: '', body: '', starts_at: '' }
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось создать.'
  } finally {
    saving.value = false
  }
}

async function remove(event) {
  if (!await confirmDialog('Удалить мероприятие?')) return
  await clansApi.deleteEvent(props.clan.id, event.id)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="ev">
    <div class="ev-bar">
      <div class="ev-filters">
        <button type="button" :class="{ on: filter === 'all' }" @click="filter = 'all'">Все <b>{{ events.length }}</b></button>
        <button
            v-for="(t, key) in TYPES"
            :key="key"
            type="button"
            :class="{ on: filter === key }"
            @click="filter = key"
        >
          {{ t.label }} <b>{{ count(key) }}</b>
        </button>
      </div>

      <button v-if="canManage" type="button" class="ev-btn ev-btn--main" @click="creating = !creating">
        + Создать
      </button>
    </div>

    <!-- Новое мероприятие -->
    <section v-if="creating" class="ev-form">
      <div class="ev-form__types">
        <button
            v-for="(t, key) in TYPES"
            :key="key"
            type="button"
            :class="['ev-type', `ev-type--${key}`, { on: form.type === key }]"
            @click="form.type = key"
        >
          <AppIcon :icon="t.icon" :size="14" />
          {{ t.label }}
        </button>
      </div>

      <div class="ev-form__grid">
        <label>
          <span>Заголовок</span>
          <input v-model="form.title" maxlength="120" placeholder="Например: тренировка состава" />
        </label>
        <label>
          <span>Когда</span>
          <input v-model="form.starts_at" type="datetime-local" />
        </label>
      </div>

      <label>
        <span>Описание</span>
        <textarea v-model="form.body" rows="3" placeholder="Что нужно знать участникам?" />
      </label>

      <p v-if="error" class="ev-error">{{ error }}</p>

      <div class="ev-form__actions">
        <button type="button" class="ev-btn" @click="creating = false">Отмена</button>
        <button type="button" class="ev-btn ev-btn--main" :disabled="saving" @click="submit">
          {{ saving ? 'Создаём…' : 'Создать' }}
        </button>
      </div>
    </section>

    <div v-if="loading" class="ev-empty">Загружаем мероприятия…</div>

    <div v-else-if="!shown.length" class="ev-empty">
      <AppIcon icon="clock" :size="18" />
      {{ events.length ? 'В этой категории пусто.' : 'Мероприятий пока нет.' }}
    </div>

    <div v-else class="ev-list">
      <article
          v-for="e in shown"
          :key="e.id"
          class="ev-card"
          :class="[`ev-card--${e.type}`, { 'ev-card--past': isPast(e) }]"
      >
        <!-- Дата -->
        <div class="ev-date">
          <template v-if="e.starts_at">
            <b>{{ new Date(e.starts_at).getDate() }}</b>
            <small>{{ new Date(e.starts_at).toLocaleDateString('ru-RU', { month: 'short' }) }}</small>
            <small>{{ new Date(e.starts_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) }}</small>
          </template>
          <AppIcon v-else :icon="TYPES[e.type]?.icon || 'chat'" :size="20" />
        </div>

        <div class="ev-body">
          <div class="ev-head">
            <span class="ev-type" :class="`ev-type--${e.type}`">
              <AppIcon :icon="TYPES[e.type]?.icon || 'chat'" :size="12" />
              {{ TYPES[e.type]?.label || 'Анонс' }}
            </span>
            <span v-if="isPast(e)" class="ev-past">прошло</span>
          </div>

          <h3>{{ e.title }}</h3>
          <p v-if="e.body">{{ e.body }}</p>

          <footer class="ev-foot">
            <span class="ev-author">
              {{ e.author?.username || 'Руководство' }}
              <template v-if="e.starts_at"> · {{ fullDate(e.starts_at) }}</template>
            </span>

            <button type="button" class="ev-link" @click="toggleComments(e.id)">
              <AppIcon icon="chat" :size="13" />
              {{ openComments[e.id] ? 'Скрыть комментарии' : 'Комментарии' }}
            </button>

            <button v-if="canManage" type="button" class="ev-link ev-link--danger" @click="remove(e)">
              Удалить
            </button>
          </footer>

          <ClanEventComments
              v-if="openComments[e.id]"
              :clan-id="clan.id"
              :event-id="e.id"
          />
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanEvents.css";
</style>
