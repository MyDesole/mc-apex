/**
 * Оформление подсветки клана: цвета и эффекты.
 *
 * Ключи совпадают с effect_value предметов магазина и с набором
 * App\Support\ClanHighlight на сервере.
 */

export const HIGHLIGHT_COLORS = {
    gold: { name: 'Золотая', color: '#facc15', glow: 'rgba(250, 204, 21, 0.42)' },
    crimson: { name: 'Багровая', color: '#ef4444', glow: 'rgba(239, 68, 68, 0.42)' },
    cyan: { name: 'Голубая', color: '#06b6d4', glow: 'rgba(6, 182, 212, 0.42)' },
    violet: { name: 'Фиолетовая', color: '#8b5cf6', glow: 'rgba(139, 92, 246, 0.42)' },
    emerald: { name: 'Изумрудная', color: '#22c55e', glow: 'rgba(34, 197, 94, 0.42)' },
    rose: { name: 'Розовая', color: '#ec4899', glow: 'rgba(236, 72, 153, 0.42)' },
}

export const HIGHLIGHT_EFFECTS = {
    frame: { name: 'Рамка', animated: false },
    glow: { name: 'Свечение', animated: false },
    pulse: { name: 'Пульсация', animated: true },
    animated: { name: 'Перелив', animated: true },
    legendary: { name: 'Легендарный', animated: true },
}

export const DEFAULT_HIGHLIGHT_COLOR = 'gold'
export const DEFAULT_HIGHLIGHT_EFFECT = 'frame'

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
        class: {
            'hl-effect-glow': effect === HIGHLIGHT_EFFECTS.glow,
            'hl-effect-pulse': effect === HIGHLIGHT_EFFECTS.pulse,
            'hl-effect-animated': effect === HIGHLIGHT_EFFECTS.animated,
            'hl-effect-legendary': effect === HIGHLIGHT_EFFECTS.legendary,
        },
        color,
        effect,
    }
}

export default { HIGHLIGHT_COLORS, HIGHLIGHT_EFFECTS, highlightStyle, highlightColor, highlightEffect }
