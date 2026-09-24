<script setup>
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
  if (!confirm('Удалить топик?')) return
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
  <div class="tab">
    <!-- Список топиков -->
    <template v-if="!selectedTopic">
      <div v-if="permissions.forum" class="actions">
        <button class="btn-create" @click="showForm = !showForm">
          {{ showForm ? 'Отмена' : '+ Новый топик' }}
        </button>
      </div>

      <div v-if="showForm" class="form">
        <input v-model="form.title" placeholder="Заголовок топика" maxlength="160" />
        <textarea v-model="form.body" rows="5" placeholder="О чём поговорим?" />
        <div class="form-actions">
          <button class="btn-save" :disabled="processing || !form.title" @click="submit">
            {{ processing ? '...' : 'Создать' }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="empty">Загрузка...</div>
      <div v-else-if="!topics.length" class="empty">Топиков пока нет</div>

      <div v-else class="topics-list">
        <div
            v-for="t in topics"
            :key="t.id"
            class="topic"
            :class="{ 'topic--pinned': t.is_pinned, 'topic--locked': t.is_locked }"
            @click="openTopic(t)"
        >
          <div class="topic__avatar">
            <img v-if="t.author?.avatar_url" :src="t.author.avatar_url" />
            <template v-else>{{ t.author?.username?.charAt(0).toUpperCase() }}</template>
          </div>

          <div class="topic__info">
            <div class="topic__title">
              <span v-if="t.is_pinned">📌</span>
              <span v-if="t.is_locked">🔒</span>
              {{ t.title }}
            </div>
            <div class="topic__meta">
              {{ t.author?.username }} · {{ formatDate(t.created_at) }}
            </div>
          </div>

          <div class="topic__stats">
            <span>{{ t.replies_count }} ответов</span>
            <span>{{ t.views }} просм.</span>
          </div>

          <div class="topic__actions" @click.stop>
            <button v-if="permissions.forum" @click="pinTopic(t)" title="Закрепить">📌</button>
            <button v-if="permissions.forum" @click="lockTopic(t)" title="Закрыть">🔒</button>
            <button
                v-if="permissions.forum || t.author_id === auth.user?.id"
                @click="removeTopic(t)"
                title="Удалить"
                class="danger"
            >🗑</button>
          </div>
        </div>
      </div>
    </template>

    <!-- Просмотр топика -->
    <template v-else>
      <button class="btn-back" @click="closeTopic">← К списку</button>

      <div v-if="topicData" class="topic-view">
        <header class="topic-view__head">
          <h2>{{ topicData.title }}</h2>
          <div class="topic-view__meta">
            Автор: {{ topicData.author?.username }} · {{ formatDate(topicData.created_at) }}
          </div>
        </header>

        <div class="topic-view__body">
          <pre>{{ topicData.body }}</pre>
        </div>

        <!-- Ответы -->
        <div v-if="topicData.replies?.length" class="replies">
          <div v-for="r in topicData.replies" :key="r.id" class="reply">
            <div class="reply__avatar">
              <img v-if="r.author?.avatar_url" :src="r.author.avatar_url" />
              <template v-else>{{ r.author?.username?.charAt(0).toUpperCase() }}</template>
            </div>
            <div class="reply__body">
              <div class="reply__head">
                <b>{{ r.author?.username }}</b>
                <span>{{ formatDate(r.created_at) }}</span>
              </div>
              <pre>{{ r.body }}</pre>
            </div>
          </div>
        </div>

        <!-- Форма ответа -->
        <div v-if="!topicData.is_locked" class="reply-form">
          <textarea v-model="replyBody" rows="3" placeholder="Написать ответ..." />
          <div class="form-actions">
            <button class="btn-save" :disabled="replyProcessing || !replyBody.trim()" @click="sendReply">
              {{ replyProcessing ? '...' : 'Ответить' }}
            </button>
          </div>
        </div>

        <div v-else class="locked-notice">
          🔒 Топик закрыт
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }

.actions { display: flex; justify-content: flex-end; }

.btn-create, .btn-save, .btn-back {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-back {
  align-self: flex-start;
  background: transparent;
  color: var(--text-dim);
  border: 1px solid var(--border);
}

.btn-back:hover { color: var(--text); }

.form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.form input,
.form textarea {
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.form-actions { display: flex; justify-content: flex-end; }

.topics-list { display: flex; flex-direction: column; gap: 6px; }

.topic {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.topic:hover { background: var(--bg-card-hover); border-color: var(--border-hover); }
.topic--pinned { border-color: rgba(250, 204, 21, 0.3); background: linear-gradient(90deg, rgba(250, 204, 21, 0.04), var(--bg-card) 40%); }
.topic--locked { opacity: 0.75; }

.topic__avatar {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.topic__avatar img { width: 100%; height: 100%; object-fit: cover; }

.topic__info { flex: 1; min-width: 0; }

.topic__title {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.topic__meta { font-size: 11px; color: var(--text-muted); }

.topic__stats {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 11px;
  color: var(--text-dim);
  text-align: right;
  flex-shrink: 0;
}

.topic__actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.topic__actions button {
  width: 30px;
  height: 30px;
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 7px;
  cursor: pointer;
  font-size: 13px;
  color: var(--text-dim);
}

.topic__actions button:hover { border-color: var(--border-hover); color: var(--text); }
.topic__actions button.danger:hover { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

/* Topic view */
.topic-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.topic-view__head {
  padding: 20px 24px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
}

.topic-view__head h2 {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.topic-view__meta { font-size: 12px; color: var(--text-muted); }

.topic-view__body {
  padding: 20px 24px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 14px;
}

.topic-view__body pre {
  margin: 0;
  color: var(--text);
  font-family: inherit;
  font-size: 14px;
  line-height: 1.7;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.replies { display: flex; flex-direction: column; gap: 12px; }

.reply {
  display: flex;
  gap: 12px;
  padding: 14px 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.reply__avatar {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 9px;
  color: #fff;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
  font-size: 13px;
}

.reply__avatar img { width: 100%; height: 100%; object-fit: cover; }

.reply__body { flex: 1; min-width: 0; }

.reply__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 6px;
  font-size: 12px;
}

.reply__head b { color: var(--text); font-size: 13px; }
.reply__head span { color: var(--text-muted); }

.reply__body pre {
  margin: 0;
  color: var(--text-dim);
  font-family: inherit;
  font-size: 13px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.reply-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.reply-form textarea {
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.locked-notice {
  padding: 20px;
  text-align: center;
  color: var(--text-muted);
  background: rgba(239, 68, 68, 0.05);
  border: 1px dashed rgba(239, 68, 68, 0.25);
  border-radius: 12px;
  font-size: 13px;
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}
</style>