import { reactive } from 'vue'

/**
 * Оформление подсветки клана: цвета и эффекты.
 *
 * Ключи совпадают с effect_value предметов магазина, с набором
 * App\Support\ClanHighlight на сервере и с классами .effect-*
 * в ClansView — поэтому купленное сразу видно на карточке клана.
 */

export const HIGHLIGHT_COLORS = {
    gold: { name: 'Золотая', color: '#facc15', glow: 'rgba(250, 204, 21, 0.42)' },
    crimson: { name: 'Багровая', color: '#ef4444', glow: 'rgba(239, 68, 68, 0.42)' },
    cyan: { name: 'Голубая', color: '#06b6d4', glow: 'rgba(6, 182, 212, 0.42)' },
    violet: { name: 'Фиолетовая', color: '#8b5cf6', glow: 'rgba(139, 92, 246, 0.42)' },
    emerald: { name: 'Изумрудная', color: '#22c55e', glow: 'rgba(34, 197, 94, 0.42)' },
    rose: { name: 'Розовая', color: '#ec4899', glow: 'rgba(236, 72, 153, 0.42)' },
    midnight: { name: 'Полночная', color: '#6366f1', glow: 'rgba(99, 102, 241, 0.42)' },
    sunset: { name: 'Закатная', color: '#fb923c', glow: 'rgba(251, 146, 60, 0.42)' },
    frost: { name: 'Морозная', color: '#7dd3fc', glow: 'rgba(125, 211, 252, 0.42)' },
    mint: { name: 'Мятная', color: '#5eead4', glow: 'rgba(94, 234, 212, 0.42)' },
    blood: { name: 'Кровавая', color: '#b91c1c', glow: 'rgba(185, 28, 28, 0.5)' },
    lavender: { name: 'Лавандовая', color: '#c4b5fd', glow: 'rgba(196, 181, 253, 0.42)' },
    chrome: { name: 'Хромовая', color: '#e2e8f0', glow: 'rgba(226, 232, 240, 0.4)' },
    toxic: { name: 'Токсичная', color: '#a3e635', glow: 'rgba(163, 230, 53, 0.45)' },
}

/**
 * Эффекты: те же, что отрисованы в ClansView.
 */
export const HIGHLIGHT_EFFECTS = {
    frame: { name: 'Рамка', animated: false },
    glow: { name: 'Свечение', animated: false },
    pulse: { name: 'Пульсация', animated: true },
    gradient: { name: 'Градиент', animated: true },
    fire: { name: 'Огонь', animated: true },
    ice: { name: 'Лёд', animated: false },
    aurora: { name: 'Северное сияние', animated: true },
    legendary: { name: 'Легендарный', animated: true },
    neon: { name: 'Неон', animated: true },
    holo: { name: 'Голограмма', animated: true },
    storm: { name: 'Гроза', animated: true },
    matrix: { name: 'Матрица', animated: true },
    galaxy: { name: 'Галактика', animated: true },
    lava: { name: 'Лава', animated: true },
    glitch: { name: 'Глитч', animated: true },
    shine: { name: 'Блик', animated: true },
}

/** Базовый эффект, если игрок ничего не покупал. */
export const DEFAULT_HIGHLIGHT_EFFECT = 'frame'

export const DEFAULT_HIGHLIGHT_COLOR = 'gold'

/** Настройки цвета: для неизвестного ключа берём золото. */
export function highlightColor(key) {
    return HIGHLIGHT_COLORS[key] ?? HIGHLIGHT_COLORS[DEFAULT_HIGHLIGHT_COLOR]
}

/** Эффект: неизвестный ключ считаем базовой рамкой. */
export function highlightEffect(key) {
    return HIGHLIGHT_EFFECTS[key] ?? HIGHLIGHT_EFFECTS[DEFAULT_HIGHLIGHT_EFFECT]
}

/**
 * Стили и классы для карточки клана.
 *
 * Возвращает объект, который удобно раскидать в шаблоне:
 *   :style="highlightStyle(clan).style"
 *   :class="highlightStyle(clan).class"
 */
export function highlightStyle(clan) {
    if (!clan?.is_highlighted) return { style: {}, class: {} }

    const color = highlightColor(clan.highlight_color)
    const effect = highlightEffect(clan.highlight_effect)

    return {
        style: {
            '--hl-color': color.color,
            '--hl-glow': color.glow,
        },
        // Классы совпадают с .effect-* в ClansView
        class: Object.fromEntries(
            Object.keys(HIGHLIGHT_EFFECTS).map((key) => [`effect-${key}`, effect === HIGHLIGHT_EFFECTS[key]])
        ),
        color,
        effect,
    }
}

/**
 * Предпросмотр оформления на мини-карточке в выборе.
 *
 * Отдаёт CSS-переменные и класс эффекта, чтобы лидер видел,
 * как будет выглядеть подсветка, до применения.
 */
export function stylePreview(colorKey, effectKey) {
    const color = highlightColor(colorKey)
    const effect = highlightEffect(effectKey)

    return {
        '--hl-color': color.color,
        '--hl-glow': color.glow,
        effectClass: `effect-${Object.keys(HIGHLIGHT_EFFECTS).find((k) => HIGHLIGHT_EFFECTS[k] === effect) ?? DEFAULT_HIGHLIGHT_EFFECT}`,
    }
}

/** Реактивное состояние выбора оформления. */
export function createStylePicker(initial = { color: null, effect: null }) {
    return reactive({
        color: initial.color ?? DEFAULT_HIGHLIGHT_COLOR,
        effect: initial.effect ?? DEFAULT_HIGHLIGHT_EFFECT,
    })
}

export default {
    HIGHLIGHT_COLORS,
    HIGHLIGHT_EFFECTS,
    highlightStyle,
    highlightColor,
    highlightEffect,
    stylePreview,
    createStylePicker,
}
