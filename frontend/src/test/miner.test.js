/**
 * «Шахтёр»: подписи событий забега и картинки, на которые ссылается страница.
 */
import { describe, expect, it } from 'vitest'
import { PIXEL_SPRITES } from '@/data/shop/pixelSprites.js'
import {
  ARTIFACT_SPRITES, BLOCKS, BLOCK_SPRITES, SPECIALS, TIER_BLOCKS, TRADE_SPRITES, UPGRADE_SPRITES,
  eventText, formatSeconds, plural,
} from '@/data/games/miner.js'

describe('miner data', () => {
  it('все картинки нарисованы и сетки ровные', () => {
    const keys = [BLOCK_SPRITES, UPGRADE_SPRITES, ARTIFACT_SPRITES, TRADE_SPRITES].flatMap(Object.values)
    keys.push('mine_chest_open')

    for (const key of keys) {
      const sprite = PIXEL_SPRITES[key]
      expect(sprite, key).toBeDefined()
      for (const row of sprite.rows) {
        for (const ch of row) expect(ch === '.' || ch in sprite.palette, `${key}: «${ch}»`).toBe(true)
      }
    }
  })

  it('у каждого блока легенды есть подпись', () => {
    for (const id of [...Object.values(TIER_BLOCKS).flat(), ...Object.keys(SPECIALS)]) {
      expect(BLOCKS[id], id).toBeTruthy()
    }
  })

  it('события забега превращаются в текст с тоном', () => {
    expect(eventText({ type: 'lava', lost: 2 })).toEqual({ text: 'Лава! Минус 2 удара', tone: 'bad' })
    expect(eventText({ type: 'combo', combo: 3, mult: 1.1 }).text).toBe('Комбо ×3 · добыча ×1.10')
    expect(eventText({ type: 'relic', name: 'Окаменелость', new: true }).tone).toBe('good')
    expect(eventText({ type: 'relic', name: 'Окаменелость', new: false, coins: 40 }).text).toContain('продан за 40')
    expect(eventText({ type: 'mimic', lost: 120 }).tone).toBe('bad')
    expect(eventText({ type: 'unknown' })).toBeNull()
  })

  it('склонения и время', () => {
    expect(plural(1, 'удар', 'удара', 'ударов')).toBe('удар')
    expect(plural(3, 'удар', 'удара', 'ударов')).toBe('удара')
    expect(plural(11, 'удар', 'удара', 'ударов')).toBe('ударов')
    expect(formatSeconds(45)).toBe('45 с')
    expect(formatSeconds(545)).toBe('9 мин 05 с')
  })
})
