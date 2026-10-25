/** Подписи статусов обращения. */
export const SUPPORT_STATUS = {
    open: 'ждёт ответа',
    answered: 'есть ответ',
    closed: 'закрыто',
}

/** Разделы обращений: те же, что на сервере (SupportTicket::CATEGORIES). */
export const SUPPORT_CATEGORIES = {
    account: 'Аккаунт и вход',
    tier_test: 'Тир-тесты',
    shop: 'Магазин и ApexCoin',
    clan: 'Кланы',
    bug: 'Ошибка на сайте',
    other: 'Другое',
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
