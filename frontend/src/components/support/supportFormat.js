/** Подписи статусов обращения. */
export const SUPPORT_STATUS = {
    open: 'ждёт ответа',
    answered: 'есть ответ',
    closed: 'закрыто',
}

export function formatMessageTime(iso) {
    if (!iso) return ''

    return new Date(iso).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}
