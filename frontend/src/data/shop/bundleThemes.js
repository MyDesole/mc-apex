/**
 * Оформление наборов косметики: фон обложки, свечение и акцент.
 * Ключ — metadata.theme набора.
 */
export const BUNDLE_THEMES = {
    nether: {
        bg: 'radial-gradient(circle at 20% 110%, rgba(249,115,22,.55), transparent 55%), linear-gradient(160deg, #2a0a0a, #5b1010 55%, #1c0505)',
        glow: '#f97316',
        accent: '#fdba74',
        hero: 'flame',
    },
    deep_dark: {
        bg: 'radial-gradient(circle at 80% 100%, rgba(45,212,191,.35), transparent 55%), linear-gradient(160deg, #031b1a, #0b2a33 55%, #020617)',
        glow: '#2dd4bf',
        accent: '#5eead4',
        hero: 'warden',
    },
    royal: {
        bg: 'radial-gradient(circle at 50% 0%, rgba(250,204,21,.45), transparent 55%), linear-gradient(160deg, #2e1065, #4c1d95 50%, #1e0b3d)',
        glow: '#facc15',
        accent: '#fde68a',
        hero: 'crown',
    },
    creeper: {
        bg: 'repeating-conic-gradient(#14532d 0 25%, #166534 0 50%) 0 0 / 28px 28px',
        glow: '#4ade80',
        accent: '#bbf7d0',
        hero: 'creeper',
    },
    bees: {
        bg: 'radial-gradient(circle at 80% 20%, rgba(255,255,255,.35), transparent 40%), linear-gradient(160deg, #fde047, #f59e0b 60%, #b45309)',
        glow: '#facc15',
        accent: '#78350f',
        hero: 'bee',
        light: true,
    },
    axolotl: {
        bg: 'radial-gradient(circle at 20% 20%, rgba(255,255,255,.5), transparent 45%), linear-gradient(160deg, #fbcfe8, #f9a8d4 55%, #c4b5fd)',
        glow: '#ec4899',
        accent: '#831843',
        hero: 'axolotl',
        light: true,
    },
    cat: {
        bg: 'radial-gradient(circle at 75% 25%, rgba(255,255,255,.4), transparent 45%), linear-gradient(160deg, #fed7aa, #fb923c 60%, #c2410c)',
        glow: '#fb923c',
        accent: '#7c2d12',
        hero: 'cat',
        light: true,
    },
}

/*
 * Премиум-наборы: вместо пиксельной картинки на обложке — аватар с
 * живой рамкой набора (frame) прямо в карточке.
 */
Object.assign(BUNDLE_THEMES, {
    cyberpunk: {
        bg: 'linear-gradient(transparent 0 0), repeating-linear-gradient(0deg, rgba(5,217,232,.06) 0 1px, transparent 1px 4px), radial-gradient(circle at 80% 20%, rgba(255,42,109,.45), transparent 50%), linear-gradient(160deg, #0b0b14, #1a0b2e 60%, #05050a)',
        glow: '#05d9e8',
        accent: '#ff2a6d',
        frame: 'glitch',
    },
    cosmos: {
        bg: 'radial-gradient(1px 1px at 20% 30%, #fff, transparent), radial-gradient(1px 1px at 70% 70%, #fff, transparent), radial-gradient(1.5px 1.5px at 40% 80%, #fff, transparent), radial-gradient(ellipse at 30% 40%, rgba(129,140,248,.45), transparent 55%), radial-gradient(ellipse at 75% 60%, rgba(236,72,153,.3), transparent 55%), #050314',
        glow: '#818cf8',
        accent: '#c7d2fe',
        frame: 'black_hole',
    },
    holo: {
        bg: 'linear-gradient(120deg, rgba(255,61,129,.35), rgba(255,176,61,.3), rgba(61,255,160,.3), rgba(61,217,255,.35), rgba(107,61,255,.4)), #120f1c',
        glow: '#c084fc',
        accent: '#f5d0fe',
        frame: 'holo',
    },
    neon: {
        bg: 'linear-gradient(transparent 62%, rgba(236,72,153,.25)), repeating-linear-gradient(90deg, transparent 0 30px, rgba(34,211,238,.18) 30px 31px), linear-gradient(180deg, #0f0a1e, #1e0b3d)',
        glow: '#e879f9',
        accent: '#67e8f9',
        frame: 'neon',
    },
    party: {
        bg: 'radial-gradient(circle at 20% 30%, rgba(244,114,182,.5), transparent 40%), radial-gradient(circle at 80% 70%, rgba(250,204,21,.45), transparent 40%), radial-gradient(circle at 60% 20%, rgba(34,211,238,.4), transparent 35%), #1a1025',
        glow: '#f472b6',
        accent: '#fde68a',
        frame: 'plasma',
    },
    luxury: {
        bg: 'radial-gradient(circle at 50% 0%, rgba(250,204,21,.5), transparent 55%), linear-gradient(160deg, #1c1407, #3b2a07 55%, #0c0802)',
        glow: '#facc15',
        accent: '#fde68a',
        frame: 'chrome',
    },
})

// Четвёртая волна
Object.assign(BUNDLE_THEMES, {
    legend: {
        bg: 'conic-gradient(from 0deg at 50% 120%, rgba(250,204,21,.0), rgba(250,204,21,.35), rgba(250,204,21,.0) 30%), radial-gradient(circle at 50% 10%, rgba(253,230,138,.5), transparent 50%), linear-gradient(160deg, #1c1407, #2e1065 60%, #0c0802)',
        glow: '#facc15',
        accent: '#fde68a',
        frame: 'crown',
    },
    elements: {
        bg: 'radial-gradient(circle at 25% 100%, rgba(249,115,22,.6), transparent 50%), radial-gradient(circle at 80% 0%, rgba(165,180,252,.4), transparent 45%), linear-gradient(160deg, #1c0505, #1e1b4b)',
        glow: '#f97316',
        accent: '#fde047',
        frame: 'hellfire',
    },
    sweet: {
        bg: 'radial-gradient(circle at 20% 20%, rgba(255,255,255,.55), transparent 40%), linear-gradient(160deg, #fbcfe8, #f9a8d4 50%, #c4b5fd)',
        glow: '#ec4899',
        accent: '#831843',
        frame: 'sakura',
        light: true,
    },
    frost: {
        bg: 'radial-gradient(circle at 70% 20%, rgba(255,255,255,.45), transparent 40%), linear-gradient(160deg, #0c4a6e, #38bdf8 60%, #e0f2fe)',
        glow: '#7dd3fc',
        accent: '#0c4a6e',
        frame: 'diamond',
    },
})

export function bundleTheme(key) {
    return BUNDLE_THEMES[key] ?? BUNDLE_THEMES.royal
}
