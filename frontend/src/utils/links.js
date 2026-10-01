/**
 * Человекочитаемые ссылки на профиль и клан.
 *
 * Профиль: /user/Ник (если ник известен) — иначе /players/3.
 * Клан:    /clan/Имя_клана (если имя известно) — иначе /clans/7.
 *
 * Имя клана экранируется: пробелы заменяются на подчёркивания, чтобы ссылка
 * не разваливалась, а спецсимволы кодируются. Сервер понимает оба варианта.
 */

/** Профиль игрока. */
export function userLink(user) {
    if (!user) return '/players'

    // Ник предпочтительнее id — так ссылка читаемая
    const username = user.username ?? user.name

    if (username) {
        return `/user/${encodeURIComponent(username)}`
    }

    const id = user.id ?? user.user_id

    return id ? `/players/${id}` : '/players'
}

/** Клан. */
export function clanLink(clan) {
    if (!clan) return '/clans'

    const name = clan.name

    if (name) {
        // Пробелы -> подчёркивания: /clan/Team_Apex
        const slug = String(name).trim().replace(/\s+/g, '_')

        return `/clan/${encodeURIComponent(slug)}`
    }

    const id = clan.id ?? clan.clan_id

    return id ? `/clans/${id}` : '/clans'
}

export default { userLink, clanLink }
