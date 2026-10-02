<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { myClanApi } from '@/services/clan/myClan.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  clan: { type: Object, required: true },
  permissions: { type: Object, default: () => ({}) },
})

const auth = useAuthStore()
const topics = ref([])
const loading = ref(true)
const showForm = ref(false)
const form = ref({ title: '', body: '' })
const processing = ref(false)

const selectedTopic = ref(null)
const topicData = ref(null)
const replyBody = ref('')
const replyProcessing = ref(false)

async function load() {
  loading.value = true
  try {
    const data = await myClanApi.forum()
    topics.value = data.data
  } finally {
    loading.value = false
  }
}

async function submit() {
  processing.value = true
  try {
    await myClanApi.createTopic(form.value)
    showForm.value = false
    form.value = { title: '', body: '' }
    await load()
  } finally {
    processing.value = false
  }
}

async function openTopic(topic) {
  selectedTopic.value = topic
  const data = await myClanApi.topic(topic.id)
  topicData.value = data.topic
}

async function closeTopic() {
  selectedTopic.value = null
  topicData.value = null
  replyBody.value = ''
  await load()
}

async function sendReply() {
  if (!replyBody.value.trim()) return
  replyProcessing.value = true
  try {
    await myClanApi.reply(selectedTopic.value.id, replyBody.value)
    replyBody.value = ''
    const data = await myClanApi.topic(selectedTopic.value.id)
    topicData.value = data.topic
  } finally {
    replyProcessing.value = false
  }
}

async function pinTopic(topic) {
  await myClanApi.pinTopic(topic.id)
  await load()
}

async function lockTopic(topic) {
  await myClanApi.lockTopic(topic.id)
  await load()
}

async function removeTopic(topic) {
  if (!await confirmDialog('Удалить топик?')) return
  await myClanApi.deleteTopic(topic.id)
  await load()
}

