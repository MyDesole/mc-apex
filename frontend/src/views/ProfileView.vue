<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
    await auth.logout()

    router.push('/')
}
</script>

<template>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-logo">
                APEX
            </div>

            <div class="auth-heading">
                <h1>{{ auth.user?.username }}</h1>
                <p>{{ auth.user?.email }}</p>
            </div>

            <button
                class="auth-submit"
                @click="logout"
            >
                Выйти из аккаунта
            </button>
        </section>
    </main>
</template>
