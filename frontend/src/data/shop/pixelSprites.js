/**
 * Пиксельные картинки в стиле Minecraft для косметики: бейджи, рамки,
 * частицы эффектов. Каждая — сетка символов и палитра; '.' — прозрачно.
 *
 * Рисуем SVG из прямоугольников: картинка остаётся чёткой в любом
 * размере, а для частиц эффектов её можно отдать как data-URL.
 */

export const PIXEL_SPRITES = {
    creeper: {
        palette: { g: '#5dd16a', d: '#2f8f3b', l: '#9be89f', k: '#101410' },
        rows: [
            'gdglgdgg',
            'glgdgglg',
            'gkkggkkg',
            'gkkdgkkg',
            'gdgkkgdg',
            'ggkkkkgl',
            'gdkkkkgg',
            'ggkdgkdg',
        ],
    },
    bee: {
        palette: { y: '#facc15', o: '#f59e0b', k: '#1c1917', w: '#e0f2fe', W: '#bae6fd', e: '#ffffff' },
        rows: [
            '...wW.wW..',
            '...WwwWw..',
            '.kyykyyko.',
            'keykyykyyo',
            'kkykyykyyo',
            '.kyykyyko.',
            '..k...k...',
        ],
    },
    axolotl: {
        palette: { p: '#f9a8d4', P: '#ec4899', k: '#1f1020', m: '#fda4af', w: '#ffffff' },
        rows: [
            'P........P',
            '.P.pppp.P.',
            'PPpppppppP',
            '..pkwppkw.',
            '..pkpppkp.',
            '..ppmmppp.',
            '...pppppp.',
        ],
    },
    tnt: {
        palette: { r: '#dc2626', R: '#991b1b', w: '#f5f5f4', k: '#1c1917', g: '#a8a29e' },
        rows: [
            'gggggggg',
            'rRrRrRrR',
            'rrRrrRrr',
            'wwwwwwww',
            'kkwkwkkw',
            'wkwkkwkw',
            'wwwwwwww',
            'rRrrRrrR',
            'RrRrRrRr',
        ],
    },
    diamond: {
        palette: { c: '#5eead4', C: '#22d3ee', b: '#0e7490', w: '#ecfeff' },
        rows: [
            '..wwCC..',
            '.wwCCCb.',
            'wCCCCCbb',
            'CCCCCCCb',
            '.CCCCCb.',
            '..CCCb..',
            '...Cb...',
        ],
    },
    heart: {
        palette: { r: '#ef4444', R: '#991b1b', w: '#fecaca', k: '#1c1917' },
        rows: [
            '.kk.kk.',
            'kwrkrrk',
            'krrrrRk',
            'krrrrRk',
            '.krrRk.',
            '..kRk..',
            '...k...',
        ],
    },
    skull: {
        palette: { d: '#3f3f46', k: '#18181b', e: '#ef4444', E: '#fca5a5', g: '#52525b' },
        rows: [
            '.dddddd.',
            'dgddddgd',
            'dkkddkkd',
            'dkeddekd',
            'dddkkddd',
            'dgdddddd',
            '.dkdkdk.',
            '..d.d.d.',
        ],
    },
    warden: {
        palette: { t: '#134e4a', T: '#0f3a37', c: '#5eead4', k: '#042f2e', s: '#1e293b' },
        rows: [
            'c.tttt.c',
            'cTttttTc',
            'tttttttt',
            'tkktkktt',
            'tkctkctt',
            'TttttttT',
            'tTkkkkTt',
            'tttssttt',
        ],
    },
    totem: {
        palette: { y: '#facc15', Y: '#ca8a04', g: '#16a34a', e: '#22c55e', b: '#0b3d1e' },
        rows: [
            '..yyyy..',
            '.yYyyYy.',
            '.ybeebY.',
            '.yyyyyy.',
            'yyggggyy',
            'YyyyyyyY',
            '.yyggyy.',
            '..yyyy..',
            '..y..y..',
        ],
    },
    cat: {
        palette: { o: '#fb923c', O: '#c2410c', w: '#fff7ed', k: '#1c1917', g: '#4ade80', p: '#f9a8d4' },
        rows: [
            'O......O',
            'oO....Oo',
            'oooooooo',
            'ogkoogko',
            'owwoowwo',
            'oowppwoo',
            '.oowwoo.',
            '..oooo..',
        ],
    },
    paw: {
        palette: { p: '#f9a8d4', P: '#f472b6' },
        rows: [
            '.p...p.',
            'ppp.ppp',
            '.p...p.',
            '..PPP..',
            '.PPPPP.',
            '.PPPPP.',
            '..P.P..',
        ],
    },
    crown: {
        palette: { y: '#facc15', Y: '#b45309', l: '#fef08a', r: '#ef4444', b: '#3b82f6', g: '#22c55e' },
        rows: [
            'l...l...l',
            'yy.yly.yy',
            'yyyyyyyyy',
            'yryygyyby',
            'yyyyyyyyy',
            'YYYYYYYYY',
        ],
    },
    // Рог Незера: кончик вверху слева, основание внизу справа
    horn: {
        palette: { y: '#fde047', o: '#f97316', r: '#991b1b', R: '#7f1d1d', d: '#3b0a0a' },
        rows: [
            'y.....',
            'oo....',
            '.ro...',
            '.rr...',
            '..rR..',
            '..dRr.',
            '...dRr',
            '...ddR',
            '....dd',
            '....dd',
        ],
    },
    // Питомцы профиля
    dog: {
        palette: { b: '#a16207', B: '#78350f', k: '#1c1917', w: '#fef3c7', p: '#f472b6' },
        rows: [
            'B......B',
            'Bb....bB',
            'bbbbbbbb',
            'bkbbbbkb',
            'bbwwwwbb',
            '.bwkkwb.',
            '..wppw..',
        ],
    },
    fox: {
        palette: { o: '#f97316', O: '#9a3412', w: '#fff7ed', k: '#1c1917' },
        rows: [
            'O......O',
            'oO....Oo',
            'oooooooo',
            'okooooko',
            'wwwoowww',
            '.wwkkww.',
            '..wwww..',
        ],
    },
    slime: {
        palette: { g: '#86efac', G: '#22c55e', k: '#14532d' },
        rows: [
            'GGGGGGGG',
            'GggggggG',
            'GgkkgkkG',
            'GgkkgkkG',
            'GggggggG',
            'GgggkggG',
            'GggggggG',
            'GGGGGGGG',
        ],
    },
    ghost: {
        palette: { w: '#f8fafc', g: '#cbd5e1', k: '#1e1b4b', p: '#f9a8d4' },
        rows: [
            '..wwww..',
            '.wwwwww.',
            'wwkwwkww',
            'wwkwwkww',
            'wpwwwwpw',
            'wwwkkwww',
            'wwwwwwww',
            'gwwwwwwg',
            'w.ww.ww.',
        ],
    },
    dragon: {
        palette: { p: '#8b5cf6', P: '#5b21b6', y: '#fde047', k: '#1c1917', w: '#ffffff' },
        rows: [
            'P........P',
            'PP......PP',
            '.pppppppp.',
            'ppkypppkyp',
            'pppppppppp',
            '.pwpwpwpp.',
            '..pppppp..',
            '...P..P...',
        ],
    },
    robot: {
        palette: { s: '#94a3b8', S: '#475569', c: '#22d3ee', r: '#ef4444' },
        rows: [
            '...rr...',
            '...SS...',
            'ssssssss',
            'sccssccs',
            'sccssccs',
            'ssssssss',
            'sSSSSSSs',
            's.s..s.s',
        ],
    },
    penguin: {
        palette: { k: '#1e293b', w: '#f8fafc', o: '#f97316' },
        rows: [
            '..kkkk..',
            '.kkkkkk.',
            '.kwkkwk.',
            '.kkookk.',
            'kkwwwwkk',
            'kwwwwwwk',
            'kwwwwwwk',
            '.kwwwwk.',
            '.oo..oo.',
        ],
    },
    // Шестая волна питомцев
    owl: {
        palette: { b: '#a16207', B: '#713f12', w: '#fef3c7', k: '#1c1917', o: '#f97316', y: '#facc15' },
        rows: [
            'B......B',
            'BbbbbbbB',
            'bwwbbwwb',
            'bwkbbkwb',
            'bwwoowwb',
            'bbbbbbbb',
            'bBbBbBbb',
            '.yy..yy.',
        ],
    },
    frog: {
        palette: { g: '#4ade80', G: '#15803d', w: '#ffffff', k: '#111827', p: '#f9a8d4', r: '#be123c' },
        rows: [
            '.www..www.',
            '.wkw..wkw.',
            'gggggggggg',
            'gpggggggpg',
            'gGrrrrrrGg',
            '.gggggggg.',
            '.GG....GG.',
        ],
    },
    bunny: {
        palette: { w: '#f8fafc', g: '#cbd5e1', p: '#f9a8d4', k: '#1e1b4b', n: '#f472b6' },
        rows: [
            '.w....w.',
            '.wp..pw.',
            '.wp..pw.',
            '.wwwwww.',
            'wwkwwkww',
            'wwwnnwww',
            'gwwwwwwg',
            '.wwwwww.',
            '..g..g..',
        ],
    },
    panda: {
        palette: { w: '#f8fafc', k: '#18181b', p: '#f9a8d4' },
        rows: [
            'kk....kk',
            'kkwwwwkk',
            'wwwwwwww',
            'wkkwwkkw',
            'wkwwwwkw',
            'wpwkkwpw',
            'kwwwwwwk',
            '.kk..kk.',
        ],
    },
    shroom: {
        palette: { r: '#dc2626', R: '#991b1b', w: '#ffffff', s: '#fef3c7', S: '#fcd34d', k: '#1c1917', p: '#fda4af' },
        rows: [
            '..rrrr..',
            '.rwrrwr.',
            'rrrrrwrr',
            'RrwrrrrR',
            '..ssss..',
            '..kssk..',
            '..pssp..',
            '..SSSS..',
        ],
    },
    octopus: {
        palette: { m: '#c084fc', M: '#7e22ce', w: '#ffffff', k: '#1e1b4b' },
        rows: [
            '..mmmm..',
            '.mmmmmm.',
            'mmmmmmmm',
            'mwkmmwkm',
            'mmmmmmmm',
            'mmMmmMmm',
            'mMmMmMmM',
            'm.m..m.m',
        ],
    },
    phoenix: {
        palette: { r: '#ef4444', o: '#f97316', y: '#fde047', k: '#1c1917' },
        rows: [
            '....yy....',
            '...yoo....',
            '..oooooo..',
            '.ookookoo.',
            'yroooyoory',
            'yrroooorry',
            '.r.oooo.r.',
            '...y..y...',
        ],
    },
    capybara: {
        palette: { b: '#a16207', B: '#713f12', k: '#1c1917', n: '#422006', o: '#f97316', g: '#22c55e' },
        rows: [
            '....go....',
            '.bb.oo.bb.',
            'bbbbbbbbbb',
            'bbkbbbbkbb',
            'bbbbbbbbbb',
            'bbbnnnnbbb',
            '.bbbbbbbb.',
            '.BB....BB.',
        ],
    },
    jelly: {
        palette: { c: '#67e8f9', C: '#0891b2', w: '#ecfeff', k: '#164e63', p: '#f0abfc' },
        rows: [
            '..cccc..',
            '.cwcccc.',
            'cwcccccc',
            'cckcckcc',
            'CCCCCCCC',
            '.p.c.p..',
            '.c.p.c..',
            '..c...p.',
            '.p...c..',
        ],
    },
    unicorn: {
        palette: { w: '#fdf4ff', p: '#f0abfc', P: '#c026d3', y: '#fde047', k: '#1e1b4b', n: '#f9a8d4' },
        rows: [
            '....y....',
            '...yyw...',
            '.Pwwwwww.',
            'Ppwkwwkw.',
            'Pwwwwwwww',
            '.Pwwnnwww',
            '..wwwwww.',
            '..p..p...',
        ],
    },
    // Для меню питомца: еда, мячик, настроение
    meat: {
        palette: { r: '#dc2626', R: '#991b1b', p: '#fca5a5', b: '#f5f5f4', B: '#d6d3d1' },
        rows: [
            '..rrrr..',
            '.rppRrr.',
            'rrpRrrr.',
            'rrrrrRr.',
            '.rRrrr..',
            '..rrbB..',
            '....bbB.',
            '.....BB.',
        ],
    },
    ball: {
        palette: { y: '#d9f99d', g: '#84cc16', w: '#f7fee7', k: '#4d7c0f' },
        rows: [
            '..gggg..',
            '.gyyyyg.',
            'gwyyyyyg',
            'gywwyyyg',
            'gyyywwyg',
            'gyyyyywg',
            '.gyyyyg.',
            '..gggg..',
        ],
    },
    smile: {
        palette: { y: '#fde047', o: '#ca8a04', k: '#422006' },
        rows: [
            '..oooo..',
            '.oyyyyo.',
            'oykyykyo',
            'oyyyyyyo',
            'oykyykyo',
            'oyykkyyo',
            '.oyyyyo.',
            '..oooo..',
        ],
    },
    sparkle: {
        palette: { y: '#fde047', w: '#fefce8' },
        rows: [
            '...y...',
            '...y...',
            '..ywy..',
            'yywwwyy',
            '..ywy..',
            '...y...',
            '...y...',
        ],
    },
    flame: {
        palette: { y: '#fde047', o: '#f97316', r: '#dc2626', w: '#fef9c3' },
        rows: [
            '...o....',
            '..oo..o.',
            '.ooyo.oo',
            '.oyyoooo',
            'ooywyyoo',
            'oyywwyyo',
            'royyyyor',
            '.rrooor.',
        ],
    },
}

