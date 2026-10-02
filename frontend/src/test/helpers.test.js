/**
 * Тесты общих хелперов оформления и форматирования.
 *
 * Эти функции были скопированы по нескольким файлам и успели
 * разойтись, поэтому важно закрепить их поведение: заглушки для
 * пустых значений и согласование слов ломаются незаметно.
 */
import { describe, expect, it } from 'vitest'

import {
  accent,
  avatarFrame,
  avatarLetter,
  isLegendary,
  profileEffectStyle,
  roleLabel,
  scoreOf,
} from '@/utils/playerStyling.js'

import {
  formatDate,
  formatDateLong,
  formatDateTime,
  formatDayMonth,
  pluralDays,
} from '@/utils/format.js'

describe('avatarLetter', () => {
  it('берёт первую букву ника', () => {
    expect(avatarLetter('Никита')).toBe('Н')
    expect(avatarLetter('player')).toBe('P')
  })

  it('подставляет заглушку вместо пустого значения', () => {
    expect(avatarLetter('')).toBe('?')
    expect(avatarLetter(null)).toBe('?')
    expect(avatarLetter(undefined)).toBe('?')
  })

  it('не падает, если передан объект', () => {
    // Раньше одна из копий принимала объект участника, другая — строку
    expect(() => avatarLetter({ username: 'Тест' })).not.toThrow()
    expect(() => avatarLetter({ id: 1 })).not.toThrow()
  })
})

describe('accent', () => {
  it('берёт цвет по тиру', () => {
    expect(accent({ tier: 'S' })).toBe('#facc15')
    expect(accent({ tier: 'A' })).toBe('#f97316')
  })

  it('возвращает запасной цвет без тира', () => {
    expect(accent(null)).toBe('#7c3aed')
    expect(accent({})).toBe('#7c3aed')
    expect(accent({ tier: 'X' })).toBe('#7c3aed')
  })
})

describe('scoreOf', () => {
  it('читает рейтинговый балл', () => {
    expect(scoreOf({ rating_score: 87 })).toBe(87)
  })

  it('возвращает ноль вместо пустого значения', () => {
    expect(scoreOf(null)).toBe(0)
    expect(scoreOf({})).toBe(0)
  })
})

describe('roleLabel', () => {
  it('переводит роль', () => {
    expect(roleLabel({ role: 'admin' })).toBe('Администратор')
    expect(roleLabel({ role: 'tester' })).toBe('Тестер')
  })

  it('молчит про обычного игрока', () => {
    expect(roleLabel({ role: 'player' })).toBe('')
    expect(roleLabel({})).toBe('')
    expect(roleLabel(null)).toBe('')
  })
})

describe('avatarFrame', () => {
  it('ничего не задаёт для рамки по умолчанию', () => {
    expect(avatarFrame({ avatar_frame: 'default' })).toEqual({})
    expect(avatarFrame({})).toEqual({})
  })

  it('задаёт градиент для особых рамок', () => {
    expect(avatarFrame({ avatar_frame: 'rainbow' })['--frame-gradient']).toBeTruthy()
    expect(avatarFrame({ avatar_frame: 'legendary' })['--frame-gradient']).toBeTruthy()
    expect(avatarFrame({ avatar_frame: 'season1' })['--frame-gradient']).toBeTruthy()
  })

  it('задаёт сплошной цвет для обычной рамки', () => {
    const style = avatarFrame({ avatar_frame: 'gold', tier: 'A' })

    expect(style['--frame-color']).toBe('#facc15')
    expect(style['--frame-gradient']).toBeUndefined()
  })
})

describe('profileEffectStyle', () => {
  it('задаёт цвет эффекта', () => {
    expect(profileEffectStyle({ profile_effect: 'fire' })).toEqual({ '--effect-color': '#f97316' })
    expect(profileEffectStyle({ profile_effect: 'ice' })).toEqual({ '--effect-color': '#06b6d4' })
  })

  it('ничего не задаёт без эффекта', () => {
    expect(profileEffectStyle({})).toEqual({})
    expect(profileEffectStyle(null)).toEqual({})
  })

  it('распознаёт легендарный эффект', () => {
    expect(isLegendary({ profile_effect: 'legendary' })).toBe(true)
    expect(isLegendary({ profile_effect: 'fire' })).toBe(false)
    expect(isLegendary(null)).toBe(false)
  })
})

describe('форматирование дат', () => {
  const date = '2026-10-05T12:00:00Z'

  it('formatDate даёт дату числом', () => {
    expect(formatDate(date)).toMatch(/\d{2}\.\d{2}\.\d{4}/)
  })

  it('остальные дают непустую строку', () => {
    expect(formatDateLong(date).length).toBeGreaterThan(5)
    expect(formatDateTime(date).length).toBeGreaterThan(5)
    expect(formatDayMonth(date).length).toBeGreaterThan(4)
  })

  it('пустое значение даёт пустую строку', () => {
    for (const fn of [formatDate, formatDateLong, formatDateTime, formatDayMonth]) {
      expect(fn(null)).toBe('')
      expect(fn('')).toBe('')
      expect(fn(undefined)).toBe('')
    }
  })
})

describe('pluralDays', () => {
  it('согласует слово с числом', () => {
    expect(pluralDays(1)).toBe('день')
    expect(pluralDays(2)).toBe('дня')
    expect(pluralDays(4)).toBe('дня')
    expect(pluralDays(5)).toBe('дней')
    expect(pluralDays(0)).toBe('дней')
  })

  it('учитывает исключения 11–14', () => {
    expect(pluralDays(11)).toBe('дней')
    expect(pluralDays(12)).toBe('дней')
    expect(pluralDays(13)).toBe('дней')
    expect(pluralDays(14)).toBe('дней')
  })

  it('учитывает десятки', () => {
    expect(pluralDays(21)).toBe('день')
    expect(pluralDays(22)).toBe('дня')
    expect(pluralDays(25)).toBe('дней')
  })
})
