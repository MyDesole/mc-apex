<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const menuOpen = ref(false)

async function logout() {
  menuOpen.value = false
  await auth.logout()
  router.push('/')
}
</script>

<template>
  <div class="app">
    <header class="site-header">
      <div class="header-inner">
        <RouterLink to="/" class="brand">
          <span class="brand-main">APEX</span>
        </RouterLink>

        <nav class="main-nav">
          <RouterLink to="/" class="nav-link">Главная</RouterLink>
          <RouterLink to="/players" class="nav-link">Игроки</RouterLink>
          <RouterLink to="/clans" class="nav-link">Кланы</RouterLink>
          <RouterLink to="/tournaments" class="nav-link">Турниры</RouterLink>
        </nav>

        <div class="header-actions">
          <template v-if="!auth.isAuthenticated">
            <RouterLink to="/login" class="btn btn-secondary">Войти</RouterLink>
            <RouterLink to="/register" class="btn btn-primary">Регистрация</RouterLink>
          </template>

          <div v-else class="user-menu">
            <button class="user-button" @click="menuOpen = !menuOpen">
              <span class="avatar">{{ (auth.user?.username || 'И').charAt(0).toUpperCase() }}</span>
              <span class="username">{{ auth.user?.username }}</span>
              <svg class="chevron" :class="{ open: menuOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>

            <div v-if="menuOpen" class="dropdown">
              <RouterLink to="/profile" class="dropdown-item" @click="menuOpen = false">Профиль</RouterLink>
              <RouterLink to="/friends" class="dropdown-item" @click="menuOpen = false">Друзья</RouterLink>
              <RouterLink to="/notifications" class="dropdown-item" @click="menuOpen = false">Уведомления</RouterLink>
              <div class="dropdown-divider"></div>
              <button class="dropdown-item danger" @click="logout">Выйти</button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="page">
      <RouterView />
    </main>
  </div>
</template>