import { api } from './api.js'

export const authApi = {
    forgotPassword(email) {
        return api.post('/forgot-password', { email })
    },
    resetPassword(payload) {
        return api.post('/reset-password', payload)
    },
    // отправка повторного письма
    resendVerification() {
        return api.post('/email/verification-notification')
    },
}