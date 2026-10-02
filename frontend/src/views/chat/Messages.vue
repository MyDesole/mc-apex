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
// СОСТОЯНИЕ
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

  return (
      (activeConversation.value.users || []).find(
          user => user.id !== auth.user?.id,
      ) || null
  )
})

function goToPlayer(user) {
  if (!user?.id) return
  router.push(userLink({ id: user.id }))
}

// ============================================
// ИСТОРИЯ
// ============================================

const oldestId = ref(null)
const hasMoreHistory = ref(false)
const loadingOlder = ref(false)

const RENDER_WINDOW = 80
const renderLimit = ref(RENDER_WINDOW)

const visibleMessages = computed(() =>
    messages.value.slice(
        Math.max(0, messages.value.length - renderLimit.value),
    ),
)

const hiddenCount = computed(() =>
    Math.max(0, messages.value.length - visibleMessages.value.length),
)

// ============================================
// ВЛОЖЕНИЯ
// ============================================

const pendingAttachments = ref([])
const previewImage = ref(null)

// ============================================
// ЗАГРУЗКА ДИАЛОГОВ
// ============================================

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

    oldestId.value =
        data.oldest_id ??
        messages.value[0]?.id ??
        null

    hasMoreHistory.value = Boolean(data.has_more)
    renderLimit.value = RENDER_WINDOW

    const idx = conversations.value.findIndex(
        c => c.id === data.conversation.id,
    )

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

// ============================================
// ПАГИНАЦИЯ
// ============================================

async function loadOlder() {
  if (
      !activeConversation.value ||
      !hasMoreHistory.value ||
      loadingOlder.value
  ) {
    return
  }

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
      oldestId.value =
          data.oldest_id ??
          older[0]?.id ??
          oldestId.value

      renderLimit.value += older.length
    }

    hasMoreHistory.value = Boolean(data.has_more)
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить историю'
  } finally {
    loadingOlder.value = false

    await nextTick()

    if (el) {
      el.scrollTop =
          topBefore +
          (el.scrollHeight - heightBefore)
    }
  }
}

function onMessagesScroll() {
  const el = messagesEl.value
  if (!el) return

  if (
      renderLimit.value < messages.value.length &&
      el.scrollTop < 400
  ) {
    renderLimit.value = Math.min(
        messages.value.length,
        renderLimit.value + RENDER_WINDOW,
    )
  }

  if (el.scrollTop < 160) {
    loadOlder()
  }
}

// ============================================
// ОТПРАВКА
// ============================================

async function sendMessage() {
  const text = body.value.trim()
  const attachmentIds = pendingAttachments.value.map(a => a.id)

  if (
      (!text && !attachmentIds.length) ||
      !activeConversation.value
  ) {
    return
  }

  const replyId = replyTo.value?.id ?? null
  const attachmentsBackup = [...pendingAttachments.value]

  body.value = ''
  replyTo.value = null
  pendingAttachments.value = []
  sending.value = true

  // Поле возвращается к исходной высоте: текст ушёл, лишние строки не нужны
  nextTick(autoGrow)

  try {
    const data = await chatApi.send(
        activeConversation.value.id,
        text,
        replyId,
        attachmentIds,
    )

    messages.value.push(data.message)
    renderLimit.value = RENDER_WINDOW

    const idx = conversations.value.findIndex(
        c => c.id === activeConversation.value.id,
    )

    if (idx !== -1) {
      conversations.value[idx].last_message = data.message
      conversations.value[idx].last_message_at =
          data.message.created_at

      const [conversation] =
          conversations.value.splice(idx, 1)

      conversations.value.unshift(conversation)
    }

    await scrollToBottom(true)
  } catch (e) {
    error.value = e.message || 'Не удалось отправить'

    body.value = text
    pendingAttachments.value = attachmentsBackup
    replyTo.value = replyId
        ? messages.value.find(m => m.id === replyId)
        : null
  } finally {
    sending.value = false
  }
}

// ============================================
// РЕДАКТИРОВАНИЕ / УДАЛЕНИЕ
// ============================================

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
    const data = await chatApi.updateMessage(
        message.id,
        editingBody.value,
    )

    const idx = messages.value.findIndex(
        m => m.id === message.id,
    )

    if (idx !== -1) {
      messages.value[idx] = {
        ...messages.value[idx],
        ...data.message,
      }
    }

    cancelEdit()
  } catch (e) {
    error.value =
        e.message || 'Не удалось сохранить изменения'
  }
}

async function removeMessage(message) {
  if (!await confirmDialog('Удалить сообщение?')) return

  try {
    await chatApi.deleteMessage(message.id)

    messages.value = messages.value.filter(
        m => m.id !== message.id,
    )
  } catch (e) {
    error.value =
        e.message || 'Не удалось удалить сообщение'
  }
}

// ============================================
// СКРОЛЛ
// ============================================

