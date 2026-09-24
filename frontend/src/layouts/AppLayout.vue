<script setup>
import {onMounted, ref} from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import {notificationsApi} from "@/services/notification.js";
import UserName from "@/components/UserName.vue";

const auth = useAuthStore()
const router = useRouter()

const menuOpen = ref(false)

async function logout() {
  menuOpen.value = false
  await auth.logout()
  router.push('/')
}

const unreadCount = ref(0)

async function loadUnread() {
  if (!auth.isAuthenticated) return
  try {
    const data = await notificationsApi.list()
    unreadCount.value = data.unread_count || 0
  } catch (e) { /* ignore */ }
}

onMounted(loadUnread)

// периодически обновлять
setInterval(loadUnread, 30000)
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
          <RouterLink
              v-if="auth.user && ['moderator', 'admin'].includes(auth.user.role)"
              to="/admin"
              class="dropdown-item"
              @click="menuOpen = false"
          >
            ⚙️ Админка
          </RouterLink>
        </nav>

        <div class="header-actions">
          <template v-if="!auth.isAuthenticated">
            <RouterLink to="/login" class="btn btn-secondary">Войти</RouterLink>
            <RouterLink to="/register" class="btn btn-primary">Регистрация</RouterLink>
          </template>

          <div v-else class="user-menu">
            <button class="user-button" @click="menuOpen = !menuOpen">
            <span class="avatar">
                <img
                    v-if="auth.user?.avatar_url"
                    :src="auth.user.avatar_url"
                    :alt="auth.user.username"
                    class="avatar-img"
                />
                <template v-else>
                    {{ (auth.user?.username || 'И').charAt(0).toUpperCase() }}
                </template>
            </span>
              <span class="username">
                  <UserName :user="auth.user" compact />
              </span>

              <svg class="chevron" :class="{ open: menuOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>

            <div v-if="menuOpen" class="dropdown">
              <RouterLink to="/profile" class="dropdown-item" @click="menuOpen = false">Профиль</RouterLink>
              <RouterLink to="/friends" class="dropdown-item" @click="menuOpen = false">Друзья</RouterLink>
              <RouterLink
                  v-if="auth.user && ['tester', 'admin'].includes(auth.user.role)"
                  to="/tester"
                  class="dropdown-item"
                  @click="menuOpen = false"
              >
                🎯 Панель тестера
              </RouterLink>
              <RouterLink
                  v-if="auth.isAuthenticated"
                  to="/notifications"
                  class="notif-bell"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                  <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                  <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                </svg>
                <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount }}</span>
              </RouterLink>              <div class="dropdown-divider"></div>
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

<style scoped>

.notif-bell {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  color: var(--text-dim);
  border-radius: 9px;
  transition: all 0.2s;
}
.avatar {
  position: relative;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
  box-shadow: 0 3px 12px rgba(124, 58, 237, 0.25);
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}
.notif-bell:hover {
  color: var(--text);
  background: var(--bg-card);
}

.notif-badge {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
}
</style>