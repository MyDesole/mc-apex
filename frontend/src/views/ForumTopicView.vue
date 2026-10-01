<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { forumApi } from '@/services/forum.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'
import ForumAttachmentsInput from '@/components/forum/ForumAttachmentsInput.vue'
import ForumReplyNode from '@/components/forum/ForumReplyNode.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const error = ref('')
const notice = ref('')

const topic = ref(null)
const replies = ref([])
const repliesCount = ref(0)
const maxDepth = ref(8)
const canReply = ref(true)

const replyBody = ref('')
const replyAttachments = ref([])
const replyParent = ref(null)
const sending = ref(false)

const editingTopic = ref(false)
const editTitle = ref('')
const editBody = ref('')

const editingReply = ref(null)
const editReplyBody = ref('')

const topicId = computed(() => route.params.id)

const parentReply = computed(() => {
  if (!replyParent.value) return null
  return findReply(replies.value, replyParent.value.id)
})

const replyProgress = computed(() => {
  return Math.min(100, Math.round((replyBody.value.length / 20000) * 100))
})

const topicCategoryColor = computed(() => {
  return topic.value?.category?.color || 'var(--accent)'
})

const topicInitial = computed(() => {
  return (topic.value?.author?.username || 'И').charAt(0).toUpperCase()
})

const topicStatus = computed(() => {
  if (topic.value?.is_locked) {
    return {
      label: 'Закрыто',
      icon: 'close',
      class: 'status--locked',
    }
  }

  if (topic.value?.is_pinned) {
    return {
      label: 'Закреплено',
      icon: 'target',
      class: 'status--pinned',
    }
  }

  return {
    label: 'Активно',
    icon: 'sparkles',
    class: 'status--active',
  }
})

function findReply(list, id) {
  for (const item of list) {
    if (item.id === id) return item

    const found = findReply(item.children ?? [], id)

    if (found) return found
  }

  return null
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await forumApi.topic(topicId.value)

    topic.value = data.topic
    replies.value = data.replies ?? []
    repliesCount.value = data.replies_count ?? 0
    maxDepth.value = data.max_depth ?? 8
    canReply.value = data.can_reply !== false
  } catch (e) {
    error.value = e.status === 404
        ? 'Тема не найдена или удалена.'
        : (e.message || 'Не удалось загрузить тему.')
  } finally {
    loading.value = false
  }
}

/* ---------------- Ответы ---------------- */

function setReplyParent(reply) {
  replyParent.value = reply

  document.getElementById('reply-input')?.focus()
}

function clearReplyParent() {
  replyParent.value = null
}

async function sendReply() {
  if (!replyBody.value.trim()) return

  sending.value = true
  error.value = ''
  notice.value = ''

  try {
    await forumApi.reply(topicId.value, {
      body: replyBody.value,
      parent_id: replyParent.value?.id ?? null,
      attachments: replyAttachments.value.map((a) => a.id),
    })

    replyBody.value = ''
    replyAttachments.value = []
    replyParent.value = null
    notice.value = 'Ответ добавлен.'

    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось отправить ответ.'
  } finally {
    sending.value = false
  }
}

async function toggleLike(reply) {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login' })
    return
  }

  try {
    const result = await forumApi.like('reply', reply.id)

    reply.liked = result.liked
    reply.likes_count = result.count
  } catch (e) {
    error.value = e.message || 'Не удалось поставить лайк.'
  }
}

async function startEditReply(reply) {
  editingReply.value = reply
  editReplyBody.value = reply.body

  setTimeout(() => {
    document.querySelector(`[data-edit-for="${reply.id}"]`)?.scrollIntoView({
      behavior: 'smooth',
      block: 'center',
    })
  })
}

async function saveReply() {
  if (!editingReply.value) return

  try {
    await forumApi.updateReply(editingReply.value.id, {
      body: editReplyBody.value,
    })

    editingReply.value = null
    notice.value = 'Сообщение обновлено.'

    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить сообщение.'
  }
}

async function removeReply(reply) {
  const hasChildren = (reply.children ?? []).length > 0

  const message = hasChildren
      ? 'Удалить это сообщение вместе со всеми ответами в ветке?'
      : 'Удалить это сообщение?'

  if (!await confirmDialog(message)) return

  try {
    await forumApi.deleteReply(reply.id)

    notice.value = 'Сообщение удалено.'

    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось удалить сообщение.'
  }
}

/* ---------------- Тема ---------------- */

