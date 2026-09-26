<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { chatApi } from '@/services/chat.js'
import { useAuthStore } from '@/stores/auth'
import { useRealtimeMessages } from '@/composables/useRealtimeMessages'
import UserName from '@/components/UserName.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const { latestMessage } = useRealtimeMessages()

const tierColors = {
  'S+': '#fbbf24',
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

// ============================================
// СПИСОК ДИАЛОГОВ И ЧАТ
// ============================================

const conversations = ref([])
const activeConversation = ref(null)
const messages = ref([])
const loadingList = ref(true)
const loadingChat = ref(false)
const sending = ref(false)
const body = ref('')
const error = ref('')

const messagesEl = ref(null)
const inputEl = ref(null)

const showChatOnMobile = computed(() => !!activeConversation.value)

// Сколько участников диалога, кроме меня — нужно для галочки «прочитано всеми»
const otherParticipantsCount = computed(() => {
  if (!activeConversation.value) return 0
  return Math.max(0, (activeConversation.value.users || []).length - 1)
})

// Собеседник в личном диалоге (для клика в шапке)
const directPartner = computed(() => {
  if (!activeConversation.value) return null
  if (activeConversation.value.type === 'clan_message') return null
  return (activeConversation.value.users || []).find(u => u.id !== auth.user?.id) || null
})

function goToPlayer(userId) {
  if (!userId) return
  router.push(`/players/${userId}`)
}

async function loadConversations() {
  loadingList.value = true
  try {
    const data = await chatApi.conversations()
    conversations.value = data.conversations ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить диалоги'
  } finally {
    loadingList.value = false
  }
}

async function openConversation(id) {
  loadingChat.value = true
  error.value = ''

  try {
    const data = await chatApi.show(id)
    activeConversation.value = data.conversation
    messages.value = data.messages ?? []

    const idx = conversations.value.findIndex(c => c.id === data.conversation.id)
    if (idx !== -1) {
      conversations.value[idx].unread_count = 0
    } else {
      conversations.value.unshift({
        ...data.conversation,
        unread_count: 0,
      })
    }

    await scrollToBottom()
  } catch (e) {
    error.value = e.message || 'Не удалось открыть диалог'
  } finally {
    loadingChat.value = false
  }
}

async function sendMessage() {
  if (!body.value.trim() || !activeConversation.value) return

  const text = body.value.trim()
  const replyId = replyTo.value?.id ?? null

  body.value = ''
  replyTo.value = null
  sending.value = true

  try {
    const data = await chatApi.send(activeConversation.value.id, text, replyId)
    messages.value.push(data.message)

    const idx = conversations.value.findIndex(c => c.id === activeConversation.value.id)
    if (idx !== -1) {
      conversations.value[idx].last_message = data.message
      conversations.value[idx].last_message_at = data.message.created_at
      const [c] = conversations.value.splice(idx, 1)
      conversations.value.unshift(c)
    }

    await scrollToBottom()
  } catch (e) {
    error.value = e.message || 'Не удалось отправить'
    body.value = text
    replyTo.value = replyId ? messages.value.find(m => m.id === replyId) : null
  } finally {
    sending.value = false
  }
}

async function scrollToBottom() {
  await nextTick()
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

async function scrollToMessage(id) {
  await nextTick()
  const el = document.querySelector(`[data-message-id="${id}"]`)
  if (!el) return

  el.scrollIntoView({ behavior: 'smooth', block: 'center' })
  el.classList.add('msg--highlight')
  setTimeout(() => el.classList.remove('msg--highlight'), 1200)
}

function closeChat() {
  activeConversation.value = null
  messages.value = []
  clearReply()
  closeForward()
  if (route.params.id) {
    router.push('/messages')
  }
}

// ============================================
// ОТВЕТ НА СООБЩЕНИЕ
// ============================================

const replyTo = ref(null)

function setReply(message) {
  replyTo.value = message
  nextTick(() => inputEl.value?.focus())
}

function clearReply() {
  replyTo.value = null
}

// ============================================
// ПЕРЕСЫЛКА
// ============================================

const forwardOpen = ref(false)
const forwardMessage = ref(null)
const forwardSearch = ref('')
const forwardResults = ref({ users: [], clans: [] })
const forwardLoading = ref(false)
const forwardSending = ref(false)

let forwardTimer = null

function openForward(message) {
  forwardMessage.value = message
  forwardOpen.value = true
  forwardSearch.value = ''
  forwardResults.value = { users: [], clans: [] }
  runForwardSearch('')
}

function closeForward() {
  forwardOpen.value = false
  forwardMessage.value = null
  forwardSearch.value = ''
  forwardResults.value = { users: [], clans: [] }
  clearTimeout(forwardTimer)
}

async function runForwardSearch(q) {
  forwardLoading.value = true
  try {
    const data = await chatApi.search(q)
    forwardResults.value = {
      users: data.users ?? [],
      clans: data.clans ?? [],
    }
  } catch (e) {
    forwardResults.value = { users: [], clans: [] }
  } finally {
    forwardLoading.value = false
  }
}

watch(forwardSearch, (q) => {
  clearTimeout(forwardTimer)
  const query = q.trim()

  if (query === '') {
    runForwardSearch('')
    return
  }

  if (query.length < 2) return

  forwardTimer = setTimeout(() => runForwardSearch(query), 300)
})

async function confirmForwardUser(userId) {
  if (!forwardMessage.value) return
  forwardSending.value = true

  try {
    const direct = await chatApi.startDirect(userId)
    const conversationId = direct.conversation.id

    await chatApi.forward(forwardMessage.value.id, conversationId)

    closeForward()
    await loadConversations()

    if (activeConversation.value?.id === conversationId) {
      await openConversation(conversationId)
    }
  } catch (e) {
    error.value = e.message || 'Не удалось переслать'
  } finally {
    forwardSending.value = false
  }
}

async function confirmForwardClan(clanId) {
  if (!forwardMessage.value) return
  forwardSending.value = true

  try {
    const clanChat = await chatApi.startClan(clanId)
    const conversationId = clanChat.conversation.id

    await chatApi.forward(forwardMessage.value.id, conversationId)

    closeForward()
    await loadConversations()

    if (activeConversation.value?.id === conversationId) {
      await openConversation(conversationId)
    }
  } catch (e) {
    error.value = e.message || 'Не удалось переслать'
  } finally {
    forwardSending.value = false
  }
}

// ============================================
// ПРОЧТЕНИЕ
// ============================================

function isMessageRead(m) {
  return (m.reads || []).some(r => r.user_id !== auth.user?.id)
}

function isMessageFullyRead(m) {
  if (!otherParticipantsCount.value) return false
  const readers = (m.reads || []).filter(r => r.user_id !== auth.user?.id).length
  return readers >= otherParticipantsCount.value
}

// ============================================
// ДРОПДАУН ПОИСКА (для кнопки «+» в списке диалогов)
// ============================================

const searchOpen = ref(false)
const searchQuery = ref('')
const searchResults = ref({ users: [], clans: [] })
const searchLoading = ref(false)
const searchWrapEl = ref(null)
const dropdownPos = ref({ top: 0, right: 0 })

let searchTimer = null

function computeDropdownPosition() {
  if (!searchWrapEl.value) return
  const rect = searchWrapEl.value.getBoundingClientRect()
  dropdownPos.value = {
    top: rect.bottom + 8,
    right: window.innerWidth - rect.right - 8,
  }
}

async function runSearch(query) {
  searchLoading.value = true
  try {
    const data = await chatApi.search(query)
    searchResults.value = {
      users: data.users ?? [],
      clans: data.clans ?? [],
    }
  } catch (e) {
    searchResults.value = { users: [], clans: [] }
  } finally {
    searchLoading.value = false
  }
}

function toggleSearch() {
  searchOpen.value = !searchOpen.value
  if (searchOpen.value) {
    computeDropdownPosition()
    runSearch('')
  } else {
    resetSearch()
  }
}

function closeSearch() {
  searchOpen.value = false
  resetSearch()
}

function resetSearch() {
  clearTimeout(searchTimer)
  searchQuery.value = ''
  searchResults.value = { users: [], clans: [] }
  searchLoading.value = false
}

watch(searchQuery, (q) => {
  clearTimeout(searchTimer)
  const query = q.trim()

  if (query === '') {
    runSearch('')
    return
  }

  if (query.length < 2) return

  searchTimer = setTimeout(() => runSearch(query), 300)
})

async function startWithUser(userId) {
  try {
    const data = await chatApi.startDirect(userId)
    closeSearch()
    router.push(`/messages/${data.conversation.id}`)
    await loadConversations()
  } catch (e) {
    error.value = e.message || 'Не удалось открыть диалог'
  }
}

async function startWithClan(clanId) {
  try {
    const data = await chatApi.startClan(clanId)
    closeSearch()
    router.push(`/messages/${data.conversation.id}`)
    await loadConversations()
  } catch (e) {
    error.value = e.message || 'Не удалось открыть чат с кланом'
  }
}

// ============================================
// WATCHERS / LIFECYCLE
// ============================================

watch(
    () => route.params.id,
    (id) => {
      clearReply()
      closeForward()
      if (id) {
        openConversation(Number(id))
      } else {
        activeConversation.value = null
        messages.value = []
      }
    },
    { immediate: true }
)

watch(latestMessage, async (m) => {
  if (!m) return

  if (activeConversation.value && m.conversation_id === activeConversation.value.id) {
    messages.value.push(m)
    await scrollToBottom()
    chatApi.show(m.conversation_id).catch(() => {})
  } else {
    const idx = conversations.value.findIndex(c => c.id === m.conversation_id)
    if (idx !== -1) {
      conversations.value[idx].unread_count = (conversations.value[idx].unread_count || 0) + 1
      conversations.value[idx].last_message = m
      conversations.value[idx].last_message_at = m.created_at
      const [c] = conversations.value.splice(idx, 1)
      conversations.value.unshift(c)
    } else {
      loadConversations()
    }
  }
})

function onClickOutsideSearch(e) {
  if (!searchOpen.value) return
  if (e.target.closest('.search-dropdown')) return
  if (e.target.closest('.conversations__search-wrap')) return
  closeSearch()
}

function onResizeOrScroll() {
  if (searchOpen.value) {
    computeDropdownPosition()
  }
}

onMounted(() => {
  loadConversations()
  document.addEventListener('click', onClickOutsideSearch)
  window.addEventListener('resize', onResizeOrScroll)
  window.addEventListener('scroll', onResizeOrScroll, true)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutsideSearch)
  window.removeEventListener('resize', onResizeOrScroll)
  window.removeEventListener('scroll', onResizeOrScroll, true)
  clearTimeout(searchTimer)
  clearTimeout(forwardTimer)
})
</script>

<template>
  <div class="messages-page" :class="{ 'messages-page--chat-open': showChatOnMobile }">
    <!-- ============================================
         СПИСОК ДИАЛОГОВ
         ============================================ -->
    <aside class="conversations">
      <header class="conversations__head">
        <h1>Сообщения</h1>

        <div ref="searchWrapEl" class="conversations__search-wrap">
          <button
              class="conversations__new"
              :class="{ 'conversations__new--active': searchOpen }"
              type="button"
              :title="searchOpen ? 'Закрыть' : 'Найти игрока или клан'"
              @click.stop="toggleSearch"
          >
            <svg v-if="!searchOpen" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14M5 12h14" />
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>
        </div>
      </header>

      <div v-if="loadingList" class="conversations__empty">Загрузка...</div>
      <div v-else-if="!conversations.length" class="conversations__empty">
        Пока нет диалогов.<br>
        Найди игрока или клан через кнопку «+».
      </div>

      <ul v-else class="conversations__list">
        <li v-for="c in conversations" :key="c.id">
          <RouterLink
              :to="`/messages/${c.id}`"
              class="conv"
              :class="{ 'conv--active': activeConversation?.id === c.id }"
          >
            <div class="conv__avatar">
              <template v-if="c.type === 'clan_message'">
                <div class="conv__clan" :style="{ background: c.clan?.banner_color || '#7c3aed' }">
                  <img v-if="c.clan?.avatar_url" :src="c.clan.avatar_url" :alt="c.clan.name" />
                  <template v-else>{{ (c.clan?.tag || 'C').charAt(0) }}</template>
                </div>
              </template>
              <template v-else>
                <template v-for="u in (c.users || []).filter(u => u.id !== auth.user?.id)" :key="u.id">
                  <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.username" />
                  <template v-else>{{ (u.username || 'И').charAt(0).toUpperCase() }}</template>
                </template>
              </template>
            </div>

            <div class="conv__body">
              <div class="conv__name">
                <template v-if="c.type === 'clan_message'">
                  [{{ c.clan?.tag }}] {{ c.clan?.name }}
                  <span class="conv__tag">обращение</span>
                </template>
                <template v-else>
                  <template v-for="u in (c.users || []).filter(u => u.id !== auth.user?.id)" :key="u.id">
                    <UserName :user="u" compact />
                  </template>
                </template>
              </div>

              <div class="conv__preview">
                <template v-if="c.last_message">
                  <span class="conv__preview-author">{{ c.last_message.user?.username }}:</span>
                  {{ c.last_message.body }}
                </template>
                <template v-else>
                  Нет сообщений
                </template>
              </div>
            </div>

            <div class="conv__meta">
              <span v-if="c.last_message_at" class="conv__time">
                {{ new Date(c.last_message_at).toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' }) }}
              </span>
              <span v-if="c.unread_count > 0" class="conv__badge">{{ c.unread_count }}</span>
            </div>
          </RouterLink>
        </li>
      </ul>
    </aside>

    <!-- ============================================
         ОКНО ЧАТА
         ============================================ -->
    <section class="chat" :class="{ 'chat--empty': !activeConversation }">
      <template v-if="!activeConversation">
        <div class="chat__empty">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          <p>Выбери диалог слева</p>
        </div>
      </template>

      <template v-else>
        <header class="chat__head">
          <button class="chat__back" @click="closeChat" aria-label="Назад">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </button>

          <!-- Аватар собеседника (клик → профиль) -->
          <div
              v-if="directPartner"
              class="chat__head-avatar"
              :title="`Открыть профиль ${directPartner.username}`"
              @click="goToPlayer(directPartner.id)"
          >
            <div class="chat__head-avatar-box">
              <img v-if="directPartner.avatar_url" :src="directPartner.avatar_url" :alt="directPartner.username" />
              <template v-else>{{ (directPartner.username || 'И').charAt(0).toUpperCase() }}</template>
            </div>
          </div>

          <div class="chat__head-body">
            <template v-if="activeConversation.type === 'clan_message'">
              <div class="chat__head-name">
                [{{ activeConversation.clan?.tag }}] {{ activeConversation.clan?.name }}
              </div>
              <div class="chat__head-sub">
                Обращение от {{ activeConversation.author?.username }}
              </div>
            </template>
            <template v-else-if="directPartner">
              <div
                  class="chat__head-name chat__head-name--link"
                  :title="`Открыть профиль ${directPartner.username}`"
                  @click="goToPlayer(directPartner.id)"
              >
                <UserName :user="directPartner" />
              </div>
            </template>
          </div>
        </header>

        <div ref="messagesEl" class="chat__messages">
          <div v-if="loadingChat" class="chat__loading">Загрузка...</div>

          <template v-else-if="messages.length">
            <div
                v-for="m in messages"
                :key="m.id"
                :data-message-id="m.id"
                class="msg"
                :class="{ 'msg--mine': m.user?.id === auth.user?.id }"
            >
              <div
                  class="msg__avatar"
                  :title="m.user ? `Открыть профиль ${m.user.username}` : ''"
                  @click="m.user && goToPlayer(m.user.id)"
              >
                <img v-if="m.user?.avatar_url" :src="m.user.avatar_url" :alt="m.user.username" />
                <template v-else>{{ (m.user?.username || 'И').charAt(0).toUpperCase() }}</template>
              </div>

              <div class="msg__body">
                <div class="msg__bubble">
                  <!-- Переслано от -->
                  <div v-if="m.forwarded_from" class="msg__forwarded">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M15 17l5-5-5-5" />
                      <path d="M4 18v-2a4 4 0 0 1 4-4h12" />
                    </svg>
                    Переслано от <b>{{ m.forwarded_from.username }}</b>
                  </div>

                  <!-- Цитата ответа -->
                  <div
                      v-if="m.reply_to"
                      class="msg__reply"
                      @click="scrollToMessage(m.reply_to.id)"
                  >
                    <div class="msg__reply-author">{{ m.reply_to.user?.username }}</div>
                    <div class="msg__reply-body">{{ m.reply_to.body }}</div>
                  </div>

                  <div class="msg__content">
                    <span class="msg__text">{{ m.body }}</span>

                    <span class="msg__meta">
                      <span class="msg__time">
                        {{ new Date(m.created_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }) }}
                      </span>

                      <span
                          v-if="m.user?.id === auth.user?.id"
                          class="msg__read-status"
                          :class="{
                            'msg__read-status--read': isMessageRead(m),
                            'msg__read-status--full': isMessageFullyRead(m),
                          }"
                          :title="isMessageFullyRead(m) ? 'Прочитано всеми' : (isMessageRead(m) ? 'Прочитано' : 'Отправлено')"
                      >
                        <svg class="msg__check msg__check--first" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M4 12.5l5 5L20 6.5" />
                        </svg>
                        <svg class="msg__check msg__check--second" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M4 12.5l5 5L20 6.5" />
                        </svg>
                      </span>
                    </span>
                  </div>
                </div>
              </div>

              <!-- Действия с сообщением -->
              <div class="msg__actions">
                <button
                    class="msg__action"
                    type="button"
                    title="Ответить"
                    @click="setReply(m)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 17l-5-5 5-5" />
                    <path d="M20 18v-2a4 4 0 0 0-4-4H4" />
                  </svg>
                </button>

                <button
                    class="msg__action"
                    type="button"
                    title="Переслать"
                    @click="openForward(m)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 17l5-5-5-5" />
                    <path d="M4 18v-2a4 4 0 0 1 4-4h12" />
                  </svg>
                </button>
              </div>
            </div>
          </template>

          <div v-else class="chat__empty-mini">
            Сообщений ещё нет. Напиши первым.
          </div>
        </div>

        <footer class="chat__footer">
          <!-- Панель «Отвечая на» -->
          <div v-if="replyTo" class="chat__reply-bar">
            <div class="chat__reply-bar-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 17l-5-5 5-5" />
                <path d="M20 18v-2a4 4 0 0 0-4-4H4" />
              </svg>
            </div>
            <div class="chat__reply-bar-body">
              <div class="chat__reply-bar-author">{{ replyTo.user?.username }}</div>
              <div class="chat__reply-bar-text">
                {{ replyTo.body?.slice(0, 100) }}
              </div>
            </div>
            <button
                class="chat__reply-bar-close"
                type="button"
                aria-label="Отменить ответ"
                @click="clearReply"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div v-if="error" class="chat__error">{{ error }}</div>

          <form class="chat__form" @submit.prevent="sendMessage">
            <textarea
                ref="inputEl"
                v-model="body"
                class="chat__input"
                placeholder="Написать сообщение..."
                maxlength="2000"
                rows="1"
                @keydown.enter.exact.prevent="sendMessage"
            />
            <button
                class="chat__send"
                type="submit"
                :disabled="sending || !body.trim()"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 2 11 13" />
                <path d="M22 2l-7 20-4-9-9-4 20-7z" />
              </svg>
            </button>
          </form>
        </footer>
      </template>
    </section>

    <!-- ============================================
         ДРОПДАУН ПОИСКА (кнопка «+»)
         ============================================ -->
    <Teleport to="body">
      <Transition name="search-fade">
        <div
            v-if="searchOpen"
            class="search-dropdown"
            :style="{
              top: dropdownPos.top + 'px',
              right: dropdownPos.right + 'px',
            }"
            @click.stop
        >
          <div class="search-input-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="7" />
              <path d="m21 21-4.3-4.3" />
            </svg>
            <input
                v-model="searchQuery"
                type="text"
                class="search-input"
                placeholder="Поиск: игрок или клан"
                autofocus
            />
          </div>

          <div class="search-body">
            <div v-if="searchLoading" class="search-hint">
              Поиск...
            </div>

            <template v-else>
              <div v-if="searchResults.users.length" class="search-section">
                <div class="search-section__title">
                  {{ searchQuery.trim() === '' ? 'Друзья' : 'Игроки' }}
                </div>

                <button
                    v-for="u in searchResults.users"
                    :key="u.id"
                    type="button"
                    class="search-item"
                    @click="startWithUser(u.id)"
                >
                  <div class="search-item__avatar">
                    <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.username" />
                    <template v-else>{{ (u.username || 'И').charAt(0).toUpperCase() }}</template>
                  </div>
                  <div class="search-item__body">
                    <div class="search-item__name">
                      {{ u.username }}
                      <svg v-if="u.is_verified" width="11" height="11" viewBox="0 0 24 24" fill="#1da1f2">
                        <path d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z" />
                      </svg>
                    </div>
                    <div class="search-item__sub">
                      Тир
                      <b :style="{ color: tierColors[u.tier] || '#6b7280' }">
                        {{ u.tier ?? '—' }}
                      </b>
                    </div>
                  </div>
                </button>
              </div>

              <div v-if="searchResults.clans.length" class="search-section">
                <div class="search-section__title">Кланы</div>

                <button
                    v-for="c in searchResults.clans"
                    :key="c.id"
                    type="button"
                    class="search-item"
                    @click="startWithClan(c.id)"
                >
                  <div
                      class="search-item__avatar"
                      :style="{ background: c.banner_color || '#7c3aed' }"
                  >
                    <img v-if="c.avatar_url" :src="c.avatar_url" :alt="c.name" />
                    <template v-else>{{ (c.tag || 'C').charAt(0) }}</template>
                  </div>
                  <div class="search-item__body">
                    <div class="search-item__name">
                      <span class="search-item__tag">[{{ c.tag }}]</span>
                      {{ c.name }}
                    </div>
                    <div class="search-item__sub">
                      Написать клану (лидеру и офицерам)
                    </div>
                  </div>
                </button>
              </div>

              <div
                  v-if="!searchResults.users.length && !searchResults.clans.length"
                  class="search-hint"
              >
                {{ searchQuery.trim() === ''
                  ? 'У тебя пока нет друзей'
                  : 'Ничего не найдено' }}
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ============================================
         МОДАЛКА ПЕРЕСЫЛКИ
         ============================================ -->
    <Teleport to="body">
      <Transition name="search-fade">
        <div
            v-if="forwardOpen"
            class="forward-modal-bg"
            @click.self="closeForward"
        >
          <div class="forward-modal">
            <header class="forward-modal__head">
              <h3>Переслать сообщение</h3>
              <button
                  class="forward-modal__close"
                  type="button"
                  aria-label="Закрыть"
                  @click="closeForward"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </header>

            <div v-if="forwardMessage" class="forward-modal__preview">
              <div class="forward-modal__preview-author">
                {{ forwardMessage.user?.username }}
              </div>
              <div class="forward-modal__preview-body">
                {{ forwardMessage.body?.slice(0, 140) }}
              </div>
            </div>

            <div class="forward-modal__search">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.3-4.3" />
              </svg>
              <input
                  v-model="forwardSearch"
                  type="text"
                  class="forward-modal__input"
                  placeholder="Игрок или клан..."
              />
            </div>

            <div class="forward-modal__body">
              <div v-if="forwardLoading" class="forward-modal__hint">Поиск...</div>

              <template v-else>
                <div v-if="forwardResults.users.length" class="forward-modal__section">
                  <div class="forward-modal__section-title">
                    {{ forwardSearch.trim() === '' ? 'Друзья' : 'Игроки' }}
                  </div>

                  <button
                      v-for="u in forwardResults.users"
                      :key="u.id"
                      type="button"
                      class="forward-modal__item"
                      :disabled="forwardSending"
                      @click="confirmForwardUser(u.id)"
                  >
                    <div class="forward-modal__item-avatar">
                      <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.username" />
                      <template v-else>{{ (u.username || 'И').charAt(0).toUpperCase() }}</template>
                    </div>
                    <div class="forward-modal__item-body">
                      <div class="forward-modal__item-name">{{ u.username }}</div>
                      <div class="forward-modal__item-sub">
                        Тир <b :style="{ color: tierColors[u.tier] || '#6b7280' }">{{ u.tier ?? '—' }}</b>
                      </div>
                    </div>
                  </button>
                </div>

                <div v-if="forwardResults.clans.length" class="forward-modal__section">
                  <div class="forward-modal__section-title">Кланы</div>

                  <button
                      v-for="c in forwardResults.clans"
                      :key="c.id"
                      type="button"
                      class="forward-modal__item"
                      :disabled="forwardSending"
                      @click="confirmForwardClan(c.id)"
                  >
                    <div
                        class="forward-modal__item-avatar"
                        :style="{ background: c.banner_color || '#7c3aed' }"
                    >
                      <img v-if="c.avatar_url" :src="c.avatar_url" :alt="c.name" />
                      <template v-else>{{ (c.tag || 'C').charAt(0) }}</template>
                    </div>
                    <div class="forward-modal__item-body">
                      <div class="forward-modal__item-name">
                        <span class="forward-modal__item-tag">[{{ c.tag }}]</span>
                        {{ c.name }}
                      </div>
                      <div class="forward-modal__item-sub">
                        Отправить лидеру и офицерам
                      </div>
                    </div>
                  </button>
                </div>

                <div
                    v-if="!forwardResults.users.length && !forwardResults.clans.length"
                    class="forward-modal__hint"
                >
                  {{ forwardSearch.trim() === ''
                    ? 'У тебя пока нет друзей'
                    : 'Ничего не найдено' }}
                </div>
              </template>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.messages-page {
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 16px;
  width: min(1200px, calc(100% - 40px));
  margin: 24px auto;
  height: calc(100vh - var(--header-height) - 48px);
  min-height: 500px;
}

