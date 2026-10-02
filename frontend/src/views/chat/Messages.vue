<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { chatApi } from '@/services/chat/chat.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { useRealtimeMessages } from '@/composables/chat/useRealtimeMessages.js'
import UserName from '@/components/players/UserName.vue'
import ChatAttachmentsInput from '@/components/chat/ChatAttachmentsInput.vue'
import { userLink } from '@/utils/links.js'

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

const otherParticipantsCount = computed(() => {
  if (!activeConversation.value) return 0
  return Math.max(0, (activeConversation.value.users || []).length - 1)
})

const directPartner = computed(() => {
  if (!activeConversation.value) return null
  if (activeConversation.value.type === 'clan_message') return null
  return (activeConversation.value.users || []).find(u => u.id !== auth.user?.id) || null
})

function goToPlayer(userId) {
  if (!userId) return
  // userLink без ника ведёт на /players/{id}; ник передаём, когда известен
  router.push(userLink({ id: userId, username: username ?? null }))
}

// Курсорная пагинация истории
const oldestId = ref(null)
const hasMoreHistory = ref(false)
const loadingOlder = ref(false)

// Вложения к следующему сообщению
const pendingAttachments = ref([])
const previewImage = ref(null)

// Сколько сообщений рендерим одновременно (виртуальный скролл)
const RENDER_WINDOW = 80
const renderLimit = ref(RENDER_WINDOW)

const visibleMessages = computed(() =>
    messages.value.slice(Math.max(0, messages.value.length - renderLimit.value))
)

const hiddenCount = computed(() => Math.max(0, messages.value.length - visibleMessages.value.length))

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
    const data = await chatApi.show(id, { limit: 40 })

    activeConversation.value = data.conversation
    messages.value = data.messages ?? []

    oldestId.value = data.oldest_id ?? messages.value[0]?.id ?? null
    hasMoreHistory.value = Boolean(data.has_more)
    renderLimit.value = RENDER_WINDOW

    const idx = conversations.value.findIndex(c => c.id === data.conversation.id)
    if (idx !== -1) {
      conversations.value[idx].unread_count = 0
    } else {
      conversations.value.unshift({
        ...data.conversation,
        unread_count: 0,
      })
    }

    loadingChat.value = false
    await scrollToBottom()
  } catch (e) {
    error.value = e.message || 'Не удалось открыть диалог'
  } finally {
    loadingChat.value = false
  }
}

/**
 * Подгрузить предыдущую страницу истории (скролл вверх).
 * Сохраняем позицию: запоминаем высоту до вставки и компенсируем после.
 */
async function loadOlder() {
  if (!activeConversation.value || !hasMoreHistory.value || loadingOlder.value) return

  loadingOlder.value = true

  const el = messagesEl.value
  const heightBefore = el?.scrollHeight ?? 0
  const topBefore = el?.scrollTop ?? 0

  try {
    const data = await chatApi.show(activeConversation.value.id, {
      beforeId: oldestId.value,
      limit: 40,
      markRead: false,
    })

    const older = data.messages ?? []

    if (older.length) {
      messages.value = [...older, ...messages.value]
      oldestId.value = data.oldest_id ?? older[0]?.id ?? oldestId.value

      // Показываем все загруженные, окно рендера расширяем
      renderLimit.value += older.length
    }

    hasMoreHistory.value = Boolean(data.has_more)
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить историю'
  } finally {
    loadingOlder.value = false

    await nextTick()

    if (el) {
      // Возвращаем пользователя к тому же сообщению
      el.scrollTop = topBefore + (el.scrollHeight - heightBefore)
    }
  }
}

/** Автоподгрузка при приближении к верху. */
function onMessagesScroll() {
  const el = messagesEl.value
  if (!el) return

  // Показать скрытые ранее сообщения
  if (renderLimit.value < messages.value.length && el.scrollTop < 400) {
    renderLimit.value = Math.min(messages.value.length, renderLimit.value + RENDER_WINDOW)
  }

  if (el.scrollTop < 160) {
    loadOlder()
  }
}