async function toggleTopicLike() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login' })
    return
  }

  try {
    const result = await forumApi.like('topic', topic.value.id)

    topic.value.liked = result.liked
    topic.value.likes_count = result.count
  } catch (e) {
    error.value = e.message || 'Не удалось поставить лайк.'
  }
}

async function startEditTopic() {
  editingTopic.value = true
  editTitle.value = topic.value.title
  editBody.value = topic.value.body
}

async function saveTopic() {
  try {
    await forumApi.updateTopic(topicId.value, {
      title: editTitle.value,
      body: editBody.value,
    })

    editingTopic.value = false
    notice.value = 'Тема обновлена.'

    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить тему.'
  }
}

async function removeTopic() {
  if (!await confirmDialog('Удалить тему? Действие необратимо.')) return

  try {
    await forumApi.deleteTopic(topicId.value)

    router.push({ name: 'forum' })
  } catch (e) {
    error.value = e.message || 'Не удалось удалить тему.'
  }
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleString('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

watch(topicId, () => {
  replyParent.value = null
  load()
})

onMounted(load)
</script>

<template>
  <main class="topic-page">
    <div class="topic-page__ambient" />

    <div class="topic-page__container">
      <!-- Навигация -->
      <div class="topic-nav">
        <RouterLink class="back-link" :to="{ name: 'forum' }">
          <span class="back-link__icon">
            <AppIcon icon="send" :size="14" />
          </span>

          <span>
            <small>APEX COMMUNITY</small>
            Назад к форуму
          </span>
        </RouterLink>

        <div v-if="topic" class="topic-nav__id">
          THREAD #{{ topic.id }}
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="state-card">
        <div class="state-card__icon">
          <AppIcon icon="sparkles" :size="24" />
        </div>

        <div>
          <strong>Загружаем тему</strong>
          <span>Подготавливаем обсуждение…</span>
        </div>

        <div class="loader-dots">
          <i />
          <i />
          <i />
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error && !topic" class="alert alert--error">
        <span class="alert__icon">
          <AppIcon icon="close" :size="16" />
        </span>

        <div>
          <strong>Не удалось открыть тему</strong>
          <span>{{ error }}</span>
        </div>
      </div>

      <template v-else-if="topic">
        <!-- Alerts -->
        <div v-if="error" class="alert alert--error">
          <span class="alert__icon">
            <AppIcon icon="close" :size="16" />
          </span>

          <span>{{ error }}</span>
        </div>

        <div v-if="notice" class="alert alert--success">
          <span class="alert__icon">
            <AppIcon icon="check" :size="16" />
          </span>

          <span>{{ notice }}</span>
        </div>

        <!-- Hero / Topic -->
        <article class="topic-card">
          <div
              class="topic-card__accent"
              :style="{ background: topicCategoryColor }"
          />

          <header class="topic-card__header">
            <div class="topic-card__author">
              <RouterLink
                  v-if="topic.author"
                  class="author-avatar"
                  :to="{ name: 'player', params: { id: topic.author.id } }"
              >
                <img
                    v-if="topic.author.avatar_url"
                    :src="topic.author.avatar_url"
                    :alt="topic.author.username"
                >

                <template v-else>
                  {{ topicInitial }}
                </template>
              </RouterLink>

              <div v-else class="author-avatar">
                {{ topicInitial }}
              </div>

              <div class="author-info">
                <div class="author-info__name">
                  <RouterLink
                      v-if="topic.author"
                      :to="{ name: 'player', params: { id: topic.author.id } }"
                  >
                    {{ topic.author.username }}
                  </RouterLink>

                  <template v-else>
                    Удалённый пользователь
                  </template>

                  <span
                      v-if="topic.author?.is_verified"
                      class="verified"
                      title="Верифицирован"
                  >
                    <AppIcon icon="check" :size="10" />
                  </span>

                  <span
                      v-if="topic.author?.is_media"
                      class="media-badge"
                  >
                    <AppIcon icon="star" :size="10" />
                    МЕДИЙКА
                  </span>
                </div>

                <span class="author-info__date">
                  {{ formatDate(topic.created_at) }}
                </span>
              </div>
            </div>

            <div class="topic-card__badges">
              <span
                  class="status-badge"
                  :class="topicStatus.class"
              >
                <AppIcon :icon="topicStatus.icon" :size="11" />
                {{ topicStatus.label }}
              </span>

              <RouterLink
                  v-if="topic.category"
                  class="category-badge"
                  :style="{
                  '--category-color': topicCategoryColor,
                }"
                  :to="{
                  name: 'forum',
                  query: { category: topic.category.slug },
                }"
              >
                <span class="category-badge__dot" />
                {{ topic.category.name }}
              </RouterLink>
            </div>
          </header>

          <template v-if="editingTopic">
            <div class="topic-editor">
              <div class="field">
                <label>Заголовок</label>

                <input
                    v-model="editTitle"
                    class="input"
                    type="text"
                    maxlength="200"
                >
              </div>

              <div class="field">
                <label>Содержание</label>

                <textarea
                    v-model="editBody"
                    class="textarea"
                    rows="9"
                />
              </div>

              <div class="editor-actions">
                <button
                    class="btn btn--ghost"
                    type="button"
                    @click="editingTopic = false"
                >
                  Отмена
                </button>

                <button
                    class="btn btn--primary"
                    type="button"
                    @click="saveTopic"
                >
                  <AppIcon icon="check" :size="15" />
                  Сохранить изменения
                </button>
              </div>
            </div>
          </template>

          <template v-else>
            <div class="topic-card__eyebrow">
              <span>DISCUSSION</span>
              <i />
              <span>COMMUNITY</span>
            </div>

            <h1 class="topic-card__title">
              {{ topic.title }}
            </h1>

            <div class="topic-card__body">
              {{ topic.body }}
            </div>
          </template>

          <!-- Attachments -->
          <div
              v-if="topic.attachments?.length"
              class="attachments"
          >
            <div class="section-label">
              <AppIcon icon="frame" :size="13" />
              Вложения
            </div>

            <div class="attachments__grid">
              <template
                  v-for="file in topic.attachments"
                  :key="file.id"
              >
                <a
                    v-if="file.is_image"
                    :href="file.url"
                    target="_blank"
                    rel="noopener"
                    class="attachment-image"
                >
                  <img
                      :src="file.url"
                      :alt="file.name"
                  >

                  <span class="attachment-image__overlay">
                    <AppIcon icon="send" :size="15" />
                  </span>
                </a>

                <a
                    v-else
                    :href="file.url"
                    target="_blank"
                    rel="noopener"
                    class="attachment-file"
                >
                  <span class="attachment-file__icon">
                    <AppIcon icon="doc" :size="16" />
                  </span>

                  <span class="attachment-file__content">
                    <strong>{{ file.name }}</strong>
                    <small>{{ file.size }}</small>
                  </span>

                  <AppIcon
                      class="attachment-file__arrow"
                      icon="send"
                      :size="13"
                  />
                </a>
              </template>
            </div>
          </div>

          <!-- Stats -->
          <footer class="topic-card__footer">
            <button
                class="like-button"
                :class="{ 'like-button--active': topic.liked }"
                type="button"
                @click="toggleTopicLike"
            >
              <span class="like-button__icon">
                <AppIcon icon="heart" :size="15" />
              </span>

              <span>{{ topic.likes_count }}</span>
            </button>

            <div class="topic-stat">
              <AppIcon icon="target" :size="14" />
              <span>{{ topic.views }}</span>
              <small>просмотров</small>
            </div>

            <div class="topic-stat">
              <AppIcon icon="send" :size="14" />
              <span>{{ repliesCount }}</span>
              <small>ответов</small>
            </div>

            <div class="topic-card__spacer" />

            <button
                v-if="topic.can_edit"
                class="action-link"
                type="button"
                @click="startEditTopic"
            >
              <AppIcon icon="palette" :size="13" />
              Редактировать
            </button>

            <button
                v-if="topic.can_delete"
                class="action-link action-link--danger"
                type="button"
                @click="removeTopic"
            >
              <AppIcon icon="trash" :size="13" />
              Удалить
            </button>
          </footer>
        </article>

        <!-- Replies heading -->
        <div class="replies-header">
          <div>
            <div class="section-eyebrow">
              APEX / COMMUNITY
            </div>

            <h2>
              Ответы
              <span>{{ repliesCount }}</span>
            </h2>
          </div>

          <div class="replies-header__meta">
            <AppIcon icon="send" :size="13" />
            {{ repliesCount ? 'ОБСУЖДЕНИЕ АКТИВНО' : 'БУДЬ ПЕРВЫМ' }}
          </div>
        </div>

        <!-- Empty replies -->
        <div
            v-if="!replies.length"
            class="empty-replies"
        >
          <div class="empty-replies__icon">
            <AppIcon icon="send" :size="22" />
          </div>

          <strong>Пока никто не ответил</strong>

          <span>
            Начни обсуждение первым — твой ответ будет первым в этой теме.
          </span>
        </div>

        <!-- Replies -->
        <div v-else class="replies">
          <ForumReplyNode
              v-for="reply in replies"
              :key="reply.id"
              :reply="reply"
              :can-reply="canReply"
              @reply="setReplyParent"
              @like="toggleLike"
              @edit="startEditReply"
              @delete="removeReply"
          />
        </div>

        <!-- Reply edit -->
        <div
            v-if="editingReply"
            class="edit-card"
            :data-edit-for="editingReply.id"
        >
          <div class="edit-card__head">
            <div class="edit-card__icon">
              <AppIcon icon="palette" :size="16" />
            </div>

            <div>
              <small>EDIT MESSAGE</small>
              <strong>
                Правка сообщения
                {{ editingReply.author?.username }}
              </strong>
            </div>

            <button
                type="button"
                class="icon-button"
                @click="editingReply = null"
            >
              <AppIcon icon="close" :size="15" />
            </button>
          </div>

          <textarea
              v-model="editReplyBody"
              class="textarea"
              rows="5"
          />

          <div class="edit-card__actions">
            <button
                class="btn btn--ghost"
                type="button"
                @click="editingReply = null"
            >
              Отмена
            </button>

            <button
                class="btn btn--primary"
                type="button"
                @click="saveReply"
            >
              <AppIcon icon="check" :size="15" />
              Сохранить
            </button>
          </div>
        </div>

        <!-- Reply form -->
        <section
            v-if="canReply"
            class="reply-form"
        >
          <div class="reply-form__top">
            <div>
              <div class="section-eyebrow">
                APEX / REPLY
              </div>

              <h3>
                <template v-if="parentReply">
                  Ответ для
                  <strong>
                    {{ parentReply.author?.username || 'сообщения' }}
                  </strong>
                </template>

                <template v-else>
                  Ваш ответ
                </template>
              </h3>
            </div>

            <button
                v-if="parentReply"
                class="cancel-reply"
                type="button"
                @click="clearReplyParent"
            >
              <AppIcon icon="close" :size="13" />
              Отменить
            </button>
          </div>

          <div
              v-if="parentReply"
              class="reply-context"
          >
            <span class="reply-context__line" />

            <div>
              <small>
                ОТВЕТ НА СООБЩЕНИЕ {{ parentReply.author?.username }}
              </small>

              <p>
                {{ (parentReply.body || '').slice(0, 220) }}
                <span v-if="parentReply.body?.length > 220">…</span>
              </p>
            </div>
          </div>

          <div class="reply-form__editor">
            <textarea
                id="reply-input"
                v-model="replyBody"
                class="textarea textarea--reply"
                rows="6"
                maxlength="20000"
                :placeholder="
                replyParent
                  ? 'Ответь на сообщение…'
                  : 'Напиши ответ…'
              "
            />

            <div class="reply-form__progress">
              <span
                  :style="{ width: `${replyProgress}%` }"
              />
            </div>
          </div>

          <ForumAttachmentsInput
              v-model="replyAttachments"
          />

          <div class="reply-form__bottom">
            <div class="reply-hint">
              <span class="reply-hint__count">
                {{ replyBody.length }}
              </span>
              <span>/ 20000 символов</span>
              <i />
              <span>вложенность до {{ maxDepth }} уровней</span>
            </div>

            <button
                class="btn btn--primary btn--send"
                type="button"
                :disabled="sending || !replyBody.trim()"
                @click="sendReply"
            >
              <AppIcon
                  :icon="sending ? 'sparkles' : 'send'"
                  :size="15"
              />

              {{ sending ? 'Отправляем…' : 'Отправить ответ' }}
            </button>
          </div>
        </section>

        <!-- Locked / login -->
        <div
            v-else-if="auth.isAuthenticated"
            class="closed-state"
        >
          <div class="closed-state__icon">
            <AppIcon icon="close" :size="18" />
          </div>

          <div>
            <strong>Тема закрыта</strong>
            <span>Новые ответы в этой теме запрещены.</span>
          </div>
        </div>

        <div
            v-else
            class="login-state"
        >
          <div class="login-state__icon">
            <AppIcon icon="shield" :size="18" />
          </div>

          <div>
            <strong>Хочешь присоединиться?</strong>
            <span>
              <RouterLink
                  class="action-link"
                  :to="{
                  name: 'login',
                  query: { redirect: route.fullPath },
                }"
              >
                Войди в аккаунт
              </RouterLink>
              , чтобы ответить в теме.
            </span>
          </div>
        </div>
      </template>
    </div>
  </main>
