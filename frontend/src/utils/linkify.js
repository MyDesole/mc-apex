/**
 * Разбор текста на куски «текст» и «ссылка».
 *
 * Возвращает массив сегментов вида { type: 'text' | 'link', text, href }.
 * Ссылками считаются только http(s):// и www. — так в разметку не попадёт
 * javascript: и прочие опасные схемы. HTML не строится вообще: компонент
 * рисует сегменты сам.
 */

const URL_RE = /(https?:\/\/[^\s<>"']+|www\.[^\s<>"']+)/gi

/** Знаки, которыми заканчивается предложение, а не адрес. */
const TRAILING = /[.,;:!?»”"'\]}]+$/

/** Сколько закрывающих скобок лишние: «(см. https://a.ru/x)» не забирает «)». */
function trimTrailing(raw) {
    let url = raw

    for (;;) {
        const before = url

        url = url.replace(TRAILING, '')

        // Скобка принадлежит адресу, только если открывающая есть внутри него
        while (url.endsWith(')') && (url.match(/\)/g) || []).length > (url.match(/\(/g) || []).length) {
            url = url.slice(0, -1)
        }

        if (url === before) break
    }

    return url
}

export function linkify(text) {
    const source = String(text ?? '')
    const segments = []

    let last = 0

    for (const match of source.matchAll(URL_RE)) {
        const url = trimTrailing(match[0])

        if (!url) continue

        if (match.index > last) {
            segments.push({ type: 'text', text: source.slice(last, match.index) })
        }

        segments.push({
            type: 'link',
            text: url,
            href: /^www\./i.test(url) ? `https://${url}` : url,
        })

        last = match.index + url.length
    }

    if (last < source.length) {
        segments.push({ type: 'text', text: source.slice(last) })
    }

    return segments
}

/** Путь внутри сайта, если ссылка ведёт на этот же сайт, иначе null. */
export function internalPath(href, hosts = []) {
    try {
        const url = new URL(href)

        return hosts.includes(url.hostname) ? `${url.pathname}${url.search}${url.hash}` : null
    } catch {
        return null
    }
}