/* ============================================
   CONVERSATIONS LIST
   ============================================ */

.conversations {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.conversations__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 18px;
  border-bottom: 1px solid var(--border);
}

.conversations__head h1 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.conversations__search-wrap {
  position: relative;
}

.conversations__new {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.3);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}

.conversations__new:hover {
  background: rgba(124, 58, 237, 0.18);
}

.conversations__new--active {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}

.conversations__empty {
  padding: 40px 20px;
  text-align: center;
  color: var(--text-muted);
  font-size: 13px;
  line-height: 1.6;
}

.conversations__list {
  flex: 1;
  overflow-y: auto;
  list-style: none;
  margin: 0;
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border-radius: 0 0 16px 16px;
}

.conv {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 10px;
  text-decoration: none;
  transition: background 0.15s;
}

.conv:hover {
  background: rgba(255, 255, 255, 0.03);
}

.conv--active {
  background: rgba(124, 58, 237, 0.1);
}

.conv__avatar {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 11px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  overflow: hidden;
}

.conv__avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.conv__clan {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  overflow: hidden;
}

.conv__clan img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.conv__body {
  flex: 1;
  min-width: 0;
}

.conv__name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 3px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conv__tag {
  padding: 1px 6px;
  border-radius: 999px;
  background: rgba(124, 58, 237, 0.12);
  color: var(--accent-light);
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.conv__preview {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conv__preview-author {
  color: var(--accent-light);
  font-weight: 700;
  margin-right: 4px;
}

.conv__meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.conv__time {
  font-size: 10px;
  color: var(--text-muted);
  font-weight: 700;
  text-transform: uppercase;
}

.conv__badge {
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
}

/* ============================================
   CHAT
   ============================================ */

.chat {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  min-width: 0;
}

.chat--empty {
  justify-content: center;
  align-items: center;
}

.chat__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: var(--text-muted);
  font-size: 14px;
}

.chat__empty svg {
  opacity: 0.3;
}

.chat__head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.chat__back {
  display: none;
  width: 32px;
  height: 32px;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  flex-shrink: 0;
}

/* Аватар собеседника в шапке — кликабельный */
.chat__head-avatar {
  flex-shrink: 0;
  cursor: pointer;
  transition: transform 0.15s;
}

.chat__head-avatar:hover {
  transform: scale(1.05);
}

.chat__head-avatar-box {
  position: relative;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  font-size: 15px;
  font-weight: 800;
  overflow: hidden;
}

.chat__head-avatar-box img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.chat__head-body {
  flex: 1;
  min-width: 0;
}

.chat__head-name {
  font-size: 14.5px;
  font-weight: 800;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Имя собеседника — кликабельно */
.chat__head-name--link {
  cursor: pointer;
  transition: color 0.15s;
}

.chat__head-name--link:hover {
  color: var(--accent-light);
}

.chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.chat__loading {
  text-align: center;
  padding: 40px;
  color: var(--text-muted);
  font-size: 13px;
}

.chat__empty-mini {
  text-align: center;
  padding: 40px;
  color: var(--text-muted);
  font-size: 13px;
}

/* ============================================
   MESSAGE
   ============================================ */

.msg {
  display: flex;
  gap: 10px;
  max-width: 80%;
}

.msg--mine {
  margin-left: auto;
  flex-direction: row-reverse;
}

/* Аватарка в сообщении — кликабельная */
.msg__avatar {
  position: relative;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 9px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.15s;
}

.msg__avatar:hover {
  transform: scale(1.06);
}

.msg__avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.msg__body {
  position: relative;
  min-width: 0;
}

/* Пузырь сообщения */
.msg__bubble {
  padding: 8px 12px 6px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  max-width: 100%;
}

.msg--mine .msg__bubble {
  background: rgba(124, 58, 237, 0.15);
  border-color: rgba(124, 58, 237, 0.3);
}

/* Переслано */
.msg__forwarded {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 4px;
  font-size: 10.5px;
  color: var(--text-muted);
  font-weight: 700;
}

.msg__forwarded b {
  color: var(--accent-light);
  font-weight: 800;
}

.msg__forwarded svg {
  opacity: 0.7;
}

/* Цитата ответа */
.msg__reply {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 6px 10px;
  margin-bottom: 5px;
  background: rgba(124, 58, 237, 0.08);
  border-left: 2px solid var(--accent);
  border-radius: 6px;
  cursor: pointer;
  max-width: 100%;
  overflow: hidden;
}

.msg__reply:hover {
  background: rgba(124, 58, 237, 0.14);
}

.msg__reply-author {
  font-size: 10.5px;
  font-weight: 800;
  color: var(--accent-light);
}

.msg__reply-body {
  font-size: 11.5px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Контент: текст + мета справа снизу */
.msg__content {
  display: block;
}

.msg__text {
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--text);
  white-space: pre-wrap;
  word-break: break-word;
}

.msg__meta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  float: right;
  margin: 8px -4px -2px 8px;
  position: relative;
  top: 3px;
}

.msg__time {
  font-size: 10.5px;
  color: var(--text-muted);
  font-weight: 600;
  white-space: nowrap;
  line-height: 1;
}

.msg--mine .msg__time {
  color: rgba(167, 139, 250, 0.8);
}

/* ============================================
   Галочки
   ============================================ */

.msg__read-status {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 11px;
  color: rgba(167, 139, 250, 0.7);
  transition: color 0.25s ease;
  flex-shrink: 0;
}

.msg__check {
  position: absolute;
  top: 50%;
  left: 50%;
  transition:
      transform 0.22s cubic-bezier(0.4, 0, 0.2, 1),
      opacity 0.2s ease,
      color 0.25s ease;
}

.msg__check--first {
  opacity: 1;
  transform: translate(-50%, -50%);
}

.msg__check--second {
  opacity: 0;
  transform: translate(calc(-50% - 5px), -50%) scale(0.8);
}

.msg__read-status--read {
  color: #22c55e;
}

.msg__read-status--full {
  color: #22c55e;
}

.msg__read-status--full .msg__check--first {
  transform: translate(calc(-50% - 4px), -50%);
}

.msg__read-status--full .msg__check--second {
  opacity: 1;
  transform: translate(calc(-50% + 4px), -50%) scale(1);
}

/* Подсветка при скролле к сообщению */
.msg--highlight .msg__bubble {
  animation: msgHighlight 1.2s ease;
}

@keyframes msgHighlight {
  0% { background: rgba(124, 58, 237, 0.3); }
  100% { background: #0d0d14; }
}

.msg--mine.msg--highlight .msg__bubble {
  animation: msgHighlightMine 1.2s ease;
}

@keyframes msgHighlightMine {
  0% { background: rgba(124, 58, 237, 0.35); }
  100% { background: rgba(124, 58, 237, 0.15); }
}

/* Действия с сообщением */
.msg__actions {
  display: flex;
  gap: 4px;
  align-self: center;
  opacity: 0;
  transition: opacity 0.15s;
}

.msg:hover .msg__actions {
  opacity: 1;
}

.msg__action {
  width: 26px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 7px;
  cursor: pointer;
  transition: all 0.15s;
}

.msg__action:hover {
  color: var(--text);
  border-color: var(--accent);
  background: rgba(124, 58, 237, 0.1);
}

/* ============================================
   REPLY BAR
   ============================================ */

.chat__reply-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  margin-bottom: 8px;
  background: rgba(124, 58, 237, 0.08);
  border-left: 3px solid var(--accent);
  border-radius: 8px;
}

.chat__reply-bar-icon {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.15);
  border-radius: 7px;
}

