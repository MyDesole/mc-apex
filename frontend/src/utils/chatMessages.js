/**
 * Слияние списков сообщений.
 *
 * Одно и то же сообщение приходит тремя путями: ответом на отправку,
 * по сокету и запасным опросом. Порядок прихода не гарантирован — опрос
 * может принести своё сообщение раньше, чем закончится его отправка.
 * Поэтому все пути добавляют сообщения только через эту функцию.
 */

/**
 * Новый список: известные сообщения плюс новые, без повторов по id,
 * по возрастанию id (id растут вместе со временем отправки).
 *
 * Уже известное сообщение не заменяется: у него могли появиться
 * локальные правки — отметки прочтения, редактирование.
 *
 * @param {Array<{id: number}>} current
 * @param {Array<{id: number}>|{id: number}} incoming
 * @returns {{ list: Array, added: Array }}
 */
export function mergeMessages(current, incoming) {
    const items = Array.isArray(incoming) ? incoming : [incoming]
    const known = new Set(current.map((message) => message.id))
    const added = []

    for (const message of items) {
        if (!message || message.id === undefined || message.id === null) continue
        if (known.has(message.id)) continue

        known.add(message.id)
        added.push(message)
    }

    if (!added.length) return { list: current, added }

    const list = [...current, ...added]

    // Сортируем, только если новое пришло не в конец: обычно сортировка не нужна
    const last = current[current.length - 1]

    if (last && added.some((message) => message.id < last.id)) {
        list.sort((a, b) => a.id - b.id)
    }

    return { list, added }
}
