import { api } from './api.js'

function withQuery(url, params = {}) {
    const query = new URLSearchParams(
        Object.fromEntries(
            Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')
        )
    ).toString()

    return query ? `${url}?${query}` : url
}

/**
 * Кошелёк ApexCoin.
 */
export const walletApi = {
    // Баланс и сводка по источникам начислений
    summary() {
        return api.get('/wallet')
    },

    transactions(params = {}) {
        return api.get(withQuery('/wallet/transactions', params))
    },

    claimDailyBonus() {
        return api.post('/wallet/daily-bonus')
    },
}
