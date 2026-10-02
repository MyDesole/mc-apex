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
import { usePresence } from '@/composables/chat/presence.js'
import { useMessagePolling } from '@/composables/chat/useMessagePolling.js'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const { latestMessage, latestRead, typingUsers } = useRealtimeMessages()
/* Присутствие подключается ниже: нужен activeConversation */


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

/*
 * Присутствие. Передаём собеседников открытого диалога: по ним статус
 * обновляется сам, без перезагрузки страницы.
 */
const presencePeers = computed(() => {
  const conversation = activeConversation.value

  if (!conversation) return []

  return (conversation.users || [])
      .map((u) => Number(u.id))
      .filter((id) => id && id !== Number(auth.user?.id ?? 0))
})

const {
  isOnline: isPlayerOnline,
  presenceReady,
  refresh: refreshPresence,
  refreshPeers: refreshPeersPresence,
} = usePresence(() => presencePeers.value)

/*
 * Запасной опрос: если соединение с Reverb отвалилось и не поднялось,
 * сообщения всё равно придут — пусть и с небольшой задержкой. Опрос
 * включается только когда открыт диалог.
 */
useMessagePolling(
    computed(() => activeConversation.value?.id ?? null),
    messages,
)

/*
 * Кто печатает в открытом диалоге. Событие приходит с промежутком,
 * поэтому надпись гасится сама по времени — см. useRealtimeMessages.
 */
const typingPeer = computed(() => {
  const id = activeConversation.value?.id

  if (!id) return null

  const entry = typingUsers.value[id]

  return entry && entry.id !== auth.user?.id ? entry : null
})

/*
 * Кто и когда прочитал диалог: { [conversationId]: { [userId]: время } }.
 *
 * Нужно потому, что событие о новом сообщении не содержит списка reads:
 * сравнение по времени показывает, что собеседник прочитал его позже.
 */
const lastReadAt = ref({})

/** Когда последний раз сообщали о своей печати. */
let lastTypingSentAt = 0

/** Как часто сообщать о печати. */
const TYPING_SEND_INTERVAL_MS = 2500

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

/**
 * Онлайн ли собеседник в личном диалоге.
 *
 * Сначала спрашиваем присутствие, а если ответа ещё нет, берём признак
 * из данных диалога: так точка не мигает при первой отрисовке.
 */
const directPartnerOnline = computed(() => {
  if (!directPartner.value) return false

  if (isPlayerOnline(directPartner.value.id)) return true

  /*
   * Запасной признак только до первой загрузки присутствия: сервер
   * считает онлайн по времени последней активности, и после выхода
   * игрока он ещё пару минут остаётся истинным.
   */
  return presenceReady.value ? false : Boolean(directPartner.value.is_online)
})

/** Онлайн ли кто-то из участников диалога в списке. */
function conversationOnline(conversation) {
  if (conversation.type === 'clan_message') return false

  return (conversation.users || [])
      .filter((u) => u.id !== auth.user?.id)
      .some((u) => isPlayerOnline(u.id)
          || (!presenceReady.value && Boolean(u.is_online)))
}

/**
 * Сообщает серверу, что игрок печатает.
 *
 * Не чаще, чем раз в TYPING_SEND_INTERVAL_MS: иначе каждое нажатие
 * клавиши отправляло бы событие.
 */
function notifyTyping() {
  const id = activeConversation.value?.id

  if (!id) return

  const now = Date.now()

  if (now - lastTypingSentAt < TYPING_SEND_INTERVAL_MS) return

  lastTypingSentAt = now

  chatApi.typing(id).catch(() => {
    /* Печать — вещь дополнительная, сбой не должен мешать */
  })
}

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

  // Прочтения другого диалога не должны влиять на этот
  const { [id]: _keep, ...restReads } = lastReadAt.value

  lastReadAt.value = restReads

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

/**
 * Догружает историю, пока нужное сообщение не окажется в ленте.
 *
 * Поиск ищет по всей истории, а в ленте загружен только её хвост.
 * Поэтому перед переходом к найденному сообщению подтягиваем старые.
 *
 * @param {number} messageId  куда нужно попасть
 * @returns {Promise<boolean>} нашлось ли сообщение
 */