</template>

<style scoped>
.topic-page {
  position: relative;
  min-height: 100%;
  padding: 34px 0 90px;
  overflow: hidden;
}

.topic-page__ambient {
  position: absolute;
  top: -260px;
  left: 50%;
  width: 760px;
  height: 500px;
  pointer-events: none;
  transform: translateX(-50%);
  background:
      radial-gradient(
          circle,
          color-mix(in srgb, var(--accent) 13%, transparent) 0%,
          transparent 68%
      );
  filter: blur(10px);
}

.topic-page__container {
  position: relative;
  z-index: 1;
  width: min(1040px, calc(100% - 40px));
  margin: 0 auto;
}

/* =========================
   Navigation
   ========================= */

.topic-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 20px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 11px;
  color: var(--text-dim);
  transition:
      color 0.2s ease,
      transform 0.2s ease;
}

.back-link:hover {
  color: var(--text);
  transform: translateX(-3px);
}

.back-link__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.08);
  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 10px;
}

.back-link__icon :deep(svg) {
  transform: rotate(180deg);
}

.back-link span:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
  font-weight: 700;
}

.back-link small {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.4px;
}

.topic-nav__id {
  color: var(--text-muted);
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 1.5px;
}

/* =========================
   Alerts / states
   ========================= */

.alert {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
  padding: 13px 16px;
  border: 1px solid;
  border-radius: 12px;
  font-size: 13px;
}

