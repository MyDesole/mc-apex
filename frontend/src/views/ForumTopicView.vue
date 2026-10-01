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

/** На каком сообщении отвечаем — для подписи над формой. */
const parentReply = computed(() => {
  if (!replyParent.value) return null

  return findReply(replies.value, replyParent.value.id)
})

/** Ищем ответ в дереве по id. */
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

  // Форма правки — прямо в ветке
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
    await forumApi.updateReply(editingReply.value.id, { body: editReplyBody.value })

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

async function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleString('ru-RU', {
    day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

watch(topicId, () => {
  replyParent.value = null
  load()
})

onMounted(load)
</script>

<template>
  <main class="topic">
    <RouterLink class="back" :to="{ name: 'forum' }">
      ← Назад к форуму
    </RouterLink>

    <p v-if="loading" class="state">Загружаем тему…</p>
    <p v-else-if="error && !topic" class="alert">{{ error }}</p>

    <template v-else-if="topic">
      <p v-if="error" class="alert">{{ error }}</p>
      <p v-if="notice" class="notice">{{ notice }}</p>

      <!-- Тема -->
      <article class="post post--topic">
        <header class="post__head">
          <div class="post__author">
            <span class="post__avatar">
              <img v-if="topic.author?.avatar_url" :src="topic.author.avatar_url" :alt="topic.author.username">
              <template v-else>{{ (topic.author?.username || 'И').charAt(0).toUpperCase() }}</template>
            </span>

            <span class="post__author-info">
              <span class="post__author-name">
                <RouterLink v-if="topic.author" :to="{ name: 'player', params: { id: topic.author.id } }">
                  {{ topic.author.username }}
                </RouterLink>
                <template v-else>Удалён</template>

                <span v-if="topic.author?.is_media" class="tag tag--media">
                  <AppIcon icon="star" :size="11" /> Медийка
                </span>
                <span v-if="topic.author?.is_verified" class="verified" title="Верифицирован">✓</span>
              </span>
              <span class="post__date">{{ formatDate(topic.created_at) }}</span>
            </span>
          </div>

          <div class="post__badges">
            <span v-if="topic.is_pinned" class="flag flag--pin">Закреплено</span>
            <span v-if="topic.is_locked" class="flag flag--lock">Закрыто</span>
            <RouterLink
                v-if="topic.category"
                class="flag"
                :style="{ '--flag-color': topic.category.color || 'var(--accent)' }"
                :to="{ name: 'forum', query: { category: topic.category.slug } }"
            >
              {{ topic.category.name }}
            </RouterLink>
          </div>
        </header>

        <template v-if="editingTopic">
          <input v-model="editTitle" class="input" type="text" maxlength="200">
          <textarea v-model="editBody" class="textarea" rows="8" />

          <div class="post__edit-actions">
            <button class="btn btn-secondary" type="button" @click="editingTopic = false">Отмена</button>
            <button class="btn btn-primary" type="button" @click="saveTopic">Сохранить</button>
          </div>
        </template>

        <template v-else>
          <h1 class="post__title">{{ topic.title }}</h1>
          <div class="post__body">{{ topic.body }}</div>
        </template>

        <div v-if="topic.attachments?.length" class="attachments">
          <template v-for="file in topic.attachments" :key="file.id">
            <a
                v-if="file.is_image"
                :href="file.url"
                target="_blank"
                rel="noopener"
                class="attachments__image"
            >
              <img :src="file.url" :alt="file.name">
            </a>
            <a v-else :href="file.url" target="_blank" rel="noopener" class="attachments__file">
              <AppIcon icon="doc" :size="15" />
              {{ file.name }} <span class="attachments__size">{{ file.size }}</span>
            </a>
          </template>
        </div>

        <footer class="post__foot">
          <button
              class="like"
              :class="{ 'like--on': topic.liked }"
              type="button"
              @click="toggleTopicLike"
          >
            <AppIcon icon="heart" :size="15" />
            {{ topic.likes_count }}
          </button>

          <span class="post__stat">
            <AppIcon icon="target" :size="14" /> {{ topic.views }} просмотров
          </span>
          <span class="post__stat">
            <AppIcon icon="send" :size="14" /> {{ repliesCount }} ответов
          </span>

          <span class="post__spacer" />

          <button v-if="topic.can_edit" class="link" type="button" @click="startEditTopic">
            Редактировать
          </button>
          <button v-if="topic.can_delete" class="link link--danger" type="button" @click="removeTopic">
            Удалить
          </button>
        </footer>
      </article>

      <!-- Ветки ответов -->
      <h2 class="replies__title">
        Ответы <span class="replies__count">{{ repliesCount }}</span>
      </h2>

      <p v-if="!replies.length" class="state state--sm">
        Ответов пока нет — будь первым.
      </p>

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

      <!-- Правка выбранного сообщения -->
      <div v-if="editingReply" class="edit-box" :data-edit-for="editingReply.id">
        <div class="edit-box__title">
          Правка сообщения {{ editingReply.author?.username }}
        </div>

        <textarea v-model="editReplyBody" class="textarea" rows="4" />

        <div class="edit-box__actions">
          <button class="btn btn-secondary" type="button" @click="editingReply = null">Отмена</button>
          <button class="btn btn-primary" type="button" @click="saveReply">Сохранить</button>
        </div>
      </div>

      <!-- Форма ответа -->
      <section v-if="canReply" class="reply-form">
        <h3 class="reply-form__title">
          <template v-if="parentReply">
            <span class="reply-form__to">
              Ответ для <b>{{ parentReply.author?.username || 'сообщения' }}</b>
            </span>
            <button class="link" type="button" @click="clearReplyParent">отменить</button>
          </template>
          <template v-else>Ваш ответ</template>
        </h3>

        <p v-if="parentReply" class="reply-form__quote">
          {{ (parentReply.body || '').slice(0, 160) }}
        </p>

        <textarea
            id="reply-input"
            v-model="replyBody"
            class="textarea"
            rows="5"
            maxlength="20000"
            :placeholder="replyParent ? 'Ответь на сообщение…' : 'Напиши ответ…'"
        />

        <ForumAttachmentsInput v-model="replyAttachments" />

        <div class="reply-form__actions">
          <span class="reply-form__hint">
            {{ replyBody.length }} / 20000
            · вложенность до {{ maxDepth }} уровней
          </span>

          <button
              class="btn btn-primary"
              type="button"
              :disabled="sending || !replyBody.trim()"
              @click="sendReply"
          >
            {{ sending ? 'Отправляем…' : 'Отправить' }}
          </button>
        </div>
      </section>

      <p v-else-if="auth.isAuthenticated" class="state state--sm">
        Тема закрыта — новые ответы запрещены.
      </p>

      <p v-else class="state state--sm">
        <RouterLink class="link" :to="{ name: 'login', query: { redirect: route.fullPath } }">
          Войди
        </RouterLink>, чтобы ответить в теме.
      </p>
    </template>
  </main>
</template>

<style scoped>
.topic {
  width: min(920px, calc(100% - 40px));
  margin: 34px auto 80px;
}

.back {
  display: inline-block;
  margin-bottom: 16px;
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}

.back:hover {
  color: var(--accent-light);
}

.post {
  padding: 18px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
  margin-bottom: 10px;
}

.post--topic {
  border-left: 3px solid var(--accent);
}

.post__head {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}

.post__author {
  display: flex;
  gap: 10px;
  align-items: center;
}

.post__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  overflow: hidden;
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  font-size: 15px;
  font-weight: 800;
  flex-shrink: 0;
}

.post__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.post__author-info {
  display: flex;
  flex-direction: column;
}

.post__author-name {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: var(--text);
  font-size: 14px;
  font-weight: 700;
}

.post__author-name a:hover {
  color: var(--accent-light);
}

.post__date {
  color: var(--text-muted);
  font-size: 11px;
}

.post__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.flag {
  display: inline-flex;
  align-items: center;
  padding: 2px 9px;
  color: var(--flag-color, var(--text-dim));
  background: rgba(255, 255, 255, 0.05);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.flag--pin { color: #fbbf24; background: rgba(251, 191, 36, 0.12); }
.flag--lock { color: #9ca3af; background: rgba(156, 163, 175, 0.12); }

.tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.tag--media {
  color: #f472b6;
  background: linear-gradient(135deg, rgba(244, 114, 182, 0.18), rgba(168, 85, 247, 0.18));
  border: 1px solid rgba(244, 114, 182, 0.45);
}

.verified {
  color: #38bdf8;
}

.post__title {
  margin: 0 0 10px;
  font-size: 21px;
  font-weight: 900;
  line-height: 1.3;
}

.post__body {
  color: var(--text);
  font-size: 14.5px;
  line-height: 1.65;
  white-space: pre-wrap;
  word-break: break-word;
}

.post__foot {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: center;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.post__stat {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--text-muted);
  font-size: 12px;
}

.post__spacer {
  flex: 1;
}

.post__edit-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 10px;
}

.like {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: color 0.18s ease, border-color 0.18s ease, background 0.18s ease;
}

.like:hover {
  color: #f472b6;
  border-color: rgba(244, 114, 182, 0.5);
}

.like--on {
  color: #f472b6;
  background: rgba(244, 114, 182, 0.12);
  border-color: rgba(244, 114, 182, 0.5);
}

.attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 14px;
}

.attachments__image img {
  max-width: 260px;
  max-height: 200px;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.attachments__file {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 13px;
  color: var(--text-dim);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 13px;
}

.attachments__size {
  color: var(--text-muted);
  font-size: 11px;
}

/* Ветки */
.replies__title {
  margin: 26px 0 12px;
  font-size: 17px;
  font-weight: 800;
}

.replies__count {
  color: var(--text-muted);
  font-weight: 600;
}

.replies {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
  padding: 0 18px;
}

.reply__avatar-fallback {
  display: none;
}

/* Правка сообщения */
.edit-box {
  margin-top: 16px;
  padding: 16px 18px;
  background: var(--bg-card);
  border: 1px solid var(--accent);
  border-radius: 12px;
}

.edit-box__title {
  margin-bottom: 10px;
  color: var(--text);
  font-size: 13px;
  font-weight: 700;
}

.edit-box__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 10px;
}

/* Форма ответа */
.reply-form {
  margin-top: 22px;
  padding: 18px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
}

.reply-form__title {
  display: flex;
  gap: 10px;
  align-items: center;
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 700;
}

.reply-form__to b {
  color: var(--accent-light);
}

.reply-form__quote {
  margin: 0 0 10px;
  padding: 9px 13px;
  color: var(--text-dim);
  background: var(--bg);
  border-left: 3px solid var(--accent);
  border-radius: 8px;
  font-size: 13px;
  font-style: italic;
}

.reply-form__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
}

.reply-form__hint {
  color: var(--text-muted);
  font-size: 12px;
}

/* Поля */
.input,
.textarea {
  width: 100%;
  padding: 11px 14px;
  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-family: inherit;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s ease;
}

.input:focus,
.textarea:focus {
  border-color: var(--accent);
}

.input {
  margin-bottom: 10px;
  font-weight: 700;
}

.textarea {
  resize: vertical;
  line-height: 1.6;
}

.link {
  color: var(--accent-light);
  background: transparent;
  border: 0;
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}

.link:hover {
  text-decoration: underline;
}

.link--danger {
  color: #f87171;
}

.state {
  padding: 40px 0;
  color: var(--text-dim);
  text-align: center;
}

.state--sm {
  padding: 20px 0;
  font-size: 13px;
}

.alert {
  margin: 0 0 16px;
  padding: 12px 16px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.35);
  border-radius: 10px;
  font-size: 13px;
}

.notice {
  margin: 0 0 16px;
  padding: 12px 16px;
  color: #86efac;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.35);
  border-radius: 10px;
  font-size: 13px;
}
</style>
