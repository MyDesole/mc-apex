<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { myClanApi } from '@/services/myClan.js'
import { useAuthStore } from '@/stores/auth'

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
.forum-tab {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}

/* =========================
   Header
========================= */

.forum-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 22px 24px;
  overflow: hidden;
  background:
      radial-gradient(circle at 0% 0%, rgba(124, 58, 237, 0.13), transparent 32%),
      linear-gradient(135deg, rgba(255,255,255,0.025), rgba(255,255,255,0.008));
  border: 1px solid var(--border);
  border-radius: 18px;
}

.forum-header::after {
  content: '';
  position: absolute;
  top: 0;
  right: 18%;
  width: 180px;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(139, 92, 246, .7), transparent);
}

.forum-header__content {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.forum-header__icon {
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.12);
  border: 1px solid rgba(139, 92, 246, 0.24);
  border-radius: 13px;
  box-shadow: 0 8px 28px rgba(124, 58, 237, .12);
}

.forum-header__icon svg {
  width: 22px;
  height: 22px;
}

.forum-header h2 {
  margin: 0 0 4px;
  color: var(--text);
  font-size: 19px;
  font-weight: 800;
  letter-spacing: -.02em;
}

.forum-header p {
  margin: 0;
  color: var(--text-muted);
  font-size: 12px;
}

/* =========================
   Buttons
========================= */

.create-btn,
.publish-btn,
.empty-state__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 0;
  cursor: pointer;
  color: #fff;
  font-weight: 750;
  transition:
      transform .2s ease,
      box-shadow .2s ease,
      background .2s ease,
      opacity .2s ease;
}

.create-btn {
  min-height: 42px;
  padding: 0 17px;
  flex-shrink: 0;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border: 1px solid rgba(167, 139, 250, .35);
  border-radius: 11px;
  box-shadow: 0 8px 24px rgba(109, 40, 217, .2);
  font-size: 12px;
}

.create-btn svg {
  width: 16px;
  height: 16px;
}

.create-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 30px rgba(109, 40, 217, .3);
}

.create-btn--active {
  background: rgba(255,255,255,.06);
  border-color: var(--border-hover);
  box-shadow: none;
}

.publish-btn {
  min-height: 40px;
  padding: 0 16px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  font-size: 12px;
  box-shadow: 0 8px 22px rgba(109, 40, 217, .18);
}

.publish-btn svg {
  width: 15px;
  height: 15px;
}

.publish-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 11px 28px rgba(109, 40, 217, .28);
}

.publish-btn:disabled,
.create-btn:disabled {
  cursor: not-allowed;
  opacity: .45;
  transform: none;
  box-shadow: none;
}

/* =========================
   Create panel
========================= */

.create-panel {
  position: relative;
  overflow: hidden;
  padding: 24px;
  background:
      linear-gradient(145deg, rgba(124,58,237,.075), transparent 38%),
      var(--bg-card);
  border: 1px solid rgba(139,92,246,.2);
  border-radius: 17px;
  box-shadow: 0 18px 50px rgba(0,0,0,.16);
}

.create-panel__glow {
  position: absolute;
  top: -100px;
  right: -80px;
  width: 260px;
  height: 220px;
  pointer-events: none;
  background: radial-gradient(circle, rgba(124,58,237,.13), transparent 68%);
}

.create-panel__head {
  position: relative;
  margin-bottom: 22px;
}

.create-panel__eyebrow,
.section-heading__eyebrow {
  margin-bottom: 5px;
  color: #a78bfa;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: .14em;
}

.create-panel h3 {
  margin: 0 0 4px;
  color: var(--text);
  font-size: 17px;
  font-weight: 800;
}

.create-panel__head p {
  margin: 0;
  color: var(--text-muted);
  font-size: 12px;
}

.field {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 14px;
}

.field label {
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
}

.field input,
.field textarea,
.reply-panel textarea {
  width: 100%;
  box-sizing: border-box;
  color: var(--text);
  background: rgba(7, 7, 12, .65);
  border: 1px solid var(--border);
  border-radius: 11px;
  outline: none;
  font: inherit;
  transition:
      border-color .2s ease,
      box-shadow .2s ease,
      background .2s ease;
}

.field input {
  height: 44px;
  padding: 0 13px;
  padding-right: 60px;
  font-size: 13px;
}

.field textarea,
.reply-panel textarea {
  padding: 12px 13px;
  resize: vertical;
  font-size: 13px;
  line-height: 1.6;
}

