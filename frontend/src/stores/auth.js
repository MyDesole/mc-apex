import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, getCsrfCookie } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const loading = ref(false)
    const initialized = ref(false)

    const isAuthenticated = computed(() => !!user.value)

    async function fetchMe() {
        try {
            const response = await api.get('/auth/me')
            user.value = response.user
        } catch (error) {
            if (error.status !== 401) {
                console.error(error)
            }

            user.value = null
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

            return response
        } finally {
            loading.value = false
        }
    }

    async function register(username, email, password, passwordConfirmation) {
        loading.value = true

        try {
            await getCsrfCookie()

            const response = await api.post('/auth/register', {
                username,
                email,
                password,
                password_confirmation: passwordConfirmation,
            })

            user.value = response.user

            return response
        } finally {
            loading.value = false
        }
    }

    async function logout() {
        loading.value = true

        try {
            await api.post('/auth/logout')
            user.value = null
        } finally {
            loading.value = false
        }
    }

    return {
        user,
        loading,
        initialized,
        isAuthenticated,
        fetchMe,
        login,
        register,
        logout,
    }
})
