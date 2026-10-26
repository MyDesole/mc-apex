/**
 * Стикеры для чата (магазин, тип sticker_pack). Ключи наборов и
 * стикеров совпадают с effect_value и metadata.stickers в ShopItemSeeder.
 * В чат стикер уходит текстом «[sticker:набор/стикер]».
 */
export const STICKER_PACKS = {
    mobs: {
        creeper: { sprite: 'creeper', caption: 'Ссс…', anim: 'shake' },
        bee: { sprite: 'bee', caption: 'Жжж!', anim: 'bounce' },
        axolotl: { sprite: 'axolotl', caption: ':3', anim: 'bounce' },
        slime: { sprite: 'slime', caption: 'Буль', anim: 'bounce' },
        ghost: { sprite: 'ghost', caption: 'Бу!', anim: 'wiggle' },
        dragon: { sprite: 'dragon', caption: 'Ррр!', anim: 'glow' },
    },
    mood: {
        love: { sprite: 'heart', caption: 'Люблю', anim: 'pulse' },
        dead: { sprite: 'skull', caption: 'Я умер', anim: 'wiggle' },
        fire: { sprite: 'flame', caption: 'Огонь!', anim: 'bounce' },
        king: { sprite: 'crown', caption: 'Король', anim: 'glow' },
        survived: { sprite: 'totem', caption: 'Выжил', anim: 'glow' },
        boom: { sprite: 'tnt', caption: 'БУМ', anim: 'shake' },
    },
    pets: {
        cat: { sprite: 'cat', caption: 'Мяу', anim: 'wiggle' },
        dog: { sprite: 'dog', caption: 'Гав!', anim: 'bounce' },
        fox: { sprite: 'fox', caption: 'Фыр', anim: 'wiggle' },
        penguin: { sprite: 'penguin', caption: 'Холодно', anim: 'shake' },
        robot: { sprite: 'robot', caption: 'Бип-буп', anim: 'pulse' },
        warden: { sprite: 'warden', caption: 'Тсс…', anim: 'pulse' },
    },
}

export const STICKER_PATTERN = /^\[sticker:([a-z0-9_]+)\/([a-z0-9_]+)\]$/

/** Стикер по тексту сообщения или null, если это обычный текст. */
export function parseSticker(body) {
    const match = STICKER_PATTERN.exec(String(body ?? '').trim())
    if (!match) return null

    const sticker = STICKER_PACKS[match[1]]?.[match[2]]
    return sticker ? { pack: match[1], id: match[2], ...sticker } : null
}

export function stickerToken(pack, id) {
    return `[sticker:${pack}/${id}]`
}