.field input::placeholder,
.field textarea::placeholder,
.reply-panel textarea::placeholder {
  color: #5f6170;
}

.field input:focus,
.field textarea:focus,
.reply-panel textarea:focus {
  background: rgba(7, 7, 12, .9);
  border-color: rgba(139, 92, 246, .55);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, .08);
}

.field__counter {
  position: absolute;
  right: 12px;
  bottom: 12px;
  color: var(--text-muted);
  font-size: 10px;
}

.create-panel__footer,
.reply-panel__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-top: 17px;
}

.hint {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--text-muted);
  font-size: 10px;
}

.hint svg {
  width: 14px;
  height: 14px;
}

/* =========================
   Topics
========================= */

.topics {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.topic-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 72px;
  padding: 12px 15px;
  overflow: hidden;
  background:
      linear-gradient(90deg, rgba(255,255,255,.018), transparent 70%),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  cursor: pointer;
  transition:
      transform .2s ease,
      border-color .2s ease,
      background .2s ease,
      box-shadow .2s ease;
}

.topic-card:hover {
  transform: translateY(-1px);
  background:
      linear-gradient(90deg, rgba(124,58,237,.055), transparent 70%),
      var(--bg-card-hover);
  border-color: rgba(139,92,246,.28);
  box-shadow: 0 12px 35px rgba(0,0,0,.15);
}

.topic-card--pinned {
  border-color: rgba(250,204,21,.2);
  background:
      linear-gradient(90deg, rgba(250,204,21,.045), transparent 55%),
      var(--bg-card);
}

.topic-card--locked {
  opacity: .72;
}

.topic-card__accent {
  position: absolute;
  left: 0;
  top: 12px;
  bottom: 12px;
  width: 2px;
  background: #facc15;
  border-radius: 0 3px 3px 0;
  box-shadow: 0 0 12px rgba(250,204,21,.5);
}

.topic-card__avatar,
.topic-post__avatar,
.reply__avatar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #5b21b6);
  font-weight: 800;
}

.topic-card__avatar {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  font-size: 14px;
}

.topic-card__avatar img,
.topic-post__avatar img,
.reply__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.topic-card__online {
  position: absolute;
  right: 1px;
  bottom: 1px;
  width: 7px;
  height: 7px;
  background: #22c55e;
  border: 2px solid var(--bg-card);
  border-radius: 50%;
}

.topic-card__main {
  flex: 1;
  min-width: 0;
}

.topic-card__title {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  margin-bottom: 5px;
}

.topic-title-text {
  overflow: hidden;
  color: var(--text);
  font-size: 13px;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.topic-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  padding: 3px 6px;
  border-radius: 5px;
  font-size: 8px;
  font-weight: 800;
  letter-spacing: .02em;
  text-transform: uppercase;
}

.topic-badge svg {
  width: 10px;
  height: 10px;
}

.topic-badge--pin {
  color: #facc15;
  background: rgba(250,204,21,.08);
  border: 1px solid rgba(250,204,21,.15);
}

.topic-badge--lock {
  color: #fb7185;
  background: rgba(244,63,94,.07);
  border: 1px solid rgba(244,63,94,.14);
}

.topic-card__meta {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--text-muted);
  font-size: 10px;
}

.author-name {
  color: var(--text-dim);
  font-weight: 600;
}

.meta-dot {
  width: 3px;
  height: 3px;
  background: var(--text-muted);
  border-radius: 50%;
}

.topic-card__stats {
  display: flex;
  align-items: center;
  gap: 17px;
  flex-shrink: 0;
}

.stat {
  display: flex;
  align-items: center;
  gap: 5px;
  color: var(--text-muted);
  font-size: 9px;
}

.stat svg {
  width: 14px;
  height: 14px;
  color: #77798a;
}

.stat strong {
  color: var(--text-dim);
  font-size: 11px;
}

.stat span {
  display: none;
}

.topic-card__arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #5f6170;
  transition: transform .2s ease, color .2s ease;
}

.topic-card__arrow svg {
  width: 17px;
  height: 17px;
}

.topic-card:hover .topic-card__arrow {
  color: #a78bfa;
  transform: translateX(2px);
}

.topic-card__actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
  opacity: 0;
  transform: translateX(5px);
  transition: opacity .2s ease, transform .2s ease;
}

.topic-card:hover .topic-card__actions {
  opacity: 1;
  transform: translateX(0);
}

