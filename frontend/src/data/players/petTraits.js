/**
 * Как выглядят черты уникального питомца. Названия и шансы приходят с
 * сервера (PetTraits.php), здесь — только вид каждого варианта.
 */

/** Окрас: CSS-фильтр поверх пиксельного спрайта. */
export const PET_MODELS = {
    classic: { filter: 'none' },
    shadow: { filter: 'brightness(0.55) contrast(1.2) drop-shadow(0 0 4px rgba(139, 92, 246, 0.8))' },
    ash: { filter: 'grayscale(1) brightness(1.1)' },
    mint: { filter: 'hue-rotate(90deg) saturate(1.2)' },
    rose: { filter: 'hue-rotate(300deg) saturate(1.3)' },
    ocean: { filter: 'hue-rotate(180deg) saturate(1.4)' },
    gold: { filter: 'sepia(1) saturate(4) hue-rotate(-10deg) brightness(1.15) drop-shadow(0 0 5px rgba(250, 204, 21, 0.85))' },
    neon: { filter: 'saturate(2.6) brightness(1.2) drop-shadow(0 0 6px rgba(34, 211, 238, 0.9))' },
    crystal: { filter: 'saturate(0.4) brightness(1.5) hue-rotate(160deg) drop-shadow(0 0 6px rgba(255, 255, 255, 0.9))', opacity: 0.85 },
    rainbow: { filter: 'saturate(2)', animated: 'pet-rainbow' },
    void: { filter: 'invert(1) hue-rotate(180deg) drop-shadow(0 0 6px rgba(168, 85, 247, 0.9))' },
    galaxy: { filter: 'hue-rotate(250deg) saturate(2) drop-shadow(0 0 6px rgba(236, 72, 153, 0.9))', animated: 'pet-galaxy' },
    cherry: { filter: 'hue-rotate(320deg) saturate(1.8) brightness(0.95)' },
    lime: { filter: 'hue-rotate(60deg) saturate(1.8) brightness(1.1)' },
    pastel: { filter: 'saturate(0.55) brightness(1.3) contrast(0.9)' },
    frost: { filter: 'hue-rotate(170deg) saturate(0.7) brightness(1.4) drop-shadow(0 0 4px rgba(186, 230, 253, 0.9))' },
    obsidian: { filter: 'grayscale(1) brightness(0.4) contrast(1.6) drop-shadow(0 0 4px rgba(168, 85, 247, 0.7))' },
    toxic: { filter: 'hue-rotate(75deg) saturate(3) brightness(1.15) drop-shadow(0 0 5px rgba(132, 204, 22, 0.9))' },
    lava: { filter: 'sepia(1) saturate(5) hue-rotate(-30deg)', animated: 'pet-lava' },
    holo: { filter: 'saturate(1.6) brightness(1.3)', animated: 'pet-holo', opacity: 0.92 },
}

