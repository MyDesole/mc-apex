<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { forumApi } from '@/services/forum/forum.js'
import { userLink } from '@/utils/links.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
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

function startEditReply(reply) {
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

function startEditTopic() {
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
                  :to="userLink(topic.author)"
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
@import "@/views/forum/ForumTopicView.css";
</style>
