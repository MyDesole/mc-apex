/**
 * Пиксельные иконки ачивок: ровная сетка и только известные цвета.
 * Опечатка в одной строке сдвигает иконку — ловим это здесь.
 */
import { describe, expect, it } from 'vitest'
import { FIXED_COLORS, PIXEL_ICONS } from '@/data/achievements/pixelIcons.js'

const ALLOWED = new Set(['.', 'm', 'l', 'd', 'w', ...Object.keys(FIXED_COLORS)])

describe('PIXEL_ICONS', () => {
  for (const [name, rows] of Object.entries(PIXEL_ICONS)) {
    it(`${name}: 16×16 и известные цвета`, () => {
      expect(rows).toHaveLength(16)

      rows.forEach((row, y) => {
        expect(row.length, `${name}, строка ${y}`).toBe(16)

        for (const ch of row) {
          expect(ALLOWED.has(ch), `${name}, строка ${y}: «${ch}»`).toBe(true)
        }
      })
    })
  }

  it('в каталоге ачивок нет ключей без иконки', async () => {
    // Ключи из сидера бэкенда должны быть нарисованы
    const used = ['shield', 'swords', 'flag', 'head', 'flame', 'crown', 'bolt', 'sword', 'diamond',
      'emerald', 'star', 'book', 'trophy', 'pickaxe', 'orb', 'bed', 'tnt', 'heart', 'chat', 'chest', 'coin']

    used.forEach((key) => expect(PIXEL_ICONS[key], key).toBeDefined())
  })
})
