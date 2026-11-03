/**
 * Иконки экипировки: 22 нарисованные формы × 14 материалов = 308 уникальных
 * спрайтов. Ключ — `gi-<форма>-<материал>`. Форма рисуется левой половиной
 * 16×16 (правая зеркалится), материал задаёт цвета: b/B/d — основной, свет и
 * тень металла; g/G — камень; h — рукоять и кожа; H — оправа.
 */
const sym = (half) => half + [...half].reverse().join('')

export const GEAR_SHAPES = {
    // ---------- Оружие ----------
    sword: [
        '......kk', '.....kBb', '.....kBb', '.....kBb', '.....kBb', '.....kBb', '.....kBb', '.....kBb',
        '.....kBb', '..kkkkHH', '.kHHHHgg', '..kkkkhh', '.....khh', '.....khh', '....kkHH', '.....kkk',
    ],
    greatsword: [
        '.......k', '......kB', '.....kBB', '.....kBb', '....kBbb', '....kBbb', '....kBbd', '....kBbd',
        '....kBbd', '..kkkkkd', '.kHHHHHH', '.kHgHHHH', '..kkkkhh', '.....khh', '....kkHH', '....kkkk',
    ],
    dagger: [
        '........', '........', '........', '.......k', '......kB', '......kB', '......kb', '......kb',
        '....kkHH', '....kHHg', '.....khh', '.....khh', '.....kHH', '......kk', '........', '........',
    ],
    spear: [
        '.......k', '......kB', '.....kBB', '.....kBb', '......kb', '.....kHH', '......kh', '......kh',
        '......kh', '......kh', '......kh', '......kh', '......kh', '......kh', '.....kHH', '......kk',
    ],
    staff: [
        '......kk', '.....kGg', '....kGgg', '....kggg', '.....kgg', '......kk', '.....kHH', '......kh',
        '......kh', '......kh', '......kh', '......kh', '......kh', '......kh', '.....kHh', '......kk',
    ],
    hammer: [
        '........', '.kkkkkkk', '.kBBBBbb', '.kBbbbbb', '.kBbbbbd', '.kkkkkkk', '......kh', '......kh',
        '......kh', '......kh', '......kh', '......kh', '......kh', '......kh', '.....kHh', '......kk',
    ],
    mace: [
        '..k..k..', '.....kkk', '...kkBBb', '..kkBbbb', '..kBbbbd', '..kBbbdd', '..kkbddd', '...kkddd',
        '....kkkk', '......kh', '......kh', '......kh', '......kh', '......kh', '.....kHh', '......kk',
    ],
    wand: [
        '........', '.......k', '......kG', '.....kGg', '......kg', '.......k', '......kh', '......kh',
        '......kh', '......kh', '......kh', '......kh', '......kh', '......kh', '......kH', '.......k',
    ],
    axe: [
        '........', '..kkkk..', '.kBBBBk.', 'kBBbbbkh', 'kBbbbbkh', 'kBbbbdkh', '.kbbddkh', '.kkddkkh',
        '..kkkkkh', '......kh', '......kh', '......kh', '......kh', '......kh', '.....kHh', '......kk',
    ],

    // ---------- Доспехи ----------
    chest: [
        '........', '..kkkk..', '.kBBbbk.', 'kBBbbbbb', 'kBbbbbbb', 'kkbbbbgG', '.kbbbbbg', '.kdbbbbb',
        '.kHHHHHH', '.kdbbbbb', '.kddbbbb', '..kddbbb', '..kkddbb', '...kkkdd', '......kk', '........',
    ],
    cloak: [
        '...kkkkk', '..kBBbbb', '..kBbbbb', '..kkHHHH', '.kBbbbbg', '.kBbbbbb', '.kBbbbbb', 'kBbbbbbb',
        'kBbbbdbb', 'kBbbbbbb', 'kBbbddbb', 'kbbbbddb', 'kbbdbbdd', 'kdbbdbbd', 'kHHHHHHH', 'kkkkkkkk',
    ],
    helm: [
        '........', '.....kHH', '...kkkHH', '..kBBBbb', '.kBBbbbb', '.kBbbbbb', '.kBbkgkk', '.kBbkkkk',
        '.kBbbbbb', '.kbbbbHH', '.kbbbbHH', '.kddbbHH', '..kkddHH', '...kkkkk', '........', '........',
    ],
    shield: [
        '........', '..kkkkkk', '.kHHHHHH', '.kHBbbbb', '.kHbbbgg', '.kHbbgGg', '.kHbbbgg', '.kHbbbbb',
        '.kHdbbbb', '..kHdbbb', '..kHddbb', '...kHddb', '....kHdd', '.....kHH', '......kk', '........',
    ],
    vest: [
        '........', '..kkkk..', '.khhhhk.', 'khhhbbbb', 'khhbbbbb', 'khhbbbkH', '.khbbbHk', '.khbbbkH',
        '.kHHHHHH', '.khdbbHk', '.khdbbkH', '..khdbbH', '..kkhdbb', '...kkhdd', '....kkkk', '........',
    ],

    // ---------- Талисманы ----------
    ring: [
        '........', '........', '......kk', '.....kGg', '.....kgg', '......kk', '....kkHH', '...kHHBb',
        '..kHB...', '..kHb...', '..kHb...', '..kHd...', '...kHdd.', '....kHdd', '.....kkk', '........',
    ],
    amulet: [
        '.k......', '.kh.....', '..kh....', '..kh....', '...kh...', '...kh...', '....kh..', '.....khh',
        '.....kHH', '....kHBb', '...kHBgg', '...kHBgG', '...kHBgg', '....kHbb', '.....kHH', '......kk',
    ],
    orb: [
        '........', '....kkkk', '...kGGGg', '..kGGGgg', '.kGGgggg', '.kGgggbg', '.kggggbb', '.kgggbbd',
        '.kggbbdd', '.kkgbbdd', '..kkbddd', '...kkddd', '..kHHHHH', '.kkHHHHH', '.kkkkkkk', '........',
    ],
    star: [
        '.......k', '......kG', '......kG', '.....kGg', 'kkkkkkGg', 'kGGGGGgg', '.kGGgggg', '..kGgggg',
        '..kggggg', '.kgggkkk', '.kggk...', '.kgk....', '.kk.....', '........', '........', '........',
    ],
    eye: [
        '........', '........', '........', '...kkkkk', '.kkkBBBB', '.kBBBBGG', 'kBBBBGgg', 'kBBBGgkk',
        'kBBBGgkk', '.kBBBBGg', '.kkkBBBB', '...kkkkk', '........', '........', '........', '........',
    ],
    crown: [
        '........', '........', '........', '.k..k..k', '.kHkkHkH', '.kHHHHHH', '.kBBbbbb', '.kBbGGgb',
        '.kbbgggb', '.kddbbbb', '.kHHHHHH', '.kkkkkkk', '........', '........', '........', '........',
    ],
    book: [
        '........', '........', '........', '..kkkkkk', '.kBBBBBb', '.kBHHHHH', '.kBHbbbb', '.kBHbGGg',
        '.kBHbGGg', '.kBHbbbb', '.kBHHHHH', '.kdddddd', '..kkkkkk', '........', '........', '........',
    ],
    vial: [
        '........', '.....kkk', '.....khh', '.....kkk', '......kB', '......kB', '...kkBBB', '..kBBggg',
        '.kBgggGg', '.kBggGgg', '.kBggggg', '.kdgggbb', '.kddgggg', '..kkddgg', '....kkkk', '........',
    ],
}

