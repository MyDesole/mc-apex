/**
 * Пиксельные иконки ачивок 16×16 — собственная отрисовка в духе Minecraft.
 *
 * Каждая иконка — 16 строк по 16 символов, символ — цвет пикселя:
 *   .  прозрачный          o  контур
 *   m  основной цвет ачивки l  светлее   d  темнее   w  блик
 *   r  красный   b B  дерево (светлое, тёмное)   s S  сталь
 *   k K  кожа    u  синий (глаза)
 *
 * Симметричные иконки задаются левой половиной (8 символов): правая
 * получается отражением, и на ней светлое становится основным, а
 * основное — тёмным, будто свет падает слева.
 */

const SHADE_RIGHT = { l: 'm', m: 'd' }

/** Достраивает правую половину симметричной иконки. */
function mirror(rows) {
    return rows.map((half) => {
        const right = [...half].reverse().map((ch) => SHADE_RIGHT[ch] ?? ch).join('')

        return half + right
    })
}

/** Добивает иконку пустыми строками до 16 сверху и снизу. */
function pad(rows, top = 0) {
    const empty = '.'.repeat(16)
    const out = [...Array(top).fill(empty), ...rows]

    while (out.length < 16) out.push(empty)

    return out
}

export const PIXEL_ICONS = {
    sword: pad([
        '............ooo.',
        '...........olmo.',
        '..........olmmo.',
        '.........olmmo..',
        '........olmmo...',
        '.......olmmo....',
        '..o...olmmo.....',
        '.odo.olmmo......',
        '..odolmmo.......',
        '...odmmo........',
        '...obddo........',
        '..obbodo........',
        '.obbo.o.........',
        'obbo............',
        'obo.............',
        'oo..............',
    ]),

    pickaxe: pad([
        '....ooooo.......',
        '...ommmmmoo.....',
        '..ollllmmmmo....',
        '..ooooobmmmo....',
        '.......obmmmo...',
        '......obo.ommo..',
        '.....obo...omo..',
        '....obo.....oo..',
        '...obo..........',
        '..obo...........',
        '.obo............',
        'obo.............',
        'oo..............',
    ], 2),

    swords: pad(mirror([
        'oo......',
        'olo.....',
        '.olo....',
        '..olo...',
        '...olo..',
        '....olo.',
        '.....olo',
        '......ol',
        '.....odo',
        '..o.odo.',
        '..odbo..',
        '...obo..',
        '..obo...',
        '.obo....',
        'obo.....',
        'oo......',
    ])),

    shield: pad(mirror([
        '..oooooo',
        '..olllll',
        '..olmmmm',
        '..olmmmw',
        '..olmmmw',
        '..olmwww',
        '..olmwww',
        '..olmmmw',
        '..olmmmw',
        '...olmmm',
        '...olmmm',
        '....olmm',
        '.....omm',
        '......oo',
    ]), 1),

    crown: pad(mirror([
        '.o.....o',
        'oro...or',
        'omo..omm',
        'ommo.omm',
        'ommmommm',
        'olmmmlmm',
        'ommmmmmm',
        'omrmmmwm',
        'ommmmmmm',
        'oddddddd',
        'oooooooo',
    ]), 3),

    diamond: pad(mirror([
        '....oooo',
        '...owwll',
        '..owwlll',
        '.owlllll',
        'oooooooo',
        '.ommmmmm',
        '..ommmmm',
        '...ommmm',
        '....ommm',
        '.....omm',
        '......om',
        '.......o',
    ]), 2),

    emerald: pad(mirror([
        '......oo',
        '.....oll',
        '....olll',
        '...olwll',
        '...olwll',
        '...ollll',
        '...ollmm',
        '...ollmm',
        '...olmmm',
        '...olmmm',
        '....ommm',
        '.....omm',
        '......oo',
    ]), 1),

    trophy: pad(mirror([
        '..oooooo',
        'oooollll',
        'o..ollll',
        'o..olwll',
        '.o.olwll',
        '..ooolll',
        '....olll',
        '.....oll',
        '......ol',
        '......om',
        '......om',
        '.....oom',
        '....ommm',
        '....oooo',
    ]), 1),

    star: pad(mirror([
        '.......o',
        '......ol',
        '......ol',
        '.....oll',
        'ooooooll',
        '.olllllw',
        '..olllll',
        '...ollll',
        '....olll',
        '....olll',
        '...ollo.',
        '..olo...',
        '..oo....',
    ]), 1),

    heart: pad(mirror([
        '..oooo..',
        '.owwlmo.',
        'owwllmmm',
        'owllmmmm',
        'olmmmmmm',
        '.olmmmmm',
        '..ommmmm',
        '...ommmm',
        '....ommm',
        '.....omm',
        '......om',
        '.......o',
    ]), 2),

    orb: pad(mirror([
        '.....ooo',
        '...oolll',
        '..ollwwl',
        '.olwwlll',
        '.olwllll',
        'olllllmm',
        'olllmmmm',
        'ollmmmmm',
        '.olmmmmm',
        '.ommmmmm',
        '..ommmmm',
        '...oommm',
        '.....ooo',
    ]), 2),

    coin: pad(mirror([
        '.....ooo',
        '...oommm',
        '..ommlll',
        '.omllwll',
        '.omlwlld',
        'omllllld',
        'omllllld',
        'omllllld',
        '.omlllld',
        '.omlllll',
        '..ommlll',
        '...oommm',
        '.....ooo',
    ]), 2),

    head: pad(mirror([
        '...ooooo',
        '...oBBBB',
        '...oBBBB',
        '...oBkkk',
        '...okkkk',
        '...okwuk',
        '...okkkk',
        '...okkKK',
        '...okKBB',
        '...okkkk',
        '...ooooo',
    ]), 3),

    flame: pad([
        '.......o........',
        '......oro.......',
        '......orro......',
        '.....orrro..o...',
        '....orrrro.oro..',
        '...orrmrrrorro..',
        '..orrmmmrrrrro..',
        '..ormmlmmrrrro..',
        '.orrmllllmmrrro.',
        '.ormllwwllmmrro.',
        '.ormlwwwwlmmrro.',
        '..ormlwwlmmrro..',
        '...orrmmmmrro...',
        '....oorrrroo....',
    ], 1),

    bolt: pad([
        '.......oooooo...',
        '......olllmmo...',
        '.....olllmmo....',
        '....olllmmo.....',
        '...olllmmooooo..',
        '..olllllmmmmmo..',
        '..oooooolllmo...',
        '......ollmmo....',
        '.....ollmmo.....',
        '....ollmmo......',
        '...olmmo........',
        '..olmo..........',
        '..oo............',
    ], 1),

    book: pad([
        '..ooooooooooo...',
        '..odmmmmmmmmmo..',
        '..odmllllllmmo..',
        '..odmlwwwwlmmo..',
        '..odmllllllmmo..',
        '..odmmmmmmmmmo..',
        '..odmmmmmmmmmo..',
        '..odmmmmmmmmmo..',
        '..odmmmmmmmmmo..',
        '..odmmmmmmmmmo..',
        '..odssssssssso..',
        '..odwwwwwwwwwo..',
        '..oooooooooooo..',
    ], 2),

    chat: pad([
        '..oooooooooooo..',
        '.ollllllllllmmo.',
        '.olwwwwwwwwlmmo.',
        '.olllllllllmmmo.',
        '.olwwwwwwlllmmo.',
        '.ollllllllmmmmo.',
        '.ommmmmmmmmmmdo.',
        '..ooommooooooo..',
        '....ommo........',
        '....omo.........',
        '....oo..........',
    ], 3),

    tnt: pad([
        '..oooooooooooo..',
        '..orrrrrrrrrro..',
        '..orrdrrrrdrro..',
        '..orrdrrrrdrro..',
        '..owwwwwwwwwwo..',
        '..owdwwddwwdwo..',
        '..owwwwwwwwwwo..',
        '..orrdrrrrdrro..',
        '..orrdrrrrdrro..',
        '..orrrrrrrrrro..',
        '..oooooooooooo..',
    ], 3),

    chest: pad([
        '..oooooooooooo..',
        '..obbbbbbbbbbo..',
        '..obBBBBBBBBbo..',
        '..obbbbbbbbbbo..',
        '..ooooossooooo..',
        '..obbbbsSbbbbo..',
        '..obbbbbbbbbbo..',
        '..obBBBBBBBBbo..',
        '..obbbbbbbbbbo..',
        '..obbbbbbbbbbo..',
        '..oooooooooooo..',
    ], 3),

    flag: pad([
        '..oo............',
        '..obooooooooo...',
        '..obommmmmmmmo..',
        '..obolllmmmmmo..',
        '..obolwlmmmmmo..',
        '..obolllmmmmmo..',
        '..obommmmmmmmo..',
        '..obommmmmmmdo..',
        '..obooooooooo...',
        '..obo...........',
        '..obo...........',
        '..obo...........',
        '..obo...........',
        '.obbbo..........',
        '.ooooo..........',
    ], 1),

    bed: pad([
        '.oooooooooooooo.',
        '.owwwolllllllmo.',
        '.owwwommmmmmmmo.',
        '.oooommmmmmmmdo.',
        '.obbbbbbbbbbbbo.',
        '.obo........obo.',
        '.ooo........ooo.',
    ], 5),
}

export const PIXEL_ICON_KEYS = Object.keys(PIXEL_ICONS)

/** Фиксированные цвета, не зависящие от цвета ачивки. */
export const FIXED_COLORS = {
    o: '#120f18',
    r: '#e5484d',
    b: '#a06b3c',
    B: '#6b4423',
    s: '#d6d6de',
    S: '#8d8d99',
    k: '#d9a274',
    K: '#b07e55',
    u: '#4f6bd8',
}
