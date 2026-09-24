<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  clanId: { type: [Number, String], required: true },
  eventId: { type: [Number, String], required: true },
})

const auth = useAuthStore()

const loading = ref(true)
const comments = ref([])
const newBody = ref('')
const sending = ref(false)
const replyingTo = ref(null)
const replyBody = ref('')

async function load() {
  loading.value = true
  try {
    const data = await clansApi.eventComments(props.clanId, props.eventId)
    comments.value = data.comments || []
  } finally {
    loading.value = false
  }
}

async function send() {
  const body = newBody.value.trim()
  if (!body || sending.value) return

  sending.value = true
  try {
    await clansApi.createEventComment(props.clanId, props.eventId, body)
    newBody.value = ''
    await load()
  } finally {
    sending.value = false
  }
}

async function sendReply(parent) {
  const body = replyBody.value.trim()
  if (!body) return

  try {
    await clansApi.createEventComment(props.clanId, props.eventId, body, parent.id)
    replyingTo.value = null
    replyBody.value = ''
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  }
}

async function remove(comment) {
  if (!confirm('Удалить комментарий?')) return
  await clansApi.deleteEventComment(props.clanId, props.eventId, comment.id)
  await load()
}

function canDelete(comment) {
  return comment.user_id === auth.user?.id
}

function startReply(comment) {
  replyingTo.value = comment
  replyBody.value = ''
}

function cancelReply() {
  replyingTo.value = null
  replyBody.value = ''
}