.topic-card__actions button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 29px;
  height: 29px;
  padding: 0;
  color: var(--text-muted);
  background: rgba(255,255,255,.025);
  border: 1px solid var(--border);
  border-radius: 7px;
  cursor: pointer;
  transition: all .18s ease;
}

.topic-card__actions button svg {
  width: 13px;
  height: 13px;
}

.topic-card__actions button:hover {
  color: var(--text);
  background: rgba(255,255,255,.06);
  border-color: var(--border-hover);
}

.topic-card__actions button.danger:hover {
  color: #fb7185;
  border-color: rgba(244,63,94,.3);
  background: rgba(244,63,94,.06);
}

/* =========================
   Empty
========================= */

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  padding: 40px;
  text-align: center;
  background:
      radial-gradient(circle at 50% 20%, rgba(124,58,237,.08), transparent 32%),
      var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 17px;
}

.empty-state__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 58px;
  height: 58px;
  margin-bottom: 16px;
  color: #8b5cf6;
  background: rgba(124,58,237,.1);
  border: 1px solid rgba(139,92,246,.18);
  border-radius: 16px;
}

.empty-state__icon svg {
  width: 26px;
  height: 26px;
}

.empty-state h3 {
  margin: 0 0 6px;
  color: var(--text);
  font-size: 16px;
  font-weight: 800;
}

.empty-state p {
  margin: 0 0 18px;
  color: var(--text-muted);
  font-size: 12px;
}

.empty-state__button {
  min-height: 38px;
  padding: 0 14px;
  color: #c4b5fd;
  background: rgba(124,58,237,.09);
  border: 1px solid rgba(139,92,246,.2);
  border-radius: 9px;
  font-size: 11px;
}

.empty-state__button span {
  font-size: 17px;
  line-height: 1;
}

.empty-state__button:hover {
  background: rgba(124,58,237,.15);
  border-color: rgba(139,92,246,.35);
}

/* =========================
   Skeleton
========================= */

.topic-card--skeleton {
  cursor: default;
}

.topic-card--skeleton:hover {
  transform: none;
  background: var(--bg-card);
  border-color: var(--border);
  box-shadow: none;
}

.skeleton {
  position: relative;
  overflow: hidden;
  background: rgba(255,255,255,.055);
  border-radius: 7px;
}

.skeleton::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
      90deg,
      transparent,
      rgba(255,255,255,.055),
      transparent
  );
  animation: shimmer 1.5s infinite;
}

.skeleton--avatar {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  flex-shrink: 0;
}

.skeleton-content {
  flex: 1;
}

.skeleton--title {
  width: 48%;
  height: 12px;
  margin-bottom: 8px;
}

.skeleton--meta {
  width: 25%;
  height: 8px;
}

.skeleton-stats {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.skeleton--stat {
  width: 55px;
  height: 8px;
}

.skeleton--stat.short {
  width: 40px;
}

/* =========================
   Topic view
========================= */

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  align-self: flex-start;
  padding: 8px 11px;
  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  font-size: 11px;
  transition: all .2s ease;
}

.back-btn svg {
  width: 15px;
  height: 15px;
}

.back-btn:hover {
  color: var(--text);
  background: rgba(255,255,255,.025);
  border-color: var(--border-hover);
  transform: translateX(-2px);
}

.topic-view {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.topic-post {
  position: relative;
  overflow: hidden;
  padding: 24px;
  background:
      radial-gradient(circle at 100% 0%, rgba(124,58,237,.08), transparent 35%),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 17px;
}

.topic-post::before {
  content: '';
  position: absolute;
  top: 0;
  left: 24px;
  right: 24px;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(139,92,246,.45), transparent);
}

.topic-post__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 22px;
}

.topic-post__author {
  display: flex;
  align-items: center;
  gap: 11px;
}

.topic-post__avatar {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  font-size: 13px;
}

.topic-post__username {
  color: var(--text);
  font-size: 12px;
  font-weight: 750;
}

.topic-post__date {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 10px;
}

.topic-post__badges {
  display: flex;
  gap: 6px;
}

.topic-post h1 {
  margin: 0 0 18px;
  color: var(--text);
  font-size: 24px;
  line-height: 1.25;
  font-weight: 850;
  letter-spacing: -.025em;
}

.topic-post__content {
  padding-top: 18px;
  border-top: 1px solid rgba(255,255,255,.055);
}

.topic-post__content pre {
  margin: 0;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  line-height: 1.8;
  white-space: pre-wrap;
  word-break: break-word;
}

/* =========================
   Replies
========================= */