.alert > span:last-child {
  min-width: 0;
}

.alert strong,
.alert span {
  display: block;
}

.alert--error {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.24);
}

.alert--success {
  color: #86efac;
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.22);
}

.alert__icon {
  display: flex !important;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  background: rgba(255, 255, 255, 0.04);
  border-radius: 9px;
}

.state-card {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 140px;
  padding: 26px;
  color: var(--text-dim);
  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, 0.08),
          transparent 50%
      ),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.state-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.2);
  border-radius: 12px;
}

.state-card > div:nth-child(2) {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.state-card strong {
  color: var(--text);
  font-size: 14px;
}

.state-card span {
  color: var(--text-muted);
  font-size: 12px;
}

.loader-dots {
  display: flex;
  gap: 4px;
  margin-left: auto;
}

.loader-dots i {
  width: 5px;
  height: 5px;
  background: var(--accent);
  border-radius: 50%;
  animation: pulse 1s infinite ease-in-out;
}

.loader-dots i:nth-child(2) {
  animation-delay: 0.15s;
}

.loader-dots i:nth-child(3) {
  animation-delay: 0.3s;
}

@keyframes pulse {
  0%,
  70%,
  100% {
    opacity: 0.25;
    transform: translateY(0);
  }

  35% {
    opacity: 1;
    transform: translateY(-3px);
  }
}

/* =========================
   Topic card
   ========================= */

.topic-card {
  position: relative;
  overflow: hidden;
  padding: 24px 26px 18px;
  background:
      radial-gradient(
          circle at 90% 0%,
          rgba(139, 92, 246, 0.08),
          transparent 32%
      ),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  box-shadow:
      0 18px 60px rgba(0, 0, 0, 0.18),
      inset 0 1px 0 rgba(255, 255, 255, 0.025);
}

.topic-card:hover {
  border-color: var(--border-hover);
}

.topic-card__accent {
  position: absolute;
  top: 0;
  left: 0;
  width: 3px;
  height: 100%;
  opacity: 0.9;
  box-shadow: 0 0 22px currentColor;
}

.topic-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 22px;
}

