<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'
import ClanEventComments from '@/components/clan/ClanEventComments.vue'

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
/* =========================================================
   ROOT
========================================================= */

.events-tab {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* =========================================================
   TOOLBAR
========================================================= */

.events-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 18px;

  padding: 2px 1px;
}

.events-toolbar__title {
  display: flex;
  align-items: center;
  gap: 11px;
}

.events-toolbar__icon {
  width: 38px;
  height: 38px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border: 1px solid rgba(139, 92, 246, 0.12);
  border-radius: 11px;

  background: rgba(139, 92, 246, 0.07);

  color: var(--accent-light);
}

.events-toolbar__icon svg {
  width: 18px;
  height: 18px;
}

.events-toolbar h2 {
  margin: 0;

  color: var(--text);

  font-size: 14px;
  font-weight: 850;
  letter-spacing: -0.02em;
}

.events-toolbar p {
  margin: 3px 0 0;

  color: var(--text-muted);

  font-size: 10px;
  line-height: 1.4;
}

.create-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  min-height: 37px;

  padding: 0 13px;

  border: 1px solid rgba(139, 92, 246, 0.24);
  border-radius: 9px;

  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, 0.95),
          rgba(109, 40, 217, 0.95)
      );

  color: #fff;

  font-family: inherit;
  font-size: 10px;
  font-weight: 800;

  cursor: pointer;

  box-shadow:
      0 7px 20px rgba(109, 40, 217, 0.13);

  transition:
      transform 0.18s ease,
      box-shadow 0.18s ease,
      background 0.18s ease;
}

.create-button svg {
  width: 14px;
  height: 14px;
}

.create-button:hover {
  transform: translateY(-1px);

  box-shadow:
      0 9px 26px rgba(109, 40, 217, 0.23);
}

.create-button--active {
  color: #c4b5fd;

  background: rgba(139, 92, 246, 0.08);

  border-color: rgba(139, 92, 246, 0.2);

  box-shadow: none;
}

/* =========================================================
   CREATE PANEL
========================================================= */

.create-panel {
  overflow: hidden;

  border: 1px solid rgba(139, 92, 246, 0.13);
  border-radius: 15px;

  background:
      radial-gradient(
          circle at 0% 0%,
          rgba(139, 92, 246, 0.065),
          transparent 40%
      ),
      var(--bg-card);

  box-shadow:
      0 18px 50px rgba(0, 0, 0, 0.16);
}

.create-panel__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 15px;

  padding: 18px 19px 15px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.create-panel__eyebrow {
  display: block;

  margin-bottom: 4px;

  color: var(--accent-light);

  font-size: 8px;
  font-weight: 850;
  letter-spacing: 0.14em;
}

.create-panel__header h3 {
  margin: 0;

  color: var(--text);

  font-size: 14px;
  font-weight: 850;
}

.create-panel__header p {
  margin: 4px 0 0;

  color: var(--text-muted);

  font-size: 9px;
}

.create-panel__badge {
  width: 32px;
  height: 32px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 9px;

  background: rgba(139, 92, 246, 0.08);

  color: var(--accent-light);
}

.create-panel__badge svg {
  width: 15px;
  height: 15px;
}

.create-panel__fields {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr);

  gap: 13px;

  padding: 17px 19px;
}

.form-field {
  min-width: 0;
}

.form-field:nth-child(3) {
  grid-column: 1 / -1;
}

.form-field:nth-child(4) {
  grid-column: 1 / -1;
}

.form-field > label,
.form-label-row label {
  display: block;

  color: var(--text-dim);

  font-size: 9px;
  font-weight: 750;
}

.form-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-label-row span {
  color: #52525b;

  font-size: 8px;
}

.form-field input,
.form-field textarea,
.type-select,
.date-input {
  width: 100%;
  box-sizing: border-box;

  border: 1px solid var(--border);
  border-radius: 9px;

  background: #0c0c12;

  color: var(--text);

  font-family: inherit;
  font-size: 10px;

  outline: none;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.form-field > input,
.form-field > textarea {
  margin-top: 6px;
}

.form-field > input {
  height: 37px;

  padding: 0 10px;
}

.form-field > textarea {
  display: block;

  min-height: 105px;

  padding: 10px;

  resize: vertical;

  line-height: 1.55;
}

.form-field input::placeholder,
.form-field textarea::placeholder {
  color: #4f4f59;
}

.form-field input:focus,
.form-field textarea:focus,
.type-select:focus-within,
.date-input:focus-within {
  border-color: rgba(139, 92, 246, 0.52);

  background: rgba(139, 92, 246, 0.025);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, 0.065);
}

