/**
 * Ссылки на соцсети из того, что ввёл человек.
 *
 * Чаще всего пишут не ссылку, а ник: «@username», «username» или
 * «t.me/username» без https. Собираем из этого рабочую ссылку под
 * каждую сеть. Если собрать нельзя (ник в Discord — это не ссылка),
 * возвращаем null: такой ник показываем и копируем по клику.
 */

// Ник: буквы, цифры, _ . - (у YouTube и VK бывают точки и дефисы)
const HANDLE = /^[A-Za-z0-9_.-]{2,64}$/

// Похоже на адрес сайта: что-то.домен, дальше может идти путь
const LOOKS_LIKE_DOMAIN = /^[\w-]+(\.[\w-]+)+(:\d+)?(\/\S*)?$/i

/** Как из ника сделать ссылку в каждой сети. */
const PROFILE_URL = {
    telegram: (h) => `https://t.me/${h}`,
    youtube: (h) => `https://www.youtube.com/@${h}`,
    twitch: (h) => `https://www.twitch.tv/${h}`,
    vk: (h) => `https://vk.com/${h}`,
    // Steam: 17 цифр — id профиля, иначе — свой адрес профиля
    steam: (h) => (/^\d{17}$/.test(h) ? `https://steamcommunity.com/profiles/${h}` : `https://steamcommunity.com/id/${h}`),
    tiktok: (h) => `https://www.tiktok.com/@${h}`,
    instagram: (h) => `https://www.instagram.com/${h}`,
    twitter: (h) => `https://x.com/${h}`,
    x: (h) => `https://x.com/${h}`,
    github: (h) => `https://github.com/${h}`,
}

/** Ключ сети без приставки настроек сайта: social_telegram → telegram. */
function networkOf(key) {
    return String(key || '').replace(/^social_/, '').toLowerCase()
}

/**
 * Рабочая ссылка или null, если это не ссылка (ник в Discord и т.п.).
 *
 * @param {string} key   сеть: telegram, youtube, discord…
 * @param {string} value то, что ввёл человек
 */
export function socialHref(key, value) {
    const raw = String(value ?? '').trim()
    if (!raw) return null

    if (/^https?:\/\//i.test(raw)) return raw

    // «t.me/name», «discord.gg/abc», «mysite.ru» — ссылка без https
    if (LOOKS_LIKE_DOMAIN.test(raw) && !raw.startsWith('@')) return `https://${raw}`

    const network = networkOf(key)
    const handle = raw.replace(/^@+/, '')
    const build = PROFILE_URL[network]

    if (build && HANDLE.test(handle)) return build(handle)

    return null
}

/**
 * Что показать на карточке: «@ник» для ника и для ссылки на профиль,
 * домен с путём — для остальных ссылок.
 */
export function socialDisplay(key, value) {
    const raw = String(value ?? '').trim()
    if (!raw) return ''

    const href = socialHref(key, value)

    if (!href) return raw

    // Ввели ник — показываем его же с @
    if (!/^https?:\/\//i.test(raw) && !LOOKS_LIKE_DOMAIN.test(raw)) {
        return `@${raw.replace(/^@+/, '')}`
    }

    try {
        const url = new URL(href)
        const host = url.hostname.replace(/^www\./, '')
        const path = url.pathname.replace(/\/+$/, '')

        // Ссылка на профиль в известной сети — показываем ник
        const handle = path.split('/').filter(Boolean).pop()
        if (PROFILE_URL[networkOf(key)] && handle && path.split('/').filter(Boolean).length <= 2) {
            return `@${handle.replace(/^@/, '')}`
        }

        const text = host + path
        return text.length > 32 ? `${text.slice(0, 31)}…` : text
    } catch {
        return raw
    }
}
