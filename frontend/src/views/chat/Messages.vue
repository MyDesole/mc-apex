<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { chatApi } from '@/services/chat/chat.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { useRealtimeMessages } from '@/composables/chat/useRealtimeMessages.js'
import UserName from '@/components/players/UserName.vue'
import ChatAttachmentsInput from '@/components/chat/ChatAttachmentsInput.vue'
import ChatSticker from '@/components/chat/ChatSticker.vue'
import { STICKER_PACKS, parseSticker, stickerToken } from '@/data/shop/stickers.js'
import { shopApi } from '@/services/shop/shop.js'
import { userLink } from '@/utils/links.js'
import { usePresence } from '@/composables/chat/presence.js'
import { useMessagePolling } from '@/composables/chat/useMessagePolling.js'
import { mergeMessages } from '@/utils/chatMessages.js'
import { dayLabel, startsNewDay } from '@/utils/chatDates.js'
import { openReport } from '@/composables/core/useReport.js'
import ChatLinkCard from '@/components/chat/ChatLinkCard.vue'
import ChatVoicePlayer from '@/components/chat/ChatVoicePlayer.vue'
import ChatVoiceRecorder from '@/components/chat/ChatVoiceRecorder.vue'
import ChatGiftPanel from '@/components/chat/ChatGiftPanel.vue'
import ChatGroupModal from '@/components/chat/ChatGroupModal.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { previewText, canEdit, withMine, conversationTime, readDrafts, writeDraft } from '@/utils/chatPreview.js'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const { latestMessage, latestRead, latestChange, typingUsers, chatUnread, setChatUnread } = useRealtimeMessages()
/* Присутствие подключается ниже: нужен activeConversation */


import { bridgeRankOf, rankColor, rankLabel } from '@/utils/playerStyling.js'

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
    {
      /*
       * Опрос приносил сообщения, но не помечал их прочитанными: при
       * мёртвом сокете у собеседника не вставала галочка, а у себя висел
       * счётчик непрочитанных.
       */
      onNew(added) {
        const fromOthers = added.filter((m) => m.user?.id !== auth.user?.id)

        if (!fromOthers.length) return

        noteArrivals(fromOthers.length)
        markActiveRead()
      },
    },
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

const isGroup = computed(() => activeConversation.value?.type === 'group')