.topic-card__author {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.author-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  overflow: hidden;
  color: #fff;
  background:
      linear-gradient(
          135deg,
          #9b72ff,
          #5b21b6
      );
  border: 1px solid rgba(167, 139, 250, 0.4);
  border-radius: 12px;
  box-shadow: 0 8px 22px rgba(109, 40, 217, 0.22);
  font-size: 15px;
  font-weight: 900;
}

.author-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.author-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.author-info__name {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;
  color: var(--text);
  font-size: 13px;
  font-weight: 800;
}

.author-info__name a {
  color: var(--text);
}

.author-info__name a:hover {
  color: var(--accent-light);
}

.author-info__date {
  color: var(--text-muted);
  font-size: 10px;
}

.verified {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 17px;
  height: 17px;
  color: #67e8f9;
  background: rgba(34, 211, 238, 0.1);
  border: 1px solid rgba(34, 211, 238, 0.2);
  border-radius: 50%;
}

.media-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 7px;
  color: #f9a8d4;
  background: rgba(236, 72, 153, 0.08);
  border: 1px solid rgba(236, 72, 153, 0.22);
  border-radius: 999px;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.5px;
}

.topic-card__badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 7px;
}

.status-badge,
.category-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 9px;
  border: 1px solid;
  border-radius: 8px;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.status--active {
  color: #86efac;
  background: rgba(34, 197, 94, 0.07);
  border-color: rgba(34, 197, 94, 0.18);
}