async function sendMessage() {
  const text = body.value.trim()
  const attachmentIds = pendingAttachments.value.map((a) => a.id)

  if ((!text && !attachmentIds.length) || !activeConversation.value) return

  const replyId = replyTo.value?.id ?? null
  const attachmentsBackup = [...pendingAttachments.value]

  body.value = ''
  replyTo.value = null
  pendingAttachments.value = []
  sending.value = true

  try {
    const data = await chatApi.send(activeConversation.value.id, text, replyId, attachmentIds)
    messages.value.push(data.message)
    renderLimit.value = RENDER_WINDOW

    const idx = conversations.value.findIndex(c => c.id === activeConversation.value.id)
    if (idx !== -1) {
      conversations.value[idx].last_message = data.message
      conversations.value[idx].last_message_at = data.message.created_at
      const [c] = conversations.value.splice(idx, 1)
      conversations.value.unshift(c)
    }

    await scrollToBottom(true)
  } catch (e) {
    error.value = e.message || 'Не удалось отправить'
    body.value = text
    pendingAttachments.value = attachmentsBackup
    replyTo.value = replyId ? messages.value.find(m => m.id === replyId) : null
  } finally {
    sending.value = false
  }
}

/* ---------- Редактирование и удаление своих сообщений ---------- */

const editingId = ref(null)
const editingBody = ref('')

function startEdit(message) {
  editingId.value = message.id
  editingBody.value = message.body || ''
}

function cancelEdit() {
  editingId.value = null
  editingBody.value = ''
}

async function saveEdit(message) {
  try {
    const data = await chatApi.updateMessage(message.id, editingBody.value)

    const idx = messages.value.findIndex((m) => m.id === message.id)
    if (idx !== -1) messages.value[idx] = { ...messages.value[idx], ...data.message }

    cancelEdit()
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить изменения'
  }
}

async function removeMessage(message) {
  if (!await confirmDialog('Удалить сообщение?')) return

  try {
    await chatApi.deleteMessage(message.id)
    messages.value = messages.value.filter((m) => m.id !== message.id)
  } catch (e) {
    error.value = e.message || 'Не удалось удалить сообщение'
  }
}