async function scrollToBottom(smooth = false) {
  await nextTick()
  await nextTick()

  if (!messagesEl.value) return

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

  const el = document.querySelector(
      `[data-message-id="${id}"]`,
  )

  if (!el) return

  el.scrollIntoView({
    behavior: 'smooth',
    block: 'center',
  })

  el.classList.add('msg--highlight')

  setTimeout(() => {
    el.classList.remove('msg--highlight')
  }, 1200)
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
// ПОЛЕ ВВОДА
// ============================================

/**
 * Подгоняет высоту поля под текст.
 *
 * Сначала высота сбрасывается в auto: иначе scrollHeight не уменьшается
 * при удалении строк и поле не сжимается обратно. Растёт до предела,
 * заданного в CSS (max-height), дальше включается прокрутка.
 */
function autoGrow() {
  const el = inputEl.value

  if (!el) return

  el.style.height = 'auto'
  el.style.height = `${el.scrollHeight}px`
}

// ============================================
// ОТВЕТ
// ============================================

const replyTo = ref(null)

function setReply(message) {
  replyTo.value = message

  nextTick(() => {
    inputEl.value?.focus()
  })
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
const forwardResults = ref({
  users: [],
  clans: [],
})
const forwardLoading = ref(false)
const forwardSending = ref(false)

let forwardTimer = null

function openForward(message) {
  forwardMessage.value = message
  forwardOpen.value = true
  forwardSearch.value = ''
  forwardResults.value = {
    users: [],
    clans: [],
  }

  runForwardSearch('')
}

function closeForward() {
  forwardOpen.value = false
  forwardMessage.value = null
  forwardSearch.value = ''
  forwardResults.value = {
    users: [],
    clans: [],
  }

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
  } catch {
    forwardResults.value = {
      users: [],
      clans: [],
    }
  } finally {
    forwardLoading.value = false
  }
}

watch(forwardSearch, q => {
  clearTimeout(forwardTimer)

  const query = q.trim()

  if (query === '') {
    runForwardSearch('')
    return
  }

  if (query.length < 2) return

  forwardTimer = setTimeout(
      () => runForwardSearch(query),
      300,
  )
})

async function confirmForwardUser(userId) {
  if (!forwardMessage.value) return

  forwardSending.value = true

  try {
    const direct = await chatApi.startDirect(userId)
    const conversationId = direct.conversation.id

    await chatApi.forward(
        forwardMessage.value.id,
        conversationId,
    )

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

    await chatApi.forward(
        forwardMessage.value.id,
        conversationId,
    )

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
// ПРОЧИТАНО
// ============================================

function isMessageRead(message) {
  return (message.reads || []).some(
      read => read.user_id !== auth.user?.id,
  )
}

function isMessageFullyRead(message) {
  if (!otherParticipantsCount.value) return false

  const readers = (message.reads || []).filter(
      read => read.user_id !== auth.user?.id,
  ).length

  return readers >= otherParticipantsCount.value
}

// ============================================
// LONG PRESS
// ============================================

const actionSheetOpen = ref(false)
const actionSheetMessage = ref(null)

let longPressTimer = null
let touchStartPos = { x: 0, y: 0 }
let touchMoved = false

function onMsgTouchStart(e, message) {
  if (!e.touches?.length) return

  touchMoved = false

  touchStartPos = {
    x: e.touches[0].clientX,
    y: e.touches[0].clientY,
  }

  clearTimeout(longPressTimer)

  longPressTimer = setTimeout(() => {
    if (touchMoved) return

    if (navigator.vibrate) {
      navigator.vibrate(15)
    }

    actionSheetMessage.value = message
    actionSheetOpen.value = true
  }, 450)
}

function onMsgTouchMove(e) {
  if (!e.touches?.length) return

  const dx = Math.abs(
      e.touches[0].clientX - touchStartPos.x,
  )

  const dy = Math.abs(
      e.touches[0].clientY - touchStartPos.y,
  )

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

  const message = actionSheetMessage.value

  closeActionSheet()

  setTimeout(() => {
    openForward(message)
  }, 80)
}

// ============================================
// ПОИСК
// ============================================

const searchOpen = ref(false)
const searchQuery = ref('')
const searchResults = ref({
  users: [],
  clans: [],
})
const searchLoading = ref(false)

const searchWrapEl = ref(null)
const dropdownPos = ref({
  top: 0,
  right: 0,
})

let searchTimer = null

function computeDropdownPosition() {
  if (!searchWrapEl.value) return

  const rect =
      searchWrapEl.value.getBoundingClientRect()

  dropdownPos.value = {
    top: rect.bottom + 8,
    right:
        window.innerWidth -
        rect.right -
        8,
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
  } catch {
    searchResults.value = {
      users: [],
      clans: [],
    }
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

  searchResults.value = {
    users: [],
    clans: [],
  }

  searchLoading.value = false
}

watch(searchQuery, q => {
  clearTimeout(searchTimer)

  const query = q.trim()

  if (query === '') {
    runSearch('')
    return
  }

  if (query.length < 2) return

  searchTimer = setTimeout(
      () => runSearch(query),
      300,
  )
})

async function startWithUser(userId) {
  try {
    const data = await chatApi.startDirect(userId)

    closeSearch()

    router.push(
        `/messages/${data.conversation.id}`,
    )

    await loadConversations()
  } catch (e) {
    error.value =
        e.message || 'Не удалось открыть диалог'
  }
}

async function startWithClan(clanId) {
  try {
    const data = await chatApi.startClan(clanId)

    closeSearch()

    router.push(
        `/messages/${data.conversation.id}`,
    )

    await loadConversations()
  } catch (e) {
    error.value =
        e.message || 'Не удалось открыть чат с кланом'
  }
}

// ============================================
// WATCHERS
// ============================================

watch(
    () => route.params.id,
    id => {
      clearReply()
      closeForward()

      if (id) {
        openConversation(Number(id))
      } else {
        activeConversation.value = null
        messages.value = []
      }
    },
    { immediate: true },
)

watch(
    () => messages.value.length,
    async (newLen, oldLen) => {
      if (
          newLen === 0 ||
          newLen <= oldLen
      ) {
        return
      }

      const el = messagesEl.value
      if (!el) return

      const wasNearBottom =
          el.scrollHeight -
          el.scrollTop -
          el.clientHeight < 150

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
    },
)

watch(latestMessage, async message => {
  if (!message) return

  if (
      activeConversation.value &&
      message.conversation_id ===
      activeConversation.value.id
  ) {
    messages.value.push(message)

    chatApi
        .show(message.conversation_id)
        .catch(() => {})
  } else {
    const idx = conversations.value.findIndex(
        c => c.id === message.conversation_id,
    )

    if (idx !== -1) {
      conversations.value[idx].unread_count =
          (conversations.value[idx].unread_count || 0) + 1

      conversations.value[idx].last_message = message
      conversations.value[idx].last_message_at =
          message.created_at

      const [conversation] =
          conversations.value.splice(idx, 1)

      conversations.value.unshift(conversation)
    } else {
      loadConversations()
    }
  }
})

// ============================================
// LIFECYCLE
// ============================================

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

  document.addEventListener(
      'click',
      onClickOutsideSearch,
  )

  window.addEventListener(
      'resize',
      onResizeOrScroll,
  )

  window.addEventListener(
      'scroll',
      onResizeOrScroll,
      true,
  )
})

onUnmounted(() => {
  document.removeEventListener(
      'click',
      onClickOutsideSearch,
  )

  window.removeEventListener(
      'resize',
      onResizeOrScroll,
  )

  window.removeEventListener(
      'scroll',
      onResizeOrScroll,
      true,
  )

  clearTimeout(searchTimer)
  clearTimeout(forwardTimer)
  clearTimeout(longPressTimer)
})
</script>

<template>
  <div
      class="messages-page"
      :class="{
      'messages-page--chat-open': showChatOnMobile,
    }"
  >
    <!-- ==========================================
         СПИСОК ДИАЛОГОВ
         ========================================== -->

    <aside class="conversations">
      <header class="conversations__head">
        <div class="conversations__title">
          <span class="conversations__title-mark"></span>

          <div>
            <h1>Сообщения</h1>
            <span class="conversations__subtitle">
              Твои диалоги
            </span>
          </div>
        </div>

        <div
            ref="searchWrapEl"
            class="conversations__search-wrap"
        >
          <button
              class="conversations__new"
              :class="{
              'conversations__new--active':
                searchOpen,
            }"
              type="button"
              :title="
              searchOpen
                ? 'Закрыть'
                : 'Найти игрока или клан'
            "
              @click.stop="toggleSearch"
          >
            <svg
                v-if="!searchOpen"
                width="17"
                height="17"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M12 5v14M5 12h14" />
            </svg>

            <svg
                v-else
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.4"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>
        </div>
      </header>

      <div
          v-if="loadingList"
          class="conversations__empty"
      >
        <div class="empty-pulse"></div>
        <span>Загружаем диалоги...</span>
      </div>

      <div
          v-else-if="!conversations.length"
          class="conversations__empty"
      >
        <div class="empty-icon">
          <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path
                d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
            />
          </svg>
        </div>

        <strong>Пока тихо</strong>
        <span>
          Найди игрока или клан<br>
          через кнопку «+».
        </span>
      </div>

      <ul
          v-else
          class="conversations__list scroll-thin"
      >
        <li
            v-for="c in conversations"
            :key="c.id"
        >
          <RouterLink
              :to="`/messages/${c.id}`"
              class="conv"
              :class="{
              'conv--active':
                activeConversation?.id === c.id,
            }"
          >
            <div
                class="conv__avatar"
                :class="{
                'conv__avatar--clan':
                  c.type === 'clan_message',
              }"
            >
              <template
                  v-if="c.type === 'clan_message'"
              >
                <div
                    class="conv__clan"
                    :style="{
                    background:
                      c.clan?.banner_color ||
                      '#7c3aed',
                  }"
                >
                  <img
                      v-if="c.clan?.avatar_url"
                      :src="c.clan.avatar_url"
                      :alt="c.clan.name"
                  />

                  <template v-else>
                    {{
                      (c.clan?.tag || 'C')
                          .charAt(0)
                    }}
                  </template>
                </div>

                <span class="conv__online-dot"></span>
              </template>

              <template v-else>
                <template
                    v-for="u in (c.users || []).filter(
                    u => u.id !== auth.user?.id,
                  )"
                    :key="u.id"
                >
                  <img
                      v-if="u.avatar_url"
                      :src="u.avatar_url"
                      :alt="u.username"
                  />

                  <template v-else>
                    {{
                      (u.username || 'И')
                          .charAt(0)
                          .toUpperCase()
                    }}
                  </template>
                </template>
              </template>
            </div>

            <div class="conv__body">
              <div class="conv__name">
                <template
                    v-if="c.type === 'clan_message'"
                >
                  <span class="conv__clan-tag">
                    [{{ c.clan?.tag }}]
                  </span>

                  {{ c.clan?.name }}

                  <span class="conv__tag">
                    клан
                  </span>
                </template>

                <template v-else>
                  <template
                      v-for="u in (c.users || []).filter(
                      u => u.id !== auth.user?.id,
                    )"
                      :key="u.id"
                  >
                    <UserName
                        :user="u"
                        compact
                    />
                  </template>
                </template>
              </div>

              <div class="conv__preview">
                <template v-if="c.last_message">
                  <span class="conv__preview-author">
                    {{ c.last_message.user?.username }}:
                  </span>

                  {{ c.last_message.body }}
                </template>

                <template v-else>
                  Новый диалог
                </template>
              </div>
            </div>

            <div class="conv__meta">
              <span
                  v-if="c.last_message_at"
                  class="conv__time"
              >
                {{
                  new Date(
                      c.last_message_at,
                  ).toLocaleDateString(
                      'ru-RU',
                      {
                        day: '2-digit',
                        month: 'short',
                      },
                  )
                }}
              </span>

              <span
                  v-if="c.unread_count > 0"
                  class="conv__badge"
              >
                {{ c.unread_count }}
              </span>
            </div>
          </RouterLink>
        </li>
      </ul>
    </aside>

    <!-- ==========================================
         ЧАТ
         ========================================== -->

    <section
        class="chat"
        :class="{
        'chat--empty': !activeConversation,
      }"
    >
      <template v-if="!activeConversation">
        <div class="chat__empty">
          <div class="chat__empty-orb">
            <svg
                width="34"
                height="34"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path
                  d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
              />
            </svg>
          </div>

          <div class="chat__empty-title">
            Выбери диалог
          </div>

          <div class="chat__empty-subtitle">
            Все сообщения будут здесь
          </div>
        </div>
      </template>

      <template v-else>
        <!-- HEADER -->

        <header class="chat__head">
          <button
              class="chat__back"
              type="button"
              aria-label="Назад"
              @click="closeChat"
          >
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </button>

          <div
              v-if="directPartner"
              class="chat__head-avatar"
              :title="
              `Открыть профиль ${directPartner.username}`
            "
              @click="goToPlayer(directPartner)"
          >
            <div class="chat__head-avatar-box">
              <img
                  v-if="directPartner.avatar_url"
                  :src="directPartner.avatar_url"
                  :alt="directPartner.username"
              />

              <template v-else>
                {{
                  (directPartner.username || 'И')
                      .charAt(0)
                      .toUpperCase()
                }}
              </template>
            </div>

            <span class="chat__head-status"></span>
          </div>

          <div class="chat__head-body">
            <template
                v-if="
                activeConversation.type ===
                'clan_message'
              "
            >
              <div class="chat__head-name">
                <span class="chat__head-clan-tag">
                  [{{ activeConversation.clan?.tag }}]
                </span>

                {{ activeConversation.clan?.name }}
              </div>

              <div class="chat__head-sub">
                Обращение от
                {{ activeConversation.author?.username }}
              </div>
            </template>

            <template
                v-else-if="directPartner"
            >
              <div
                  class="
                  chat__head-name
                  chat__head-name--link
                "
                  :title="
                  `Открыть профиль ${directPartner.username}`
                "
                  @click="goToPlayer(directPartner)"
              >
                <UserName :user="directPartner" />
              </div>

              <div class="chat__head-sub">
                Личный диалог
              </div>
            </template>
          </div>

          <div class="chat__head-accent"></div>
        </header>

        <!-- MESSAGES -->

        <div
            ref="messagesEl"
            class="chat__messages scroll-thin"
            @scroll.passive="onMessagesScroll"
        >
          <div
              v-if="loadingChat"
              class="chat__loading"
          >
            <div class="loading-dots">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>

          <template
              v-else-if="messages.length"
          >
            <button
                v-if="hasMoreHistory"
                class="chat__load-older"
                type="button"
                :disabled="loadingOlder"
                @click="loadOlder"
            >
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M12 19V5" />
                <path d="m5 12 7-7 7 7" />
              </svg>

              {{
                loadingOlder
                    ? 'Загружаем…'
                    : 'Более ранние сообщения'
              }}
            </button>

            <button
                v-if="hiddenCount"
                class="chat__load-older chat__load-older--secondary"
                type="button"
                @click="
                renderLimit += RENDER_WINDOW
              "
            >
              Показать ещё
              {{
                Math.min(
                    hiddenCount,
                    RENDER_WINDOW,
                )
              }}
            </button>

            <div
                v-for="m in visibleMessages"
                :key="m.id"
                :data-message-id="m.id"
                class="msg"
                :class="{
                'msg--mine':
                  m.user?.id === auth.user?.id,
              }"
                @touchstart.passive="
                onMsgTouchStart($event, m)
              "
                @touchmove.passive="
                onMsgTouchMove
              "
                @touchend="onMsgTouchEnd"
                @touchcancel="onMsgTouchEnd"
                @contextmenu.prevent
            >
              <div
                  class="msg__avatar"
                  :title="
                  m.user
                    ? `Открыть профиль ${m.user.username}`
                    : ''
                "
                  @click="
                  m.user && goToPlayer(m.user)
                "
              >
                <img
                    v-if="m.user?.avatar_url"
                    :src="m.user.avatar_url"
                    :alt="m.user.username"
                />

                <template v-else>
                  {{
                    (m.user?.username || 'И')
                        .charAt(0)
                        .toUpperCase()
                  }}
                </template>
              </div>

              <div class="msg__body">
                <div class="msg__bubble">
                  <div
                      v-if="m.forwarded_from"
                      class="msg__forwarded"
                  >
                    <svg
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                      <path d="M15 17l5-5-5-5" />
                      <path
                          d="M4 18v-2a4 4 0 0 1 4-4h12"
                      />
                    </svg>

                    Переслано от
                    <b>
                      {{ m.forwarded_from.username }}
                    </b>
                  </div>

                  <div
                      v-if="m.reply_to"
                      class="msg__reply"
                      @click="
                      scrollToMessage(
                        m.reply_to.id,
                      )
                    "
                  >
                    <div class="msg__reply-author">
                      {{ m.reply_to.user?.username }}
                    </div>

                    <div class="msg__reply-body">
                      {{ m.reply_to.body }}
                    </div>
                  </div>

                  <div
                      v-if="m.attachments?.length"
                      class="msg__attachments"
                  >
                    <template
                        v-for="file in m.attachments"
                        :key="file.id"
                    >
                      <button
                          v-if="file.is_image"
                          class="msg__image"
                          type="button"
                          @click="
                          previewImage = file
                        "
                      >
                        <img
                            :src="file.url"
                            :alt="file.name"
                            loading="lazy"
                        >
                      </button>

                      <a
                          v-else
                          class="msg__file"
                          :href="file.url"
                          target="_blank"
                          rel="noopener"
                      >
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                          <path
                              d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                          />
                          <path d="M14 2v6h6" />
                        </svg>

                        <span class="msg__file-name">
                          {{ file.name }}
                        </span>

                        <span class="msg__file-size">
                          {{ file.size }}
                        </span>
                      </a>
                    </template>
                  </div>

                  <div
                      v-if="editingId === m.id"
                      class="msg__edit"
                  >
                    <textarea
                        v-model="editingBody"
                        class="msg__edit-input"
                        rows="3"
                    />

                    <div class="msg__edit-actions">
                      <button
                          type="button"
                          class="msg__edit-btn"
                          @click="cancelEdit"
                      >
                        Отмена
                      </button>

                      <button
                          type="button"
                          class="
                          msg__edit-btn
                          msg__edit-btn--primary
                        "
                          @click="saveEdit(m)"
                      >
                        Сохранить
                      </button>
                    </div>
                  </div>

                  <div
                      v-else
                      class="msg__content"
                  >
                    <span class="msg__text">
                      {{ m.body }}
                    </span>

                    <span
                        v-if="m.edited_at"
                        class="msg__edited"
                    >
                      изменено
                    </span>

                    <span class="msg__meta">
                      <span class="msg__time">
                        {{
                          new Date(
                              m.created_at,
                          ).toLocaleTimeString(
                              'ru-RU',
                              {
                                hour: '2-digit',
                                minute: '2-digit',
                              },
                          )
                        }}
                      </span>

                      <span
                          v-if="
                          m.user?.id ===
                          auth.user?.id
                        "
                          class="msg__read-status"
                          :class="{
                          'msg__read-status--read':
                            isMessageRead(m),
                          'msg__read-status--full':
                            isMessageFullyRead(m),
                        }"
                          :title="
                          isMessageFullyRead(m)
                            ? 'Прочитано всеми'
                            : isMessageRead(m)
                              ? 'Прочитано'
                              : 'Отправлено'
                        "
                      >
                        <svg
                            class="
                            msg__check
                            msg__check--first
                          "
                            width="15"
                            height="15"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                          <path
                              d="M4 12.5l5 5L20 6.5"
                          />
                        </svg>

                        <svg
                            class="
                            msg__check
                            msg__check--second
                          "
                            width="15"
                            height="15"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                          <path
                              d="M4 12.5l5 5L20 6.5"
                          />
                        </svg>
                      </span>
                    </span>
                  </div>
                </div>
              </div>

              <div class="msg__actions">
                <button
                    class="msg__action"
                    type="button"
                    title="Ответить"
                    @click="setReply(m)"
                >
                  <svg
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="M9 17l-5-5 5-5" />
                    <path
                        d="M20 18v-2a4 4 0 0 0-4-4H4"
                    />
                  </svg>
                </button>

                <button
                    class="msg__action"
                    type="button"
                    title="Переслать"
                    @click="openForward(m)"
                >
                  <svg
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="M15 17l5-5-5-5" />
                    <path
                        d="M4 18v-2a4 4 0 0 1 4-4h12"
                    />
                  </svg>
                </button>

                <template
                    v-if="
                    m.user?.id ===
                    auth.user?.id
                  "
                >
                  <button
                      class="msg__action"
                      type="button"
                      title="Изменить"
                      @click="startEdit(m)"
                  >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                      <path d="M12 20h9" />
                      <path
                          d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"
                      />
                    </svg>
                  </button>

                  <button
                      class="
                      msg__action
                      msg__action--danger
                    "
                      type="button"
                      title="Удалить"
                      @click="removeMessage(m)"
                  >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                      <path
                          d="M3 6h18M8 6V4h8v2M6 6l1 16h10l1-16"
                      />
                    </svg>
                  </button>
                </template>
              </div>
            </div>
          </template>

          <div
              v-else
              class="chat__empty-mini"
          >
            <div class="chat__empty-mini-icon">
              <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.6"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path
                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                />
              </svg>
            </div>

            <span>
              Сообщений ещё нет
            </span>

            <small>
              Напиши что-нибудь первым
            </small>
          </div>
        </div>

        <!-- IMAGE PREVIEW -->

        <div
            v-if="previewImage"
            class="chat__image-preview"
            @click.self="
            previewImage = null
          "
        >
          <div class="chat__image-preview-inner">
            <img
                :src="previewImage.url"
                :alt="previewImage.name"
            />

            <button
                class="chat__image-preview-close"
                type="button"
                aria-label="Закрыть"
                @click="
                previewImage = null
              "
            >
              <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.2"
                  stroke-linecap="round"
              >
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- FOOTER -->

        <footer class="chat__footer">
          <div
              v-if="replyTo"
              class="chat__reply-bar"
          >
            <div class="chat__reply-bar-icon">
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M9 17l-5-5 5-5" />
                <path
                    d="M20 18v-2a4 4 0 0 0-4-4H4"
                />
              </svg>
            </div>

            <div class="chat__reply-bar-body">
              <div class="chat__reply-bar-author">
                {{ replyTo.user?.username }}
              </div>

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
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.4"
                  stroke-linecap="round"
              >
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div
              v-if="error"
              class="chat__error"
          >
            <span>{{ error }}</span>

            <button
                type="button"
                @click="error = ''"
            >
              ×
            </button>
          </div>

          <div
              v-if="activeConversation"
              class="chat__attach-row"
          >
            <ChatAttachmentsInput
                v-model="pendingAttachments"
                :max="10"
            />
          </div>

          <form
              class="chat__form"
              @submit.prevent="sendMessage"
          >
            <textarea
                ref="inputEl"
                v-model="body"
                class="chat__input"
                placeholder="Написать сообщение..."
                maxlength="2000"
                rows="1"
                @input="autoGrow"
                @keydown.enter.exact.prevent="
                sendMessage
              "
            />

            <button
                class="chat__send"
                type="submit"
                :disabled="
                sending ||
                (!body.trim() &&
                  !pendingAttachments.length)
              "
                :title="
                pendingAttachments.length &&
                !body.trim()
                  ? 'Отправить файлы'
                  : 'Отправить'
              "
            >
              <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M22 2 11 13" />
                <path
                    d="M22 2l-7 20-4-9-9-4 20-7z"
                />
              </svg>
            </button>
          </form>
        </footer>
      </template>
    </section>

    <!-- ==========================================
         SEARCH
         ========================================== -->

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
          <div class="search-dropdown__head">
            <div class="search-input-wrap">
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.3-4.3" />
              </svg>

              <input
                  v-model="searchQuery"
                  type="text"
                  class="search-input"
                  placeholder="Игрок или клан..."
                  autofocus
              />
            </div>
          </div>

          <div class="search-body scroll-thin">
            <div
                v-if="searchLoading"
                class="search-hint"
            >
              Поиск...
            </div>

            <template v-else>
              <div
                  v-if="searchResults.users.length"
                  class="search-section"
              >
                <div class="search-section__title">
                  {{
                    searchQuery.trim() === ''
                        ? 'Друзья'
                        : 'Игроки'
                  }}
                </div>

                <button
                    v-for="u in searchResults.users"
                    :key="u.id"
                    type="button"
                    class="search-item"
                    @click="startWithUser(u.id)"
                >
                  <div class="search-item__avatar">
                    <img
                        v-if="u.avatar_url"
                        :src="u.avatar_url"
                        :alt="u.username"
                    />

                    <template v-else>
                      {{
                        (u.username || 'И')
                            .charAt(0)
                            .toUpperCase()
                      }}
                    </template>
                  </div>

                  <div class="search-item__body">
                    <div class="search-item__name">
                      {{ u.username }}

                      <svg
                          v-if="u.is_verified"
                          width="11"
                          height="11"
                          viewBox="0 0 24 24"
                          fill="#1da1f2"
                      >
                        <path
                            d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z"
                        />
                      </svg>
                    </div>

                    <div class="search-item__sub">
                      Тир
                      <b
                          :style="{
                          color:
                            tierColors[u.tier] ||
                            '#6b7280',
                        }"
                      >
                        {{ u.tier ?? '—' }}
                      </b>
                    </div>
                  </div>

                  <svg
                      class="search-item__arrow"
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="m9 18 6-6-6-6" />
                  </svg>
                </button>
              </div>

              <div
                  v-if="searchResults.clans.length"
                  class="search-section"
              >
                <div class="search-section__title">
                  Кланы
                </div>

                <button
                    v-for="c in searchResults.clans"
                    :key="c.id"
                    type="button"
                    class="search-item"
                    @click="startWithClan(c.id)"
                >
                  <div
                      class="search-item__avatar"
                      :style="{
                      background:
                        c.banner_color ||
                        '#7c3aed',
                    }"
                  >
                    <img
                        v-if="c.avatar_url"
                        :src="c.avatar_url"
                        :alt="c.name"
                    />

                    <template v-else>
                      {{
                        (c.tag || 'C').charAt(0)
                      }}
                    </template>
                  </div>

                  <div class="search-item__body">
                    <div class="search-item__name">
                      <span class="search-item__tag">
                        [{{ c.tag }}]
                      </span>

                      {{ c.name }}
                    </div>

                    <div class="search-item__sub">
                      Написать клану
                    </div>
                  </div>

                  <svg
                      class="search-item__arrow"
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="m9 18 6-6-6-6" />
                  </svg>
                </button>
              </div>

              <div
                  v-if="
                  !searchResults.users.length &&
                  !searchResults.clans.length
                "
                  class="search-hint"
              >
                {{
                  searchQuery.trim() === ''
                      ? 'У тебя пока нет друзей'
                      : 'Ничего не найдено'
                }}
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ==========================================
         FORWARD
         ========================================== -->

    <Teleport to="body">
      <Transition name="search-fade">
        <div
            v-if="forwardOpen"
            class="forward-modal-bg"
            @click.self="closeForward"
        >
          <div class="forward-modal">
            <header class="forward-modal__head">
              <div>
                <span class="forward-modal__eyebrow">
                  Действие
                </span>

                <h3>Переслать сообщение</h3>
              </div>

              <button
                  class="forward-modal__close"
                  type="button"
                  aria-label="Закрыть"
                  @click="closeForward"
              >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                >
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </header>

            <div
                v-if="forwardMessage"
                class="forward-modal__preview"
            >
              <div
                  class="forward-modal__preview-author"
              >
                {{ forwardMessage.user?.username }}
              </div>

              <div
                  class="forward-modal__preview-body"
              >
                {{
                  forwardMessage.body?.slice(
                      0,
                      140,
                  )
                }}
              </div>
            </div>

            <div class="forward-modal__search">
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
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

            <div
                class="
                forward-modal__body
                scroll-thin
              "
            >
              <div
                  v-if="forwardLoading"
                  class="forward-modal__hint"
              >
                Поиск...
              </div>

              <template v-else>
                <div
                    v-if="forwardResults.users.length"
                    class="forward-modal__section"
                >
                  <div
                      class="
                      forward-modal__section-title
                    "
                  >
                    {{
                      forwardSearch.trim() === ''
                          ? 'Друзья'
                          : 'Игроки'
                    }}
                  </div>

                  <button
                      v-for="u in forwardResults.users"
                      :key="u.id"
                      type="button"
                      class="forward-modal__item"
                      :disabled="forwardSending"
                      @click="
                      confirmForwardUser(u.id)
                    "
                  >
                    <div
                        class="
                        forward-modal__item-avatar
                      "
                    >
                      <img
                          v-if="u.avatar_url"
                          :src="u.avatar_url"
                          :alt="u.username"
                      />

                      <template v-else>
                        {{
                          (u.username || 'И')
                              .charAt(0)
                              .toUpperCase()
                        }}
                      </template>
                    </div>

                    <div
                        class="
                        forward-modal__item-body
                      "
                    >
                      <div
                          class="
                          forward-modal__item-name
                        "
                      >
                        {{ u.username }}
                      </div>

                      <div
                          class="
                          forward-modal__item-sub
                        "
                      >
                        Тир
                        <b
                            :style="{
                            color:
                              tierColors[u.tier] ||
                              '#6b7280',
                          }"
                        >
                          {{ u.tier ?? '—' }}
                        </b>
                      </div>
                    </div>
                  </button>
                </div>

                <div
                    v-if="forwardResults.clans.length"
                    class="forward-modal__section"
                >
                  <div
                      class="
                      forward-modal__section-title
                    "
                  >
                    Кланы
                  </div>

                  <button
                      v-for="c in forwardResults.clans"
                      :key="c.id"
                      type="button"
                      class="forward-modal__item"
                      :disabled="forwardSending"
                      @click="
                      confirmForwardClan(c.id)
                    "
                  >
                    <div
                        class="
                        forward-modal__item-avatar
                      "
                        :style="{
                        background:
                          c.banner_color ||
                          '#7c3aed',
                      }"
                    >
                      <img
                          v-if="c.avatar_url"
                          :src="c.avatar_url"
                          :alt="c.name"
                      />

                      <template v-else>
                        {{
                          (c.tag || 'C').charAt(0)
                        }}
                      </template>
                    </div>

                    <div
                        class="
                        forward-modal__item-body
                      "
                    >
                      <div
                          class="
                          forward-modal__item-name
                        "
                      >
                        <span
                            class="
                            forward-modal__item-tag
                          "
                        >
                          [{{ c.tag }}]
                        </span>

                        {{ c.name }}
                      </div>

                      <div
                          class="
                          forward-modal__item-sub
                        "
                      >
                        Отправить лидеру и офицерам
                      </div>
                    </div>
                  </button>
                </div>

                <div
                    v-if="
                    !forwardResults.users.length &&
                    !forwardResults.clans.length
                  "
                    class="forward-modal__hint"
                >
                  {{
                    forwardSearch.trim() === ''
                        ? 'У тебя пока нет друзей'
                        : 'Ничего не найдено'
                  }}
                </div>
              </template>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ==========================================
         ACTION SHEET
         ========================================== -->

    <Teleport to="body">
      <Transition name="sheet-fade">
        <div
            v-if="actionSheetOpen"
            class="action-sheet-bg"
            @click.self="closeActionSheet"
        >
          <div class="action-sheet">
            <div class="action-sheet__handle"></div>

            <div
                v-if="actionSheetMessage"
                class="action-sheet__preview"
            >
              <div
                  class="
                  action-sheet__preview-author
                "
              >
                {{ actionSheetMessage.user?.username }}
              </div>

              <div
                  class="
                  action-sheet__preview-body
                "
              >
                {{
                  actionSheetMessage.body?.slice(
                      0,
                      120,
                  )
                }}
              </div>
            </div>

            <button
                class="action-sheet__item"
                type="button"
                @click="actionReply"
            >
              <span class="action-sheet__icon">
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                  <path d="M9 17l-5-5 5-5" />
                  <path
                      d="M20 18v-2a4 4 0 0 0-4-4H4"
                  />
                </svg>
              </span>

              Ответить
            </button>

            <button
                class="action-sheet__item"
                type="button"
                @click="actionForward"
            >
              <span class="action-sheet__icon">
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                  <path d="M15 17l5-5-5-5" />
                  <path
                      d="M4 18v-2a4 4 0 0 1 4-4h12"
                  />
                </svg>
              </span>

              Переслать
            </button>

            <button
                class="
                action-sheet__item
                action-sheet__item--cancel
              "
                type="button"
                @click="closeActionSheet"
            >
              Отмена
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.scroll-thin {
scrollbar-width: thin;
scrollbar-color: rgba(124, 58, 237, 0.32) transparent;
}