.status--pinned {
  color: #fcd34d;
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.2);
}

.status--locked {
  color: var(--text-muted);
  background: rgba(156, 163, 175, 0.06);
  border-color: rgba(156, 163, 175, 0.16);
}

.category-badge {
  color: var(--category-color);
  background: color-mix(
      in srgb,
      var(--category-color) 7%,
      transparent
  );
  border-color: color-mix(
      in srgb,
      var(--category-color) 22%,
      transparent
  );
}

.category-badge__dot {
  width: 5px;
  height: 5px;
  background: currentColor;
  border-radius: 50%;
  box-shadow: 0 0 8px currentColor;
}

.topic-card__eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 9px;
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.6px;
}

.topic-card__eyebrow i {
  width: 18px;
  height: 1px;
  background: var(--border-hover);
}

.topic-card__title {
  max-width: 850px;
  margin: 0 0 15px;
  color: var(--text);
  word-break: break-word;
  font-size: clamp(24px, 3vw, 34px);
  font-weight: 950;
  line-height: 1.12;
  letter-spacing: -0.7px;
}

.topic-card__body {
  max-width: 880px;
  color: var(--text-dim);
  font-size: 14px;
  line-height: 1.75;
  white-space: pre-wrap;
  word-break: break-word;
}

.topic-card__footer {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 9px;
  margin-top: 24px;
  padding-top: 15px;
  border-top: 1px solid var(--border);
}

.topic-card__spacer {
  flex: 1;
}

.like-button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 32px;
  padding: 4px 10px 4px 5px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
  transition: 0.2s ease;
}

.like-button:hover,
.like-button--active {
  color: #f472b6;
  background: rgba(244, 114, 182, 0.08);
  border-color: rgba(244, 114, 182, 0.25);
}

.like-button__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: rgba(244, 114, 182, 0.08);
  border-radius: 7px;
}

.topic-stat {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--text-muted);
  font-size: 11px;
}

.topic-stat :deep(svg) {
  opacity: 0.7;
}

.topic-stat span {
  color: var(--text-dim);
  font-weight: 800;
}

.topic-stat small {
  font-size: 10px;
}

.action-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  border-radius: 7px;
  cursor: pointer;
  font: inherit;
  font-size: 11px;
  font-weight: 700;
  transition: 0.2s ease;
}

.action-link:hover {
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.07);
}

.action-link--danger:hover {
  color: #f87171;
  background: rgba(239, 68, 68, 0.07);
}

/* =========================
   Attachments
   ========================= */

.attachments {
  margin-top: 22px;
}

.section-label {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 9px;
  color: var(--text-muted);
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.attachments__grid {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
}

.attachment-image {
  position: relative;
  display: block;
  overflow: hidden;
  max-width: 280px;
  max-height: 210px;
  border: 1px solid var(--border);
  border-radius: 11px;
  background: var(--bg);
}

.attachment-image img {
  display: block;
  width: 100%;
  max-height: 210px;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.attachment-image:hover img {
  transform: scale(1.03);
}

.attachment-image__overlay {
  position: absolute;
  right: 9px;
  bottom: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  color: #fff;
  background: rgba(10, 8, 18, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.attachment-image:hover .attachment-image__overlay {
  opacity: 1;
}

.attachment-file {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 210px;
  padding: 9px 11px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border);
  border-radius: 10px;
  transition: 0.2s ease;
}

.attachment-file:hover {
  color: var(--text);
  border-color: var(--border-hover);
  transform: translateY(-1px);
}

.attachment-file__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 31px;
  height: 31px;
  flex: 0 0 31px;
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.08);
  border-radius: 8px;
}

.attachment-file__content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.attachment-file__content strong {
  overflow: hidden;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attachment-file__content small {
  color: var(--text-muted);
  font-size: 9px;
}

.attachment-file__arrow {
  color: var(--text-muted);
  transform: rotate(180deg);
}

/* =========================
   Replies
   ========================= */

.replies-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin: 38px 2px 14px;
}

.section-eyebrow {
  margin-bottom: 4px;
  color: var(--accent-light);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.5px;
}

.replies-header h2 {
  margin: 0;
  color: var(--text);
  font-size: 20px;
  font-weight: 900;
  letter-spacing: -0.3px;
}

.replies-header h2 span {
  margin-left: 5px;
  color: var(--text-muted);
  font-size: 15px;
}

.replies-header__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.9px;
}