.type-select {
  position: relative;

  height: 37px;

  display: flex;

  margin-top: 6px;
}

.type-select select {
  width: 100%;

  appearance: none;

  padding: 0 30px 0 10px;

  border: 0;
  outline: 0;

  background: transparent;

  color: var(--text);

  font: inherit;
  font-size: 10px;

  cursor: pointer;
}

.type-select svg {
  position: absolute;

  top: 50%;
  right: 10px;

  width: 12px;
  height: 12px;

  pointer-events: none;

  transform: translateY(-50%);

  color: var(--text-muted);
}

.date-input {
  position: relative;

  height: 37px;

  display: flex;
  align-items: center;

  margin-top: 6px;
}

.date-input svg {
  width: 14px;
  height: 14px;

  flex: 0 0 auto;

  margin-left: 10px;

  color: var(--text-muted);
}

.date-input input {
  width: 100%;
  height: 100%;

  padding: 0 9px;

  border: 0 !important;

  background: transparent !important;

  box-shadow: none !important;

  color: var(--text);

  font-family: inherit;
  font-size: 10px;
}

.optional {
  margin-left: 4px;

  color: #52525b;

  font-size: 8px;
  font-weight: 500;
}

.create-panel__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 15px;

  padding: 12px 19px;

  border-top: 1px solid rgba(255, 255, 255, 0.055);

  background: rgba(0, 0, 0, 0.08);
}

.create-panel__hint {
  display: flex;
  align-items: center;
  gap: 5px;

  color: #52525b;

  font-size: 8px;
}

.create-panel__hint svg {
  width: 12px;
  height: 12px;
}

.publish-button {
  min-height: 36px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 6px;

  padding: 0 13px;

  border: 0;
  border-radius: 8px;

  background: linear-gradient(
      135deg,
      #8b5cf6,
      #6d28d9
  );

  color: #fff;

  font-family: inherit;
  font-size: 10px;
  font-weight: 800;

  cursor: pointer;

  box-shadow:
      0 7px 18px rgba(109, 40, 217, 0.17);
}

.publish-button svg {
  width: 13px;
  height: 13px;
}

.publish-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
}

.spinner {
  width: 12px;
  height: 12px;

  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;

  border-radius: 50%;

  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================================
   EVENTS LIST
========================================================= */

.events-list {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.event-card {
  position: relative;

  overflow: hidden;

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 14px;

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.022),
          transparent 45%
      ),
      var(--bg-card);

  transition:
      border-color 0.2s ease,
      transform 0.2s ease,
      box-shadow 0.2s ease;
}

.event-card:hover {
  border-color: rgba(255, 255, 255, 0.1);

  transform: translateY(-1px);

  box-shadow:
      0 14px 35px rgba(0, 0, 0, 0.12);
}

.event-card__accent {
  position: absolute;
  inset: 0 auto 0 0;

  width: 2px;

  background: #60a5fa;

  opacity: 0.8;
}

.event-card--event .event-card__accent {
  background: #f472b6;
}

.event-card--training .event-card__accent {
  background: #34d399;
}

.event-card__inner {
  padding: 15px 17px 13px;
}

.event-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 10px;

  margin-bottom: 11px;
}

.event-card__meta {
  display: flex;
  align-items: center;

  gap: 8px;

  min-width: 0;
}

.event-type {
  display: inline-flex;
  align-items: center;

  gap: 6px;

  flex: 0 0 auto;

  padding: 4px 8px;

  border-radius: 999px;

  font-size: 8px;
  font-weight: 850;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

.event-type__dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: currentColor;

  box-shadow:
      0 0 8px currentColor;
}

.event-type--announcement {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.08);
}

.event-type--event {
  color: #f472b6;
  background: rgba(244, 114, 182, 0.08);
}

