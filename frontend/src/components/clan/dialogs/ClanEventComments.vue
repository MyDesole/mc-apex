<script setup>
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { userLink } from '@/utils/links.js'
import { avatarLetter } from '@/utils/playerStyling.js'

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
    await alertDialog(e.message || 'Ошибка')
  }
}

async function remove(comment) {
  if (!await confirmDialog('Удалить комментарий?')) return
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
              :to="userLink(comment.user)"
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
                  :to="userLink(comment.user)"
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
                  :to="userLink(reply.user)"
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
                      :to="userLink(reply.user)"
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
@import "@/components/clan/dialogs/ClanEventComments.css";
</style>