.replies {
  padding: 4px 18px;
  background:
      linear-gradient(
          180deg,
          rgba(139, 92, 246, 0.025),
          transparent 25%
      ),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.empty-replies {
  display: flex;
  align-items: center;
  flex-direction: column;
  gap: 7px;
  padding: 45px 24px;
  text-align: center;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.empty-replies__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  margin-bottom: 4px;
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.08);
  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 13px;
}

.empty-replies strong {
  color: var(--text);
  font-size: 14px;
}

.empty-replies > span {
  max-width: 380px;
  color: var(--text-muted);
  font-size: 12px;
  line-height: 1.5;
}

/* =========================
   Editor
   ========================= */

.topic-editor,
.edit-card {
  margin-top: 12px;
}

.field {
  margin-bottom: 12px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-muted);
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.input,
.textarea {
  width: 100%;
  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
  font-family: inherit;
  transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      background 0.2s ease;
}

.input {
  padding: 12px 14px;
  font-size: 14px;
  font-weight: 700;
}

.textarea {
  padding: 12px 14px;
  resize: vertical;
  font-size: 13px;
  line-height: 1.65;
}

.input:focus,
.textarea:focus {
  background: rgba(139, 92, 246, 0.025);
  border-color: rgba(139, 92, 246, 0.55);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.07);
}

.editor-actions,
.edit-card__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 15px;
  border: 1px solid transparent;
  border-radius: 9px;
  cursor: pointer;
  font: inherit;
  font-size: 11px;
  font-weight: 800;
  transition:
      transform 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn:not(:disabled):hover {
  transform: translateY(-1px);
}

.btn--ghost {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border-color: var(--border);
}

.btn--ghost:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.btn--primary {
  color: #fff;
  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );
  border-color: rgba(167, 139, 250, 0.5);
  box-shadow:
      0 7px 20px rgba(109, 40, 217, 0.2),
      inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.btn--primary:not(:disabled):hover {
  box-shadow:
      0 10px 28px rgba(109, 40, 217, 0.3),
      inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

/* =========================
   Edit reply
   ========================= */

.edit-card {
  padding: 18px;
  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, 0.07),
          transparent 55%
      ),
      var(--bg-card);
  border: 1px solid rgba(139, 92, 246, 0.3);
  border-radius: 15px;
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.14);
}

.edit-card__head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}

.edit-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 9px;
}

.edit-card__head > div:nth-child(2) {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.edit-card__head small {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1px;
}

.edit-card__head strong {
  color: var(--text);
  font-size: 12px;
}

.icon-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 29px;
  height: 29px;
  margin-left: auto;
  color: var(--text-muted);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 8px;
  cursor: pointer;
}

.icon-button:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.04);
  border-color: var(--border);
}

/* =========================
   Reply form
   ========================= */

.reply-form {
  position: relative;
  margin-top: 22px;
  padding: 20px;
  overflow: hidden;
  background:
      radial-gradient(
          circle at 100% 0%,
          rgba(139, 92, 246, 0.09),
          transparent 35%
      ),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.14);
}

.reply-form::before {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 1px;
  content: '';
  background: linear-gradient(
      90deg,
      transparent,
      var(--accent),
      transparent
  );
  opacity: 0.5;
}

.reply-form__top {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 14px;
}

.reply-form__top h3 {
  margin: 0;
  color: var(--text);
  font-size: 17px;
  font-weight: 900;
}

.reply-form__top h3 strong {
  color: var(--accent-light);
}

.cancel-reply {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 8px;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  border-radius: 7px;
  cursor: pointer;
  font: inherit;
  font-size: 10px;
  font-weight: 700;
}

.cancel-reply:hover {
  color: #f87171;
  background: rgba(239, 68, 68, 0.06);
}

