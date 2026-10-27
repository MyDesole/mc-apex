<script setup>
/**
 * Гостевая книга профиля: гости оставляют короткие записи. Удалить
 * запись может хозяин профиля, автор или модератор.
 */
import { computed, onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import UserName from '@/components/players/UserName.vue'

const props = defineProps({
  owner: { type: Object, required: true },
  title: { type: String, default: '' },
  // В режиме конструктора книга только показывается
  preview: { type: Boolean, default: false },
})

const auth = useAuthStore()

const entries = ref([])
const loading = ref(true)
const body = ref('')
const sending = ref(false)
const error = ref('')

const isOwner = computed(() => auth.user?.id === props.owner.id)
const isStaff = computed(() => ['admin', 'moderator'].includes(auth.user?.role))

async function load() {
  loading.value = true
  try {
    entries.value = (await api.get(`/players/${props.owner.id}/guestbook`)).entries ?? []
  } catch {
    entries.value = []
  } finally {
    loading.value = false
  }
}

async function write() {
  const text = body.value.trim()
  if (!text || sending.value) return

  sending.value = true
  error.value = ''

  try {
    const { entry } = await api.post(`/players/${props.owner.id}/guestbook`, { body: text })
    entries.value.unshift(entry)
    body.value = ''
  } catch (e) {
    error.value = e.message || 'Не удалось оставить запись.'
  } finally {
    sending.value = false
  }
}

async function remove(entry) {
  try {
    await api.delete(`/guestbook/${entry.id}`)
    entries.value = entries.value.filter((e) => e.id !== entry.id)
  } catch (e) {
    error.value = e.message || 'Не удалось удалить.'
  }
}

function canRemove(entry) {
  return isOwner.value || isStaff.value || entry.author?.id === auth.user?.id
}

function ago(value) {
  const diff = (Date.now() - new Date(value).getTime()) / 1000
  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч`
  return new Date(value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

onMounted(load)
</script>

<template>
  <div class="guestbook">
    <h3 class="block-title">{{ title || 'Гостевая книга' }}</h3>

    <form
        v-if="auth.isAuthenticated && !isOwner && !preview"
        class="guestbook__form"
        @submit.prevent="write"
    >
      <input
          v-model="body"
          class="guestbook__input"
          maxlength="200"
          placeholder="Оставь запись — хозяин профиля её увидит"
      />
      <button class="guestbook__send" type="submit" :disabled="sending || !body.trim()">
        {{ sending ? '…' : 'Оставить' }}
      </button>
    </form>
    <p v-if="error" class="guestbook__error">{{ error }}</p>

    <div v-if="loading" class="guestbook__empty">Загружаем…</div>
    <div v-else-if="!entries.length" class="guestbook__empty">
      {{ isOwner ? 'Пока никто ничего не написал. Позови друзей!' : 'Будь первым, кто оставит запись.' }}
    </div>

    <ul v-else class="guestbook__list">
      <li v-for="entry in entries" :key="entry.id" class="guestbook__entry">
        <span class="guestbook__avatar">
          <img v-if="entry.author?.avatar_url" :src="entry.author.avatar_url" alt="" />
          <template v-else>{{ (entry.author?.username || '?').charAt(0).toUpperCase() }}</template>
        </span>
        <div class="guestbook__content">
          <div class="guestbook__head">
            <RouterLink v-if="entry.author" :to="`/user/${entry.author.username}`" class="guestbook__author">
              <UserName :user="entry.author" compact />
            </RouterLink>
            <span class="guestbook__time">{{ ago(entry.created_at) }}</span>
            <button
                v-if="canRemove(entry) && !preview"
                type="button"
                class="guestbook__remove"
                title="Удалить запись"
                @click="remove(entry)"
            >×</button>
          </div>
          <p class="guestbook__body">{{ entry.body }}</p>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.guestbook {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.guestbook__form {
  display: flex;
  gap: 8px;
}

.guestbook__input {
  flex: 1;
  min-width: 0;

  padding: 9px 12px;

  color: var(--text, #fff);
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
  border-radius: 10px;

  font: inherit;
  font-size: 13px;
}

.guestbook__input:focus {
  outline: none;
  border-color: var(--accent-color, #8b5cf6);
}

.guestbook__send {
  padding: 0 14px;

  color: #fff;
  background: var(--accent-color, #7c3aed);
  border: 0;
  border-radius: 10px;

  font-size: 12px;
  font-weight: 800;

  cursor: pointer;
}

.guestbook__send:disabled {
  opacity: 0.5;
  cursor: default;
}

.guestbook__error {
  margin: 0;
  color: #f87171;
  font-size: 12px;
}

.guestbook__empty {
  padding: 14px;

  color: var(--text-muted, #8a8598);
  border: 1px dashed var(--border, rgba(255, 255, 255, 0.1));
  border-radius: 10px;

  font-size: 13px;
  text-align: center;
}

.guestbook__list {
  max-height: 320px;
  overflow-y: auto;

  margin: 0;
  padding: 0;

  display: flex;
  flex-direction: column;
  gap: 8px;

  list-style: none;
}

.guestbook__entry {
  display: flex;
  gap: 10px;

  padding: 9px 10px;

  background: rgba(255, 255, 255, 0.03);
  border-radius: 10px;
}

.guestbook__avatar {
  width: 30px;
  height: 30px;

  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;

  color: #fff;
  background: var(--accent-color, #7c3aed);
  border-radius: 8px;

  font-size: 12px;
  font-weight: 800;
}

.guestbook__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.guestbook__content {
  min-width: 0;
  flex: 1;
}

.guestbook__head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.guestbook__author {
  color: var(--text, #fff);
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
}

.guestbook__time {
  color: var(--text-muted, #8a8598);
  font-size: 11px;
}

.guestbook__remove {
  margin-left: auto;
  padding: 0 4px;

  color: var(--text-muted, #8a8598);
  background: none;
  border: 0;

  font-size: 16px;
  cursor: pointer;
}

.guestbook__remove:hover {
  color: #f87171;
}

.guestbook__body {
  margin: 3px 0 0;
  color: var(--text-dim, #d4d0de);
  font-size: 13px;
  line-height: 1.45;
  overflow-wrap: anywhere;
}
</style>