.scroll-thin::-webkit-scrollbar {
width: 7px;
height: 7px;
}

.scroll-thin::-webkit-scrollbar-track {
background: transparent;
}

.scroll-thin::-webkit-scrollbar-thumb {
background: rgba(124, 58, 237, 0.25);
border-radius: 999px;
border: 2px solid transparent;
background-clip: padding-box;
}

.scroll-thin::-webkit-scrollbar-thumb:hover {
background: rgba(124, 58, 237, 0.48);
background-clip: padding-box;
}

/* =========================================================
PAGE
========================================================= */

.messages-page {
position: relative;

display: grid;
grid-template-columns: 340px minmax(0, 1fr);
gap: 14px;

width: min(1240px, calc(100% - 40px));
height: calc(100vh - var(--header-height) - 40px);

min-height: 560px;
margin: 20px auto;

isolation: isolate;
}

.messages-page::before {
content: "";

position: fixed;
left: 50%;
top: 30%;
z-index: -1;

width: 560px;
height: 420px;

transform: translate(-50%, -50%);

background:
radial-gradient(
circle,
rgba(124, 58, 237, 0.07),
transparent 68%
);

pointer-events: none;
}

/* =========================================================
CONVERSATIONS
========================================================= */

.conversations {
position: relative;

display: flex;
flex-direction: column;

min-width: 0;
overflow: hidden;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.035),
rgba(255, 255, 255, 0.012)
),
var(--bg-card);

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 18px;