.chat__reply-bar-body {
  flex: 1;
  min-width: 0;
}

.chat__reply-bar-author {
  font-size: 11px;
  font-weight: 800;
  color: var(--accent-light);
  margin-bottom: 1px;
}

.chat__reply-bar-text {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat__reply-bar-close {
  width: 26px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.chat__reply-bar-close:hover {
  color: #e5e7eb;
  background: rgba(255, 255, 255, 0.06);
}

/* ============================================
   FOOTER
   ============================================ */

.chat__footer {
  padding: 12px 18px 16px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}

.chat__error {
  padding: 8px 12px;
  margin-bottom: 8px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 12px;
}

.chat__form {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.chat__input {
  flex: 1;
  min-height: 40px;
  max-height: 140px;
  padding: 10px 14px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  font: inherit;
  font-size: 13.5px;
  line-height: 1.4;
  resize: none;
  outline: none;
  transition: border-color 0.15s;
}

.chat__input:focus {
  border-color: var(--accent);
}

.chat__send {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
}

.chat__send:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.chat__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ============================================
   SEARCH DROPDOWN
   ============================================ */

.search-dropdown {
  position: fixed;
  width: 340px;
  max-width: calc(100vw - 40px);
  background: #16161f;
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow:
      0 20px 50px -20px rgba(0, 0, 0, 0.8),
      0 2px 6px rgba(0, 0, 0, 0.3);
  z-index: 4000;
  overflow: hidden;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
}

.search-input-wrap svg {
  position: absolute;
  left: 22px;
  color: var(--text-muted);
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 8px 10px 8px 30px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 8px;
  font: inherit;
  font-size: 13px;
  outline: none;
  transition: border-color 0.15s;
}

.search-input:focus {
  border-color: var(--accent);
}

.search-body {
  max-height: 400px;
  overflow-y: auto;
  padding: 6px;
}

.search-hint {
  padding: 24px 16px;
  text-align: center;
  color: var(--text-muted);
  font-size: 12.5px;
}

.search-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 4px 0;
}

.search-section + .search-section {
  border-top: 1px solid var(--border);
  margin-top: 4px;
  padding-top: 8px;
}

.search-section__title {
  padding: 6px 10px 4px;
  font-size: 10px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.search-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 10px;
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}

.search-item:hover {
  background: rgba(255, 255, 255, 0.04);
}

.search-item__avatar {
  position: relative;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 9px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
}

.search-item__avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.search-item__body {
  flex: 1;
  min-width: 0;
}

.search-item__name {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.search-item__tag {
  color: var(--accent-light);
  font-weight: 800;
}

.search-item__sub {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.search-item__sub b {
  font-weight: 800;
}

/* ============================================
   FORWARD MODAL
   ============================================ */

.forward-modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(6, 6, 10, 0.72);
  backdrop-filter: blur(8px);
}

.forward-modal {
  width: 100%;
  max-width: 420px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  background: #16161f;
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.7);
}

.forward-modal__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
}

.forward-modal__head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
}