/** Фон: сияние позади питомца (в шапке — мягкое, в карточке — во всю плашку). */
export const PET_BACKDROPS = {
    none: null,
    sky: 'radial-gradient(circle, rgba(125, 211, 252, 0.55), rgba(59, 130, 246, 0.15) 60%, transparent 72%)',
    sunset: 'radial-gradient(circle, rgba(251, 146, 60, 0.6), rgba(236, 72, 153, 0.2) 60%, transparent 72%)',
    forest: 'radial-gradient(circle, rgba(74, 222, 128, 0.5), rgba(21, 128, 61, 0.2) 60%, transparent 72%)',
    nebula: 'radial-gradient(circle, rgba(192, 132, 252, 0.6), rgba(79, 70, 229, 0.25) 60%, transparent 72%)',
    ember: 'radial-gradient(circle, rgba(248, 113, 113, 0.6), rgba(234, 88, 12, 0.25) 60%, transparent 72%)',
    aurora: 'conic-gradient(from 0deg, rgba(34, 197, 94, 0.55), rgba(6, 182, 212, 0.55), rgba(139, 92, 246, 0.55), rgba(34, 197, 94, 0.55))',
    cosmos: 'radial-gradient(circle, rgba(255, 255, 255, 0.35) 0 2%, transparent 3%) 0 0 / 14px 14px, radial-gradient(circle, rgba(99, 102, 241, 0.6), rgba(15, 23, 42, 0.3) 65%, transparent 72%)',
    prism: 'conic-gradient(from 0deg, #f87171, #fbbf24, #4ade80, #22d3ee, #818cf8, #e879f9, #f87171)',
    ocean: 'radial-gradient(circle, rgba(34, 211, 238, 0.55), rgba(14, 116, 144, 0.25) 60%, transparent 72%)',
    sakura: 'radial-gradient(circle, rgba(251, 207, 232, 0.65), rgba(244, 114, 182, 0.25) 60%, transparent 72%)',
    candy: 'conic-gradient(from 45deg, rgba(244, 114, 182, 0.6), rgba(253, 224, 71, 0.6), rgba(125, 211, 252, 0.6), rgba(244, 114, 182, 0.6))',
    night: 'radial-gradient(circle, rgba(255, 255, 255, 0.5) 0 2%, transparent 3%) 0 0 / 10px 10px, radial-gradient(circle, rgba(30, 27, 75, 0.85), rgba(15, 23, 42, 0.4) 65%, transparent 72%)',
    matrix: 'repeating-linear-gradient(90deg, rgba(34, 197, 94, 0.55) 0 2px, transparent 2px 6px), radial-gradient(circle, rgba(21, 128, 61, 0.5), transparent 70%)',
    lava: 'radial-gradient(circle, rgba(253, 224, 71, 0.6), rgba(239, 68, 68, 0.45) 45%, rgba(127, 29, 29, 0.2) 65%, transparent 72%)',
}

/**
 * Аксессуар: пиксельная картинка (PetAccessory) на питомце.
 * dy — высота низа аксессуара от низа спрайта, w — ширина, в долях
 * размера питомца.
 */
export const PET_ACCESSORIES = {
    none: null,
    cap: { dy: 0.74, w: 0.62 },
    bow: { dy: 0.74, w: 0.5 },
    flower: { dy: 0.74, w: 0.36 },
    glasses: { dy: 0.42, w: 0.66 },
    tophat: { dy: 0.78, w: 0.52 },
    scarf: { dy: 0.1, w: 0.74 },
    crown: { dy: 0.78, w: 0.56 },
    halo: { dy: 0.98, w: 0.6 },
    horns: { dy: 0.8, w: 0.84 },
    wizard: { dy: 0.78, w: 0.58 },
    headphones: { dy: 0.36, w: 0.98 },
    party: { dy: 0.8, w: 0.4 },
    monocle: { dy: 0.4, w: 0.26 },
    leaf: { dy: 0.86, w: 0.3 },
    pirate: { dy: 0.44, w: 0.72 },
}

