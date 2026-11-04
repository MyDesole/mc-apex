import { describe, expect, it } from 'vitest'
import { rankShort, shortRankLabel } from '@/utils/playerStyling.js'

describe('короткие звания бриджа', () => {
  it('берёт инициалы слов и переводит кириллицу в латиницу', () => {
    expect(shortRankLabel('BRIDGE ПРОФИ')).toBe('B.P')
    expect(shortRankLabel('BRIDGE БОЕЦ')).toBe('B.B')
    expect(shortRankLabel('Мастер моста')).toBe('M.M')
    expect(shortRankLabel('Bridge Pro Max')).toBe('B.P.M')
  })

  it('короткое слово оставляет, длинное режет до трёх букв', () => {
    expect(shortRankLabel('Telly')).toBe('TELLY')
    expect(shortRankLabel('Легенда')).toBe('ЛЕГ')
    expect(shortRankLabel('')).toBe('')
    expect(shortRankLabel(null)).toBe('')
  })

  it('для тира отдаёт тир, для бриджера сокращённое звание', () => {
    expect(rankShort({ tier: 'HT3' })).toBe('HT3')
    expect(rankShort({ tier: 'HT3', display_rank: { type: 'bridge', label: 'BRIDGE ПРОФИ' } })).toBe('B.P')
    expect(rankShort({})).toBe('—')
  })
})