export const GEAR_MATERIALS = {
    iron: { b: '#8c93a3', B: '#c3c9d6', d: '#59606f', g: '#6b7280', G: '#9ca3af', h: '#6b4a2a', H: '#4b5563' },
    bronze: { b: '#b8793a', B: '#e0a35c', d: '#7a4a1f', g: '#e0a35c', G: '#f5d08a', h: '#5a3a1a', H: '#8a5a24' },
    steel: { b: '#9fb4c8', B: '#d9e6f2', d: '#5d7288', g: '#38bdf8', G: '#bae6fd', h: '#3f4a5a', H: '#6b7f95' },
    gold: { b: '#e0b030', B: '#fff0a0', d: '#9a7410', g: '#ef4444', G: '#fca5a5', h: '#6b4a1a', H: '#c89a1a' },
    ice: { b: '#7fc4e8', B: '#e0f5ff', d: '#3f84b0', g: '#38bdf8', G: '#e0f5ff', h: '#3a5a7a', H: '#5aa0cc' },
    fire: { b: '#e8651a', B: '#ffb347', d: '#a02a0a', g: '#fde047', G: '#fff3b0', h: '#4a2a1a', H: '#7a3a1a' },
    void: { b: '#7c3aed', B: '#c4b5fd', d: '#4c1d95', g: '#e879f9', G: '#fae8ff', h: '#2a1a4a', H: '#5b21b6' },
    toxic: { b: '#4caf3a', B: '#a3e66b', d: '#2a6a1f', g: '#d9f99d', G: '#f7fee7', h: '#3a4a1a', H: '#3f7a2a' },
    blood: { b: '#b3262e', B: '#f26b6b', d: '#6a1018', g: '#ffd0d0', G: '#ffffff', h: '#3a1a1a', H: '#7a1a22' },
    sea: { b: '#1f8a9a', B: '#6fd6e0', d: '#0f4a5a', g: '#5eead4', G: '#ccfbf1', h: '#2a4a4a', H: '#2a7a8a' },
    bone: { b: '#d8cfb4', B: '#f5efdc', d: '#9a9070', g: '#a3a3a3', G: '#e5e5e5', h: '#5a4a30', H: '#b0a680' },
    sun: { b: '#fcd34d', B: '#fffbe0', d: '#d99a10', g: '#fb923c', G: '#fff0c0', h: '#8a5a1a', H: '#f5b520' },
    shadow: { b: '#3b3a52', B: '#6b6a8c', d: '#1c1b2e', g: '#a78bfa', G: '#ddd6fe', h: '#1a1a28', H: '#2a2a42' },
    crystal: { b: '#d946ef', B: '#f5d0fe', d: '#86198f', g: '#22d3ee', G: '#cffafe', h: '#3a1a4a', H: '#a21caf' },
}

export const GEAR_SPRITES = {}

for (const [shape, halves] of Object.entries(GEAR_SHAPES)) {
    const rows = halves.map(sym)

    for (const [material, colors] of Object.entries(GEAR_MATERIALS)) {
        GEAR_SPRITES[`gi-${shape}-${material}`] = { palette: { k: '#15101a', ...colors }, rows }
    }
}
