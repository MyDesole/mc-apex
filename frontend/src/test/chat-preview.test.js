/**
 * Подписи сообщений, право на правку, реакции из сокета и черновики.
 */
import { beforeEach, describe, expect, it } from 'vitest'
import { canEdit, conversationTime, formatDuration, previewText, readDrafts, withMine, writeDraft } from '@/utils/chatPreview.js'

describe('previewText', () => {
  it('стикер, подарок, голосовое и фото подписаны словами, а не сырым текстом', () => {
    expect(previewText({ body: '[sticker:mobs/bee]' })).toBe('Стикер «Жжж!»')
    expect(previewText({ kind: 'gift', body: 'на кейс', meta: { amount: 500 } })).toBe('Подарок: 500 ApexCoin · на кейс')
    expect(previewText({ body: '', attachments: [{ is_voice: true }] })).toBe('Голосовое сообщение')
    expect(previewText({ body: '', attachments: [{ is_image: true }, { is_image: true }] })).toBe('Фото: 2')
    expect(previewText({ body: '', attachments: [{ is_image: false }] })).toBe('Файл')
    expect(previewText({ body: '  привет ' })).toBe('привет')
    expect(previewText(null)).toBe('')
  })
})

describe('canEdit', () => {
  it('править можно только свой обычный текст', () => {
    expect(canEdit({ user: { id: 1 }, body: 'текст' }, 1)).toBe(true)
    expect(canEdit({ user: { id: 2 }, body: 'текст' }, 1)).toBe(false)
    expect(canEdit({ user: { id: 1 }, body: '[sticker:mobs/bee]' }, 1)).toBe(false)
    expect(canEdit({ user: { id: 1 }, kind: 'gift', body: '' }, 1)).toBe(false)
    expect(canEdit({ user: { id: 1 }, kind: 'system', body: 'x' }, 1)).toBe(false)
  })
})

describe('withMine', () => {
  it('«моя» реакция считается по user_ids, а не по чужому mine', () => {
    const result = withMine([{ emoji: '🔥', count: 2, mine: true, user_ids: [5, 7] }], 3)

    expect(result[0].mine).toBe(false)
    expect(withMine(result.map((r) => ({ ...r, user_ids: [3] })), 3)[0].mine).toBe(true)
  })
})

describe('время и длительность', () => {
  it('сегодня — часы, раньше — дата', () => {
    const now = new Date(2026, 9, 29, 18, 0)

    expect(conversationTime(new Date(2026, 9, 29, 9, 5).toISOString(), now)).toMatch(/09:05/)
    expect(conversationTime(new Date(2026, 9, 20, 9, 5).toISOString(), now)).toMatch(/20/)
  })

  it('длительность голосового', () => {
    expect(formatDuration(7)).toBe('0:07')
    expect(formatDuration(90)).toBe('1:30')
  })
})

describe('черновики', () => {
  beforeEach(() => localStorage.clear())

  it('запоминаются для каждого диалога и стираются пустым текстом', () => {
    let drafts = writeDraft({}, 4, 'недописанное')
    drafts = writeDraft(drafts, 9, 'другое')

    expect(readDrafts()).toEqual({ 4: 'недописанное', 9: 'другое' })

    drafts = writeDraft(drafts, 4, '   ')

    expect(readDrafts()).toEqual({ 9: 'другое' })
  })
})