.forward-modal__close {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 7px;
  cursor: pointer;
}

.forward-modal__close:hover {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text);
}

.forward-modal__preview {
  padding: 10px 16px;
  background: rgba(124, 58, 237, 0.06);
  border-bottom: 1px solid var(--border);
  border-left: 3px solid var(--accent);
}

.forward-modal__preview-author {
  font-size: 11px;
  font-weight: 800;
  color: var(--accent-light);
  margin-bottom: 2px;
}

.forward-modal__preview-body {
  font-size: 12.5px;
  color: var(--text-dim);
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.forward-modal__search {
  position: relative;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
}

.forward-modal__search svg {
  position: absolute;
  left: 24px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  pointer-events: none;
}

.forward-modal__input {
  width: 100%;
  padding: 9px 12px 9px 32px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 8px;
  font: inherit;
  font-size: 13px;
  outline: none;
}

.forward-modal__input:focus {
  border-color: var(--accent);
}

.forward-modal__body {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.forward-modal__hint {
  padding: 24px 16px;
  text-align: center;
  color: var(--text-muted);
  font-size: 12.5px;
}

.forward-modal__section {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.forward-modal__section + .forward-modal__section {
  border-top: 1px solid var(--border);
  margin-top: 6px;
  padding-top: 8px;
}

.forward-modal__section-title {
  padding: 6px 8px 4px;
  font-size: 10px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.forward-modal__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}

.forward-modal__item:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.04);
}

.forward-modal__item:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.forward-modal__item-avatar {
  position: relative;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 9px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
}

.forward-modal__item-avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.forward-modal__item-body {
  flex: 1;
  min-width: 0;
}

.forward-modal__item-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.forward-modal__item-tag {
  color: var(--accent-light);
  font-weight: 800;
}

.forward-modal__item-sub {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.forward-modal__item-sub b {
  font-weight: 800;
}

/* Transition */

.search-fade-enter-active,
.search-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.search-fade-enter-from,
.search-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* ============================================
   MOBILE
   ============================================ */

@media (max-width: 900px) {
  .messages-page {
    grid-template-columns: 1fr;
    height: calc(100vh - var(--header-height) - 24px);
    margin: 12px auto;
    width: calc(100% - 24px);
  }

  .messages-page--chat-open .conversations {
    display: none;
  }

  .messages-page:not(.messages-page--chat-open) .chat {
    display: none;
  }

  .chat__back {
    display: inline-flex;
  }

  .search-dropdown {
    left: 12px !important;
    right: 12px !important;
    width: auto;
    max-width: none;
  }

  .msg {
    max-width: 92%;
  }

  .msg__actions {
    opacity: 1;
  }
}
</style>