const directPartner = computed(() => {
  if (!activeConversation.value) return null
  if (activeConversation.value.type !== 'direct') return null

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
  if (conversation.type !== 'direct') return false

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

/** Первое непрочитанное при открытии: перед ним — «Новые сообщения». */
const firstUnreadId = ref(null)

/** Сколько новых пришло, пока лента прокручена вверх. */
const newBelow = ref(0)

/** Лента у нижнего края: новое сообщение можно показать прокруткой. */
const atBottom = ref(true)

function isNearBottom() {
  const el = messagesEl.value

  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 150
}

/** Новые сообщения пришли: если лента не внизу, растёт счётчик на кнопке. */
function noteArrivals(count) {
  if (!isNearBottom()) newBelow.value += count
}

function jumpToBottom() {
  newBelow.value = 0
  scrollToBottom(true)
}

/*
 * Прочтение открытого диалога. Только когда вкладку видно: раньше
 * сообщение в фоновой вкладке сразу получало галочку «прочитано».
 * Отложенное прочтение срабатывает при возврате на вкладку.
 */
let markTimer = null
let readPending = false

function markActiveRead() {
  const id = activeConversation.value?.id

  if (!id) return

  if (document.visibilityState !== 'visible') {
    readPending = true

    return
  }

  readPending = false
  clearTimeout(markTimer)

  markTimer = setTimeout(async () => {
    try {
      const data = await chatApi.markConversationRead(id)

      setChatUnread(data.unread_count)
    } catch {
      // прочитаем при следующем сообщении
    }
  }, 250)
}

function onVisibility() {
  if (document.visibilityState === 'visible' && readPending) markActiveRead()
}

// ============================================
// ЧЕРНОВИКИ
// ============================================

/*
 * Недописанное сообщение запоминается для каждого диалога. Раньше текст
 * оставался в поле при переходе в другой диалог — и легко уходил не тому.
 */
const drafts = ref(readDrafts())

/** Какому диалогу принадлежит текст в поле. */
let draftOwner = null
let draftTimer = null

function saveDraftNow() {
  clearTimeout(draftTimer)

  if (draftOwner) drafts.value = writeDraft(drafts.value, draftOwner, body.value)
}

function switchDraft(id) {
  saveDraftNow()
  draftOwner = id
  body.value = id ? (drafts.value[id] ?? '') : ''
  nextTick(autoGrow)
}

function draftOf(conversation) {
  return conversation.id !== activeConversation.value?.id ? drafts.value[conversation.id] : null
}

// ============================================
// ПОДЕЛИТЬСЯ
// ============================================

/**
 * Ссылка, которой делятся со страницы игрока, клана или лота: приходит
 * в адресе (?share=), подставляется в поле выбранного диалога.
 */
const shareText = ref('')

function takeShare() {
  const value = String(route.query.share || '').trim()

  if (!value) return

  shareText.value = value.startsWith('/') ? `${window.location.origin}${value}` : value

  const { share: _drop, ...rest } = route.query

  router.replace({ query: rest })
}

function applyShare() {
  if (!shareText.value) return

  body.value = body.value.trim() ? `${body.value.trim()} ${shareText.value}` : shareText.value
  shareText.value = ''

  nextTick(() => {
    autoGrow()
    inputEl.value?.focus()
  })
}

// ============================================
// ВЛОЖЕНИЯ
// ============================================

const pendingAttachments = ref([])
const previewImage = ref(null)

// Выбор файлов: кнопка-скрепка в поле ввода открывает его
const attachInput = ref(null)

// ============================================
// ЗАГРУЗКА ДИАЛОГОВ
// ============================================

async function loadConversations(silent = false) {
  if (!silent) loadingList.value = true

  try {
    const data = await chatApi.conversations()
    conversations.value = data.conversations ?? []
  } catch (e) {
    if (!silent) error.value = e.message || 'Не удалось загрузить диалоги'
  } finally {
    loadingList.value = false
  }
}

/*
 * Без сокета список диалогов не узнавал о новых сообщениях до перезагрузки
 * страницы. Пока соединения нет, сверяемся с сервером раз в 20 секунд.
 */
let listTimer = null

function refreshListIfOffline() {
  const alive = window.Echo?.connector?.pusher?.connection?.state === 'connected'

  if (!alive && document.visibilityState === 'visible') loadConversations(true)
}

/** Номер последнего открытия: ответ на прошлое открытие не должен затереть новое. */
let openRequest = 0

async function openConversation(id) {
  const request = ++openRequest

  loadingChat.value = true
  error.value = ''

  // Прочтения другого диалога не должны влиять на этот
  const { [id]: _keep, ...restReads } = lastReadAt.value

  lastReadAt.value = restReads

  if (draftOwner !== id) switchDraft(id)

  showGift.value = false
  newBelow.value = 0

  try {
    const data = await chatApi.show(id, { limit: 40 })

    // Игрок успел открыть другой диалог — этот ответ устарел
    if (request !== openRequest) return

    activeConversation.value = data.conversation
    messages.value = data.messages ?? []
    firstUnreadId.value = data.first_unread_id ?? null

    // Открытие помечает диалог прочитанным — сверяем счётчик в шапке
    chatApi.unreadCount().then((r) => setChatUnread(r.unread_count)).catch(() => {})

    applyShare()

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

    // Есть непрочитанные — открываемся на разделителе, а не в самом низу
    if (firstUnreadId.value && messages.value.some((m) => m.id === firstUnreadId.value)) {
      await nextTick()
      await nextTick()
      document.querySelector('.msg-unread')?.scrollIntoView({ block: 'start' })
    } else {
      await scrollToBottom()
    }
  } catch (e) {
    if (request === openRequest) error.value = e.message || 'Не удалось открыть диалог'
  } finally {
    if (request === openRequest) loadingChat.value = false
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

  const conversationId = activeConversation.value.id

  try {
    const data = await chatApi.show(conversationId, {
      beforeId: oldestId.value,
      limit: 40,
      markRead: false,
    })

    // Пока грузили историю, открыли другой диалог
    if (activeConversation.value?.id !== conversationId) return

    const older = data.messages ?? []

    if (older.length) {
      messages.value = mergeMessages(messages.value, older).list
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

  atBottom.value = isNearBottom()

  if (atBottom.value) newBelow.value = 0

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
  saveDraftNow()
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

    await pushOwnMessage(data.message)
  } catch (e) {
    error.value = e.message || 'Не удалось отправить'

    body.value = text
    saveDraftNow()
    pendingAttachments.value = attachmentsBackup
    replyTo.value = replyId
        ? messages.value.find(m => m.id === replyId)
        : null
  } finally {
    sending.value = false
  }
}

/**
 * Своё сообщение в ленту и наверх списка диалогов.
 *
 * Общая точка для текста, стикера, голосового и подарка: опрос мог уже
 * принести это сообщение, поэтому только через слияние.
 */
async function pushOwnMessage(message, conversationId = activeConversation.value?.id) {
  if (conversationId === activeConversation.value?.id) {
    messages.value = mergeMessages(messages.value, message).list
    renderLimit.value = RENDER_WINDOW
    // Своё сообщение — всё выше уже прочитано
    firstUnreadId.value = null
  }

  bumpConversation(conversationId, message)

  await scrollToBottom(true)
}

/** Диалог с новым сообщением — наверх списка, с новым превью. */
function bumpConversation(conversationId, message) {
  const idx = conversations.value.findIndex((c) => c.id === conversationId)

  if (idx === -1) return false

  const [conversation] = conversations.value.splice(idx, 1)

  conversation.last_message = message
  conversation.last_message_at = message.created_at
  conversations.value.unshift(conversation)

  return true
}

// ============================================
// ГОЛОСОВЫЕ
// ============================================

const voiceRecording = ref(false)
const voiceSending = ref(false)

async function sendVoice({ blob, duration }) {
  const conversationId = activeConversation.value?.id

  if (!conversationId) return

  voiceSending.value = true
  error.value = ''

  const replyId = replyTo.value?.id ?? null

  try {
    const uploaded = await chatApi.uploadVoice(blob, duration)
    const data = await chatApi.send(conversationId, '', replyId, [uploaded.attachment.id])

    replyTo.value = null
    await pushOwnMessage(data.message, conversationId)
  } catch (e) {
    error.value = e.message || 'Не удалось отправить голосовое'
  } finally {
    voiceSending.value = false
  }
}

// ============================================
// ПОДАРОК
// ============================================

const showGift = ref(false)

async function onGiftSent(data) {
  showGift.value = false

  // Баланс в шапке — сразу, как после покупки в магазине
  if (auth.user && data.balance !== undefined) auth.user.apex_coins = data.balance

  await pushOwnMessage(data.message)
}

// ============================================
// БЕСЕДЫ
// ============================================

/** null — закрыто, 'create' — новая беседа, 'manage' — открытая. */
const groupModal = ref(null)

async function onGroupCreated(conversation) {
  groupModal.value = null

  await loadConversations()
  router.push(`/messages/${conversation.id}`)
}

function onGroupUpdated(data) {
  if (data.conversation) applyConversation(data.conversation)

  if (data.message) pushOwnMessage(data.message, data.conversation?.id)
}

function onGroupLeft(id) {
  groupModal.value = null
  dropConversation(id)
}

/** Новые данные диалога: в открытый и в список. */
function applyConversation(conversation) {
  if (activeConversation.value?.id === conversation.id) {
    activeConversation.value = { ...activeConversation.value, ...conversation }
  }

  const idx = conversations.value.findIndex((c) => c.id === conversation.id)

  if (idx !== -1) {
    conversations.value[idx] = { ...conversations.value[idx], ...conversation }
  }
}

/** Диалога у меня больше нет: вышел из беседы или исключили. */
function dropConversation(id) {
  conversations.value = conversations.value.filter((c) => c.id !== id)
  drafts.value = writeDraft(drafts.value, id, '')

  if (activeConversation.value?.id === id) {
    draftOwner = null
    closeChat()
  }
}

/** Название диалога для списка и шапки. */
function conversationTitle(conversation) {
  if (conversation.type === 'group') return conversation.title || 'Беседа'
  if (conversation.type === 'clan_message') return conversation.clan?.name || 'Клан'

  return (conversation.users || []).find((u) => u.id !== auth.user?.id)?.username || 'Диалог'
}

// ============================================
// СТИКЕРЫ
// ============================================

const stickerPanel = ref(false)
const stickerPacks = ref([])
const stickersLoading = ref(false)
let stickersLoaded = false

async function toggleStickers() {
  stickerPanel.value = !stickerPanel.value

  if (!stickerPanel.value || stickersLoaded) return

  stickersLoading.value = true
  try {
    stickerPacks.value = (await shopApi.stickers()).packs ?? []
    stickersLoaded = true
  } catch {
    stickerPacks.value = []
  } finally {
    stickersLoading.value = false
  }
}

/** Стикер уходит обычным сообщением-меткой; сервер проверит, что набор есть. */
async function sendSticker(pack, id) {
  const conversationId = activeConversation.value?.id

  if (!conversationId || sending.value) return

  stickerPanel.value = false
  sending.value = true

  /*
   * Стикер уходит отдельно и не трогает поле ввода. Раньше он подменял
   * собой набранный текст, и недописанное сообщение терялось.
   */
  try {
    const data = await chatApi.send(conversationId, stickerToken(pack, id), replyTo.value?.id ?? null, [])

    replyTo.value = null
    await pushOwnMessage(data.message, conversationId)
  } catch (e) {
    error.value = e.message || 'Не удалось отправить стикер'
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
  if (!canEdit(message, auth.user?.id)) return

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

    replaceMessage(data.message)

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

    forgetMessage(message.conversation_id, message.id)
  } catch (e) {
    error.value =
        e.message || 'Не удалось удалить сообщение'
  }
}

/** Изменённое сообщение — в ленту, закреп и превью списка. */
function replaceMessage(message) {
  const idx = messages.value.findIndex((m) => m.id === message.id)

  if (idx !== -1) {
    messages.value[idx] = { ...messages.value[idx], ...message, reactions: messages.value[idx].reactions }
  }

  const active = activeConversation.value

  if (active?.id === message.conversation_id && active.pinned_message?.id === message.id) {
    activeConversation.value = { ...active, pinned_message: { ...active.pinned_message, body: message.body } }
  }

  const conversation = conversations.value.find((c) => c.id === message.conversation_id)

  if (conversation?.last_message?.id === message.id) {
    conversation.last_message = { ...conversation.last_message, body: message.body }
  }
}

/** Перейти к сообщению, даже если оно выше загруженной истории. */
async function goToMessage(id) {
  if (!id) return

  await ensureMessageLoaded(id)
  await scrollToMessage(id)
}

/**
 * Сообщение удалено (мной или собеседником): из ленты, из закрепа и из
 * превью списка. Раньше закреп и превью показывали удалённый текст.
 */
function forgetMessage(conversationId, messageId, lastMessage) {
  messages.value = messages.value.filter((m) => m.id !== messageId)

  if (activeConversation.value?.id === conversationId && activeConversation.value.pinned_message_id === messageId) {
    activeConversation.value = { ...activeConversation.value, pinned_message_id: null, pinned_message: null }
  }

  const conversation = conversations.value.find((c) => c.id === conversationId)

  if (conversation && conversation.last_message?.id === messageId) {
    conversation.last_message = lastMessage !== undefined
        ? lastMessage
        : (conversationId === activeConversation.value?.id ? messages.value[messages.value.length - 1] ?? null : null)
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
  switchDraft(null)

  activeConversation.value = null
  messages.value = []
  firstUnreadId.value = null
  showGift.value = false

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

  clearTimeout(draftTimer)
  draftTimer = setTimeout(saveDraftNow, 400)
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

/* ---------- Фильтры и избранные диалоги ---------- */

const CONV_FILTERS = [
  { id: 'all', label: 'Все' },
  { id: 'unread', label: 'Непрочитанные' },
  { id: 'direct', label: 'Личные' },
  { id: 'clan', label: 'Кланы' },
  { id: 'favorites', label: '★' },
]

const convFilter = ref('all')

// Избранное — удобство одного зрителя, хранится в браузере
function readFavorites() {
  try {
    return new Set(JSON.parse(localStorage.getItem('apex:chat-favorites') || '[]'))
  } catch {
    return new Set()
  }
}

const favorites = ref(readFavorites())

function toggleFavorite(id) {
  const next = new Set(favorites.value)
  next.has(id) ? next.delete(id) : next.add(id)
  favorites.value = next
  try {
    localStorage.setItem('apex:chat-favorites', JSON.stringify([...next]))
  } catch {
    // не запомним — не страшно
  }
}

const unreadTotal = computed(() => conversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0))

const filteredConversations = computed(() => {
  const list = conversations.value.filter((c) => {
    if (convFilter.value === 'unread') return c.unread_count > 0
    if (convFilter.value === 'direct') return c.type !== 'clan_message'
    if (convFilter.value === 'clan') return c.type === 'clan_message'
    if (convFilter.value === 'favorites') return favorites.value.has(c.id)
    return true
  })

  // Избранные — наверху, остальной порядок как пришёл (свежие сверху)
  return [...list].sort((a, b) => Number(favorites.value.has(b.id)) - Number(favorites.value.has(a.id)))
})

/* ---------- Реакции и закреп ---------- */

const REACTIONS = ['❤️', '👍', '😂', '😮', '😢', '🔥', '💀', '👑']
const reactPickerId = ref(null)

async function toggleReaction(message, emoji) {
  if (!message?.id) return

  try {
    const res = await chatApi.react(message.id, emoji)

    // Объект из меню действий может быть копией — обновляем ленту по id
    setReactions(message.id, res.reactions ?? [])
  } catch (e) {
    error.value = e.message || 'Не удалось поставить реакцию.'
  }
}

function setReactions(messageId, reactions) {
  const target = messages.value.find((m) => m.id === messageId)

  if (target) target.reactions = withMine(reactions, auth.user?.id)
}

async function setPin(messageId) {
  if (!activeConversation.value) return

  try {
    const res = await chatApi.pin(activeConversation.value.id, messageId)
    activeConversation.value = { ...activeConversation.value, ...res.conversation }
  } catch (e) {
    error.value = e.message || 'Не удалось закрепить.'
  }
}

function togglePin(message) {
  setPin(activeConversation.value?.pinned_message_id === message.id ? null : message.id)
}

function unpin() {
  setPin(null)
}

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

/** Жалоба на чужое сообщение — модераторам в админку. */
function reportMessage(message) {
  if (!message) return
  openReport({ type: 'message', id: message.id, name: `Сообщение от ${message.user?.username ?? 'игрока'}` })
}

function actionReport() {
  const message = actionSheetMessage.value
  closeActionSheet()
  reportMessage(message)
}

/*
 * На телефоне нет наведения, и кнопок «изменить» и «удалить» не было
 * видно вовсе: своё сообщение нельзя было ни поправить, ни удалить.
 */
function actionEdit() {
  const message = actionSheetMessage.value

  closeActionSheet()
  startEdit(message)
}

function actionDelete() {
  const message = actionSheetMessage.value

  closeActionSheet()
  removeMessage(message)
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
    messages: [],
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
        switchDraft(null)
        activeConversation.value = null
        messages.value = []
      }
    },
    { immediate: true },
)

watch(() => route.query.share, takeShare, { immediate: true })

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

  const mine = message.user?.id === auth.user?.id

  // Подарили монеты — баланс в шапке растёт сразу
  if (!mine && message.kind === 'gift' && auth.user) {
    auth.user.apex_coins = (auth.user.apex_coins ?? 0) + Number(message.meta?.received || 0)
  }

  if (
      activeConversation.value &&
      message.conversation_id ===
      activeConversation.value.id
  ) {
    // Опрос мог принести его раньше сокета
    const { list, added } = mergeMessages(messages.value, message)

    if (added.length && !mine) noteArrivals(1)

    messages.value = list
    bumpConversation(message.conversation_id, message)

    /*
     * Раньше здесь заново грузилась вся история (40 сообщений) только
     * ради отметки прочтения — и в фоновой вкладке тоже.
     */
    if (!mine) markActiveRead()

    // Служебная отметка беседы: состав или название могли измениться
    if (message.kind === 'system') refreshActiveConversation()
  } else if (bumpConversation(message.conversation_id, message)) {
    if (!mine) {
      const conversation = conversations.value[0]

      conversation.unread_count = (conversation.unread_count || 0) + 1
      setChatUnread(chatUnread.value + 1)
    }
  } else {
    // Новый диалог или беседа, куда позвали
    loadConversations()

    if (!mine) setChatUnread(chatUnread.value + 1)
  }
})

/** Данные открытого диалога без истории: состав, название, закреп. */
async function refreshActiveConversation() {
  const id = activeConversation.value?.id

  if (!id) return

  try {
    const data = await chatApi.show(id, { limit: 1, markRead: false })

    if (activeConversation.value?.id === id) applyConversation(data.conversation)
  } catch {
    // не страшно: обновится при следующем открытии
  }
}

/*
 * Изменения в диалогах от собеседников и других вкладок: правка,
 * удаление, реакции, закреп, состав беседы.
 */
watch(latestChange, (change) => {
  if (!change) return

  const id = change.conversation_id
  const isActive = activeConversation.value?.id === id

  switch (change.kind) {
    case 'message.updated':
      if (change.message) replaceMessage(change.message)
      break

    case 'message.deleted':
      forgetMessage(id, change.message_id, change.last_message ?? null)
      break

    case 'reactions':
      if (isActive) setReactions(change.message_id, change.reactions)
      break

    case 'pinned':
      if (isActive) {
        activeConversation.value = {
          ...activeConversation.value,
          pinned_message_id: change.pinned_message_id,
          pinned_message: change.pinned_message,
        }
      }
      break

    case 'conversation': {
      const conversation = change.conversation

      // Меня исключили — беседа пропадает из списка
      if (conversation && !(conversation.users || []).some((u) => u.id === auth.user?.id)) {
        dropConversation(id)
      } else if (conversation) {
        applyConversation(conversation)
      }
      break
    }
  }
})

// ============================================
// LIFECYCLE
// ============================================

function onClickOutsideSearch(e) {
  if (showGift.value && !e.target.closest('.chat-gift')) showGift.value = false

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

/*
 * Высота страницы: над чатом бывают объявление и полоса события, их
 * высота заранее неизвестна. Меряем, где страница начинается, иначе
 * поле ввода уезжает за нижний край экрана.
 */
const pageEl = ref(null)
const pageTop = ref(0)

function measurePageTop() {
  if (!pageEl.value) return
  pageTop.value = Math.max(0, Math.round(pageEl.value.getBoundingClientRect().top + window.scrollY))
}

let topObserver = null

/*
 * Клавиатура на телефоне (особенно iPhone): Safari не уменьшает
 * страницу, а только видимую область и прокручивает страницу — поле
 * ввода повисало посередине с пустотой снизу. Пока клавиатура открыта,
 * чат ровно накрывает видимую область (visualViewport).
 */
const keyboard = ref({ open: false, top: 0, height: 0 })

function onViewportChange() {
  const vv = window.visualViewport
  if (!vv) return

  const phone = window.matchMedia('(max-width: 900px)').matches
  const open = phone && window.innerHeight - vv.height > 120

  const wasOpen = keyboard.value.open
  keyboard.value = { open, top: Math.round(vv.offsetTop), height: Math.round(vv.height) }

  if (open && !wasOpen) nextTick(() => scrollToBottom())
}

onMounted(() => {
  window.visualViewport?.addEventListener('resize', onViewportChange)
  window.visualViewport?.addEventListener('scroll', onViewportChange)
  document.addEventListener('visibilitychange', onVisibility)
  listTimer = setInterval(refreshListIfOffline, 20000)
})

onUnmounted(() => {
  window.visualViewport?.removeEventListener('resize', onViewportChange)
  window.visualViewport?.removeEventListener('scroll', onViewportChange)
  document.removeEventListener('visibilitychange', onVisibility)

  // Ушли со страницы посреди фразы — черновик сохраняем сразу
  saveDraftNow()
  clearTimeout(markTimer)
  clearInterval(listTimer)
})

onMounted(() => {
  loadConversations()

  nextTick(measurePageTop)
  window.addEventListener('resize', measurePageTop)
  // Объявление закрыли или подгрузилась полоса события — пересчитываем
  if (typeof ResizeObserver !== 'undefined') {
    topObserver = new ResizeObserver(measurePageTop)
    topObserver.observe(document.body)
  }

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
  window.removeEventListener('resize', measurePageTop)
  topObserver?.disconnect()

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
      ref="pageEl"
      class="messages-page"
      :class="{
      'messages-page--chat-open': showChatOnMobile,
      'messages-page--kb': keyboard.open,
    }"
      :style="{
        '--messages-top': `${pageTop}px`,
        '--kb-top': `${keyboard.top}px`,
        '--kb-height': `${keyboard.height}px`,
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
          <!-- Новая групповая беседа с друзьями -->
          <button
              class="conversations__new"
              type="button"
              title="Новая беседа"
              @click.stop="groupModal = 'create'"
          >
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M19 8v6M22 11h-6" />
            </svg>
          </button>

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

      <!-- Ссылкой поделились со страницы игрока, клана или лота -->
      <div v-if="shareText" class="conv-share">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17l5-5-5-5" /><path d="M4 18v-2a4 4 0 0 1 4-4h12" /></svg>
        <span>Выбери диалог — ссылка встанет в поле ввода.</span>
        <button type="button" aria-label="Отменить" @click="shareText = ''">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </div>

      <!-- Фильтры списка -->
      <div v-if="!loadingList && conversations.length" class="conv-filters">
        <button
            v-for="f in CONV_FILTERS"
            :key="f.id"
            type="button"
            class="conv-filters__btn"
            :class="{ 'is-on': convFilter === f.id }"
            @click="convFilter = f.id"
        >
          {{ f.label }}
          <b v-if="f.id === 'unread' && unreadTotal">{{ unreadTotal }}</b>
        </button>
      </div>

      <p v-if="!loadingList && conversations.length && !filteredConversations.length" class="conv-filters__empty">
        {{ convFilter === 'favorites' ? 'Отметь диалог звёздочкой — он будет здесь.' : 'Здесь пусто.' }}
      </p>

      <ul
          v-if="!loadingList && conversations.length"
          class="conversations__list scroll-thin"
      >
        <li
            v-for="c in filteredConversations"
            :key="c.id"
            class="conv-item"
        >
          <!-- Избранное: наверху списка и во вкладке «★» -->
          <button
              type="button"
              class="conv-star"
              :class="{ 'is-on': favorites.has(c.id) }"
              :title="favorites.has(c.id) ? 'Убрать из избранного' : 'В избранное'"
              @click.stop.prevent="toggleFavorite(c.id)"
          >★</button>
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

              <!-- Беседа: первая буква названия на плашке -->
              <div v-else-if="c.type === 'group'" class="conv__group">
                {{ (c.title || 'Б').charAt(0).toUpperCase() }}
              </div>

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

                <template v-else-if="c.type === 'group'">
                  <span class="conv__group-title">{{ c.title }}</span>
                  <span class="conv__tag">{{ (c.users || []).length }}</span>
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
                <!-- Недописанное сообщение важнее последнего -->
                <template v-if="draftOf(c)">
                  <span class="conv__preview-draft">Черновик:</span>
                  {{ draftOf(c) }}
                </template>

                <template v-else-if="c.last_message">
                  <span v-if="c.last_message.kind !== 'system'" class="conv__preview-author">
                    {{ c.last_message.user?.id === auth.user?.id ? 'Вы' : c.last_message.user?.username }}:
                  </span>

                  {{ previewText(c.last_message) }}
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
                {{ conversationTime(c.last_message_at) }}
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

              <div class="chat__head-sub" :class="{ 'chat__head-sub--typing': typingPeer }">
                <template v-if="typingPeer">{{ typingPeer.username }} печатает…</template>
                <template v-else>Обращение от {{ activeConversation.author?.username }}</template>
              </div>
            </template>

            <!-- Беседа: клик — участники и настройки -->
            <template v-else-if="isGroup">
              <div class="chat__head-name chat__head-name--link" title="Участники беседы" @click="groupModal = 'manage'">
                {{ activeConversation.title }}
              </div>

              <div class="chat__head-sub" :class="{ 'chat__head-sub--typing': typingPeer }">
                <template v-if="typingPeer">{{ typingPeer.username }} печатает…</template>
                <template v-else>Беседа · {{ (activeConversation.users || []).length }} участн.</template>
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

          <button
              v-if="isGroup"
              type="button"
              class="chat__head-btn"
              title="Участники беседы"
              @click="groupModal = 'manage'"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </button>

          <div class="chat__head-accent"></div>
        </header>

        <!-- Закреплённое сообщение: клик — перейти к нему -->
        <div v-if="activeConversation?.pinned_message" class="chat__pinned">
          <button type="button" class="chat__pinned-body" @click="goToMessage(activeConversation.pinned_message.id)">
            <span class="chat__pinned-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5" /><path d="M9 10.8V4h6v6.8l3 3.2v2H6v-2z" /></svg></span>
            <span class="chat__pinned-text">
              <b>Закреплено{{ activeConversation.pinned_message.user ? ` · ${activeConversation.pinned_message.user.username}` : '' }}</b>
              <span>{{ previewText(activeConversation.pinned_message) || 'Вложение' }}</span>
            </span>
          </button>
          <button type="button" class="chat__pinned-close" title="Открепить" aria-label="Открепить" @click="unpin">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>

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

            <template
                v-for="(m, index) in visibleMessages"
                :key="m.id"
            >
            <!-- Разделитель по дням, как в Telegram -->
            <div
                v-if="startsNewDay(m, visibleMessages[index - 1])"
                class="msg-day"
            >
              <span>{{ dayLabel(m.created_at) }}</span>
            </div>

            <!-- С этого места — то, что пришло, пока диалог был закрыт -->
            <div v-if="m.id === firstUnreadId" class="msg-unread">
              <span>Новые сообщения</span>
            </div>

            <!-- Служебная отметка беседы: кто вошёл, вышел, переименовал -->
            <div v-if="m.kind === 'system'" :data-message-id="m.id" class="msg-system">
              <span>{{ m.body }}</span>
            </div>

            <div
                v-else
                :data-message-id="m.id"
                class="msg"
                :class="{
                'msg--mine':
                  m.user?.id === auth.user?.id,
                'msg--gift': m.kind === 'gift',
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
                <!-- Оформление сообщений автора из магазина: видно собеседнику -->
                <div
                    class="msg__bubble"
                    :class="[
                      m.user?.chat_bubble && !parseSticker(m.body) && m.kind !== 'gift' ? ['bubble-fx', `bubble-fx--${m.user.chat_bubble}`] : null,
                      { 'msg__bubble--sticker': parseSticker(m.body), 'msg__bubble--gift': m.kind === 'gift' },
                    ]"
                    title="Двойной клик — ❤️"
                    @dblclick="toggleReaction(m, '❤️')"
                >
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
                      @click="goToMessage(m.reply_to.id)"
                  >
                    <div class="msg__reply-author">
                      {{ m.reply_to.user?.username }}
                    </div>

                    <div class="msg__reply-body">
                      {{ previewText(m.reply_to) || 'Вложение' }}
                    </div>
                  </div>

                  <!-- Подарок монет: посылка с суммой -->
                  <div v-if="m.kind === 'gift'" class="msg__gift">
                    <PixelSprite sprite="diamond" :size="30" anim="bounce" glow="rgba(251, 191, 36, .7)" />
                    <div class="msg__gift-body">
                      <span class="msg__gift-label">
                        {{ m.user?.id === auth.user?.id ? 'Вы подарили' : 'Вам подарок' }}
                      </span>
                      <strong class="msg__gift-amount">{{ Number(m.meta?.amount || 0).toLocaleString('ru-RU') }} ApexCoin</strong>
                      <span v-if="m.meta?.fee" class="msg__gift-fee">дошло {{ Number(m.meta.received).toLocaleString('ru-RU') }}, комиссия {{ m.meta.fee }}</span>
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
                      <ChatVoicePlayer v-if="file.is_voice" :file="file" />

                      <button
                          v-else-if="file.is_image"
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
                        maxlength="5000"
                        @keydown.esc.prevent="cancelEdit"
                        @keydown.enter.ctrl.prevent="saveEdit(m)"
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
                      <!-- Стикер из магазина: картинка вместо текста-метки -->
                      <ChatSticker v-if="parseSticker(m.body)" :sticker="parseSticker(m.body)" />
                      <template v-else>{{ m.body }}</template>
                    </span>

                    <!-- Ссылки на игрока, питомца, клан или лот — живыми карточками -->
                    <span v-if="m.cards?.length" class="msg__cards">
                      <ChatLinkCard v-for="card in m.cards" :key="card.url" :card="card" />
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

                <!-- Реакции: эмодзи со счётчиком, нажать — поставить или снять -->
                <div v-if="m.reactions?.length" class="msg__reactions">
                  <button
                      v-for="r in m.reactions"
                      :key="r.emoji"
                      type="button"
                      class="msg__reaction"
                      :class="{ 'is-mine': r.mine }"
                      @click="toggleReaction(m, r.emoji)"
                  >{{ r.emoji }} <b>{{ r.count }}</b></button>
                </div>

                <!-- Выбор реакции -->
                <div v-if="reactPickerId === m.id" class="msg__react-picker">
                  <button
                      v-for="emoji in REACTIONS"
                      :key="emoji"
                      type="button"
                      @click="toggleReaction(m, emoji); reactPickerId = null"
                  >{{ emoji }}</button>
                </div>
              </div>

              <div class="msg__actions">
                <button
                    class="msg__action"
                    type="button"
                    title="Реакция"
                    @click="reactPickerId = reactPickerId === m.id ? null : m.id"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M8.5 14.5s1.3 1.8 3.5 1.8 3.5-1.8 3.5-1.8" />
                    <path d="M9 9.5h.01M15 9.5h.01" />
                  </svg>
                </button>
                <button
                    class="msg__action"
                    :class="{ 'msg__action--on': activeConversation?.pinned_message_id === m.id }"
                    type="button"
                    :title="activeConversation?.pinned_message_id === m.id ? 'Открепить' : 'Закрепить'"
                    @click="togglePin(m)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 17v5" />
                    <path d="M9 10.8V4h6v6.8l3 3.2v2H6v-2z" />
                  </svg>
                </button>
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
                    v-if="m.kind !== 'gift'"
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

                <!-- Пожаловаться: только на чужое сообщение -->
                <button
                    v-if="m.user?.id !== auth.user?.id"
                    class="msg__action"
                    type="button"
                    title="Пожаловаться"
                    @click="reportMessage(m)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 22V4" />
                    <path d="M4 4h12l-2 4 2 4H4" />
                  </svg>
                </button>

                <template
                    v-if="
                    m.user?.id ===
                    auth.user?.id
                  "
                >
                  <button
                      v-if="canEdit(m, auth.user?.id)"
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

        <!-- IMAGE PREVIEW: в body, иначе картинка уходит под шапку сайта -->

        <Teleport to="body">
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
        </Teleport>

        <!-- FOOTER -->

        <footer class="chat__footer">
          <!-- Новые сообщения ниже, а лента прокручена вверх -->
          <button
              v-if="!atBottom && messages.length"
              type="button"
              class="chat__to-bottom"
              :title="newBelow ? `Новых: ${newBelow}` : 'Вниз'"
              @click="jumpToBottom"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7" /></svg>
            <b v-if="newBelow">{{ newBelow > 99 ? '99+' : newBelow }}</b>
          </button>

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
                {{ (previewText(replyTo) || 'Вложение').slice(0, 100) }}
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
                aria-label="Скрыть"
                @click="error = ''"
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
          </div>

          <div
              v-if="activeConversation"
              class="chat__attach-row"
          >
            <ChatAttachmentsInput
                ref="attachInput"
                v-model="pendingAttachments"
                :max="10"
                inline
            />
          </div>

          <form
              class="chat__form"
              :class="{ 'is-recording': voiceRecording }"
              @submit.prevent="sendMessage"
          >
            <!-- Скрепка: файлы до 12 МБ, до 10 штук -->
            <button
                v-if="activeConversation"
                type="button"
                class="chat__attach"
                :class="{ 'is-busy': attachInput?.uploading }"
                :disabled="pendingAttachments.length >= 10"
                :title="pendingAttachments.length >= 10 ? 'Не больше 10 файлов' : 'Прикрепить файлы (до 12 МБ)'"
                @click="attachInput?.open()"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
              </svg>
              <span v-if="pendingAttachments.length" class="chat__attach-count">{{ pendingAttachments.length }}</span>
            </button>

            <!-- Стикеры: свои наборы из магазина -->
            <div class="sticker-picker">
              <button
                  type="button"
                  class="sticker-picker__toggle"
                  :class="{ 'is-open': stickerPanel }"
                  title="Стикеры"
                  @click="toggleStickers"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M15.5 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z" />
                  <path d="M15 3v6h6" />
                  <path d="M8 14s1.5 2 4 2 4-2 4-2" />
                  <path d="M9 9h.01M12 9h.01" />
                </svg>
              </button>

              <div v-if="stickerPanel" class="sticker-picker__panel">
                <div v-if="stickersLoading" class="sticker-picker__empty">Загружаем…</div>

                <div v-else-if="!stickerPacks.length" class="sticker-picker__empty">
                  Стикеров пока нет.
                  <RouterLink to="/shop?type=sticker_pack">Купить в магазине →</RouterLink>
                </div>

                <template v-else>
                  <div v-for="pack in stickerPacks" :key="pack.id" class="sticker-picker__pack">
                    <span class="sticker-picker__title">{{ pack.name.replace(/^Стикеры\s+/, '').replace(/[«»]/g, '') }}</span>
                    <div class="sticker-picker__grid">
                      <button
                          v-for="id in pack.stickers"
                          :key="id"
                          type="button"
                          class="sticker-picker__item"
                          :disabled="sending"
                          @click="sendSticker(pack.id, id)"
                      >
                        <ChatSticker v-if="STICKER_PACKS[pack.id]?.[id]" :sticker="STICKER_PACKS[pack.id][id]" :size="40" />
                      </button>
                    </div>
                  </div>
                </template>
              </div>
            </div>

            <!-- Подарок монет: только в личном диалоге -->
            <div v-if="directPartner" class="chat-gift">
              <button
                  type="button"
                  class="chat__attach chat-gift__toggle"
                  :class="{ 'is-open': showGift }"
                  title="Подарить ApexCoin"
                  @click.stop="showGift = !showGift"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 12v9H4v-9" />
                  <path d="M2 7h20v5H2z" />
                  <path d="M12 21V7" />
                  <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z" />
                </svg>
              </button>

              <ChatGiftPanel
                  v-if="showGift"
                  class="chat-gift__panel"
                  :conversation-id="activeConversation.id"
                  :partner="directPartner"
                  @sent="onGiftSent"
                  @close="showGift = false"
              />
            </div>

            <textarea
                ref="inputEl"
                v-model="body"
                class="chat__input"
                placeholder="Написать сообщение..."
                maxlength="5000"
                rows="1"
                @input="onBodyInput"
                @keydown.enter.exact.prevent="
                sendMessage
              "
            />

            <!-- Поле пустое — вместо отправки микрофон, как в мессенджерах -->
            <ChatVoiceRecorder
                v-if="!body.trim() && !pendingAttachments.length"
                :disabled="voiceSending || sending"
                @recorded="sendVoice"
                @error="(message) => (error = message)"
                @state="(on) => (voiceRecording = on)"
            />

            <button
                v-else
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
                      {{ bridgeRankOf(u) ? 'Звание' : 'Тир' }}
                      <b
                          :style="{
                          color:
                            rankColor(u, '#6b7280'),
                            
                        }"
                      >
                        {{ rankLabel(u) }}
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
                        {{ bridgeRankOf(u) ? 'Звание' : 'Тир' }}
                        <b
                            :style="{
                            color:
                              rankColor(u, '#6b7280'),
                              
                          }"
                        >
                          {{ rankLabel(u) }}
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

            <!-- Реакция одним касанием -->
            <div v-if="actionSheetMessage" class="action-sheet__reactions">
              <button
                  v-for="emoji in REACTIONS"
                  :key="emoji"
                  type="button"
                  :class="{ 'is-mine': actionSheetMessage.reactions?.some((r) => r.emoji === emoji && r.mine) }"
                  @click="toggleReaction(actionSheetMessage, emoji); closeActionSheet()"
              >{{ emoji }}</button>
            </div>

            <button
                v-if="actionSheetMessage"
                type="button"
                class="action-sheet__item"
                @click="togglePin(actionSheetMessage); closeActionSheet()"
            >
              <span class="action-sheet__icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5" /><path d="M9 10.8V4h6v6.8l3 3.2v2H6v-2z" /></svg></span>
              {{ activeConversation?.pinned_message_id === actionSheetMessage.id ? 'Открепить' : 'Закрепить' }}
            </button>

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
                {{ previewText(actionSheetMessage).slice(0, 120) }}
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
                v-if="canEdit(actionSheetMessage, auth.user?.id)"
                class="action-sheet__item"
                type="button"
                @click="actionEdit"
            >
              <span class="action-sheet__icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
              </span>

              Изменить
            </button>

            <button
                v-if="actionSheetMessage?.kind !== 'gift'"
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
                v-if="actionSheetMessage && actionSheetMessage.user?.id !== auth.user?.id"
                class="action-sheet__item"
                type="button"
                @click="actionReport"
            >
              <span class="action-sheet__icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 22V4" />
                  <path d="M4 4h12l-2 4 2 4H4" />
                </svg>
              </span>

              Пожаловаться
            </button>

            <button
                v-if="actionSheetMessage && actionSheetMessage.user?.id === auth.user?.id"
                class="action-sheet__item action-sheet__item--danger"
                type="button"
                @click="actionDelete"
            >
              <span class="action-sheet__icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 16h10l1-16" /></svg>
              </span>

              Удалить
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

    <!-- Беседа: создать новую или управлять открытой -->
    <ChatGroupModal
        v-if="groupModal"
        :conversation="groupModal === 'manage' ? activeConversation : null"
        :me-id="auth.user?.id"
        @close="groupModal = null"
        @created="onGroupCreated"
        @updated="onGroupUpdated"
        @left="onGroupLeft"
    />
  </div>
</template>

<style scoped>
@import "@/views/chat/Messages.css";
</style>