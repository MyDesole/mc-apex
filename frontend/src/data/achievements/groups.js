/**
 * Группы ачивок для страницы каталога.
 *
 * Группа определяется по коду: так новые ачивки из сидера сразу
 * попадают в свой раздел, а созданные админом вручную — в «Особые».
 */
export const ACHIEVEMENT_GROUPS = [
    { key: 'tiers', label: 'Тиры и рейтинг', match: /^(tier_[a-z]+(_plus)?|top_\d+)$/ },
    { key: 'tests', label: 'Тир-тесты и навыки', match: /^(tier_test_|tier_comeback|block_|rod_|movement_|aim_|tactician|all_rounder|aspect_|bedwars_|dual_mode)/ },
    { key: 'clan', label: 'Кланы и войны', match: /^clan_/ },
    { key: 'tournaments', label: 'Турниры', match: /^tournament_/ },
    { key: 'social', label: 'Сообщество', match: /^(friends_|recommended|referral_|forum_|chat_)/ },
    { key: 'shop', label: 'Магазин', match: /^(shop_|big_spender|gift_giver)/ },
    { key: 'misc', label: 'Профиль и бридж', match: /^(minecraft_|profile_|account_|bridge_)/ },
]

export const SPECIAL_GROUP = { key: 'special', label: 'Особые' }

export const RARITY_LABELS = {
    common: 'Обычная',
    rare: 'Редкая',
    epic: 'Эпическая',
    legendary: 'Легендарная',
}

export const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary']

/** Ключ группы по коду ачивки. */
export function groupOf(code) {
    return ACHIEVEMENT_GROUPS.find((group) => group.match.test(code ?? ''))?.key ?? SPECIAL_GROUP.key
}
