import { describe, expect, it } from 'vitest'
import { dayLabel, startsNewDay } from '@/utils/chatDates.js'

describe('разделители дней в чате', () => {
    const now = new Date(2026, 9, 25, 15, 0) // 25 октября 2026, 15:00

    it('сегодня и вчера — словами', () => {
        expect(dayLabel(new Date(2026, 9, 25, 0, 5), now)).toBe('Сегодня')
        expect(dayLabel(new Date(2026, 9, 24, 23, 59), now)).toBe('Вчера')
    })

    it('в этом году — число и месяц, в прошлые — с годом', () => {
        expect(dayLabel(new Date(2026, 8, 3, 12), now)).toBe('3 сентября')
        expect(dayLabel(new Date(2025, 11, 31, 12), now)).toBe('31 декабря 2025')
    })

    it('новый день — по календарю, а не по 24 часам', () => {
        const late = { created_at: new Date(2026, 9, 24, 23, 50).toISOString() }
        const early = { created_at: new Date(2026, 9, 25, 0, 10).toISOString() }
        const later = { created_at: new Date(2026, 9, 25, 9, 0).toISOString() }

        expect(startsNewDay(late, undefined)).toBe(true)
        expect(startsNewDay(early, late)).toBe(true)
        expect(startsNewDay(later, early)).toBe(false)
    })
})
