<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'
import ClanEventComments from '@/components/clan/dialogs/ClanEventComments.vue'

const props = defineProps({
  clan: { type: Object, required: true },
  permissions: { type: Object, default: () => ({}) },
})

const auth = useAuthStore()

const events = ref([])
const loading = ref(true)
const showForm = ref(false)

const form = ref({
  type: 'announcement',
  title: '',
  body: '',
  starts_at: '',
})

const processing = ref(false)
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

    form.value = {
      type: 'announcement',
      title: '',
      body: '',
      starts_at: '',
    }

    await load()
  } finally {
    processing.value = false
  }
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

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="events-tab">

    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <div class="events-toolbar">
      <div class="events-toolbar__title">
        <div class="events-toolbar__icon">
          <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
          >
            <path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8l-4 3V5Z" />
            <path d="M8 8h8" />
            <path d="M8 12h5" />
          </svg>
        </div>

        <div>
          <h2>Новости клана</h2>
          <p>
            Анонсы, мероприятия и важные события для участников.
          </p>
        </div>
      </div>

      <button
          v-if="permissions.news"
          class="create-button"
          :class="{ 'create-button--active': showForm }"
          type="button"
          @click="showForm = !showForm"
      >
        <svg
            v-if="!showForm"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>

        <svg
            v-else
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
          <path d="M6 6l12 12" />
          <path d="M18 6 6 18" />
        </svg>

        {{ showForm ? 'Отмена' : 'Создать запись' }}
      </button>
    </div>

    <!-- =====================================================
         CREATE FORM
    ====================================================== -->

    <Transition name="create-form">
      <div
          v-if="showForm"
          class="create-panel"
      >
        <div class="create-panel__header">
          <div>
            <span class="create-panel__eyebrow">
              НОВАЯ ЗАПИСЬ
            </span>

            <h3>
              Создать новость
            </h3>

            <p>
              Опубликуй информацию, которую увидят участники клана.
            </p>
          </div>

          <div class="create-panel__badge">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
            >
              <path d="M12 3v18" />
              <path d="M5 8h14" />
              <path d="M5 16h14" />
            </svg>
          </div>
        </div>

        <div class="create-panel__fields">

          <div class="form-field">
            <label>
              Тип записи
            </label>

            <div class="type-select">
              <select v-model="form.type">
                <option value="announcement">
                  Анонс
                </option>

                <option value="event">
                  Мероприятие
                </option>

                <option value="training">
                  Тренировка
                </option>
              </select>

              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path d="m6 9 6 6 6-6" />
              </svg>
            </div>
          </div>

          <div class="form-field">
            <div class="form-label-row">
              <label for="event-title">
                Заголовок
              </label>

              <span>
                {{ form.title.length }}/120
              </span>
            </div>

            <input
                id="event-title"
                v-model="form.title"
                type="text"
                maxlength="120"
                placeholder="Например: Турнир клана в субботу"
            >
          </div>

          <div class="form-field">
            <div class="form-label-row">
              <label for="event-body">
                Текст
              </label>

              <span>
                {{ form.body.length }}
              </span>
            </div>

            <textarea
                id="event-body"
                v-model="form.body"
                rows="5"
                placeholder="Расскажи участникам подробности..."
            ></textarea>
          </div>

          <div class="form-field">
            <label for="event-date">
              Дата и время
              <span class="optional">необязательно</span>
            </label>

            <div class="date-input">
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.7"
              >
                <rect
                    x="3"
                    y="4"
                    width="18"
                    height="17"
                    rx="2"
                />
                <path d="M16 2v4" />
                <path d="M8 2v4" />
                <path d="M3 10h18" />
              </svg>

              <input
                  id="event-date"
                  v-model="form.starts_at"
                  type="datetime-local"
              >
            </div>
          </div>

        </div>

        <div class="create-panel__footer">
          <div class="create-panel__hint">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
            >
              <circle cx="12" cy="12" r="9" />
              <path d="M12 11v5" />
              <path d="M12 8h.01" />
            </svg>

            Запись будет сразу видна участникам.
          </div>

          <button
              class="publish-button"
              type="button"
              :disabled="processing || !form.title"
              @click="submit"
          >
            <span
                v-if="processing"
                class="spinner"
            ></span>

            <svg
                v-else
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
              <path d="m22 2-7 20-4-9-9-4Z" />
              <path d="M22 2 11 13" />
            </svg>

            {{ processing ? 'Публикуем…' : 'Опубликовать' }}
          </button>
        </div>
      </div>
    </Transition>

    <!-- =====================================================
         LOADING
    ====================================================== -->

    <div
        v-if="loading"
        class="events-loading"
    >
      <div
          v-for="i in 3"
          :key="i"
          class="skeleton-event"
      >
        <div class="skeleton-event__top">
          <span></span>
          <span></span>
        </div>

        <div class="skeleton-event__title"></div>

        <div class="skeleton-event__line"></div>
        <div class="skeleton-event__line skeleton-event__line--short"></div>

        <div class="skeleton-event__bottom"></div>
      </div>
    </div>

    <!-- =====================================================
         EMPTY
    ====================================================== -->

    <div
        v-else-if="!events.length"
        class="events-empty"
    >
      <div class="events-empty__icon">
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
        >
          <path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8l-4 3V5Z" />
          <path d="M8 9h8" />
          <path d="M8 13h5" />
        </svg>
      </div>

      <strong>
        Пока здесь тихо
      </strong>

      <span>
        Новостей и событий клана пока нет.
      </span>

      <button
          v-if="permissions.news"
          type="button"
          @click="showForm = true"
      >
        Создать первую запись
      </button>
    </div>

    <!-- =====================================================
         LIST
    ====================================================== -->

    <div
        v-else
        class="events-list"
    >
      <article
          v-for="e in events"
          :key="e.id"
          class="event-card"
          :class="`event-card--${e.type}`"
      >
        <!-- Accent line -->
        <div class="event-card__accent"></div>

        <div class="event-card__inner">

          <!-- Header -->
          <header class="event-card__header">
            <div class="event-card__meta">
              <span
                  class="event-type"
                  :class="`event-type--${e.type}`"
              >
                <span class="event-type__dot"></span>

                {{ typeLabels[e.type] }}
              </span>

              <span
                  v-if="e.starts_at"
                  class="event-date"
              >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                >
                  <rect
                      x="3"
                      y="4"
                      width="18"
                      height="17"
                      rx="2"
                  />
                  <path d="M16 2v4" />
                  <path d="M8 2v4" />
                  <path d="M3 10h18" />
                </svg>

                {{ formatDate(e.starts_at) }}
              </span>
            </div>

            <button
                v-if="permissions.news"
                type="button"
                class="delete-button"
                title="Удалить"
                @click="remove(e)"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M4 7h16" />
                <path d="M10 11v6" />
                <path d="M14 11v6" />
                <path d="M6 7l1 14h10l1-14" />
                <path d="M9 7V4h6v3" />
              </svg>
            </button>
          </header>

          <!-- Content -->
          <div class="event-card__content">
            <h3 class="event-card__title">
              {{ e.title }}
            </h3>

            <pre
                v-if="e.body"
                class="event-card__body"
            >{{ e.body }}</pre>
          </div>

          <!-- Footer -->
          <footer class="event-card__footer">
            <div class="event-author">
              <div
                  class="event-author__avatar"
                  :style="{
                  background: clan.banner_color || 'var(--accent)'
                }"
              >
                {{ e.author?.username?.charAt(0)?.toUpperCase() || '?' }}
              </div>

              <div>
                <span>Опубликовал</span>
                <strong>
                  {{ e.author?.username || 'Неизвестный игрок' }}
                </strong>
              </div>
            </div>

            <button
                class="comments-button"
                :class="{
                'comments-button--open': openComments[e.id]
              }"
                type="button"
                @click="toggleComments(e.id)"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>

              <span>
                {{ openComments[e.id] ? 'Скрыть комментарии' : 'Комментарии' }}
              </span>

              <svg
                  class="comments-button__chevron"
                  :class="{
                  'comments-button__chevron--open': openComments[e.id]
                }"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
              >
                <path d="m6 9 6 6 6-6" />
              </svg>
            </button>
          </footer>

          <!-- Comments -->
          <Transition name="comments">
            <div
                v-if="openComments[e.id]"
                class="event-card__comments"
            >
              <ClanEventComments
                  :clan-id="clan.id"
                  :event-id="e.id"
              />
            </div>
          </Transition>

        </div>
      </article>
    </div>

  </div>
</template>

<style scoped>
@import "@/components/clan/tabs/ClanNewsTab.css";
</style>
