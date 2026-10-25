/**
 * Косметика из магазина, которую рисует фронтенд: фон 3D-скина и тема
 * карточки игрока. Значения ключей совпадают с effect_value в ShopItemSeeder.
 */

/** Сцены за 3D-скином в профиле. Оформление — CSS-класс showcase-stage--{id}. */
export const SKIN_BACKDROPS = {
    overworld: { name: 'Верхний мир' },
    ocean: { name: 'Океан' },
    nether: { name: 'Незер' },
    end: { name: 'Край' },
    deep_dark: { name: 'Глубокая тьма' },
}

/**
 * Темы карточки игрока (PNG).
 *
 * bg и panel/panelAlt — фон и шахматка постамента под фигурой,
 * accent — полоса сверху и полосы аспектов (если не задано, берётся
 * цвет тира), border — рамка карточки.
 */
export const CARD_THEMES = {
    default: {
        name: 'Обычная',
        bg: '#16141c',
        panel: '#1b1825',
        panelAlt: '#201c2b',
        border: '#2a2633',
        accent: null,
    },
    emerald: {
        name: 'Изумруд',
        bg: '#0f1b16',
        panel: '#11251c',
        panelAlt: '#153023',
        border: '#1f6b4a',
        accent: '#10b981',
    },
    nether: {
        name: 'Незер',
        bg: '#1c0f0f',
        panel: '#3a1414',
        panelAlt: '#4a1a18',
        border: '#7f1d1d',
        accent: '#f97316',
    },
    end: {
        name: 'Край',
        bg: '#120f1c',
        panel: '#2a2340',
        panelAlt: '#e9e5a8',
        border: '#6d28d9',
        accent: '#c084fc',
        // Шахматка из эндерняка: светлые клетки приглушаем
        panelAltAlpha: 0.12,
    },
    gold: {
        name: 'Золото',
        bg: '#1a1508',
        panel: '#2b2208',
        panelAlt: '#3a2e0a',
        border: '#ca8a04',
        accent: '#facc15',
    },
}

export function cardTheme(id) {
    return CARD_THEMES[id] ?? CARD_THEMES.default
}
