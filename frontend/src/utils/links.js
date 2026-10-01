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

/** Является ли клан кланом текущего игрока. */
export function isOwnClan(clan, myClanId) {
    if (!clan || myClanId === null || myClanId === undefined) return false

    const clanId = clan.id ?? clan.clan_id

    return clanId !== null && clanId !== undefined && Number(clanId) === Number(myClanId)
}

/**
 * Клан.
 *
 * Свой клан ведёт во вкладку «Мой клан»: там доступны форум, ресурсы,
 * участники и выход — то, чего нет на публичной странице клана.
 */
export function clanLink(clan, { myClanId = null } = {}) {
    if (!clan) return '/clans'

    if (isOwnClan(clan, myClanId)) return '/my-clan'

    const name = clan.name

    if (name) {
        // Пробелы -> подчёркивания: /clan/Team_Apex
        const slug = String(name).trim().replace(/\s+/g, '_')

        return `/clan/${encodeURIComponent(slug)}`
    }

    const id = clan.id ?? clan.clan_id

    return id ? `/clans/${id}` : '/clans'
}

export default { userLink, clanLink, isOwnClan }
