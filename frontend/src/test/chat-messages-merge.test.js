/**
 * Слияние сообщений: одно и то же сообщение приходит ответом на
 * отправку, по сокету и опросом — в списке оно должно быть одно.
 */
import { describe, expect, it } from 'vitest'
import { mergeMessages } from '@/utils/chatMessages.js'

describe('mergeMessages', () => {
  it('не дублирует сообщение, пришедшее дважды', () => {
    const current = [{ id: 1 }, { id: 2 }]

    const fromPoll = mergeMessages(current, [{ id: 3 }]).list
    const { list, added } = mergeMessages(fromPoll, { id: 3, body: 'ответ на отправку' })

    expect(list.map((m) => m.id)).toEqual([1, 2, 3])
    expect(added).toEqual([])
  })

  it('сохраняет локальные правки известного сообщения', () => {
    const current = [{ id: 1, reads: [{ user_id: 2 }] }]

    const { list } = mergeMessages(current, [{ id: 1, reads: [] }])

    expect(list[0].reads).toHaveLength(1)
  })

  it('держит порядок по id, даже если старое пришло позже', () => {
    const { list } = mergeMessages([{ id: 5 }, { id: 9 }], [{ id: 7 }, { id: 2 }])

    expect(list.map((m) => m.id)).toEqual([2, 5, 7, 9])
  })

  it('без новых сообщений возвращает тот же массив', () => {
    const current = [{ id: 1 }]

    expect(mergeMessages(current, [{ id: 1 }]).list).toBe(current)
  })

  it('пропускает пустые записи', () => {
    const { list } = mergeMessages([], [null, { body: 'без id' }, { id: 4 }])

    expect(list).toEqual([{ id: 4 }])
  })
})