.reply-context {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
  padding: 10px 12px;
  background: rgba(139, 92, 246, 0.035);
  border: 1px solid rgba(139, 92, 246, 0.1);
  border-radius: 9px;
}

.reply-context__line {
  width: 2px;
  flex: 0 0 2px;
  background: var(--accent);
  border-radius: 999px;
}

.reply-context div {
  min-width: 0;
}

.reply-context small {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.8px;
}

.reply-context p {
  margin: 4px 0 0;
  overflow: hidden;
  color: var(--text-dim);
  font-size: 11px;
  line-height: 1.45;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reply-form__editor {
  position: relative;
}

.textarea--reply {
  min-height: 130px;
  padding-bottom: 17px;
}

.reply-form__progress {
  position: absolute;
  right: 1px;
  bottom: 1px;
  left: 1px;
  height: 2px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 0 0 9px 9px;
}

.reply-form__progress span {
  display: block;
  height: 100%;
  background: linear-gradient(
      90deg,
      #6d28d9,
      #a78bfa
  );
  transition: width 0.15s ease;
}

.reply-form__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-top: 12px;
}

.reply-hint {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  color: var(--text-muted);
  font-size: 9px;
}

.reply-hint__count {
  color: var(--text-dim);
  font-weight: 800;
}

.reply-hint i {
  width: 3px;
  height: 3px;
  background: var(--border-hover);
  border-radius: 50%;
}

.btn--send {
  min-height: 40px;
  padding: 0 17px;
}

/* =========================
   Closed / login
   ========================= */

.closed-state,
.login-state {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 20px;
  padding: 15px 17px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
}

.closed-state__icon,
.login-state__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  border-radius: 9px;
}

.closed-state__icon {
  color: var(--text-muted);
  background: rgba(156, 163, 175, 0.07);
}

.login-state__icon {
  color: var(--accent-light);
  background: rgba(139, 92, 246, 0.08);
}

.closed-state div:last-child,
.login-state div:last-child {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.closed-state strong,
.login-state strong {
  color: var(--text);
  font-size: 12px;
}

.closed-state span,
.login-state span {
  color: var(--text-muted);
  font-size: 11px;
}

.login-state .action-link {
  display: inline;
  padding: 0;
  color: var(--accent-light);
  font-size: inherit;
}

/* =========================
   Responsive
   ========================= */

@media (max-width: 700px) {
  .topic-page {
    padding-top: 20px;
    padding-bottom: 50px;
  }

  .topic-page__container {
    width: min(100% - 24px, 1040px);
  }

  .topic-nav {
    margin-bottom: 14px;
  }

  .topic-nav__id {
    display: none;
  }

  .topic-card {
    padding: 18px 16px 15px;
    border-radius: 14px;
  }

  .topic-card__header {
    flex-direction: column;
    gap: 12px;
  }

  .topic-card__badges {
    justify-content: flex-start;
  }

  .topic-card__title {
    font-size: 24px;
  }

  .topic-card__body {
    font-size: 13px;
    line-height: 1.65;
  }

  .topic-card__footer {
    gap: 6px;
  }

  .topic-card__spacer {
    display: none;
  }

  .action-link {
    padding: 6px;
  }

  .replies-header {
    align-items: flex-start;
    flex-direction: column;
    gap: 7px;
    margin-top: 28px;
  }

  .replies {
    padding: 2px 10px;
    border-radius: 13px;
  }

  .reply-form {
    padding: 16px;
  }

  .reply-form__bottom {
    align-items: stretch;
    flex-direction: column;
  }

  .reply-hint {
    order: 2;
  }

  .btn--send {
    width: 100%;
  }

  .attachment-image {
    max-width: 100%;
  }

  .attachment-file {
    width: 100%;
  }
}

@media (max-width: 460px) {
  .back-link span:last-child {
    font-size: 12px;
  }

  .topic-card__author {
    width: 100%;
  }

  .author-avatar {
    width: 40px;
    height: 40px;
    flex-basis: 40px;
  }

  .topic-card__title {
    font-size: 21px;
  }

  .topic-stat small {
    display: none;
  }

  .topic-stat {
    padding: 5px 7px;
    background: rgba(255, 255, 255, 0.025);
    border-radius: 7px;
  }

  .topic-card__footer {
    align-items: stretch;
  }

  .like-button {
    margin-right: auto;
  }

  .edit-card__actions,
  .editor-actions {
    flex-direction: column-reverse;
  }

  .edit-card__actions .btn,
  .editor-actions .btn {
    width: 100%;
  }
}
</style>