.event-type--training {
  color: #34d399;
  background: rgba(52, 211, 153, 0.08);
}

.event-date {
  display: inline-flex;
  align-items: center;

  gap: 4px;

  min-width: 0;

  color: var(--text-muted);

  font-size: 9px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.event-date svg {
  width: 12px;
  height: 12px;

  flex: 0 0 auto;
}

.delete-button {
  width: 27px;
  height: 27px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border: 1px solid transparent;
  border-radius: 7px;

  background: transparent;

  color: #52525b;

  cursor: pointer;

  transition: 0.18s;
}

.delete-button svg {
  width: 13px;
  height: 13px;
}

.delete-button:hover {
  border-color: rgba(248, 113, 113, 0.12);

  background: rgba(248, 113, 113, 0.07);

  color: #f87171;
}

.event-card__content {
  padding: 0 1px;
}

.event-card__title {
  margin: 0;

  color: var(--text);

  font-size: 14px;
  line-height: 1.35;
  font-weight: 850;
  letter-spacing: -0.015em;
}

.event-card__body {
  margin: 7px 0 0;

  color: var(--text-dim);

  font-family: inherit;
  font-size: 10px;
  line-height: 1.65;

  white-space: pre-wrap;

  overflow-wrap: anywhere;
}

.event-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 12px;

  margin-top: 14px;
  padding-top: 11px;

  border-top: 1px solid rgba(255, 255, 255, 0.045);
}

.event-author {
  display: flex;
  align-items: center;

  gap: 7px;

  min-width: 0;
}

.event-author__avatar {
  width: 25px;
  height: 25px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 7px;

  color: #fff;

  font-size: 9px;
  font-weight: 900;
}

.event-author > div:last-child {
  min-width: 0;
}

.event-author span,
.event-author strong {
  display: block;
}

.event-author span {
  color: #52525b;

  font-size: 7px;
}

.event-author strong {
  margin-top: 1px;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 700;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.comments-button {
  display: inline-flex;
  align-items: center;

  gap: 5px;

  padding: 6px 8px;

  border: 1px solid transparent;
  border-radius: 7px;

  background: transparent;

  color: var(--text-muted);

  font-family: inherit;
  font-size: 9px;
  font-weight: 750;

  cursor: pointer;

  transition: 0.18s;
}

.comments-button svg {
  width: 13px;
  height: 13px;
}

.comments-button:hover,
.comments-button--open {
  border-color: rgba(139, 92, 246, 0.13);

  background: rgba(139, 92, 246, 0.06);

  color: var(--accent-light);
}

.comments-button__chevron {
  width: 10px !important;
  height: 10px !important;

  margin-left: 1px;

  transition: transform 0.2s ease;
}

.comments-button__chevron--open {
  transform: rotate(180deg);
}

.event-card__comments {
  margin: 12px -17px -13px;
  padding: 13px 17px;

  border-top: 1px solid rgba(255, 255, 255, 0.055);

  background: rgba(0, 0, 0, 0.08);
}

/* =========================================================
   COMMENTS TRANSITION
========================================================= */

.comments-enter-active,
.comments-leave-active {
  overflow: hidden;

  transition:
      opacity 0.2s ease,
      max-height 0.25s ease;
}

.comments-enter-from,
.comments-leave-to {
  max-height: 0;
  opacity: 0;
}

.comments-enter-to,
.comments-leave-from {
  max-height: 800px;
  opacity: 1;
}

/* =========================================================
   CREATE TRANSITION
========================================================= */

.create-form-enter-active,
.create-form-leave-active {
  overflow: hidden;

  transition:
      opacity 0.2s ease,
      transform 0.2s ease,
      max-height 0.25s ease;
}

.create-form-enter-from,
.create-form-leave-to {
  max-height: 0;

  opacity: 0;

  transform: translateY(-6px);
}

.create-form-enter-to,
.create-form-leave-from {
  max-height: 600px;

  opacity: 1;

  transform: translateY(0);
}

/* =========================================================
   EMPTY
========================================================= */

.events-empty {
  min-height: 260px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  padding: 30px;

  border: 1px dashed rgba(255, 255, 255, 0.08);
  border-radius: 15px;

  background:
      radial-gradient(
          circle at 50% 25%,
          rgba(139, 92, 246, 0.05),
          transparent 42%
      ),
      rgba(255, 255, 255, 0.012);

  text-align: center;
}

.events-empty__icon {
  width: 48px;
  height: 48px;

  display: grid;
  place-items: center;

  margin-bottom: 11px;

  border: 1px solid rgba(139, 92, 246, 0.12);
  border-radius: 13px;

  background: rgba(139, 92, 246, 0.06);

  color: var(--accent-light);
}

.events-empty__icon svg {
  width: 21px;
  height: 21px;
}

.events-empty strong {
  color: var(--text);

  font-size: 12px;
  font-weight: 800;
}

.events-empty span {
  margin-top: 4px;

  color: var(--text-muted);

  font-size: 9px;
}

.events-empty button {
  margin-top: 13px;

  padding: 7px 11px;

  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 8px;

  background: rgba(139, 92, 246, 0.07);

  color: var(--accent-light);

  font-family: inherit;
  font-size: 9px;
  font-weight: 800;

  cursor: pointer;

  transition: 0.18s;
}

.events-empty button:hover {
  background: rgba(139, 92, 246, 0.12);
}

/* =========================================================
   LOADING
========================================================= */

.events-loading {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.skeleton-event {
  min-height: 145px;

  padding: 15px 17px;

  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 14px;

  background: var(--bg-card);

  overflow: hidden;
}

.skeleton-event > * {
  position: relative;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.045);
  border-radius: 6px;
}

.skeleton-event > *::after {
  content: '';

  position: absolute;
  inset: 0;

  transform: translateX(-100%);

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(255, 255, 255, 0.045),
          transparent
      );

  animation: skeleton 1.4s infinite;
}