async function ensureMessageLoaded(messageId) {
  /* Уже в ленте — ничего делать не нужно */
  const inList = () => messages.value.some((m) => m.id === messageId)

  if (inList()) return true

  /*
   * Ограничение на число шагов: история может быть очень длинной, а
   * бесконечная догрузка подвесила бы страницу.
   */
  const maxSteps = 20
  let steps = 0

  while (!inList() && hasMoreHistory.value && steps < maxSteps) {
    const before = messages.value.length

    await loadOlder()

    steps++

    /* История не выросла — дальше идти незачем */
    if (messages.value.length === before) break
  }

  return inList()
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
function onBodyInput() {
  autoGrow()
  notifyTyping()
}

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

/**
 * Прочитал ли сообщение кто-то, кроме автора.
 *
 * Кроме списка reads учитываем время: событие о новом сообщении список
 * не содержит, поэтому сверяем его отправку с временем прочтения диалога.
 */
function isMessageRead(message) {
  const readers = (message.reads || [])
      .filter(read => read.user_id !== auth.user?.id)

  if (readers.length) return true

  return readerIds(message).length > 0
}

/** Кто прочитал сообщение: из списка reads и по времени. */
function readerIds(message) {
  const byTime = lastReadAt.value[message.conversation_id] || {}

  return Object.entries(byTime)
      .filter(([userId, readAt]) => {
        if (Number(userId) === Number(auth.user?.id)) return false

        return readAt && message.created_at
            ? new Date(readAt) >= new Date(message.created_at)
            : false
      })
      .map(([userId]) => Number(userId))
}

function isMessageFullyRead(message) {
  if (!otherParticipantsCount.value) return false

  const readers = new Set([
    ...(message.reads || [])
        .map(read => Number(read.user_id))
        .filter(id => id !== Number(auth.user?.id)),
    ...readerIds(message),
  ])

  return readers.size >= otherParticipantsCount.value
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
  messages: [],
})

/** Режим поиска: люди и кланы или сообщения текущего диалога. */
const searchMode = ref('people')

/** Идёт ли поиск по сообщениям. */
const messageSearchLoading = ref(false)
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
    if (searchMode.value === 'messages') {
      await runMessageSearch(query)
      searchResults.value = { ...searchResults.value, users: [], clans: [] }

      return
    }

    searchResults.value.messages = []

    const data = await chatApi.search(query)

    searchResults.value = {
      users: data.users ?? [],
      clans: data.clans ?? [],
      messages: [],
    }
  } catch {
    searchResults.value = {
      users: [],
      clans: [],
      messages: [],
    }
  } finally {
    searchLoading.value = false
  }
}

/** Переключение режима поиска. */
function setSearchMode(mode) {
  if (searchMode.value === mode) return

  searchMode.value = mode

  runSearch(searchQuery.value)
}

/** Ищет по тексту сообщений в текущем диалоге. */
async function runMessageSearch(query) {
  if (!activeConversation.value || query.trim().length < 2) {
    searchResults.value.messages = []

    return
  }

  messageSearchLoading.value = true

  try {
    const data = await chatApi.searchMessages(activeConversation.value.id, query)

    searchResults.value.messages = data.messages ?? []
  } catch {
    searchResults.value.messages = []
  } finally {
    messageSearchLoading.value = false
  }
}

/**
 * Открывает найденное сообщение в ленте.
 *
 * Сообщение может быть выше загруженной истории, поэтому сначала
 * догружаем её до нужного места, затем прокручиваем и подсвечиваем.
 */