box-shadow:
0 18px 50px rgba(0, 0, 0, 0.2),
inset 0 1px rgba(255, 255, 255, 0.035);
}

.conversations::before {
content: "";

position: absolute;
left: 0;
right: 0;
top: 0;

height: 1px;

background:
linear-gradient(
90deg,
transparent,
rgba(167, 139, 250, 0.45),
transparent
);

opacity: 0.65;
}

.conversations__head {
position: relative;

display: flex;
align-items: center;
justify-content: space-between;

padding: 17px 17px 15px;

border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.conversations__title {
display: flex;
align-items: center;
gap: 10px;
min-width: 0;
}

.conversations__title-mark {
width: 7px;
height: 28px;

flex-shrink: 0;

border-radius: 999px;

background:
linear-gradient(
180deg,
#a78bfa,
#7c3aed
);

box-shadow:
0 0 18px rgba(124, 58, 237, 0.42);
}

.conversations__head h1 {
margin: 0;

color: var(--text);

font-size: 16px;
font-weight: 850;
letter-spacing: -0.02em;
}

.conversations__subtitle {
display: block;

margin-top: 2px;

color: var(--text-muted);

font-size: 10px;
font-weight: 600;
letter-spacing: 0.02em;
}

.conversations__search-wrap {
position: relative;
}

.conversations__new {
width: 34px;
height: 34px;

display: inline-flex;
align-items: center;
justify-content: center;

color: #bda8ff;

background:
linear-gradient(
145deg,
rgba(124, 58, 237, 0.16),
rgba(124, 58, 237, 0.06)
);

border: 1px solid rgba(124, 58, 237, 0.28);
border-radius: 10px;

cursor: pointer;

box-shadow:
inset 0 1px rgba(255, 255, 255, 0.04);

transition:
transform 0.16s ease,
background 0.16s ease,
border-color 0.16s ease,
box-shadow 0.16s ease;
}

.conversations__new:hover {
transform: translateY(-1px);

color: #fff;

background:
linear-gradient(
145deg,
rgba(124, 58, 237, 0.28),
rgba(124, 58, 237, 0.12)
);

border-color: rgba(167, 139, 250, 0.5);

box-shadow:
0 8px 22px rgba(124, 58, 237, 0.18);
}

.conversations__new--active {
color: #fff;
background: var(--accent);
border-color: var(--accent);

box-shadow:
0 8px 24px rgba(124, 58, 237, 0.28);
}

.conversations__empty {
flex: 1;

display: flex;
flex-direction: column;
align-items: center;
justify-content: center;

gap: 7px;

padding: 30px 20px;

color: var(--text-muted);

text-align: center;
font-size: 12px;
line-height: 1.55;
}

.conversations__empty strong {
color: var(--text-dim);
font-size: 13px;
}

.empty-icon {
width: 46px;
height: 46px;

display: flex;
align-items: center;
justify-content: center;

margin-bottom: 5px;

color: #a78bfa;

background:
radial-gradient(
circle,
rgba(124, 58, 237, 0.16),
rgba(124, 58, 237, 0.04)
);

border: 1px solid rgba(124, 58, 237, 0.18);
border-radius: 14px;
}

.empty-pulse {
width: 34px;
height: 34px;

border-radius: 50%;

border: 2px solid rgba(124, 58, 237, 0.18);
border-top-color: #8b5cf6;

animation: emptySpin 0.8s linear infinite;
}

@keyframes emptySpin {
to {
transform: rotate(360deg);
}
}

.conversations__list {
flex: 1;

min-height: 0;

list-style: none;

margin: 0;
padding: 7px;

overflow-y: auto;
}

/* =========================================================
CONVERSATION ROW
========================================================= */

.conv {
position: relative;

display: flex;
align-items: center;

gap: 11px;

min-width: 0;

padding: 10px 10px;

color: inherit;
text-decoration: none;

border: 1px solid transparent;
border-radius: 12px;

transition:
background 0.16s ease,
border-color 0.16s ease,
transform 0.16s ease;
}

.conv::before {
content: "";

position: absolute;
left: 0;
top: 9px;
bottom: 9px;

width: 2px;

background: var(--accent);

border-radius: 999px;

opacity: 0;

transform: scaleY(0.5);

transition:
opacity 0.16s ease,
transform 0.16s ease;
}

.conv:hover {
background: rgba(255, 255, 255, 0.035);
border-color: rgba(255, 255, 255, 0.045);
}

.conv--active {
background:
linear-gradient(
90deg,
rgba(124, 58, 237, 0.15),
rgba(124, 58, 237, 0.045)
);

border-color: rgba(124, 58, 237, 0.16);
}

.conv--active::before {
opacity: 1;
transform: scaleY(1);
}

.conv__avatar {
position: relative;

width: 43px;
height: 43px;

display: flex;
align-items: center;
justify-content: center;

flex-shrink: 0;

overflow: hidden;

color: #fff;

background:
linear-gradient(
135deg,
#8b5cf6,
#4c1d95
);

border: 1px solid rgba(255, 255, 255, 0.08);
border-radius: 12px;

font-size: 15px;
font-weight: 850;

box-shadow:
0 7px 18px rgba(0, 0, 0, 0.18);
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

font-size: 15px;
font-weight: 850;
}

.conv__clan img {
position: absolute;
inset: 0;

width: 100%;
height: 100%;

object-fit: cover;
}

.conv__online-dot {
position: absolute;

right: 2px;
bottom: 2px;

width: 8px;
height: 8px;

border: 2px solid var(--bg-card);
border-radius: 50%;

background: #22c55e;

box-shadow:
0 0 8px rgba(34, 197, 94, 0.5);
}

.conv__body {
flex: 1;
min-width: 0;
}

.conv__name {
display: flex;
align-items: center;
gap: 6px;

min-width: 0;

margin-bottom: 3px;

color: var(--text);

font-size: 13px;
font-weight: 750;

overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.conv__clan-tag {
color: #a78bfa;
font-weight: 850;
}

.conv__tag {
padding: 2px 6px;

flex-shrink: 0;

color: #a78bfa;

background: rgba(124, 58, 237, 0.1);

border: 1px solid rgba(124, 58, 237, 0.13);
border-radius: 999px;

font-size: 8px;
font-weight: 850;
text-transform: uppercase;
letter-spacing: 0.4px;
}

.conv__preview {
min-width: 0;

color: var(--text-muted);

font-size: 11.5px;
line-height: 1.4;

overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.conv__preview-author {
color: #9180c9;
font-weight: 700;
}

.conv__meta {
display: flex;
flex-direction: column;
align-items: flex-end;

gap: 5px;

flex-shrink: 0;
}

.conv__time {
color: var(--text-muted);

font-size: 9px;
font-weight: 700;

white-space: nowrap;
}

.conv__badge {
min-width: 19px;
height: 19px;

padding: 0 5px;

display: flex;
align-items: center;
justify-content: center;

color: #fff;

background:
linear-gradient(
135deg,
#ef4444,
#dc2626
);

border-radius: 999px;

font-size: 9px;
font-weight: 850;

box-shadow:
0 4px 12px rgba(239, 68, 68, 0.2);
}

/* =========================================================
CHAT
========================================================= */

.chat {
position: relative;

display: flex;
flex-direction: column;

min-width: 0;
min-height: 0;

overflow: hidden;

background:
radial-gradient(
circle at 75% 5%,
rgba(124, 58, 237, 0.045),
transparent 30%
),
linear-gradient(
180deg,
rgba(255, 255, 255, 0.025),
rgba(255, 255, 255, 0.008)
),
var(--bg-card);

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 18px;

box-shadow:
0 18px 50px rgba(0, 0, 0, 0.2),
inset 0 1px rgba(255, 255, 255, 0.035);
}

.chat--empty {
align-items: center;
justify-content: center;
}

.chat__empty {
display: flex;
flex-direction: column;
align-items: center;

text-align: center;
}

.chat__empty-orb {
width: 76px;
height: 76px;

display: flex;
align-items: center;
justify-content: center;

margin-bottom: 15px;

color: #a78bfa;

background:
radial-gradient(
circle at 35% 30%,
rgba(167, 139, 250, 0.2),
rgba(124, 58, 237, 0.05) 65%,
transparent
);

border: 1px solid rgba(167, 139, 250, 0.16);
border-radius: 24px;

box-shadow:
0 20px 60px rgba(124, 58, 237, 0.1),
inset 0 1px rgba(255, 255, 255, 0.05);
}

.chat__empty-title {
color: var(--text);

font-size: 16px;
font-weight: 800;
}

.chat__empty-subtitle {
margin-top: 5px;

color: var(--text-muted);

font-size: 12px;
}

/* =========================================================
CHAT HEADER
========================================================= */

.chat__head {
position: relative;

display: flex;
align-items: center;

gap: 11px;

padding: 13px 17px;

flex-shrink: 0;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.025),
transparent
);

border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.chat__head-accent {
position: absolute;

left: 0;
right: 0;
bottom: -1px;

height: 1px;

background:
linear-gradient(
90deg,
transparent,
rgba(124, 58, 237, 0.45),
transparent
);

opacity: 0.5;
}

.chat__back {
display: none;

width: 34px;
height: 34px;

align-items: center;
justify-content: center;

color: var(--text-dim);

background: transparent;
border: 1px solid var(--border);
border-radius: 9px;

cursor: pointer;
}

.chat__head-avatar {
position: relative;

flex-shrink: 0;

cursor: pointer;

transition: transform 0.15s ease;
}

.chat__head-avatar:hover {
transform: scale(1.035);
}

.chat__head-avatar-box {
position: relative;

width: 40px;
height: 40px;

display: flex;
align-items: center;
justify-content: center;

overflow: hidden;

color: #fff;

background:
linear-gradient(
135deg,
#8b5cf6,
#4c1d95
);

border: 1px solid rgba(255, 255, 255, 0.09);
border-radius: 12px;

font-size: 14px;
font-weight: 850;
}

.chat__head-avatar-box img {
position: absolute;
inset: 0;

width: 100%;
height: 100%;

object-fit: cover;
}

.chat__head-status {
position: absolute;

right: -1px;
bottom: -1px;

width: 10px;
height: 10px;

border: 2px solid var(--bg-card);
border-radius: 50%;

background: #22c55e;

box-shadow:
0 0 9px rgba(34, 197, 94, 0.5);
}

.chat__head-body {
min-width: 0;
flex: 1;
}

.chat__head-name {
color: var(--text);

font-size: 14px;
font-weight: 820;
letter-spacing: -0.01em;

overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.chat__head-name--link {
cursor: pointer;

transition: color 0.15s ease;
}

.chat__head-name--link:hover {
color: #bda8ff;
}

.chat__head-clan-tag {
color: #a78bfa;
}

.chat__head-sub {
margin-top: 2px;

color: var(--text-muted);

font-size: 10.5px;
}

/* =========================================================
MESSAGES
========================================================= */

.chat__messages {
position: relative;

flex: 1;

min-height: 0;

overflow-y: auto;

padding: 17px 20px 18px;

display: flex;
flex-direction: column;
gap: 12px;

overscroll-behavior: contain;
}

.chat__loading {
display: flex;
align-items: center;
justify-content: center;

min-height: 100px;
}

.loading-dots {
display: flex;
gap: 5px;
}

.loading-dots span {
width: 5px;
height: 5px;

border-radius: 50%;

background: #8b5cf6;

animation: loadingDot 0.9s ease-in-out infinite;
}

.loading-dots span:nth-child(2) {
animation-delay: 0.12s;
}

.loading-dots span:nth-child(3) {
animation-delay: 0.24s;
}

@keyframes loadingDot {
0%,
60%,
100% {
transform: translateY(0);
opacity: 0.35;
}

30% {
transform: translateY(-4px);
opacity: 1;
}
}

.chat__load-older {
width: 100%;

display: flex;
align-items: center;
justify-content: center;
gap: 7px;

margin: 0 0 3px;

padding: 8px 10px;

color: #9d8fc7;

background: rgba(124, 58, 237, 0.045);

border: 1px solid rgba(124, 58, 237, 0.12);
border-radius: 9px;

font-size: 11px;
font-weight: 700;

cursor: pointer;

transition:
background 0.15s ease,
border-color 0.15s ease,
color 0.15s ease;
}

.chat__load-older:hover:not(:disabled) {
color: #c4b5fd;

background: rgba(124, 58, 237, 0.09);
border-color: rgba(124, 58, 237, 0.25);
}

.chat__load-older--secondary {
width: auto;
align-self: center;

padding: 6px 10px;

background: transparent;
border-color: rgba(255, 255, 255, 0.07);

font-size: 10px;
}

.chat__load-older:disabled {
opacity: 0.5;
cursor: progress;
}

/* =========================================================
MESSAGE
========================================================= */

.msg {
display: flex;
align-items: flex-end;

gap: 8px;

max-width: 82%;

animation: messageIn 0.16s ease-out;
}

@keyframes messageIn {
from {
opacity: 0;
transform: translateY(4px);
}

to {
opacity: 1;
transform: translateY(0);
}
}

.msg--mine {
margin-left: auto;
flex-direction: row-reverse;
}

.msg__avatar {
position: relative;

width: 31px;
height: 31px;

display: flex;
align-items: center;
justify-content: center;

flex-shrink: 0;

overflow: hidden;

color: #fff;

background:
linear-gradient(
135deg,
#8b5cf6,
#4c1d95
);

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 9px;

font-size: 11px;
font-weight: 850;

cursor: pointer;

transition:
transform 0.15s ease,
box-shadow 0.15s ease;
}

.msg__avatar:hover {
transform: scale(1.06);

box-shadow:
0 5px 15px rgba(124, 58, 237, 0.18);
}

.msg__avatar img {
position: absolute;
inset: 0;

width: 100%;
height: 100%;

object-fit: cover;
}

.msg__body {
min-width: 0;
position: relative;
}

.msg__bubble {
position: relative;

padding: 9px 12px 7px;

background:
linear-gradient(
145deg,
rgba(255, 255, 255, 0.04),
rgba(255, 255, 255, 0.018)
),
#0d0d14;

border: 1px solid rgba(255, 255, 255, 0.065);
border-radius: 13px 13px 13px 5px;

box-shadow:
0 7px 20px rgba(0, 0, 0, 0.1);

max-width: 100%;
}

.msg--mine .msg__bubble {
background:
linear-gradient(
145deg,
rgba(124, 58, 237, 0.2),
rgba(124, 58, 237, 0.09)
);

border-color: rgba(124, 58, 237, 0.22);

border-radius: 13px 13px 5px 13px;

box-shadow:
0 7px 24px rgba(91, 33, 182, 0.1);
}

.msg__forwarded {
display: flex;
align-items: center;
gap: 5px;

margin-bottom: 5px;

color: var(--text-muted);

font-size: 9.5px;
font-weight: 700;
}

.msg__forwarded b {
color: #a78bfa;
}

.msg__forwarded svg {
opacity: 0.7;
}

.msg__reply {
display: flex;
flex-direction: column;

gap: 1px;

padding: 6px 9px;

margin-bottom: 6px;

background: rgba(124, 58, 237, 0.075);

border-left: 2px solid #8b5cf6;
border-radius: 6px;

cursor: pointer;

overflow: hidden;

transition: background 0.15s ease;
}

.msg__reply:hover {
background: rgba(124, 58, 237, 0.13);
}

.msg__reply-author {
color: #a78bfa;

font-size: 10px;
font-weight: 800;
}

.msg__reply-body {
color: var(--text-muted);

font-size: 10.5px;

overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.msg__content {
display: block;
min-width: 0;
}

.msg__text {
color: var(--text);

font-size: 13px;
line-height: 1.55;

white-space: pre-wrap;
word-break: break-word;
}

.msg__meta {
display: inline-flex;
align-items: center;
gap: 3px;

float: right;

margin:
7px
-4px
-2px
8px;

position: relative;
top: 3px;
}

.msg__time {
color: var(--text-muted);

font-size: 9.5px;
font-weight: 600;

line-height: 1;

white-space: nowrap;
}

.msg--mine .msg__time {
color: rgba(196, 181, 253, 0.68);
}

.msg__edited {
margin-left: 5px;

color: var(--text-muted);

font-size: 9px;
font-style: italic;
}

.msg__read-status {
position: relative;

display: inline-flex;
align-items: center;
justify-content: center;

width: 18px;
height: 11px;

color: rgba(167, 139, 250, 0.65);

flex-shrink: 0;

transition: color 0.2s ease;
}

.msg__check {
position: absolute;

top: 50%;
left: 50%;

transition:
transform 0.22s ease,
opacity 0.2s ease,
color 0.2s ease;
}

.msg__check--first {
opacity: 1;
transform: translate(-50%, -50%);
}

.msg__check--second {
opacity: 0;
transform:
translate(calc(-50% - 5px), -50%)
scale(0.8);
}

.msg__read-status--read,
.msg__read-status--full {
color: #34d399;
}

.msg__read-status--full
.msg__check--first {
transform:
translate(calc(-50% - 4px), -50%);
}

.msg__read-status--full
.msg__check--second {
opacity: 1;
transform:
translate(calc(-50% + 4px), -50%)
scale(1);
}

/* =========================================================
ACTIONS
========================================================= */

.msg__actions {
display: flex;
align-items: center;
gap: 3px;

align-self: center;

opacity: 0;

transform: translateY(2px);

transition:
opacity 0.15s ease,
transform 0.15s ease;
}

.msg:hover .msg__actions {
opacity: 1;
transform: translateY(0);
}

.msg__action {
width: 25px;
height: 25px;

display: inline-flex;
align-items: center;
justify-content: center;

color: var(--text-muted);

background: rgba(13, 13, 20, 0.92);

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 7px;

cursor: pointer;

transition:
color 0.15s ease,
background 0.15s ease,
border-color 0.15s ease,
transform 0.15s ease;
}

.msg__action:hover {
color: #fff;

background: rgba(124, 58, 237, 0.14);

border-color: rgba(124, 58, 237, 0.35);

transform: translateY(-1px);
}

.msg__action--danger:hover {
color: #f87171;

background: rgba(239, 68, 68, 0.08);

border-color: rgba(239, 68, 68, 0.2);
}

/* =========================================================
HIGHLIGHT
========================================================= */

.msg--highlight .msg__bubble {
animation: msgHighlight 1.2s ease;
}

@keyframes msgHighlight {
0% {
box-shadow:
0 0 0 3px rgba(124, 58, 237, 0.24),
0 0 30px rgba(124, 58, 237, 0.18);
}

100% {
box-shadow:
0 7px 20px rgba(0, 0, 0, 0.1);
}
}

/* =========================================================
ATTACHMENTS
========================================================= */

.msg__attachments {
display: flex;
flex-direction: column;
gap: 6px;

margin-top: 5px;
}

.msg__image {
max-width: 280px;

padding: 0;

overflow: hidden;

background: #08080d;

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 10px;

cursor: zoom-in;

transition:
transform 0.18s ease,
border-color 0.18s ease;
}

.msg__image:hover {
transform: translateY(-1px);

border-color: rgba(167, 139, 250, 0.3);
}

.msg__image img {
display: block;

width: 100%;
max-height: 260px;

object-fit: cover;
}

.msg__file {
max-width: 280px;

display: inline-flex;
align-items: center;
gap: 8px;

padding: 8px 10px;

color: #c9c9d6;

background: rgba(0, 0, 0, 0.22);

border: 1px solid rgba(255, 255, 255, 0.06);
border-radius: 9px;

font-size: 11px;

text-decoration: none;

transition:
background 0.15s ease,
border-color 0.15s ease;
}

.msg__file:hover {
background: rgba(124, 58, 237, 0.07);
border-color: rgba(124, 58, 237, 0.2);
}

.msg__file-name {
overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.msg__file-size {
flex-shrink: 0;
color: var(--text-muted);
}

/* =========================================================
EDIT
========================================================= */

.msg__edit {
display: flex;
flex-direction: column;
gap: 7px;
}

.msg__edit-input {
width: 100%;
min-width: 220px;

padding: 9px 10px;

color: var(--text);

background: #09090e;

border: 1px solid rgba(167, 139, 250, 0.24);
border-radius: 8px;

font-family: inherit;
font-size: 13px;

resize: vertical;
outline: none;
}

.msg__edit-input:focus {
border-color: var(--accent);
}

.msg__edit-actions {
display: flex;
justify-content: flex-end;
gap: 5px;
}

.msg__edit-btn {
padding: 6px 10px;

color: var(--text-dim);

background: transparent;

border: 1px solid var(--border);
border-radius: 7px;

font-size: 11px;
font-weight: 700;

cursor: pointer;
}

.msg__edit-btn--primary {
color: #fff;

background: var(--accent);
border-color: var(--accent);
}

/* =========================================================
FOOTER / COMPOSER
========================================================= */

.chat__footer {
position: relative;

padding: 10px 14px 13px;

flex-shrink: 0;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.008),
rgba(255, 255, 255, 0.025)
);

border-top: 1px solid rgba(255, 255, 255, 0.055);
}

.chat__form {
display: flex;
align-items: flex-end;
gap: 8px;

padding: 5px;

background: rgba(7, 7, 12, 0.68);

border: 1px solid rgba(255, 255, 255, 0.075);
border-radius: 14px;

box-shadow:
inset 0 1px rgba(255, 255, 255, 0.025),
0 8px 24px rgba(0, 0, 0, 0.12);

transition:
border-color 0.16s ease,
box-shadow 0.16s ease;
}

.chat__form:focus-within {
border-color: rgba(124, 58, 237, 0.38);

box-shadow:
inset 0 1px rgba(255, 255, 255, 0.025),
0 0 0 3px rgba(124, 58, 237, 0.055);
}

.chat__input {
flex: 1;

min-height: 38px;
max-height: 140px;

padding: 9px 10px;

color: var(--text);

background: transparent;

border: 0;
outline: none;

font: inherit;
font-size: 13px;
line-height: 1.45;

resize: none;
}

.chat__input::placeholder {
color: #5f5f72;
}

.chat__send {
width: 38px;
height: 38px;

display: inline-flex;
align-items: center;
justify-content: center;

flex-shrink: 0;

color: #fff;

background:
linear-gradient(
145deg,
#8b5cf6,
#6d28d9
);

border: 0;
border-radius: 10px;

cursor: pointer;

box-shadow:
0 6px 18px rgba(124, 58, 237, 0.2);

transition:
transform 0.16s ease,
box-shadow 0.16s ease,
filter 0.16s ease;
}

.chat__send:hover:not(:disabled) {
transform: translateY(-1px);

filter: brightness(1.08);

box-shadow:
0 9px 24px rgba(124, 58, 237, 0.28);
}

.chat__send:active:not(:disabled) {
transform: translateY(0);
}

.chat__send:disabled {
opacity: 0.38;
cursor: not-allowed;
box-shadow: none;
}

.chat__attach-row {
padding: 0 4px 7px;
}

.chat__reply-bar {
display: flex;
align-items: center;
gap: 9px;

padding: 8px 10px;

margin-bottom: 7px;

background:
linear-gradient(
90deg,
rgba(124, 58, 237, 0.11),
rgba(124, 58, 237, 0.035)
);

border: 1px solid rgba(124, 58, 237, 0.13);
border-left: 3px solid var(--accent);

border-radius: 9px;
}

.chat__reply-bar-icon {
width: 27px;
height: 27px;

display: inline-flex;
align-items: center;
justify-content: center;

flex-shrink: 0;

color: #a78bfa;

background: rgba(124, 58, 237, 0.12);
border-radius: 7px;
}

.chat__reply-bar-body {
flex: 1;
min-width: 0;
}

.chat__reply-bar-author {
margin-bottom: 1px;

color: #a78bfa;

font-size: 10px;
font-weight: 850;
}

.chat__reply-bar-text {
color: var(--text-muted);

font-size: 11px;

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
border-radius: 7px;

cursor: pointer;
}

.chat__reply-bar-close:hover {
color: #fff;
background: rgba(255, 255, 255, 0.06);
}

.chat__error {
display: flex;
align-items: center;
justify-content: space-between;
gap: 10px;

padding: 7px 10px;

margin-bottom: 7px;

color: #fca5a5;

background: rgba(239, 68, 68, 0.065);

border: 1px solid rgba(239, 68, 68, 0.16);
border-radius: 8px;

font-size: 11px;
}

.chat__error button {
color: inherit;
background: transparent;
border: 0;
cursor: pointer;
font-size: 16px;
}

/* =========================================================
EMPTY CHAT
========================================================= */

.chat__empty-mini {
flex: 1;

display: flex;
flex-direction: column;
align-items: center;
justify-content: center;

gap: 5px;

color: var(--text-dim);

text-align: center;
font-size: 13px;
}

.chat__empty-mini small {
color: var(--text-muted);
font-size: 10px;
}

.chat__empty-mini-icon {
width: 44px;
height: 44px;

display: flex;
align-items: center;
justify-content: center;

margin-bottom: 4px;

color: #9f8be0;

background: rgba(124, 58, 237, 0.07);

border: 1px solid rgba(124, 58, 237, 0.12);
border-radius: 13px;
}

/* =========================================================
IMAGE PREVIEW
========================================================= */

.chat__image-preview {
position: fixed;
inset: 0;

z-index: 300;

display: flex;
align-items: center;
justify-content: center;

padding: 30px;

background: rgba(3, 3, 7, 0.9);

backdrop-filter: blur(12px);
}

.chat__image-preview-inner {
position: relative;

max-width: 94vw;
max-height: 90vh;
}

.chat__image-preview img {
display: block;

max-width: 94vw;
max-height: 88vh;

border-radius: 13px;

box-shadow:
0 30px 100px rgba(0, 0, 0, 0.55);
}

.chat__image-preview-close {
position: absolute;

top: -14px;
right: -14px;

width: 34px;
height: 34px;

display: flex;
align-items: center;
justify-content: center;

color: #fff;

background: rgba(22, 22, 31, 0.94);

border: 1px solid rgba(255, 255, 255, 0.1);
border-radius: 50%;

cursor: pointer;

box-shadow:
0 8px 25px rgba(0, 0, 0, 0.4);
}

/* =========================================================
SEARCH DROPDOWN
========================================================= */

.search-dropdown {
position: fixed;

width: 360px;
max-width: calc(100vw - 40px);

overflow: hidden;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.035),
transparent 35%
),
#15151e;

border: 1px solid rgba(255, 255, 255, 0.09);
border-radius: 14px;

box-shadow:
0 25px 70px rgba(0, 0, 0, 0.55),
0 5px 20px rgba(0, 0, 0, 0.25);

backdrop-filter: blur(18px);

z-index: 4000;
}

.search-dropdown__head {
padding: 9px;

border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.search-input-wrap {
position: relative;

display: flex;
align-items: center;
}

.search-input-wrap svg {
position: absolute;
left: 12px;

color: var(--text-muted);

pointer-events: none;
}

.search-input {
width: 100%;

padding: 9px 10px 9px 32px;

color: var(--text);

background: #0b0b11;

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 9px;

font: inherit;
font-size: 12px;

outline: none;

transition: border-color 0.15s ease;
}

.search-input:focus {
border-color: rgba(124, 58, 237, 0.45);
}

.search-body {
max-height: 420px;

overflow-y: auto;

padding: 5px;
}

.search-hint {
padding: 28px 16px;

color: var(--text-muted);

text-align: center;

font-size: 11.5px;
}

.search-section {
display: flex;
flex-direction: column;
gap: 2px;

padding: 3px 0;
}

.search-section + .search-section {
margin-top: 5px;
padding-top: 7px;

border-top: 1px solid rgba(255, 255, 255, 0.055);
}

.search-section__title {
padding: 5px 9px 4px;

color: var(--text-muted);

font-size: 9px;
font-weight: 850;

text-transform: uppercase;
letter-spacing: 0.6px;
}

.search-item {
display: flex;
align-items: center;

width: 100%;

gap: 10px;

padding: 8px 9px;

color: inherit;

background: transparent;

border: 0;
border-radius: 9px;

cursor: pointer;

text-align: left;

transition:
background 0.14s ease,
transform 0.14s ease;
}

.search-item:hover {
background: rgba(255, 255, 255, 0.045);
transform: translateX(1px);
}

.search-item__avatar {
position: relative;

width: 35px;
height: 35px;

display: flex;
align-items: center;
justify-content: center;

flex-shrink: 0;

overflow: hidden;

color: #fff;

background:
linear-gradient(
135deg,
#8b5cf6,
#4c1d95
);

border-radius: 10px;

font-size: 12px;
font-weight: 850;
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

color: var(--text);

font-size: 12px;
font-weight: 750;

overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.search-item__tag {
color: #a78bfa;
font-weight: 850;
}

.search-item__sub {
margin-top: 2px;

color: var(--text-muted);

font-size: 10px;
font-weight: 600;
}

.search-item__arrow {
flex-shrink: 0;

color: #4f4f61;

transition:
color 0.14s ease,
transform 0.14s ease;
}

.search-item:hover .search-item__arrow {
color: #9f8be0;
transform: translateX(2px);
}

/* =========================================================
FORWARD MODAL
========================================================= */

.forward-modal-bg {
position: fixed;
inset: 0;

z-index: 2500;

display: flex;
align-items: center;
justify-content: center;

padding: 20px;

background: rgba(3, 3, 7, 0.72);

backdrop-filter: blur(10px);
}

.forward-modal {
width: 100%;
max-width: 430px;
max-height: 85vh;

display: flex;
flex-direction: column;

overflow: hidden;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.035),
transparent 30%
),
#15151e;

border: 1px solid rgba(255, 255, 255, 0.09);
border-radius: 16px;

box-shadow:
0 30px 90px rgba(0, 0, 0, 0.6);
}

.forward-modal__head {
display: flex;
align-items: center;
justify-content: space-between;

padding: 15px 17px;

border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.forward-modal__eyebrow {
display: block;

margin-bottom: 2px;

color: #8f82b5;

font-size: 8px;
font-weight: 850;

text-transform: uppercase;
letter-spacing: 0.8px;
}

.forward-modal__head h3 {
margin: 0;

color: var(--text);

font-size: 14px;
font-weight: 820;
}

.forward-modal__close {
width: 29px;
height: 29px;

display: flex;
align-items: center;
justify-content: center;

color: var(--text-dim);

background: rgba(255, 255, 255, 0.025);

border: 1px solid rgba(255, 255, 255, 0.055);
border-radius: 8px;

cursor: pointer;
}

.forward-modal__close:hover {
color: #fff;
background: rgba(255, 255, 255, 0.06);
}

.forward-modal__preview {
padding: 10px 15px;

background: rgba(124, 58, 237, 0.055);

border-bottom: 1px solid rgba(255, 255, 255, 0.045);
border-left: 3px solid #7c3aed;
}

.forward-modal__preview-author {
margin-bottom: 2px;

color: #a78bfa;

font-size: 10px;
font-weight: 850;
}

.forward-modal__preview-body {
color: var(--text-dim);

font-size: 11.5px;

overflow: hidden;

display: -webkit-box;
-webkit-line-clamp: 2;
-webkit-box-orient: vertical;
}

.forward-modal__search {
position: relative;

padding: 9px 12px;

border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.forward-modal__search svg {
position: absolute;

left: 22px;
top: 50%;

transform: translateY(-50%);

color: var(--text-muted);

pointer-events: none;
}

.forward-modal__input {
width: 100%;

padding: 9px 11px 9px 31px;

color: var(--text);

background: #0b0b11;

border: 1px solid rgba(255, 255, 255, 0.07);
border-radius: 8px;

font: inherit;
font-size: 12px;

outline: none;
}

.forward-modal__input:focus {
border-color: rgba(124, 58, 237, 0.45);
}

.forward-modal__body {
flex: 1;

min-height: 0;

overflow-y: auto;

padding: 7px;
}

.forward-modal__hint {
padding: 26px 15px;

color: var(--text-muted);

text-align: center;

font-size: 11.5px;
}

.forward-modal__section {
display: flex;
flex-direction: column;
gap: 2px;
}

.forward-modal__section + .forward-modal__section {
margin-top: 6px;
padding-top: 7px;

border-top: 1px solid rgba(255, 255, 255, 0.055);
}

.forward-modal__section-title {
padding: 5px 8px 4px;

color: var(--text-muted);

font-size: 9px;
font-weight: 850;

text-transform: uppercase;
letter-spacing: 0.6px;
}

.forward-modal__item {
display: flex;
align-items: center;

gap: 10px;

width: 100%;

padding: 8px 9px;

color: inherit;

background: transparent;

border: 0;
border-radius: 9px;

cursor: pointer;

text-align: left;
}

.forward-modal__item:hover:not(:disabled) {
background: rgba(255, 255, 255, 0.045);
}

.forward-modal__item:disabled {
opacity: 0.5;
cursor: not-allowed;
}

.forward-modal__item-avatar {
position: relative;

width: 35px;
height: 35px;

display: flex;
align-items: center;
justify-content: center;

flex-shrink: 0;

overflow: hidden;

color: #fff;

border-radius: 10px;

font-size: 12px;
font-weight: 850;
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
color: var(--text);

font-size: 12px;
font-weight: 750;

overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}

.forward-modal__item-tag {
color: #a78bfa;
font-weight: 850;
}

.forward-modal__item-sub {
margin-top: 2px;

color: var(--text-muted);

font-size: 10px;
font-weight: 600;
}

/* =========================================================
BOTTOM SHEET
========================================================= */

.action-sheet-bg {
position: fixed;
inset: 0;

z-index: 3000;

display: flex;
align-items: flex-end;
justify-content: center;

background: rgba(3, 3, 7, 0.62);

backdrop-filter: blur(5px);
}

.action-sheet {
width: 100%;
max-width: 480px;

padding:
7px
8px
calc(8px + env(safe-area-inset-bottom));

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.04),
transparent 35%
),
#15151e;

border-top: 1px solid rgba(255, 255, 255, 0.08);
border-radius: 19px 19px 0 0;

box-shadow:
0 -25px 70px rgba(0, 0, 0, 0.55);

animation:
sheetSlideUp
0.22s
cubic-bezier(0.2, 0.9, 0.3, 1);
}

.action-sheet__handle {
width: 38px;
height: 4px;

margin: 2px auto 8px;

border-radius: 999px;

background: rgba(255, 255, 255, 0.14);
}

@keyframes sheetSlideUp {
from {
transform: translateY(100%);
}

to {
transform: translateY(0);
}
}

.action-sheet__preview {
padding: 9px 12px 11px;

margin-bottom: 5px;

border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.action-sheet__preview-author {
color: #a78bfa;

font-size: 11px;
font-weight: 850;
}

.action-sheet__preview-body {
margin-top: 3px;

color: var(--text-dim);

font-size: 12px;

overflow: hidden;

display: -webkit-box;
-webkit-line-clamp: 2;
-webkit-box-orient: vertical;
}

.action-sheet__item {
display: flex;
align-items: center;

gap: 12px;

width: 100%;

padding: 13px 12px;

color: var(--text);

background: transparent;

border: 0;
border-radius: 11px;

font: inherit;
font-size: 14px;
font-weight: 650;

text-align: left;

cursor: pointer;

transition: background 0.14s ease;
}

.action-sheet__item:hover,
.action-sheet__item:active {
background: rgba(255, 255, 255, 0.055);
}

.action-sheet__icon {
width: 32px;
height: 32px;

display: flex;
align-items: center;
justify-content: center;

color: #a78bfa;

background: rgba(124, 58, 237, 0.1);

border-radius: 9px;
}

.action-sheet__item--cancel {
justify-content: center;

margin-top: 4px;

color: var(--text-muted);

border-top: 1px solid rgba(255, 255, 255, 0.055);
border-radius: 0 0 11px 11px;
}

/* =========================================================
TRANSITIONS
========================================================= */

.search-fade-enter-active,
.search-fade-leave-active {
transition:
opacity 0.16s ease,
transform 0.16s ease;
}

.search-fade-enter-from,
.search-fade-leave-to {
opacity: 0;
transform: translateY(-5px);
}

.sheet-fade-enter-active,
.sheet-fade-leave-active {
transition: opacity 0.2s ease;
}

.sheet-fade-enter-from,
.sheet-fade-leave-to {
opacity: 0;
}

/* =========================================================
MOBILE
========================================================= */

@media (max-width: 900px) {
.messages-page {
display: flex;
flex-direction: column;

width: 100%;
height: calc(100dvh - var(--header-height));

min-height: 0;

margin: 0;
}

.conversations {
flex: 1;

min-height: 0;

border: 0;
border-radius: 0;

background: var(--bg);

box-shadow: none;
}

.conversations__head {
padding:
13px
15px;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.035),
transparent
);

position: sticky;
top: 0;

z-index: 10;
}

.conversations__head h1 {
font-size: 17px;
}

.conversations__list {
padding: 6px 8px 14px;
}

.conv {
padding: 10px;
gap: 11px;
border-radius: 12px;
}

.conv__avatar {
width: 47px;
height: 47px;

border-radius: 50%;
}

.conv__name {
font-size: 13.5px;
}

.conv__preview {
font-size: 12px;
}

.conv__time {
font-size: 10px;
}

.chat {
flex: 1;

min-height: 0;

border: 0;
border-radius: 0;

background: var(--bg);

box-shadow: none;
}

.messages-page--chat-open
.conversations {
display: none;
}

.messages-page:not(.messages-page--chat-open)
.chat {
display: none;
}

.chat__head {
padding: 9px 11px;

background:
linear-gradient(
180deg,
rgba(255, 255, 255, 0.035),
transparent
);

position: sticky;
top: 0;

z-index: 10;
}

.chat__back {
display: inline-flex;

width: 34px;
height: 34px;

border: 0;
border-radius: 50%;

background: rgba(255, 255, 255, 0.035);
}

.chat__head-avatar-box {
width: 38px;
height: 38px;

border-radius: 50%;
}

.chat__head-name {
font-size: 14px;
}

.chat__head-sub {
font-size: 10px;
}

.chat__messages {
padding: 12px 9px 8px;
gap: 8px;
}

.msg {
max-width: 89%;
gap: 7px;
}

.msg__avatar {
width: 29px;
height: 29px;
border-radius: 50%;
}

.msg__bubble {
padding: 8px 10px 6px;

border-radius: 14px 14px 14px 5px;
}

.msg--mine .msg__bubble {
border-radius: 14px 14px 5px 14px;
}

.msg__text {
font-size: 13.5px;
}

.msg__actions {
display: none;
}

.chat__footer {
padding:
7px
8px
calc(7px + env(safe-area-inset-bottom));

background:
linear-gradient(
180deg,
transparent,
rgba(255, 255, 255, 0.025)
);
}

.chat__form {
border-radius: 22px;
padding: 4px;
}

.chat__input {
min-height: 38px;

padding:
9px
14px;

font-size: 14px;
}

.chat__send {
width: 38px;
height: 38px;

border-radius: 50%;
}

.chat__reply-bar {
border-radius: 10px;
}

.search-dropdown {
left: 8px !important;
right: 8px !important;

width: auto;
max-width: none;

max-height: 72vh;

border-radius: 15px;
}

.search-item {
padding: 10px;
}

.search-item__avatar {
width: 39px;
height: 39px;

border-radius: 50%;
}

.forward-modal-bg {
padding: 0;
align-items: flex-end;
}

.forward-modal {
width: 100%;
max-width: none;
max-height: 90vh;

border-radius: 18px 18px 0 0;

animation:
sheetSlideUp
0.22s
cubic-bezier(0.2, 0.9, 0.3, 1);
}

.forward-modal__item-avatar {
width: 40px;
height: 40px;

border-radius: 50%;
}
}

@media (max-width: 480px) {
.conv__avatar {
width: 45px;
height: 45px;
}

.msg {
max-width: 93%;
}

.msg__avatar {
width: 27px;
height: 27px;
}

.chat__messages {
padding:
9px
7px
6px;
}

.chat__head {
padding-left: 8px;
padding-right: 8px;
}

.chat__head-accent {
opacity: 0.35;
}
}
</style>