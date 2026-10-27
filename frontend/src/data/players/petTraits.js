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
}

/** Аксессуар: значок поверх головы; dy — насколько выше макушки. */
export const PET_ACCESSORIES = {
    none: null,
    cap: { icon: '🧢', dy: 0.72 },
    bow: { icon: '🎀', dy: 0.7 },
    flower: { icon: '🌸', dy: 0.72 },
    glasses: { icon: '🕶️', dy: 0.38 },
    tophat: { icon: '🎩', dy: 0.8 },
    scarf: { icon: '🧣', dy: 0.05 },
    crown: { icon: '👑', dy: 0.78 },
    halo: { icon: '😇', dy: 0.95, halo: true },
    horns: { icon: '😈', dy: 0.8, horns: true },
    wizard: { icon: '🧙', dy: 0.85 },
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
