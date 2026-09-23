<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const username = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const error = ref('')
const fieldErrors = ref({})

async function submit() {
    error.value = ''
    fieldErrors.value = {}

    try {
        await auth.register(
            username.value,
            email.value,
            password.value,
            passwordConfirmation.value
        )

        router.push('/')
    } catch (e) {
        fieldErrors.value = e.errors || {}
        error.value = e.message || 'Не удалось создать аккаунт.'
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
                <h1>Создать аккаунт</h1>
                <p>Присоединяйся к Apex Community</p>
            </div>

            <form @submit.prevent="submit">
                <label class="field">
                    <span>Игровой ник</span>

                    <input
                        v-model="username"
                        type="text"
                        autocomplete="username"
                        placeholder="YourNickname"
                    >
                </label>

                <label class="field">
                    <span>Email</span>

                    <input
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                    >
                </label>

                <label class="field">
                    <span>Пароль</span>

                    <input
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Минимум 8 символов"
                    >
                </label>

                <label class="field">
                    <span>Повторите пароль</span>

                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Повторите пароль"
                    >
                </label>

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
                    {{ auth.loading ? 'Создаём...' : 'Создать аккаунт' }}
                </button>
            </form>

            <div class="auth-footer">
                Уже есть аккаунт?

                <RouterLink to="/login">
                    Войти
                </RouterLink>
            </div>
        </section>
    </main>
</template>
