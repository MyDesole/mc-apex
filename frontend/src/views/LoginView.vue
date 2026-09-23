<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const login = ref('')
const password = ref('')
const remember = ref(false)

const error = ref('')
const fieldErrors = ref({})

async function submit() {
    error.value = ''
    fieldErrors.value = {}

    try {
        await auth.login(
            login.value,
            password.value,
            remember.value
        )

        const redirect = route.query.redirect || '/'

        router.push(redirect)
    } catch (e) {
        fieldErrors.value = e.errors || {}
        error.value = e.message || 'Не удалось войти.'
    }
}
</script>

<template>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-logo">
                APEX
            </div>

            <div class="auth-heading">
                <h1>С возвращением</h1>
                <p>Войди в свой аккаунт Apex</p>
            </div>

            <form @submit.prevent="submit">
                <label class="field">
                    <span>Логин или Email</span>

                    <input
                        v-model="login"
                        type="text"
                        autocomplete="username"
                        placeholder="username или email"
                    >
                </label>

                <label class="field">
                    <span>Пароль</span>

                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                </label>

                <div class="auth-options">
                    <label class="checkbox">
                        <input
                            v-model="remember"
                            type="checkbox"
                        >
                        <span>Запомнить меня</span>
                    </label>

                    <a href="#">Забыли пароль?</a>
                </div>

                <div
                    v-if="error"
                    class="auth-error"
                >
                    {{ error }}
                </div>

                <button
                    class="auth-submit"
                    type="submit"
                    :disabled="auth.loading"
                >
                    {{ auth.loading ? 'Входим...' : 'Войти' }}
                </button>
            </form>

            <div class="auth-footer">
                Нет аккаунта?

                <RouterLink to="/register">
                    Создать аккаунт
                </RouterLink>
            </div>
        </section>
    </main>
</template>