function formatDate(d) {
  const date = new Date(d)
  const diff = Math.floor((new Date() - date) / 1000)
  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`
  return date.toLocaleDateString('ru-RU')
}

onMounted(load)
</script>

<template>
  <div class="forum-tab">
    <!-- ==================== LIST ==================== -->
    <template v-if="!selectedTopic">
      <div class="forum-header">
        <div class="forum-header__content">
          <div class="forum-header__icon">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                  d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"
                  stroke="currentColor"
                  stroke-width="1.7"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              />
            </svg>
          </div>

          <div>
            <h2>Форум клана</h2>
            <p>Общение, обсуждения и важные темы сообщества</p>
          </div>
        </div>

        <button
            v-if="permissions.forum"
            class="create-btn"
            :class="{ 'create-btn--active': showForm }"
            type="button"
            @click="showForm = !showForm"
        >
          <svg v-if="!showForm" viewBox="0 0 24 24" fill="none">
            <path
                d="M12 5v14M5 12h14"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
            />
          </svg>

          <svg v-else viewBox="0 0 24 24" fill="none">
            <path
                d="M6 6l12 12M18 6 6 18"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
            />
          </svg>

          {{ showForm ? 'Отмена' : 'Новый топик' }}
        </button>
      </div>

      <!-- Create form -->
      <Transition name="form">
        <div v-if="showForm" class="create-panel">
          <div class="create-panel__glow"></div>

          <div class="create-panel__head">
            <div>
              <div class="create-panel__eyebrow">НОВАЯ ТЕМА</div>
              <h3>Создать топик</h3>
              <p>Поделитесь новостью или начните обсуждение с участниками клана.</p>
            </div>
          </div>

          <div class="field">
            <label>Заголовок</label>
            <input
                v-model="form.title"
                maxlength="160"
                placeholder="Введите заголовок топика..."
                @keyup.enter="form.body && submit()"
            />
            <span class="field__counter">{{ form.title.length }}/160</span>
          </div>

          <div class="field">
            <label>Сообщение</label>
            <textarea
                v-model="form.body"
                rows="6"
                placeholder="О чём поговорим?"
            />
          </div>

          <div class="create-panel__footer">
            <span class="hint">
              <svg viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/>
                <path d="M12 11v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <circle cx="12" cy="8" r="1" fill="currentColor"/>
              </svg>
              Пишите понятно и по существу
            </span>

            <button
                class="publish-btn"
                :disabled="processing || !form.title.trim() || !form.body.trim()"
                type="button"
                @click="submit"
            >
              <span v-if="processing" class="spinner"></span>
              <svg v-else viewBox="0 0 24 24" fill="none">
                <path
                    d="M22 2 11 13"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="m22 2-7 20-4-9-9-4 20-7Z"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>

              {{ processing ? 'Публикация...' : 'Опубликовать' }}
            </button>
          </div>
        </div>
      </Transition>

      <!-- Loading -->
      <div v-if="loading" class="topics">
        <div v-for="n in 5" :key="n" class="topic-card topic-card--skeleton">
          <div class="skeleton skeleton--avatar"></div>

          <div class="skeleton-content">
            <div class="skeleton skeleton--title"></div>
            <div class="skeleton skeleton--meta"></div>
          </div>

          <div class="skeleton-stats">
            <div class="skeleton skeleton--stat"></div>
            <div class="skeleton skeleton--stat short"></div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="!topics.length" class="empty-state">
        <div class="empty-state__icon">
          <svg viewBox="0 0 24 24" fill="none">
            <path
                d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
          </svg>
        </div>

        <h3>Пока здесь тихо</h3>
        <p>Создайте первый топик и начните обсуждение.</p>

        <button
            v-if="permissions.forum"
            class="empty-state__button"
            type="button"
            @click="showForm = true"
        >
          <span>+</span>
          Создать первый топик
        </button>
      </div>

      <!-- Topics -->
      <div v-else class="topics">
        <div
            v-for="t in topics"
            :key="t.id"
            class="topic-card"
            :class="{
            'topic-card--pinned': t.is_pinned,
            'topic-card--locked': t.is_locked,
          }"
            @click="openTopic(t)"
        >
          <div v-if="t.is_pinned" class="topic-card__accent"></div>

          <div class="topic-card__avatar">
            <img
                v-if="t.author?.avatar_url"
                :src="t.author.avatar_url"
                alt=""
            />

            <span v-else>
              {{ t.author?.username?.charAt(0).toUpperCase() }}
            </span>

            <div class="topic-card__online"></div>
          </div>

          <div class="topic-card__main">
            <div class="topic-card__title">
              <span v-if="t.is_pinned" class="topic-badge topic-badge--pin">
                <svg viewBox="0 0 24 24" fill="none">
                  <path
                      d="m15 4 5 5-3 1-3.5 3.5V18l-3 2-1-5-4-4 5-1L14 6l1-2Z"
                      stroke="currentColor"
                      stroke-width="1.6"
                      stroke-linejoin="round"
                  />
                </svg>
                Закреплено
              </span>

              <span v-if="t.is_locked" class="topic-badge topic-badge--lock">
                <svg viewBox="0 0 24 24" fill="none">
                  <rect x="5" y="10" width="14" height="10" rx="2"
                        stroke="currentColor" stroke-width="1.6"/>
                  <path
                      d="M8 10V7a4 4 0 0 1 8 0v3"
                      stroke="currentColor"
                      stroke-width="1.6"
                      stroke-linecap="round"
                  />
                </svg>
                Закрыто
              </span>

              <span class="topic-title-text">{{ t.title }}</span>
            </div>

            <div class="topic-card__meta">
              <span class="author-name">{{ t.author?.username }}</span>
              <span class="meta-dot"></span>
              <span>{{ formatDate(t.created_at) }}</span>
            </div>
          </div>

          <div class="topic-card__stats">
            <div class="stat">
              <svg viewBox="0 0 24 24" fill="none">
                <path
                    d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
              <strong>{{ t.replies_count }}</strong>
              <span>ответов</span>
            </div>

            <div class="stat">
              <svg viewBox="0 0 24 24" fill="none">
                <path
                    d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                    stroke="currentColor"
                    stroke-width="1.6"
                />
                <circle cx="12" cy="12" r="2.5"
                        stroke="currentColor" stroke-width="1.6"/>
              </svg>
              <strong>{{ t.views }}</strong>
              <span>просмотров</span>
            </div>
          </div>

          <div class="topic-card__arrow">
            <svg viewBox="0 0 24 24" fill="none">
              <path
                  d="m9 18 6-6-6-6"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              />
            </svg>
          </div>

          <div class="topic-card__actions" @click.stop>
            <button
                v-if="permissions.forum"
                type="button"
                title="Закрепить"
                @click="pinTopic(t)"
            >
              <svg viewBox="0 0 24 24" fill="none">
                <path
                    d="m15 4 5 5-3 1-3.5 3.5V18l-3 2-1-5-4-4 5-1L14 6l1-2Z"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linejoin="round"
                />
              </svg>
            </button>

            <button
                v-if="permissions.forum"
                type="button"
                title="Закрыть"
                @click="lockTopic(t)"
            >
              <svg viewBox="0 0 24 24" fill="none">
                <rect x="5" y="10" width="14" height="10" rx="2"
                      stroke="currentColor" stroke-width="1.6"/>
                <path
                    d="M8 10V7a4 4 0 0 1 8 0v3"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                />
              </svg>
            </button>

            <button
                v-if="permissions.forum || t.author_id === auth.user?.id"
                type="button"
                title="Удалить"
                class="danger"
                @click="removeTopic(t)"
            >
              <svg viewBox="0 0 24 24" fill="none">
                <path
                    d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ==================== TOPIC VIEW ==================== -->
    <template v-else>
      <button class="back-btn" type="button" @click="closeTopic">
        <svg viewBox="0 0 24 24" fill="none">
          <path
              d="m15 18-6-6 6-6"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
          />
        </svg>
        Все топики
      </button>

      <div v-if="topicData" class="topic-view">
        <article class="topic-post">
          <div class="topic-post__top">
            <div class="topic-post__author">
              <div class="topic-post__avatar">
                <img
                    v-if="topicData.author?.avatar_url"
                    :src="topicData.author.avatar_url"
                    alt=""
                />
                <span v-else>
                  {{ topicData.author?.username?.charAt(0).toUpperCase() }}
                </span>
              </div>

              <div>
                <div class="topic-post__username">
                  {{ topicData.author?.username }}
                </div>

                <div class="topic-post__date">
                  {{ formatDate(topicData.created_at) }}
                </div>
              </div>
            </div>

            <div class="topic-post__badges">
              <span v-if="topicData.is_pinned" class="topic-badge topic-badge--pin">
                📌 Закреплено
              </span>

              <span v-if="topicData.is_locked" class="topic-badge topic-badge--lock">
                🔒 Закрыто
              </span>
            </div>
          </div>

          <h1>{{ topicData.title }}</h1>

          <div class="topic-post__content">
            <pre>{{ topicData.body }}</pre>
          </div>
        </article>

        <!-- Replies -->
        <section class="replies-section">
          <div class="section-heading">
            <div>
              <span class="section-heading__eyebrow">ОБСУЖДЕНИЕ</span>
              <h3>
                Ответы
                <span>{{ topicData.replies?.length || 0 }}</span>
              </h3>
            </div>
          </div>

          <div v-if="topicData.replies?.length" class="replies">
            <article
                v-for="r in topicData.replies"
                :key="r.id"
                class="reply"
            >
              <div class="reply__avatar">
                <img
                    v-if="r.author?.avatar_url"
                    :src="r.author.avatar_url"
                    alt=""
                />
                <span v-else>
                  {{ r.author?.username?.charAt(0).toUpperCase() }}
                </span>
              </div>

              <div class="reply__content">
                <div class="reply__head">
                  <strong>{{ r.author?.username }}</strong>
                  <span>{{ formatDate(r.created_at) }}</span>
                </div>

                <pre>{{ r.body }}</pre>
              </div>
            </article>
          </div>

          <div v-else class="no-replies">
            <span>Пока никто не ответил.</span>
            <small>Будьте первым, кто присоединится к обсуждению.</small>
          </div>
        </section>

        <!-- Reply -->
        <section v-if="!topicData.is_locked" class="reply-panel">
          <div class="reply-panel__head">
            <div class="reply-panel__icon">
              <svg viewBox="0 0 24 24" fill="none">
                <path
                    d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </div>

            <div>
              <strong>Ваш ответ</strong>
              <span>Поддерживайте конструктивное обсуждение</span>
            </div>
          </div>

          <textarea
              v-model="replyBody"
              rows="4"
              placeholder="Напишите сообщение..."
          />

          <div class="reply-panel__footer">
            <span>Enter не отправляет сообщение</span>

            <button
                class="publish-btn"
                :disabled="replyProcessing || !replyBody.trim()"
                type="button"
                @click="sendReply"
            >
              <span v-if="replyProcessing" class="spinner"></span>

              <svg v-else viewBox="0 0 24 24" fill="none">
                <path
                    d="M22 2 11 13"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />
                <path
                    d="m22 2-7 20-4-9-9-4 20-7Z"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>

              Ответить
            </button>
          </div>
        </section>

        <div v-else class="locked-notice">
          <div class="locked-notice__icon">🔒</div>
          <div>
            <strong>Топик закрыт</strong>
            <span>Новые ответы больше не принимаются.</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
@import "@/components/clan/tabs/ClanForumTab.css";
</style>