const cache = new Map()
const svgCache = new Map()

/**
 * SVG-разметка спрайта (viewBox по размеру сетки). Спрайты статичны —
 * строку собираем один раз: в инвентаре и на аукционе их сотни.
 */
export function spriteSvg(key) {
    if (!svgCache.has(key)) svgCache.set(key, buildSvg(key))

    return svgCache.get(key)
}

function buildSvg(key) {
    const sprite = PIXEL_SPRITES[key]
    if (!sprite) return ''

    const width = Math.max(...sprite.rows.map((row) => row.length))
    const height = sprite.rows.length

    let rects = ''
    sprite.rows.forEach((row, y) => {
        // Соседние пиксели одного цвета склеиваем в полосу — меньше узлов
        let x = 0
        while (x < row.length) {
            const char = row[x]
            let run = 1
            while (row[x + run] === char) run++

            const color = sprite.palette[char]
            if (char !== '.' && color) {
                rects += `<rect x="${x}" y="${y}" width="${run}" height="1" fill="${color}"/>`
            }
            x += run
        }
    })

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" shape-rendering="crispEdges">${rects}</svg>`
}

/** data-URL для CSS (частицы эффектов, фоны). */
export function spriteUrl(key) {
    if (!cache.has(key)) {
        const svg = spriteSvg(key)
        cache.set(key, svg ? `url("data:image/svg+xml;utf8,${encodeURIComponent(svg)}")` : 'none')
    }

    return cache.get(key)
}

/** Пропорции спрайта: ширина / высота. */
export function spriteRatio(key) {
    const sprite = PIXEL_SPRITES[key]
    if (!sprite) return 1

    return Math.max(...sprite.rows.map((row) => row.length)) / sprite.rows.length
}