.skeleton-event__top {
  width: 100%;
  height: 18px;

  display: flex;
  justify-content: space-between;

  background: transparent !important;
}

.skeleton-event__top span:first-child {
  width: 60px;
  height: 18px;
}

.skeleton-event__top span:last-child {
  width: 100px;
  height: 14px;
}

.skeleton-event__title {
  width: 45%;
  height: 17px;

  margin-top: 15px;
}

.skeleton-event__line {
  width: 85%;
  height: 8px;

  margin-top: 10px;
}

.skeleton-event__line--short {
  width: 60%;
  margin-top: 6px;
}

.skeleton-event__bottom {
  width: 100%;
  height: 1px;

  margin-top: 19px;
}

@keyframes skeleton {
  to {
    transform: translateX(100%);
  }
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 650px) {
  .events-toolbar {
    align-items: flex-start;
  }

  .create-panel__fields {
    grid-template-columns: 1fr;
  }

  .form-field:nth-child(3),
  .form-field:nth-child(4) {
    grid-column: auto;
  }

  .create-panel__footer {
    align-items: flex-start;
    flex-direction: column;
  }

  .publish-button {
    width: 100%;
  }

  .create-panel__hint {
    width: 100%;
  }
}

@media (max-width: 520px) {
  .events-toolbar {
    flex-direction: column;
  }

  .events-toolbar__title {
    width: 100%;
  }

  .create-button {
    width: 100%;
  }

  .event-card__header {
    align-items: flex-start;
  }

  .event-card__meta {
    flex-wrap: wrap;
  }

  .event-date {
    width: 100%;
  }

  .event-card__footer {
    align-items: flex-start;
    flex-direction: column;
  }

  .comments-button {
    width: 100%;

    justify-content: center;
  }

  .event-card__comments {
    margin-left: -17px;
    margin-right: -17px;
  }
}

@media (max-width: 390px) {
  .events-toolbar__icon {
    width: 34px;
    height: 34px;
  }

  .events-toolbar h2 {
    font-size: 13px;
  }

  .events-toolbar p {
    font-size: 9px;
  }

  .event-card__inner {
    padding: 13px;
  }

  .event-card__comments {
    margin-left: -13px;
    margin-right: -13px;
  }

  .event-card__title {
    font-size: 13px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .events-tab *,
  .events-tab *::before,
  .events-tab *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
  }
}
</style>