async function scrollToBottom(smooth = false) {
  await nextTick()
  if (!messagesEl.value) return

  await nextTick()
  requestAnimationFrame(() => {
    if (!messagesEl.value) return
    messagesEl.value.scrollTo({
      top: messagesEl.value.scrollHeight,
      behavior: smooth ? 'smooth' : 'auto',
    })
  })
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
// LONG-PRESS ДЕЙСТВИЯ (мобилка)
// ============================================

const actionSheetOpen = ref(false)
const actionSheetMessage = ref(null)

let longPressTimer = null
let touchStartPos = { x: 0, y: 0 }
let touchMoved = false

function onMsgTouchStart(e, m) {
  if (!e.touches || !e.touches.length) return

  touchMoved = false
  touchStartPos = {
    x: e.touches[0].clientX,
    y: e.touches[0].clientY,
  }

  clearTimeout(longPressTimer)
  longPressTimer = setTimeout(() => {
    if (touchMoved) return
    if (navigator.vibrate) navigator.vibrate(15)
    actionSheetMessage.value = m
    actionSheetOpen.value = true
  }, 450)
}

function onMsgTouchMove(e) {
  if (!e.touches || !e.touches.length) return

  const dx = Math.abs(e.touches[0].clientX - touchStartPos.x)
  const dy = Math.abs(e.touches[0].clientY - touchStartPos.y)

  if (dx > 10 || dy > 10) {
    touchMoved = true
    clearTimeout(longPressTimer)
  }
}

function onMsgTouchEnd() {
  clearTimeout(longPressTimer)
}

function closeActionSheet() {
  actionSheetOpen.value = false
  actionSheetMessage.value = null
}

function actionReply() {
  if (!actionSheetMessage.value) return
  setReply(actionSheetMessage.value)
  closeActionSheet()
}

function actionForward() {
  if (!actionSheetMessage.value) return
  const m = actionSheetMessage.value
  closeActionSheet()
  setTimeout(() => openForward(m), 80)
}

// ============================================
// ДРОПДАУН ПОИСКА
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

watch(() => messages.value.length, async (newLen, oldLen) => {
  if (newLen === 0 || newLen <= oldLen) return

  const el = messagesEl.value
  if (!el) return

  const wasNearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 150

  await nextTick()

  if (wasNearBottom) {
    requestAnimationFrame(() => {
      if (!messagesEl.value) return
      messagesEl.value.scrollTo({
        top: messagesEl.value.scrollHeight,
        behavior: 'smooth',
      })
    })
  }
})

watch(latestMessage, async (m) => {
  if (!m) return

  if (activeConversation.value && m.conversation_id === activeConversation.value.id) {
    messages.value.push(m)
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
  clearTimeout(longPressTimer)
})
</script>

<template>
  <div class="messages-page" :class="{ 'messages-page--chat-open': showChatOnMobile }">
    <!-- СПИСОК ДИАЛОГОВ -->
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

      <ul v-else class="conversations__list scroll-thin">
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

    <!-- ОКНО ЧАТА -->
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

        <div
            ref="messagesEl"
            class="chat__messages scroll-thin"
            @scroll.passive="onMessagesScroll"
        >
          <div v-if="loadingChat" class="chat__loading">Загрузка...</div>

          <template v-else-if="messages.length">
            <button
                v-if="hasMoreHistory"
                class="chat__load-older"
                type="button"
                :disabled="loadingOlder"
                @click="loadOlder"
            >
              {{ loadingOlder ? 'Загружаем…' : 'Показать более ранние сообщения' }}
            </button>

            <button
                v-if="hiddenCount"
                class="chat__load-older"
                type="button"
                @click="renderLimit += RENDER_WINDOW"
            >
              Показать ещё {{ Math.min(hiddenCount, RENDER_WINDOW) }} из {{ hiddenCount }} скрытых
            </button>

            <div
                v-for="m in visibleMessages"
                :key="m.id"
                :data-message-id="m.id"
                class="msg"
                :class="{ 'msg--mine': m.user?.id === auth.user?.id }"
                @touchstart.passive="onMsgTouchStart($event, m)"
                @touchmove.passive="onMsgTouchMove"
                @touchend="onMsgTouchEnd"
                @touchcancel="onMsgTouchEnd"
                @contextmenu.prevent
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
                  <div v-if="m.forwarded_from" class="msg__forwarded">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M15 17l5-5-5-5" />
                      <path d="M4 18v-2a4 4 0 0 1 4-4h12" />
                    </svg>
                    Переслано от <b>{{ m.forwarded_from.username }}</b>
                  </div>

                  <div
                      v-if="m.reply_to"
                      class="msg__reply"
                      @click="scrollToMessage(m.reply_to.id)"
                  >
                    <div class="msg__reply-author">{{ m.reply_to.user?.username }}</div>
                    <div class="msg__reply-body">{{ m.reply_to.body }}</div>
                  </div>

                  <!-- Вложения: картинки превью, файлы плашкой -->
                  <div v-if="m.attachments?.length" class="msg__attachments">
                    <template v-for="file in m.attachments" :key="file.id">
                      <button
                          v-if="file.is_image"
                          class="msg__image"
                          type="button"
                          @click="previewImage = file"
                      >
                        <img :src="file.url" :alt="file.name" loading="lazy">
                      </button>
                      <a
                          v-else
                          class="msg__file"
                          :href="file.url"
                          target="_blank"
                          rel="noopener"
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                          <path d="M14 2v6h6" />
                        </svg>
                        <span class="msg__file-name">{{ file.name }}</span>
                        <span class="msg__file-size">{{ file.size }}</span>
                      </a>
                    </template>
                  </div>

                  <!-- Режим редактирования -->
                  <div v-if="editingId === m.id" class="msg__edit">
                    <textarea v-model="editingBody" class="msg__edit-input" rows="3" />
                    <div class="msg__edit-actions">
                      <button type="button" class="msg__edit-btn" @click="cancelEdit">Отмена</button>
                      <button type="button" class="msg__edit-btn msg__edit-btn--primary" @click="saveEdit(m)">Сохранить</button>
                    </div>
                  </div>

                  <div v-else class="msg__content">
                    <span class="msg__text">{{ m.body }}</span>
                    <span v-if="m.edited_at" class="msg__edited">изменено</span>

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

              <div class="msg__actions">
                <button class="msg__action" type="button" title="Ответить" @click="setReply(m)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 17l-5-5 5-5" />
                    <path d="M20 18v-2a4 4 0 0 0-4-4H4" />
                  </svg>
                </button>
                <button class="msg__action" type="button" title="Переслать" @click="openForward(m)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 17l5-5-5-5" />
                    <path d="M4 18v-2a4 4 0 0 1 4-4h12" />
                  </svg>
                </button>

                <template v-if="m.user?.id === auth.user?.id">
                  <button class="msg__action" type="button" title="Изменить" @click="startEdit(m)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 20h9" />
                      <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />
                    </svg>
                  </button>
                  <button class="msg__action msg__action--danger" type="button" title="Удалить" @click="removeMessage(m)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18M8 6V4h8v2M6 6l1 16h10l1-16" />
                    </svg>
                  </button>
                </template>
              </div>
            </div>
          </template>

          <div v-else class="chat__empty-mini">
            Сообщений ещё нет. Напиши первым.
          </div>
        </div>

        <!-- Полноэкранный просмотр картинки -->
        <div v-if="previewImage" class="chat__image-preview" @click.self="previewImage = null">
          <img :src="previewImage.url" :alt="previewImage.name">
          <button class="chat__image-preview-close" type="button" @click="previewImage = null">×</button>
        </div>

        <footer class="chat__footer">
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
            <button class="chat__reply-bar-close" type="button" aria-label="Отменить ответ" @click="clearReply">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div v-if="error" class="chat__error">{{ error }}</div>

          <div v-if="activeConversation" class="chat__attach-row">
            <ChatAttachmentsInput v-model="pendingAttachments" :max="10" />
          </div>

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
                :disabled="sending || (!body.trim() && !pendingAttachments.length)"
                :title="pendingAttachments.length && !body.trim() ? 'Отправить файлы' : 'Отправить'"
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

    <!-- ДРОПДАУН ПОИСКА -->
    <Teleport to="body">
      <Transition name="search-fade">
        <div
            v-if="searchOpen"
            class="search-dropdown"
            :style="{ top: dropdownPos.top + 'px', right: dropdownPos.right + 'px' }"
            @click.stop
        >
          <div class="search-input-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="7" />
              <path d="m21 21-4.3-4.3" />
            </svg>
            <input v-model="searchQuery" type="text" class="search-input" placeholder="Поиск: игрок или клан" autofocus />
          </div>

          <div class="search-body scroll-thin">
            <div v-if="searchLoading" class="search-hint">Поиск...</div>
            <template v-else>
              <div v-if="searchResults.users.length" class="search-section">
                <div class="search-section__title">
                  {{ searchQuery.trim() === '' ? 'Друзья' : 'Игроки' }}
                </div>
                <button v-for="u in searchResults.users" :key="u.id" type="button" class="search-item" @click="startWithUser(u.id)">
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
                      Тир <b :style="{ color: tierColors[u.tier] || '#6b7280' }">{{ u.tier ?? '—' }}</b>
                    </div>
                  </div>
                </button>
              </div>

              <div v-if="searchResults.clans.length" class="search-section">
                <div class="search-section__title">Кланы</div>
                <button v-for="c in searchResults.clans" :key="c.id" type="button" class="search-item" @click="startWithClan(c.id)">
                  <div class="search-item__avatar" :style="{ background: c.banner_color || '#7c3aed' }">
                    <img v-if="c.avatar_url" :src="c.avatar_url" :alt="c.name" />
                    <template v-else>{{ (c.tag || 'C').charAt(0) }}</template>
                  </div>
                  <div class="search-item__body">
                    <div class="search-item__name">
                      <span class="search-item__tag">[{{ c.tag }}]</span>
                      {{ c.name }}
                    </div>
                    <div class="search-item__sub">Написать клану (лидеру и офицерам)</div>
                  </div>
                </button>
              </div>

              <div v-if="!searchResults.users.length && !searchResults.clans.length" class="search-hint">
                {{ searchQuery.trim() === '' ? 'У тебя пока нет друзей' : 'Ничего не найдено' }}
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- МОДАЛКА ПЕРЕСЫЛКИ -->
    <Teleport to="body">
      <Transition name="search-fade">
        <div v-if="forwardOpen" class="forward-modal-bg" @click.self="closeForward">
          <div class="forward-modal">
            <header class="forward-modal__head">
              <h3>Переслать сообщение</h3>
              <button class="forward-modal__close" type="button" aria-label="Закрыть" @click="closeForward">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </header>

            <div v-if="forwardMessage" class="forward-modal__preview">
              <div class="forward-modal__preview-author">{{ forwardMessage.user?.username }}</div>
              <div class="forward-modal__preview-body">{{ forwardMessage.body?.slice(0, 140) }}</div>
            </div>

            <div class="forward-modal__search">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.3-4.3" />
              </svg>
              <input v-model="forwardSearch" type="text" class="forward-modal__input" placeholder="Игрок или клан..." />
            </div>

            <div class="forward-modal__body scroll-thin">
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
                    <div class="forward-modal__item-avatar" :style="{ background: c.banner_color || '#7c3aed' }">
                      <img v-if="c.avatar_url" :src="c.avatar_url" :alt="c.name" />
                      <template v-else>{{ (c.tag || 'C').charAt(0) }}</template>
                    </div>
                    <div class="forward-modal__item-body">
                      <div class="forward-modal__item-name">
                        <span class="forward-modal__item-tag">[{{ c.tag }}]</span>
                        {{ c.name }}
                      </div>
                      <div class="forward-modal__item-sub">Отправить лидеру и офицерам</div>
                    </div>
                  </button>
                </div>

                <div v-if="!forwardResults.users.length && !forwardResults.clans.length" class="forward-modal__hint">
                  {{ forwardSearch.trim() === '' ? 'У тебя пока нет друзей' : 'Ничего не найдено' }}
                </div>
              </template>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- BOTTOM SHEET — действия с сообщением (мобилка) -->
    <Teleport to="body">
      <Transition name="sheet-fade">
        <div
            v-if="actionSheetOpen"
            class="action-sheet-bg"
            @click.self="closeActionSheet"
        >
          <div class="action-sheet">
            <div v-if="actionSheetMessage" class="action-sheet__preview">
              <div class="action-sheet__preview-author">
                {{ actionSheetMessage.user?.username }}
              </div>
              <div class="action-sheet__preview-body">
                {{ actionSheetMessage.body?.slice(0, 120) }}
              </div>
            </div>

            <button class="action-sheet__item" type="button" @click="actionReply">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 17l-5-5 5-5" />
                <path d="M20 18v-2a4 4 0 0 0-4-4H4" />
              </svg>
              Ответить
            </button>

            <button class="action-sheet__item" type="button" @click="actionForward">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 17l5-5-5-5" />
                <path d="M4 18v-2a4 4 0 0 1 4-4h12" />
              </svg>
              Переслать
            </button>

            <button class="action-sheet__item action-sheet__item--cancel" type="button" @click="closeActionSheet">
              Отмена
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
@import "@/views/chat/Messages.css";
</style>