async function openFoundMessage(message) {
  searchOpen.value = false

  await ensureMessageLoaded(message.id)
  await scrollToMessage(message.id)
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
  searchMode.value = 'people'

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

/*
 * Собеседник прочитал: помечаем свои сообщения прочитанными сразу.
 * Раньше галочка появлялась только после обновления страницы.
 */
watch(latestRead, (payload) => {
  if (!payload || payload.conversation_id !== activeConversation.value?.id) return

  const readerId = payload.reader?.id

  if (!readerId) return

  /*
   * Статус галочки считается по списку reads, поэтому добавляем
   * читателя туда. Свои сообщения, которые он ещё не прочитал,
   * получают отметку и галочка встаёт сразу.
   */
  // Время прочтения: по нему отметим и сообщения, пришедшие по сокету
  lastReadAt.value = {
    ...lastReadAt.value,
    [payload.conversation_id]: {
      ...(lastReadAt.value[payload.conversation_id] || {}),
      [readerId]: payload.read_at,
    },
  }

  messages.value = messages.value.map((message) => {
    if (message.user?.id !== auth.user?.id) return message

    const reads = message.reads || []

    if (reads.some(read => read.user_id === readerId)) return message

    return {
      ...message,
      reads: [...reads, { user_id: readerId }],
    }
  })
})

watch(latestMessage, async message => {
  // Собеседник только что написал — значит он в сети
  refreshPeersPresence()

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

                <!-- У клана нет статуса одного игрока: точку не показываем -->
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

                <!-- Точка онлайна: у личных диалогов состояние берём по собеседнику -->
                <span
                    v-if="conversationOnline(c)"
                    class="conv__online-dot"
                ></span>
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

            <span
                class="chat__head-status"
                :class="{
                'chat__head-status--online':
                  directPartnerOnline,
              }"
            ></span>
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

              <div
                  class="chat__head-sub"
                  :class="{ 'chat__head-sub--typing': typingPeer }"
              >
                <template v-if="typingPeer">
                  печатает…
                </template>

                <template v-else>
                  Личный диалог
                </template>
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
                @input="onBodyInput"
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
                  :placeholder="
                    searchMode === 'messages'
                        ? 'Поиск в этом диалоге...'
                        : 'Игрок или клан...'
                  "
                  autofocus
              />
            </div>

            <!-- Режим поиска: по людям или по сообщениям текущего диалога -->
            <div
                v-if="activeConversation"
                class="search-tabs"
            >
              <button
                  type="button"
                  class="search-tab"
                  :class="{ 'search-tab--active': searchMode === 'people' }"
                  @click="setSearchMode('people')"
              >
                Люди
              </button>

              <button
                  type="button"
                  class="search-tab"
                  :class="{ 'search-tab--active': searchMode === 'messages' }"
                  @click="setSearchMode('messages')"
              >
                Сообщения
              </button>
            </div>
          </div>

          <div class="search-body scroll-thin">
            <div
                v-if="searchLoading || messageSearchLoading"
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

              <!-- Найденные сообщения текущего диалога -->
              <div
                  v-if="
                  searchMode === 'messages' &&
                  searchResults.messages.length
                "
                  class="search-section"
              >
                <div class="search-section__title">
                  Сообщения
                </div>

                <button
                    v-for="m in searchResults.messages"
                    :key="m.id"
                    type="button"
                    class="search-item search-item--message"
                    @click="openFoundMessage(m)"
                >
                  <div class="search-item__avatar search-item__avatar--message">
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

                  <div class="search-item__body">
                    <div class="search-item__name">
                      {{ m.user?.username || 'Игрок' }}
                    </div>

                    <div class="search-item__sub">
                      {{ m.snippet }}
                    </div>
                  </div>
                </button>
              </div>

              <div
                  v-if="
                  searchMode === 'messages'
                    ? (messageSearchLoading
                        ? false
                        : !searchResults.messages.length)
                    : (!searchResults.users.length &&
                       !searchResults.clans.length)
                "
                  class="search-hint"
              >
                <template v-if="searchMode === 'messages'">
                  {{
                    searchQuery.trim().length < 2
                        ? 'Введи хотя бы два символа'
                        : 'В этом диалоге ничего не найдено'
                  }}
                </template>

                <template v-else>
                  {{
                    searchQuery.trim() === ''
                        ? 'У тебя пока нет друзей'
                        : 'Ничего не найдено'
                  }}
                </template>
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
@import "@/views/chat/Messages.css";
</style>