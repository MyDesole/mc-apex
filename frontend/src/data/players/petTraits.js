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
    // Шестая волна
    sunny: { filter: 'sepia(0.6) saturate(3) hue-rotate(5deg) brightness(1.15)' },
    retro: { filter: 'sepia(1) contrast(1.15) brightness(0.92)' },
    sakura: { filter: 'hue-rotate(290deg) saturate(0.8) brightness(1.3) drop-shadow(0 0 3px rgba(251, 207, 232, 0.9))' },
    ghostly: { filter: 'grayscale(0.6) brightness(1.5) drop-shadow(0 0 6px rgba(255, 255, 255, 0.9))', opacity: 0.55 },
    abyss: { filter: 'invert(1) hue-rotate(200deg) brightness(0.7) drop-shadow(0 0 6px rgba(34, 211, 238, 0.9))' },
    glitch: { filter: 'saturate(1.4)', animated: 'pet-glitch' },
    aurora: { filter: 'hue-rotate(100deg) saturate(1.8)', animated: 'pet-aurora' },
    // Седьмая волна
    cotton: { filter: 'hue-rotate(280deg) saturate(0.7) brightness(1.35) drop-shadow(0 0 3px rgba(251, 207, 232, 0.8))' },
    mossy: { filter: 'sepia(0.3) hue-rotate(70deg) saturate(0.9) brightness(0.85)' },
    ink: { filter: 'grayscale(1) contrast(1.8) brightness(0.8) drop-shadow(0 0 4px rgba(59, 130, 246, 0.9))' },
    royal: { filter: 'hue-rotate(250deg) saturate(1.6) drop-shadow(0 0 4px rgba(250, 204, 21, 0.9))' },
    ember: { filter: 'sepia(1) saturate(3) hue-rotate(-20deg) brightness(0.9) drop-shadow(0 0 6px rgba(249, 115, 22, 0.95))' },
    mirror: { filter: 'grayscale(1) brightness(1.7) contrast(1.2) drop-shadow(0 0 6px rgba(255, 255, 255, 0.95))' },
    cursed: { filter: 'hue-rotate(320deg) saturate(2) brightness(0.8)', animated: 'pet-cursed' },
    starlight: { filter: 'brightness(1.3) saturate(0.6)', animated: 'pet-starlight' },
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
    // Шестая волна
    desert: 'radial-gradient(circle, rgba(253, 230, 138, 0.6), rgba(217, 119, 6, 0.25) 60%, transparent 72%)',
    snowfall: 'radial-gradient(circle, rgba(255, 255, 255, 0.75) 0 1.5px, transparent 2px) 0 0 / 9px 11px, radial-gradient(circle, rgba(186, 230, 253, 0.45), rgba(30, 64, 175, 0.2) 65%, transparent 72%)',
    circuit: 'linear-gradient(rgba(34, 211, 238, 0.35) 1px, transparent 1px) 0 0 / 10px 10px, linear-gradient(90deg, rgba(34, 211, 238, 0.35) 1px, transparent 1px) 0 0 / 10px 10px, radial-gradient(circle, rgba(8, 145, 178, 0.45), transparent 70%)',
    rainbow: 'repeating-linear-gradient(135deg, rgba(248, 113, 113, 0.5) 0 6px, rgba(251, 191, 36, 0.5) 6px 12px, rgba(74, 222, 128, 0.5) 12px 18px, rgba(96, 165, 250, 0.5) 18px 24px, rgba(192, 132, 252, 0.5) 24px 30px)',
    eclipse: 'radial-gradient(circle, #050308 0 36%, rgba(253, 224, 71, 0.9) 40%, rgba(251, 146, 60, 0.4) 50%, transparent 68%)',
    rift: 'radial-gradient(circle, rgba(0, 0, 0, 0.9) 0 28%, rgba(168, 85, 247, 0.75) 34%, rgba(76, 29, 149, 0.3) 50%, transparent 70%)',
    // Седьмая волна
    meadow: 'radial-gradient(circle, rgba(250, 204, 21, 0.85) 0 1.5px, transparent 2px) 0 0 / 12px 10px, radial-gradient(circle, rgba(134, 239, 172, 0.55), rgba(22, 101, 52, 0.25) 60%, transparent 72%)',
    bubbles: 'radial-gradient(circle, transparent 0 3px, rgba(186, 230, 253, 0.75) 3px 4px, transparent 4.5px) 0 0 / 16px 18px, radial-gradient(circle, rgba(56, 189, 248, 0.45), rgba(14, 116, 144, 0.2) 60%, transparent 72%)',
    hearts: 'radial-gradient(circle, rgba(244, 114, 182, 0.75) 0 2px, transparent 2.5px) 0 0 / 11px 11px, radial-gradient(circle, rgba(251, 207, 232, 0.55), rgba(190, 24, 93, 0.2) 60%, transparent 72%)',
    storm: 'linear-gradient(115deg, transparent 46%, rgba(253, 224, 71, 0.9) 47% 49%, transparent 50%), radial-gradient(circle, rgba(71, 85, 105, 0.85), rgba(30, 41, 59, 0.5) 60%, transparent 72%)',
    portal: 'repeating-conic-gradient(from 0deg, rgba(147, 51, 234, 0.7) 0 15deg, rgba(76, 29, 149, 0.6) 15deg 30deg)',
    ender: 'radial-gradient(circle, rgba(255, 255, 255, 0.85) 0 1px, transparent 1.5px) 0 0 / 8px 9px, radial-gradient(circle, rgba(15, 10, 25, 0.95) 0 40%, rgba(250, 240, 170, 0.65) 46%, rgba(88, 28, 135, 0.4) 60%, transparent 72%)',
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
    sunglasses: { dy: 0.42, w: 0.66 },
    bowtie: { dy: 0.1, w: 0.4 },
    santa: { dy: 0.78, w: 0.56 },
    viking: { dy: 0.78, w: 0.84 },
    chef: { dy: 0.8, w: 0.52 },
    wreath: { dy: 0.76, w: 0.66 },
    antenna: { dy: 0.86, w: 0.24 },
    bandana: { dy: 0.74, w: 0.66 },
    star: { dy: 0.98, w: 0.34 },
    beanie: { dy: 0.76, w: 0.6 },
    cherry: { dy: 0.86, w: 0.26 },
    goggles: { dy: 0.42, w: 0.66 },
    cowboy: { dy: 0.78, w: 0.84 },
    mask: { dy: 0.42, w: 0.66 },
    propeller: { dy: 0.8, w: 0.56 },
    tiara: { dy: 0.78, w: 0.5 },
    icecrown: { dy: 0.78, w: 0.56 },
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
    // Вид сбоку (пчела): одна линза и дужка назад
    glasses_side: {
        palette: { B: '#111827', L: '#60a5fa' },
        map: [
            'BBBBBBB',
            'BLLBB..',
            '.BBB...',
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
    // Шестая волна. Солнечные очки — линзы там же, где у обычных (2.5 и 9.5 из 12)
    sunglasses: {
        palette: { K: '#0f172a', D: '#1e293b', W: '#94a3b8' },
        map: [
            'KKKKKKKKKKKK',
            'KWDDKKKKWDDK',
            'KDDDK..KDDDK',
            '.KKK....KKK.',
        ],
    },
    sunglasses_side: {
        palette: { K: '#0f172a', D: '#1e293b', W: '#94a3b8' },
        map: [
            'KKKKKKK',
            'KWDDK..',
            'KDDDK..',
            '.KKK...',
        ],
    },
    bowtie: {
        palette: { R: '#dc2626', K: '#7f1d1d' },
        map: [
            'RR...RR',
            'RRR.RRR',
            'RRRKRRR',
            'RRR.RRR',
            'RR...RR',
        ],
    },
    santa: {
        palette: { R: '#dc2626', D: '#991b1b', W: '#f8fafc' },
        map: [
            '.....WW.',
            '....RRW.',
            '...RRD..',
            '..RRRR..',
            '.RRDRRR.',
            'WWWWWWWW',
        ],
    },
    viking: {
        palette: { W: '#f5f5f4', G: '#9ca3af', H: '#d1d5db', B: '#78350f' },
        map: [
            'W..........W',
            'WW..GGGG..WW',
            '.WWGHGGGGWW.',
            '..GGGGGGGG..',
            '..BBBBBBBB..',
        ],
    },
    chef: {
        palette: { W: '#ffffff', G: '#e5e7eb' },
        map: [
            '.WWWWWW.',
            'WWWGWWWW',
            'WWWWWWGW',
            '.WWWWWW.',
            '.GGGGGG.',
            '.WWWWWW.',
        ],
    },
    wreath: {
        palette: { P: '#f9a8d4', Y: '#fde047', G: '#22c55e', D: '#15803d' },
        map: [
            '.P.Y.P.Y.P.',
            'GPGYGPGYGPG',
            'DGDGDGDGDGD',
        ],
    },
    antenna: {
        palette: { R: '#ef4444', L: '#fecaca', G: '#94a3b8' },
        map: [
            '.RR.',
            'RLRR',
            '.RR.',
            '.G..',
            '.G..',
            'GGG.',
        ],
    },
    bandana: {
        palette: { B: '#2563eb', W: '#ffffff', D: '#1e40af' },
        map: [
            '..BBBBBBB...',
            '.BBWBBBWBB..',
            'DDDDDDDDDDDD',
            '..........DD',
            '...........D',
        ],
    },
    // Седьмая волна
    beanie: {
        palette: { R: '#2563eb', W: '#bfdbfe', D: '#1e3a8a' },
        map: [
            '....WW....',
            '..RRRRRR..',
            '.RRWRRWRR.',
            'RRRRRRRRRR',
            'DDDDDDDDDD',
        ],
    },
    cherry: {
        palette: { R: '#dc2626', W: '#fecaca', G: '#16a34a' },
        map: [
            '...G.',
            '..GG.',
            '.G..G',
            'RR.RR',
            'RW.RW',
            'RR.RR',
        ],
    },
    // Линзы — там же, где у обычных очков (2.5 и 9.5 из 12)
    goggles: {
        palette: { K: '#78350f', G: '#38bdf8', W: '#e0f2fe' },
        map: [
            'KKKKKKKKKKKK',
            'KGGGKKKKGGGK',
            'KGWGK..KGWGK',
            '.KKK....KKK.',
        ],
    },
    goggles_side: {
        palette: { K: '#78350f', G: '#38bdf8', W: '#e0f2fe' },
        map: [
            'KKKKKKK',
            'KGGGK..',
            'KGWGK..',
            '.KKK...',
        ],
    },
    mask: {
        palette: { R: '#dc2626', D: '#7f1d1d' },
        map: [
            '.RRRRRRRRRR.',
            'R...RRRR...R',
            '.DDD.DD.DDD.',
        ],
    },
    mask_side: {
        palette: { R: '#dc2626', D: '#7f1d1d' },
        map: [
            'RRRRRR.',
            'R...RR.',
            '.DDD...',
        ],
    },
    cowboy: {
        palette: { B: '#a16207', D: '#78350f', L: '#ca8a04' },
        map: [
            '...BBBBBB...',
            '..BBLBBBBB..',
            '..DDDDDDDD..',
            'BBBBBBBBBBBB',
            '.BB......BB.',
        ],
    },
    propeller: {
        palette: { R: '#ef4444', B: '#3b82f6', Y: '#facc15', G: '#22c55e', D: '#15803d' },
        map: [
            '.RRRYBBBB.',
            '....Y.....',
            '..GGGGGG..',
            '.GYGGYGGG.',
            'GGGGGGGGGG',
            'DDDDDDDDDD',
        ],
    },
    tiara: {
        palette: { W: '#e5e7eb', S: '#9ca3af', P: '#ec4899' },
        map: [
            '....P....',
            '.W.WPW.W.',
            'WSWSWSWSW',
        ],
    },
    icecrown: {
        palette: { C: '#a5f3fc', W: '#ffffff', B: '#3b82f6', D: '#0891b2' },
        glow: true,
        map: [
            'C...C...C',
            'CW.CWC.WC',
            'CCCCCCCCC',
            'CBCCWCCBC',
            'DDDDDDDDD',
        ],
    },
    star: {
        palette: { Y: '#fde047', W: '#fffbeb' },
        glow: true,
        map: [
            '...Y...',
            '..YYY..',
            'YYYWYYY',
            '.YYYYY.',
            '.YY.YY.',
            'Y.....Y',
        ],
    },
}

/**
 * Где у каждого питомца макушка, глаза и шея — в клетках его спрайта
 * (pixelSprites.js), по центрам клеток. По ним аксессуар встаёт на своё
 * место: очки — на глаза, шляпа — на макушку, шарф — на шею. Раньше всё
 * ставилось по общей доле размера, и у пчелы очки висели в воздухе.
 */
export const PET_SHAPES = {
    bee: { top: 2, head: [0, 4], eyes: [1.5], eyeY: 3.5, neck: 6 },
    axolotl: { top: 1, head: [2, 9], eyes: [3.5, 7.5], eyeY: 3.5, neck: 5.6 },
    cat: { top: 2, head: [2, 6], eyes: [2, 6], eyeY: 3.5, neck: 6 },
    dog: { top: 2, head: [1, 7], eyes: [1.5, 6.5], eyeY: 3.5, neck: 5 },
    fox: { top: 2, head: [1, 7], eyes: [1.5, 6.5], eyeY: 3.5, neck: 5 },
    slime: { top: 0, head: [1, 7], eyes: [3, 6], eyeY: 3, neck: 6.4 },
    ghost: { top: 0, head: [1, 7], eyes: [2.5, 5.5], eyeY: 3, neck: 5 },
    dragon: { top: 2, head: [1, 9], eyes: [3, 8], eyeY: 3.5, neck: 6 },
    robot: { top: 2, head: [0, 8], eyes: [2, 6], eyeY: 4, neck: 6 },
    penguin: { top: 0, head: [1, 7], eyes: [2.5, 5.5], eyeY: 2.5, neck: 4 },
    crow: { top: 0, head: [1, 7], eyes: [2.5, 5.5], eyeY: 2.5, neck: 5 },
    owl: { top: 1, head: [0, 8], eyes: [2, 6], eyeY: 3.5, neck: 5 },
    // Глаза лягушки торчат над головой: шапка — между ними, голова уже спрайта
    frog: { top: 0, head: [2, 8], eyes: [2.5, 7.5], eyeY: 1.5, neck: 5 },
    bunny: { top: 3, head: [1, 7], eyes: [2.5, 5.5], eyeY: 4.5, neck: 6 },
    panda: { top: 1, head: [0, 8], eyes: [2, 6], eyeY: 4, neck: 6 },
    shroom: { top: 0, head: [0, 8], eyes: [2.5, 5.5], eyeY: 5.5, neck: 7 },
    octopus: { top: 0, head: [0, 8], eyes: [2, 6], eyeY: 3.5, neck: 5 },
    phoenix: { top: 2, head: [2, 8], eyes: [3.5, 6.5], eyeY: 3.5, neck: 5 },
    capybara: { top: 2, head: [0, 10], eyes: [2.5, 7.5], eyeY: 3.5, neck: 6 },
    jelly: { top: 0, head: [0, 8], eyes: [2.5, 5.5], eyeY: 3.5, neck: 4.4 },
    unicorn: { top: 2, head: [1, 9], eyes: [3.5, 6.5], eyeY: 3.5, neck: 6 },
}

/**
 * Как аксессуар садится: on — к чему крепится, w — ширина в долях головы,
 * side — на угол головы (бантик, цветок), а не по центру.
 */
const ACCESSORY_FIT = {
    cap: { on: 'top', w: 1.05 },
    tophat: { on: 'top', w: 1.0 },
    crown: { on: 'top', w: 1.05 },
    wizard: { on: 'top', w: 1.05 },
    party: { on: 'top', w: 0.6 },
    leaf: { on: 'top', w: 0.45 },
    horns: { on: 'top', w: 1.3 },
    bow: { on: 'top', w: 0.7, side: true },
    flower: { on: 'top', w: 0.55, side: true },
    halo: { on: 'halo', w: 0.95 },
    glasses: { on: 'eyes' },
    monocle: { on: 'eye', w: 1.9 },
    pirate: { on: 'band', w: 1.15 },
    headphones: { on: 'ears', w: 1.3 },
    scarf: { on: 'neck', w: 1.1 },
    sunglasses: { on: 'eyes' },
    bowtie: { on: 'neck', w: 0.4 },
    santa: { on: 'top', w: 1.0 },
    viking: { on: 'top', w: 1.3 },
    chef: { on: 'top', w: 0.7 },
    wreath: { on: 'top', w: 1.05 },
    antenna: { on: 'top', w: 0.3 },
    bandana: { on: 'top', w: 1.15 },
    star: { on: 'halo', w: 0.45 },
    beanie: { on: 'top', w: 1.0 },
    cherry: { on: 'top', w: 0.4 },
    goggles: { on: 'eyes' },
    cowboy: { on: 'top', w: 1.3 },
    mask: { on: 'eyes' },
    propeller: { on: 'top', w: 0.95 },
    tiara: { on: 'top', w: 0.8 },
    icecrown: { on: 'top', w: 1.05 },
}

/**
 * Где нарисовать аксессуар на питомце: { left, top, width, art } в px от
 * левого верхнего угла спрайта размером size (высота) — как его рисует
 * PixelSprite (ширина до 1.4 высоты, картинка по центру).
 */
export function accessoryPlacement(sprite, accessoryId, size, spriteRows) {
    const shape = PET_SHAPES[sprite]
    const fit = ACCESSORY_FIT[accessoryId]
    if (!shape || !fit || !spriteRows?.length) return null

    const cols = Math.max(...spriteRows.map((r) => r.length))
    const rows = spriteRows.length
    const boxW = Math.round(size * Math.min(1.4, cols / rows))
    const cell = Math.min(boxW / cols, size / rows)
    const ox = (boxW - cols * cell) / 2
    const oy = (size - rows * cell) / 2
    const px = (x) => ox + x * cell
    const py = (y) => oy + y * cell

    const headW = shape.head[1] - shape.head[0]
    const headMid = (shape.head[0] + shape.head[1]) / 2
    const oneEye = shape.eyes.length === 1

    // Очки на питомце с одним видимым глазом (вид сбоку) — одна линза
    const art = oneEye && PET_ACCESSORY_ART[`${accessoryId}_side`] ? `${accessoryId}_side` : accessoryId
    const map = PET_ACCESSORY_ART[art]?.map
    if (!map) return null
    const ratio = map.length / map[0].length

    let widthCells
    let centerX
    let topCells

    switch (fit.on) {
        case 'top':
            widthCells = headW * fit.w
            centerX = fit.side ? shape.head[1] - widthCells * 0.45 : headMid
            // Низ аксессуара чуть заходит на макушку
            topCells = shape.top + 0.5 - widthCells * ratio
            break
        case 'halo':
            widthCells = headW * fit.w
            centerX = headMid
            topCells = shape.top - 0.6 - widthCells * ratio
            break
        case 'eyes':
            if (oneEye) {
                widthCells = 2.6
                centerX = shape.eyes[0]
            } else {
                // Линзы картинки — на 2.5 и 9.5 из 12: расстояние между ними 7/12 ширины
                widthCells = (shape.eyes[1] - shape.eyes[0]) / (7 / 12)
                centerX = (shape.eyes[0] + shape.eyes[1]) / 2
            }
            topCells = shape.eyeY - (widthCells * ratio) / 2
            break
        case 'eye': {
            widthCells = fit.w
            centerX = shape.eyes[shape.eyes.length - 1]
            // Линза — верхние 4 из 6 строк картинки
            topCells = shape.eyeY - widthCells * ratio * (2 / 6)
            break
        }
        case 'band':
            widthCells = headW * fit.w
            // Повязка: нашивка (7.5 из 12) — на первый глаз
            centerX = shape.eyes[0] + widthCells * (0.5 - 7.5 / 12)
            topCells = shape.eyeY - 0.5
            break
        case 'ears':
            widthCells = headW * fit.w
            centerX = headMid
            topCells = shape.eyeY - widthCells * ratio * 0.72
            break
        case 'neck':
            widthCells = headW * fit.w
            centerX = headMid
            topCells = shape.neck - 0.6
            break
        default:
            return null
    }

    const width = Math.max(6, Math.round(widthCells * cell))

    return {
        art,
        width,
        left: Math.round(px(centerX) - width / 2),
        top: Math.round(py(topCells)),
    }
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
