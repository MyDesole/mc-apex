import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, getCsrfCookie } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const rank = ref({ position: null, total: 0 })
    const loading = ref(false)
    const initialized = ref(false)

    // токен, выданный после успешной проверки кода
    const verificationToken = ref(null)

    const isAuthenticated = computed(() => !!user.value)

    async function fetchMe() {
        try {
            const response = await api.get('/auth/me')
            user.value = response.user
            rank.value = response.rank ?? { position: null, total: 0 }
        } catch (error) {
            if (error.status !== 401) {
                console.error(error)
            }

            user.value = null
            rank.value = { position: null, total: 0 }
        } finally {
            initialized.value = true
        }
    }

    async function login(login, password, remember = false) {
        loading.value = true

        try {
            await getCsrfCookie()

            const response = await api.post('/auth/login', {
                login,
                password,
                remember,
            })

            user.value = response.user

            // подтянуть rank и полный профиль (clan_member.clan)
            await fetchMe()

            return response
        } finally {
            loading.value = false
        }
    }

    /**
     * Шаг 1: отправить код на email.
     */
    async function sendVerificationCode(email) {
        loading.value = true

        try {
            await getCsrfCookie()

            const response = await api.post('/auth/register/send-code', {
                email,
            })

            return response
        } finally {
            loading.value = false
        }
    }

    /**
     * Шаг 2: проверить код. Сохраняет verification_token.
     */
    async function verifyCode(email, code) {
        loading.value = true

        try {
            const response = await api.post('/auth/register/verify-code', {
                email,
                code,
            })

            verificationToken.value = response.verification_token

            return response
        } finally {
            loading.value = false
        }
    }

    /**
     * Шаг 3: создать аккаунт. Требует verification_token.
     */
    async function register(username, password, passwordConfirmation) {
        loading.value = true

        try {
            await getCsrfCookie()

            const response = await api.post('/auth/register', {
                username,
                password,
                password_confirmation: passwordConfirmation,
                verification_token: verificationToken.value,
            })

            // сбрасываем одноразовый токен — он уже использован
            verificationToken.value = null

            user.value = response.user

            await fetchMe()

            return response
        } finally {
            loading.value = false
        }
    }

    function resetVerification() {
        verificationToken.value = null
    }

    async function logout() {
        loading.value = true

        try {
            await api.post('/auth/logout')
            user.value = null
            rank.value = { position: null, total: 0 }
            verificationToken.value = null
        } finally {
            loading.value = false
        }
    }

    async function forgotPassword(email) {
        loading.value = true
        try {
            await getCsrfCookie()
            return await api.post('/auth/forgot-password', { email })
        } finally {
            loading.value = false
        }
    }

    async function resetPassword(email, code, password, passwordConfirmation) {
        loading.value = true
        try {
            await getCsrfCookie()
            return await api.post('/auth/reset-password', {
                email,
                code,
                password,
                password_confirmation: passwordConfirmation,
            })
        } finally {
            loading.value = false
        }
    }

    return {
        user,
        rank,
        loading,
        initialized,
        isAuthenticated,
        verificationToken,
        fetchMe,
        resetPassword,
        forgotPassword,
        login,
        sendVerificationCode,
        verifyCode,
        register,
        resetVerification,
        logout,
    }
})