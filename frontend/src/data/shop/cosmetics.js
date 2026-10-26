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
    sunset: { name: 'Закат' },
    synthwave: { name: 'Синтвейв' },
    cyber_city: { name: 'Кибергород' },
    space: { name: 'Открытый космос' },
    aurora_night: { name: 'Полярная ночь' },
    sakura_garden: { name: 'Сад сакуры' },
    snow_peaks: { name: 'Снежные пики' },
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

    // Третья волна: темы без Майнкрафта — неон, лёд, сакура, полночь…
    neon: {
        name: 'Неон',
        bg: '#0c0618',
        panel: '#1a0b2e',
        panelAlt: '#24103d',
        border: '#e879f9',
        accent: '#22d3ee',
    },
    frost: {
        name: 'Иней',
        bg: '#0b1620',
        panel: '#11253a',
        panelAlt: '#163049',
        border: '#7dd3fc',
        accent: '#bae6fd',
    },
    sakura: {
        name: 'Сакура',
        bg: '#1f0f17',
        panel: '#3a1a2a',
        panelAlt: '#4a2236',
        border: '#f9a8d4',
        accent: '#f472b6',
    },
    midnight: {
        name: 'Полночь',
        bg: '#070a1a',
        panel: '#111735',
        panelAlt: '#161e44',
        border: '#6366f1',
        accent: '#a5b4fc',
    },
    blood: {
        name: 'Кровавая луна',
        bg: '#120405',
        panel: '#2a080b',
        panelAlt: '#360b0f',
        border: '#b91c1c',
        accent: '#ef4444',
    },
    toxic: {
        name: 'Токсин',
        bg: '#0c1404',
        panel: '#16240a',
        panelAlt: '#1d300d',
        border: '#65a30d',
        accent: '#a3e635',
    },
    mono: {
        name: 'Монохром',
        bg: '#0a0a0a',
        panel: '#171717',
        panelAlt: '#202020',
        border: '#e5e5e5',
        accent: '#fafafa',
    },
    sunset: {
        name: 'Закат',
        bg: '#1a0d0a',
        panel: '#33150f',
        panelAlt: '#421c12',
        border: '#fb923c',
        accent: '#fbbf24',
    },
}

export function cardTheme(id) {
    return CARD_THEMES[id] ?? CARD_THEMES.default
}