.replies-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  padding: 2px 3px;
}

.section-heading h3 {
  display: flex;
  align-items: center;
  gap: 7px;
  margin: 0;
  color: var(--text);
  font-size: 15px;
  font-weight: 800;
}

.section-heading h3 span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  color: #a78bfa;
  background: rgba(124,58,237,.1);
  border: 1px solid rgba(139,92,246,.14);
  border-radius: 6px;
  font-size: 9px;
}

.replies {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.reply {
  display: flex;
  gap: 12px;
  padding: 15px 17px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
  transition: border-color .2s ease, background .2s ease;
}

.reply:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.reply__avatar {
  width: 35px;
  height: 35px;
  border-radius: 9px;
  font-size: 11px;
}

.reply__content {
  flex: 1;
  min-width: 0;
}

.reply__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 6px;
}

.reply__head strong {
  color: var(--text);
  font-size: 12px;
}

.reply__head span {
  color: var(--text-muted);
  font-size: 9px;
}

.reply__content pre {
  margin: 0;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 12px;
  line-height: 1.65;
  white-space: pre-wrap;
  word-break: break-word;
}

.no-replies {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 30px;
  color: var(--text-muted);
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 13px;
  text-align: center;
  font-size: 11px;
}

.no-replies small {
  color: #555766;
  font-size: 10px;
}

/* =========================
   Reply panel
========================= */

.reply-panel {
  padding: 18px;
  background:
      linear-gradient(145deg, rgba(124,58,237,.055), transparent 50%),
      var(--bg-card);
  border: 1px solid rgba(139,92,246,.16);
  border-radius: 15px;
}

.reply-panel__head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}

.reply-panel__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  color: #a78bfa;
  background: rgba(124,58,237,.1);
  border: 1px solid rgba(139,92,246,.15);
  border-radius: 9px;
}

.reply-panel__icon svg {
  width: 16px;
  height: 16px;
}

.reply-panel__head > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.reply-panel__head strong {
  color: var(--text);
  font-size: 12px;
}

.reply-panel__head span {
  color: var(--text-muted);
  font-size: 9px;
}

.reply-panel textarea {
  min-height: 95px;
}

.reply-panel__footer {
  margin-top: 11px;
}

.reply-panel__footer > span {
  color: #555766;
  font-size: 9px;
}

/* =========================
   Locked
========================= */

.locked-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 11px;
  padding: 19px;
  background: rgba(244,63,94,.035);
  border: 1px dashed rgba(244,63,94,.18);
  border-radius: 13px;
}

.locked-notice__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  background: rgba(244,63,94,.07);
  border-radius: 9px;
}

.locked-notice div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.locked-notice strong {
  color: #fb7185;
  font-size: 11px;
}

.locked-notice span {
  color: var(--text-muted);
  font-size: 9px;
}

/* =========================
   Animations
========================= */

.spinner {
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255,255,255,.25);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

.form-enter-active,
.form-leave-active {
  transition:
      opacity .2s ease,
      transform .2s ease;
}

.form-enter-from,
.form-leave-to {
  opacity: 0;
  transform: translateY(-7px);
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes shimmer {
  100% {
    transform: translateX(100%);
  }
}

/* =========================
   Responsive
========================= */

@media (max-width: 800px) {
  .forum-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .create-btn {
    width: 100%;
  }

  .topic-card__stats {
    display: none;
  }

  .topic-card__actions {
    opacity: 1;
    transform: none;
  }

  .topic-card__arrow {
    display: none;
  }
}

@media (max-width: 600px) {
  .forum-header,
  .create-panel,
  .topic-post {
    padding: 17px;
    border-radius: 14px;
  }

  .forum-header__icon {
    width: 40px;
    height: 40px;
  }

  .forum-header h2 {
    font-size: 16px;
  }

  .forum-header p {
    font-size: 10px;
  }

  .topic-card {
    gap: 10px;
    padding: 11px;
  }

  .topic-card__avatar {
    width: 38px;
    height: 38px;
  }

  .topic-card__actions {
    display: none;
  }

  .topic-title-text {
    font-size: 12px;
  }

  .topic-card__meta {
    font-size: 9px;
  }

  .topic-post h1 {
    font-size: 20px;
  }

  .topic-post__badges {
    display: none;
  }

  .reply {
    padding: 12px;
  }

  .create-panel__footer,
  .reply-panel__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .publish-btn {
    width: 100%;
  }

  .hint,
  .reply-panel__footer > span {
    display: none;
  }
}
</style>
