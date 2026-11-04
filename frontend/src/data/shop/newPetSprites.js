/**
 * Питомцы стихий ветра, земли и света: нарисованы вручную, 8×8.
 * Ключ совпадает с effect_value питомца в магазине.
 */
export const NEW_PET_SPRITES = {
    hawk: {
        palette: { b: '#92400e', B: '#d97706', w: '#fef3c7', y: '#facc15', k: '#1c1917' },
        rows: [
            '..bbbb..',
            '.bbbbbb.',
            '.bkbbkb.',
            '.bbyybb.',
            'bBbwwbBb',
            'BBbwwbBB',
            '.BbbbbB.',
            '..y..y..',
        ],
    },
    cloud: {
        palette: { c: '#e0f2fe', C: '#bae6fd', w: '#ffffff', k: '#0c4a6e', p: '#fda4af' },
        rows: [
            '..cccc..',
            '.cwwwcc.',
            'cwwwwwcc',
            'cckwwkcc',
            'cwwppwwc',
            'Cccccccc',
            '.CCccCC.',
            '..C..C..',
        ],
    },
    turtle: {
        palette: { g: '#16a34a', G: '#15803d', s: '#a16207', S: '#78350f', k: '#1c1917' },
        rows: [
            '..SSSS..',
            '.SsSsSS.',
            'SsSsSsSS',
            'SSSsSsSS',
            '.gSSSSg.',
            'gkggggkg',
            '.gggggg.',
            '.GG..GG.',
        ],
    },
    mole: {
        palette: { m: '#57534e', M: '#a8a29e', n: '#fda4af', k: '#0c0a09', c: '#e7e5e4' },
        rows: [
            '..mmmm..',
            '.mmmmmm.',
            '.mkmmkm.',
            '.mmnnmm.',
            'mmMnnMmm',
            'mmmccmmm',
            '.mmmmmm.',
            '.cc..cc.',
        ],
    },
    bat: {
        palette: { v: '#4c1d95', V: '#7c3aed', e: '#facc15', k: '#1e1b4b', w: '#ffffff' },
        rows: [
            'v......v',
            'vv.vv.vv',
            'vVvvvvVv',
            'vVevveVv',
            'vVvwwvVv',
            '.vvvvvv.',
            '..v..v..',
            '........',
        ],
    },
    golem: {
        palette: { s: '#78716c', S: '#a8a29e', m: '#4d7c0f', e: '#fb923c', k: '#1c1917' },
        rows: [
            '..SSSS..',
            '.SssssS.',
            '.SeSSeS.',
            'SSssssSS',
            'sSsmmsSs',
            'sSssssSs',
            '.SSssSS.',
            '.ss..ss.',
        ],
    },
    fairy: {
        palette: { p: '#fbcfe8', P: '#f472b6', w: '#ffffff', y: '#fde047', c: '#a5f3fc', k: '#831843' },
        rows: [
            '..yyyy..',
            '.yppppy.',
            '.pkppkp.',
            'cppwwppc',
            'cwcPPcwc',
            '.c.PP.c.',
            '...PP...',
            '..P..P..',
        ],
    },
    griffin: {
        palette: { g: '#a16207', G: '#fde047', w: '#f8fafc', k: '#1c1917', o: '#f97316' },
        rows: [
            '.G.ww.G.',
            'GGwwwwGG',
            'GwkwwkwG',
            'GGwoowGG',
            'GgGggGgG',
            '.GggggG.',
            '.gg..gg.',
            '.o....o.',
        ],
    },
}