/** Пиксель-арт аксессуаров: строки одной длины, «.» — пусто. */
export const PET_ACCESSORY_ART = {
    cap: {
        palette: { R: '#ef4444', D: '#991b1b', W: '#ffffff' },
        map: [
            '...RRRR....',
            '..RRRRRR...',
            '.RRRWWRRR..',
            '.RRRRRRRR..',
            '.DDDDDDDDDD',
        ],
    },
    bow: {
        palette: { P: '#f472b6', K: '#be185d', L: '#fbcfe8' },
        map: [
            'PP.....PP',
            'PLP.K.PLP',
            'PPPKKKPPP',
            'PPP.K.PPP',
            'PP.....PP',
        ],
    },
    flower: {
        palette: { P: '#f9a8d4', Y: '#facc15', G: '#22c55e' },
        map: [
            '..P.P..',
            '.PPYPP.',
            'PPYYYPP',
            '.PPYPP.',
            '..PGP..',
            '...G...',
        ],
    },
    glasses: {
        palette: { B: '#111827', L: '#60a5fa' },
        map: [
            'BBBBB..BBBBB',
            'BLLBBBBBLLBB',
            '.BBB....BBB.',
        ],
    },
    tophat: {
        palette: { B: '#1f2937', H: '#374151', R: '#dc2626' },
        map: [
            '..BBBB..',
            '..BHBB..',
            '..BHBB..',
            '..RRRR..',
            '..BBBB..',
            'BBBBBBBB',
        ],
    },
    scarf: {
        palette: { R: '#ef4444', W: '#fecaca', D: '#b91c1c' },
        map: [
            'RRWRRWRRWRR',
            'RRWRRWRRWRR',
            '.......RWR.',
            '.......DRD.',
        ],
    },
    crown: {
        palette: { Y: '#facc15', O: '#ca8a04', R: '#ef4444', B: '#3b82f6' },
        map: [
            'Y...Y...Y',
            'YY.YYY.YY',
            'YYYYYYYYY',
            'YRYYBYYRY',
            'OOOOOOOOO',
        ],
    },
    halo: {
        palette: { Y: '#fde68a' },
        glow: true,
        map: [
            '.YYYYYYYY.',
            'YY......YY',
            '.YYYYYYYY.',
        ],
    },
    horns: {
        palette: { R: '#dc2626', D: '#7f1d1d' },
        map: [
            'R..........R',
            'RR........RR',
            'DRR......RRD',
            '.DR......RD.',
        ],
    },
    wizard: {
        palette: { P: '#7c3aed', L: '#a78bfa', Y: '#fde047', D: '#4c1d95' },
        map: [
            '.....P...',
            '....PP...',
            '...PPL...',
            '...PYPP..',
            '..PPPLP..',
            '..PLPPYP.',
            '.PPPPPPP.',
            'DDDDDDDDD',
        ],
    },
    headphones: {
        palette: { B: '#374151', R: '#ef4444', D: '#1f2937' },
        map: [
            '...BBBBBB...',
            '..B......B..',
            '.B........B.',
            'RR........RR',
            'RD........DR',
            'RR........RR',
        ],
    },
    party: {
        palette: { C: '#22d3ee', M: '#f472b6', Y: '#fde047' },
        map: [
            '...Y...',
            '...C...',
            '..CMC..',
            '..MCM..',
            '.CMCMC.',
            'CMCMCMC',
        ],
    },
    monocle: {
        palette: { Y: '#facc15', G: '#bfdbfe' },
        map: [
            '.YYY.',
            'YGGGY',
            'YGGGY',
            '.YYY.',
            '...Y.',
            '...Y.',
        ],
    },
    leaf: {
        palette: { G: '#4ade80', D: '#15803d' },
        map: [
            'G...G',
            'GG.GG',
            '.GDG.',
            '..D..',
            '..D..',
        ],
    },
    pirate: {
        palette: { B: '#111827' },
        map: [
            'BBBBBBBBBBBB',
            '......BBB...',
            '......BBB...',
            '.......B....',
        ],
    },
}

/** Общая редкость экземпляра — подпись и цвет. */
export const PET_RARITY = {
    common: { label: 'Обычный', color: '#a8a3b8' },
    rare: { label: 'Редкий', color: '#38bdf8' },
    epic: { label: 'Эпический', color: '#a855f7' },
    legendary: { label: 'Легендарный', color: '#f59e0b' },
    mythic: { label: 'Мифический', color: '#f43f5e' },
}

/** Стиль спрайта для окраса. */
export function petModelStyle(traits) {
    const model = PET_MODELS[traits?.model?.id] ?? PET_MODELS.classic
    return {
        filter: model.filter,
        opacity: model.opacity ?? 1,
        animation: model.animated ? `${model.animated} 4s linear infinite` : undefined,
    }
}

export function petBackdrop(traits) {
    return PET_BACKDROPS[traits?.backdrop?.id] ?? null
}

export function petAccessory(traits) {
    return PET_ACCESSORIES[traits?.accessory?.id] ?? null
}

/** «0,5 %» — шанс черты по-русски. */
export function chanceLabel(chance) {
    if (chance === undefined || chance === null) return ''
    const value = chance >= 10 ? Math.round(chance) : chance
    return `${String(value).replace('.', ',')} %`
}
