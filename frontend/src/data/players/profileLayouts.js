/**
 * Макеты профиля из магазина (тип profile_layout). Ключи совпадают
 * с effect_value в ShopItemSeeder.
 *
 * Внешний вид задаёт CSS-класс .profile-layout--{id} в profileLayouts.css,
 * здесь — только то, что нужно скрипту и превью в магазине:
 *   arrangement — где боковая колонка: right (как обычно), left,
 *                 top (витриной над профилем), bottom (под профилем);
 *   preview     — цвета мини-макета в карточке товара.
 */
export const PROFILE_LAYOUTS = {
    nether_forge: {
        name: 'Адская кузня',
        arrangement: 'left',
        preview: { page: '#1a0b0a', card: '#2a100c', side: '#3a140e', accent: '#f97316', line: '#fbbf24' },
    },
    end_void: {
        name: 'Пустота Края',
        arrangement: 'top',
        preview: { page: '#07050f', card: '#140f24', side: '#1e1636', accent: '#c084fc', line: '#e9e5a8' },
    },
    terminal: {
        name: 'Терминал',
        arrangement: 'right',
        preview: { page: '#020603', card: '#04110a', side: '#04110a', accent: '#22c55e', line: '#4ade80' },
    },
    book: {
        name: 'Книга и перо',
        arrangement: 'bottom',
        light: true,
        preview: { page: '#2b2118', card: '#f3e7cf', side: '#eadbbd', accent: '#7c2d12', line: '#3f2a1a' },
    },
    synthwave: {
        name: 'Синтвейв',
        arrangement: 'right',
        preview: { page: '#12052a', card: '#1d0a3d', side: '#24104a', accent: '#ec4899', line: '#22d3ee' },
    },
    royal: {
        name: 'Тронный зал',
        arrangement: 'bottom',
        preview: { page: '#140a1f', card: '#221033', side: '#2b1640', accent: '#facc15', line: '#fde68a' },
    },
    ancient_city: {
        name: 'Древний город',
        arrangement: 'left',
        preview: { page: '#05100f', card: '#0a1a1a', side: '#0d2222', accent: '#2dd4bf', line: '#5eead4' },
    },
    frost: {
        name: 'Ледяной пик',
        arrangement: 'top',
        preview: { page: '#0b1622', card: '#13263a', side: '#1a3149', accent: '#7dd3fc', line: '#e0f2fe' },
    },

    // Милые макеты: светлые пастельные, круглые углы, мягкие тени
    sakura: {
        name: 'Сакура',
        arrangement: 'right',
        light: true,
        preview: { page: '#f9d5e5', card: '#fff5f9', side: '#ffe4ef', accent: '#ec4899', line: '#9d4b6e' },
    },
    strawberry_milk: {
        name: 'Клубничное молоко',
        arrangement: 'top',
        light: true,
        preview: { page: '#ffe1e8', card: '#fffaf5', side: '#ffeef2', accent: '#f472b6', line: '#a0525f' },
    },
    cotton_candy: {
        name: 'Сахарная вата',
        arrangement: 'left',
        preview: { page: '#2a1f45', card: '#352a5a', side: '#3d2f66', accent: '#f9a8d4', line: '#bae6fd' },
    },
}

/** Макет по ключу или null — обычный профиль. */
export function profileLayout(id) {
    return id ? PROFILE_LAYOUTS[id] ?? null : null
}
