/**
 * Подписи сообщений для мест, где нет места самому сообщению: список
 * диалогов, закреп, цитата ответа, меню действий.
 *
 * Раньше там показывался сырой текст: метка стикера «[sticker:mobs/bee]»,
 * пустая строка у вложения и у голосового.
 */
import { parseSticker } from '@/data/shop/stickers.js'

/**
 * @param {{ kind?: string, body?: string, meta?: object, attachments?: Array }|null} message
 */
export function previewText(message) {
    if (!message) return ''

    if (message.kind === 'gift') {
        const amount = message.meta?.amount
        const note = (message.body || '').trim()

        return `Подарок: ${amount ?? ''} ApexCoin${note ? ` · ${note}` : ''}`
    }

    if (message.kind === 'system') return message.body || ''

    const sticker = parseSticker(message.body)

    if (sticker) return `Стикер «${sticker.caption}»`

    const text = (message.body || '').trim()

    if (text) return text

    const files = message.attachments || []

    if (files.some((file) => file.is_voice)) return 'Голосовое сообщение'
    if (files.length && files.every((file) => file.is_image)) return files.length > 1 ? `Фото: ${files.length}` : 'Фото'
    if (files.length) return files.length > 1 ? `Файлы: ${files.length}` : 'Файл'

    return ''
}

/** Можно ли править сообщение: только свой обычный текст, не стикер. */
export function canEdit(message, meId) {
    return Boolean(message)
        && message.user?.id === meId
        && (message.kind ?? 'text') === 'text'
        && !parseSticker(message.body)
}

/**
 * Реакции из события по сокету: оно одно на всех, поэтому «моя ли»
 * считаем по списку user_ids.
 */
export function withMine(reactions, meId) {
    return (reactions || []).map((reaction) => ({
        ...reaction,
        mine: Array.isArray(reaction.user_ids) ? reaction.user_ids.includes(meId) : Boolean(reaction.mine),
    }))
}

/** Время в списке диалогов: сегодня — часы, раньше — дата. */
export function conversationTime(value, now = new Date()) {
    if (!value) return ''

    const date = new Date(value)

    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    }

    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: 'short' })
}

/** Длительность голосового: 0:07, 1:30. */
export function formatDuration(seconds) {
    const total = Math.max(0, Math.round(Number(seconds) || 0))

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
}

/* ----------------------------- Черновики ----------------------------- */

const DRAFTS_KEY = 'apex:chat-drafts'

/**
 * Черновики — удобство одного браузера: { [conversationId]: текст }.
 * Хранилище может быть недоступно (приватное окно) — тогда просто не
 * запоминаем.
 */
export function readDrafts() {
    try {
        const value = JSON.parse(localStorage.getItem(DRAFTS_KEY) || '{}')

        return value && typeof value === 'object' ? value : {}
    } catch {
        return {}
    }
}

export function writeDraft(drafts, conversationId, text) {
    const next = { ...drafts }
    const value = String(text ?? '')

    if (value.trim()) next[conversationId] = value.slice(0, 2000)
    else delete next[conversationId]

    try {
        localStorage.setItem(DRAFTS_KEY, JSON.stringify(next))
    } catch {
        // не запомним — не страшно
    }

    return next
}
