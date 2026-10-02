/**
 * Общие константы экономики ApexCoin для интерфейса.
 */

export const RARITY_LABELS = {
    common: 'Обычный',
    rare: 'Редкий',
    epic: 'Эпический',
    legendary: 'Легендарный',
}

export const RARITY_COLORS = {
    common: '#8b8b9e',
    rare: '#06b6d4',
    epic: '#a855f7',
    legendary: '#facc15',
}

export const TYPE_LABELS = {
    avatar_frame: 'Рамка аватара',
    profile_effect: 'Эффект профиля',
    accent_color: 'Акцентный цвет',
    card_background: 'Фон карточки',
    badge: 'Бейдж',
    tier_priority: 'Приоритет тир-теста',
    coin_bundle: 'Набор монет',
    clan_highlight: 'Подсветка клана',
    clan_highlight_style: 'Оформление подсветки',
}

export const SOURCE_LABELS = {
    tier_test: 'Тир-тесты',
    achievement: 'Ачивки',
    daily_bonus: 'Ежедневный бонус',
    gift_in: 'Подарки от друзей',
    gift_out: 'Подарки друзьям',
    purchase: 'Покупки в магазине',
    clan_fee: 'Плата за вступление в клан',
    admin: 'Начисления от админа',
    other: 'Прочее',
}

export function formatCoins(value) {
    const number = Number(value ?? 0)

    return number.toLocaleString('ru-RU')
}

export function rarityColor(rarity) {
    return RARITY_COLORS[rarity] || RARITY_COLORS.common
}

/**
 * Короткое пояснение под названием предмета.
 */
export function itemSubtitle(item) {
    if (item?.type === 'tier_priority') {
        return 'Одна заявка вне очереди'
    }

    if (item?.type === 'badge') {
        return 'Виден в профиле'
    }

    return TYPE_LABELS[item?.type] || 'Предмет'
}