function formatDate(date) {
  const d = new Date(date)
  const now = new Date()
  const diff = Math.floor((now - d) / 1000)

  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`
  if (diff < 604800) return `${Math.floor(diff / 86400)} дн назад`

  return d.toLocaleDateString('ru-RU')
}

function avatarLetter(username) {
  return (username || 'И').charAt(0).toUpperCase()
}

onMounted(load)
</script>

<template>
  <div class="comments">
    <!-- Форма нового комментария -->
    <div class="comment-form">
      <div class="avatar avatar--me">
        <img
            v-if="auth.user?.avatar_url"
            :src="auth.user.avatar_url"
            class="avatar-img"
        />
        <template v-else>{{ avatarLetter(auth.user?.username) }}</template>
      </div>

      <div class="form-input">
                <textarea
                    v-model="newBody"
                    rows="2"
                    maxlength="1000"
                    placeholder="Написать комментарий..."
                    @keydown.ctrl.enter="send"
                />
        <div class="form-bottom">
          <span class="hint">Ctrl + Enter — отправить</span>
          <button
              class="btn-send"
              :disabled="!newBody.trim() || sending"
              @click="send"
          >
            {{ sending ? 'Отправка...' : 'Отправить' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Список -->
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!comments.length" class="empty">
      Комментариев пока нет — будь первым
    </div>

    <div v-else class="comments-list">
      <div
          v-for="comment in comments"
          :key="comment.id"
          class="comment"
      >
        <div class="comment-main">
          <RouterLink
              :to="`/players/${comment.user.id}`"
              class="avatar"
          >
            <img
                v-if="comment.user.avatar_url"
                :src="comment.user.avatar_url"
                class="avatar-img"
            />
            <template v-else>
              {{ avatarLetter(comment.user.username) }}
            </template>
          </RouterLink>

          <div class="comment-body">
            <div class="comment-head">
              <RouterLink
                  :to="`/players/${comment.user.id}`"
                  class="comment-author"
              >
                {{ comment.user.username }}
              </RouterLink>
              <span class="dot">·</span>
              <span class="comment-time">
                                {{ formatDate(comment.created_at) }}
                            </span>
            </div>

            <p class="comment-text">{{ comment.body }}</p>

            <div class="comment-actions">
              <button
                  class="action-btn"
                  @click="startReply(comment)"
              >
                Ответить
              </button>
              <button
                  v-if="canDelete(comment)"
                  class="action-btn danger"
                  @click="remove(comment)"
              >
                Удалить
              </button>
            </div>
          </div>
        </div>

        <!-- Ответы -->
        <div v-if="comment.replies?.length" class="replies">
          <div
              v-for="reply in comment.replies"
              :key="reply.id"
              class="comment comment--reply"
          >
            <div class="comment-main">
              <RouterLink
                  :to="`/players/${reply.user.id}`"
                  class="avatar avatar--sm"
              >
                <img
                    v-if="reply.user.avatar_url"
                    :src="reply.user.avatar_url"
                    class="avatar-img"
                />
                <template v-else>
                  {{ avatarLetter(reply.user.username) }}
                </template>
              </RouterLink>

              <div class="comment-body">
                <div class="comment-head">
                  <RouterLink
                      :to="`/players/${reply.user.id}`"
                      class="comment-author"
                  >
                    {{ reply.user.username }}
                  </RouterLink>
                  <span class="dot">·</span>
                  <span class="comment-time">
                                        {{ formatDate(reply.created_at) }}
                                    </span>
                </div>

                <p class="comment-text">{{ reply.body }}</p>

                <div
                    v-if="canDelete(reply)"
                    class="comment-actions"
                >
                  <button
                      class="action-btn danger"
                      @click="remove(reply)"
                  >
                    Удалить
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Форма ответа -->
        <div v-if="replyingTo?.id === comment.id" class="reply-form">
                    <textarea
                        v-model="replyBody"
                        rows="2"
                        maxlength="1000"
                        :placeholder="`Ответить ${comment.user.username}...`"
                        @keydown.ctrl.enter="sendReply(comment)"
                    />
          <div class="form-bottom">
            <span class="hint">Ctrl + Enter — отправить</span>
            <div class="reply-actions">
              <button class="btn-cancel" @click="cancelReply">Отмена</button>
              <button
                  class="btn-send"
                  :disabled="!replyBody.trim()"
                  @click="sendReply(comment)"
              >
                Ответить
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.comments {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}

/* === ФОРМА === */

.comment-form {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.avatar {
  position: relative;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-size: 15px;
  font-weight: 800;
  overflow: hidden;
}

.avatar--sm {
  width: 32px;
  height: 32px;
  font-size: 13px;
  border-radius: 8px;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.form-input {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-input textarea,
.reply-form textarea {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  font: inherit;
  font-size: 13px;
  line-height: 1.5;
  outline: none;
  resize: vertical;
  min-height: 60px;
}

.form-input textarea:focus,
.reply-form textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

.form-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.hint {
  color: var(--text-muted);
  font-size: 11px;
}

.btn-send,
.btn-cancel {
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.2s;
}

.btn-send {
  color: #fff;
  background: var(--accent);
}

.btn-send:hover:not(:disabled) {
  background: var(--accent-light);
}

.btn-send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

/* === СПИСОК === */

.comments-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.comment {
  animation: fadeIn 0.2s ease;
}

.comment-main {
  display: flex;
  gap: 12px;
}

.comment-body {
  flex: 1;
  min-width: 0;
}

.comment-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  font-size: 13px;
}

.comment-author {
  color: var(--text);
  font-weight: 700;
}

.comment-author:hover {
  color: var(--accent-light);
}

.dot {
  color: var(--text-muted);
}

.comment-time {
  color: var(--text-muted);
  font-size: 12px;
}

.comment-text {
  margin: 0 0 6px;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.comment-actions {
  display: flex;
  gap: 12px;
}

.action-btn {
  padding: 0;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: color 0.2s;
}

.action-btn:hover {
  color: var(--accent-light);
}

.action-btn.danger:hover {
  color: #f87171;
}

/* === ОТВЕТЫ === */

.replies {
  margin-top: 12px;
  margin-left: 52px;
  padding-left: 12px;
  border-left: 2px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.comment--reply {
  /* ничего дополнительно */
}

/* === ФОРМА ОТВЕТА === */

.reply-form {
  margin-top: 12px;
  margin-left: 52px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reply-actions {
  display: flex;
  gap: 8px;
}

/* === STATES === */

.empty {
  padding: 24px;
  text-align: center;
  color: var(--text-muted);
  font-size: 13px;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 600px) {
  .replies,
  .reply-form {
    margin-left: 24px;
    padding-left: 8px;
  }
}
</style>