<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { notificationsApi } from '@/services/notification.js'
import UserName from '@/components/UserName.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const menuOpen = ref(false)
const mobileMenuOpen = ref(false)

const unreadCount = ref(0)

async function logout() {
  menuOpen.value = false
  mobileMenuOpen.value = false
  await auth.logout()
  router.push('/')
}

async function loadUnread() {
  if (!auth.isAuthenticated) return
  try {
    const data = await notificationsApi.list()
    unreadCount.value = data.unread_count || 0
  } catch (e) { /* ignore */ }
}

// закрываем мобильное меню при смене роута
watch(() => route.path, () => {
  mobileMenuOpen.value = false
  menuOpen.value = false
})

// закрываем при ресайзе на десктоп
function onResize() {
  if (window.innerWidth > 800) {
    mobileMenuOpen.value = false
  }
}

// закрываем дропдауны по клику вне
function onClickOutside(e) {
  if (!e.target.closest('.user-menu')) menuOpen.value = false
}

let unreadInterval = null

onMounted(() => {
  loadUnread()
  unreadInterval = setInterval(loadUnread, 30000)

  window.addEventListener('resize', onResize)
  document.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
  if (unreadInterval) clearInterval(unreadInterval)
  window.removeEventListener('resize', onResize)
  document.removeEventListener('click', onClickOutside)
})

function toggleMobile() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

// блокируем скролл body когда меню открыто
watch(mobileMenuOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
  <div class="app">
    <header class="site-header">
      <div class="header-inner">
        <!-- BURGER (mobile) -->
        <button
            class="burger"
            :class="{ open: mobileMenuOpen }"
            @click="toggleMobile"
            aria-label="Меню"
        >
          <span />
          <span />
          <span />
        </button>

        <!-- BRAND -->
        <RouterLink to="/" class="brand">
          <span class="brand-main">APEX</span>
        </RouterLink>

        <!-- NAV (desktop) -->
        <nav class="main-nav">
          <RouterLink to="/" class="nav-link">Главная</RouterLink>
          <RouterLink to="/players" class="nav-link">Игроки</RouterLink>
          <RouterLink to="/clans" class="nav-link">Кланы</RouterLink>
          <RouterLink to="/tournaments" class="nav-link">Турниры</RouterLink>
          <RouterLink to="/news" class="nav-link">Новости</RouterLink>
          <RouterLink
              v-if="auth.user?.clan_member"
              to="/my-clan"
              class="nav-link"
          >
             Мой клан
          </RouterLink>
        </nav>

        <!-- ACTIONS -->
        <div class="header-actions">
          <template v-if="!auth.isAuthenticated">
            <RouterLink to="/login" class="btn btn-secondary">Войти</RouterLink>
            <RouterLink to="/register" class="btn btn-primary">Регистрация</RouterLink>
          </template>

          <div v-else class="user-menu">
            <button class="user-button" @click.stop="menuOpen = !menuOpen">
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
              <RouterLink to="/profile" class="dropdown-item" @click="menuOpen = false">
                 Профиль
              </RouterLink>
              <RouterLink to="/friends" class="dropdown-item" @click="menuOpen = false">
                 Друзья
              </RouterLink>
              <RouterLink to="/notifications" class="dropdown-item" @click="menuOpen = false">
                Уведомления
                <span v-if="unreadCount > 0" class="dropdown-badge">{{ unreadCount }}</span>
              </RouterLink>
              <RouterLink
                  v-if="auth.user?.clan_member"
                  to="/my-clan"
                  class="dropdown-item"
                  @click="menuOpen = false"
              >
                Мой клан
              </RouterLink>
              <RouterLink
                  v-if="auth.user && ['tester', 'admin'].includes(auth.user.role)"
                  to="/tester"
                  class="dropdown-item"
                  @click="menuOpen = false"
              >
                Панель тестера
              </RouterLink>

              <RouterLink
                  v-if="auth.user && ['moderator', 'admin'].includes(auth.user.role)"
                  to="/admin"
                  class="dropdown-item"
                  @click="menuOpen = false"
              >
                 Админка
              </RouterLink>

              <div class="dropdown-divider"></div>

              <button class="dropdown-item danger" @click="logout">
                Выйти
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- MOBILE MENU -->
    <Transition name="mobile-menu">
      <div v-if="mobileMenuOpen" class="mobile-menu">
        <nav class="mobile-nav">
          <RouterLink to="/" class="mobile-nav-link">
            Главная
          </RouterLink>
          <RouterLink to="/players" class="mobile-nav-link">
            Игроки
          </RouterLink>
          <RouterLink to="/clans" class="mobile-nav-link">
            Кланы
          </RouterLink>
          <RouterLink to="/tournaments" class="mobile-nav-link">
            Турниры
          </RouterLink>
          <RouterLink to="/news" class="mobile-nav-link">
            Новости
          </RouterLink>
        </nav>

        <div v-if="auth.isAuthenticated" class="mobile-menu__divider" />

        <nav v-if="auth.isAuthenticated" class="mobile-nav">
          <RouterLink to="/profile" class="mobile-nav-link">
            Профиль
          </RouterLink>
          <RouterLink to="/friends" class="mobile-nav-link">
            Друзья
          </RouterLink>
          <RouterLink to="/notifications" class="mobile-nav-link">
            Уведомления
            <span v-if="unreadCount > 0" class="mobile-badge">{{ unreadCount }}</span>
          </RouterLink>
          <RouterLink
              v-if="auth.user?.clan_member"
              to="/my-clan"
              class="mobile-nav-link"
          >
            Мой клан
          </RouterLink>
          <RouterLink
              v-if="['tester', 'admin'].includes(auth.user?.role)"
              to="/tester"
              class="mobile-nav-link"
          >
            Панель тестера
          </RouterLink>

          <RouterLink
              v-if="['moderator', 'admin'].includes(auth.user?.role)"
              to="/admin"
              class="mobile-nav-link"
          >
            Админка
          </RouterLink>

          <button class="mobile-nav-link mobile-nav-link--danger" @click="logout">
            Выйти
          </button>
        </nav>

        <div v-else class="mobile-menu__auth">
          <RouterLink to="/login" class="mobile-btn mobile-btn--ghost">Войти</RouterLink>
          <RouterLink to="/register" class="mobile-btn mobile-btn--primary">Регистрация</RouterLink>
        </div>
      </div>
    </Transition>

    <!-- BACKDROP -->
    <Transition name="backdrop">
      <div
          v-if="mobileMenuOpen"
          class="mobile-backdrop"
          @click="mobileMenuOpen = false"
      />
    </Transition>

    <main class="page">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
/* ============================================
   HEADER
   ============================================ */

.site-header {
  position: sticky;
  top: 0;
  z-index: 1000;

  height: var(--header-height);

  background: var(--bg-header);

  border-bottom: 1px solid rgba(34, 34, 46, 0.85);

  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}

.header-inner {
  width: min(1200px, calc(100% - 40px));
  height: 100%;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 16px;
}

/* === BURGER === */

.burger {
  display: none;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  width: 40px;
  height: 40px;
  padding: 0;
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.burger:hover {
  border-color: var(--border-hover);
  background: var(--bg-card);
}

.burger span {
  display: block;
  width: 18px;
  height: 2px;
  margin: 0 auto;
  background: var(--text);
  border-radius: 999px;
  transition: all 0.25s ease;
}

.burger.open span:nth-child(1) {
  transform: translateY(7px) rotate(45deg);
}

.burger.open span:nth-child(2) {
  opacity: 0;
}

.burger.open span:nth-child(3) {
  transform: translateY(-7px) rotate(-45deg);
}

/* === BRAND === */

.brand {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  font-size: 24px;
  font-weight: 900;
  letter-spacing: -1px;
  transition: opacity 0.2s;
}

.brand:hover {
  opacity: 0.85;
}

.brand-main {
  background: linear-gradient(135deg, #c4b5fd 0%, #8b5cf6 45%, #7c3aed 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

/* === NAV === */

.main-nav {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-left: 32px;
}

.nav-link {
  position: relative;
  padding: 9px 13px;
  color: var(--text-dim);
  font-size: 14px;
  font-weight: 500;
  border-radius: 8px;
  transition: color 0.2s, background 0.2s;
}

.nav-link:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.035);
}

.nav-link.router-link-exact-active {
  color: #fff;
}

.nav-link.router-link-exact-active::after {
  content: '';
  position: absolute;
  left: 13px;
  right: 13px;
  bottom: 2px;
  height: 2px;
  background: var(--accent);
  border-radius: 999px;
  box-shadow: 0 0 10px var(--accent-glow);
}

/* === ACTIONS === */

.header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-left: auto;
}

/* === BUTTONS === */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 15px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  transition: all 0.2s;
}

.btn-secondary {
  color: var(--text);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-secondary:hover {
  background: var(--bg-card);
  border-color: var(--border-hover);
}

.btn-primary {
  color: #fff;
  background: var(--accent);
  border: 1px solid var(--accent);
  box-shadow: 0 5px 20px rgba(124, 58, 237, 0.2);
}

.btn-primary:hover {
  background: var(--accent-light);
  box-shadow: 0 7px 25px rgba(124, 58, 237, 0.3);
}

/* === USER MENU === */

.user-menu {
  position: relative;
}

.user-button {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 5px 9px 5px 5px;
  color: var(--text);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 9px;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
}

.user-button:hover,
.user-button:focus-visible {
  background: var(--bg-card);
  border-color: var(--border);
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

.username {
  max-width: 140px;
  overflow: hidden;
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron {
  width: 15px;
  height: 15px;
  color: var(--text-dim);
  transition: transform 0.2s;
}

.chevron.open {
  transform: rotate(180deg);
}

/* === DROPDOWN === */

.dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 220px;
  padding: 6px;
  background: rgba(18, 18, 26, 0.97);
  border: 1px solid var(--border);
  border-radius: 11px;
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(16px);
  animation: dropdownIn 0.15s ease;
}

@keyframes dropdownIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 11px;
  color: var(--text-dim);
  background: transparent;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
  transition: color 0.2s, background 0.2s;
}

.dropdown-item:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.045);
}

.dropdown-item.danger {
  color: #f87171;
}

.dropdown-item.danger:hover {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
}

.dropdown-badge {
  margin-left: auto;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
}

.dropdown-divider {
  height: 1px;
  margin: 5px 4px;
  background: var(--border);
}

/* ============================================
   MOBILE MENU
   ============================================ */

.mobile-menu {
  position: fixed;
  top: var(--header-height);
  left: 0;
  right: 0;
  z-index: 999;
  max-height: calc(100vh - var(--header-height));
  overflow-y: auto;
  background: rgba(10, 10, 15, 0.98);
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(18px);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mobile-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mobile-nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px 16px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s;
}

.mobile-nav-link:hover,
.mobile-nav-link.router-link-exact-active {
  color: var(--text);
  background: rgba(124, 58, 237, 0.1);
}

.mobile-nav-link.router-link-exact-active {
  color: var(--accent-light);
}

.mobile-nav-link--danger {
  color: #f87171;
}

.mobile-nav-link--danger:hover {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
}

.mobile-nav-icon {
  width: 24px;
  text-align: center;
  font-size: 18px;
  flex-shrink: 0;
}

.mobile-badge {
  margin-left: auto;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
}

.mobile-menu__divider {
  height: 1px;
  margin: 8px 0;
  background: var(--border);
}

.mobile-menu__auth {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.mobile-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 0 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  transition: all 0.2s;
}

.mobile-btn--ghost {
  color: var(--text);
  background: transparent;
  border: 1px solid var(--border);
}

.mobile-btn--ghost:hover {
  background: var(--bg-card);
}

.mobile-btn--primary {
  color: #fff;
  background: var(--accent);
  border: 1px solid var(--accent);
  box-shadow: 0 5px 20px rgba(124, 58, 237, 0.2);
}

.mobile-btn--primary:hover {
  background: var(--accent-light);
}

/* === BACKDROP === */

.mobile-backdrop {
  position: fixed;
  inset: 0;
  z-index: 998;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(2px);
  top: var(--header-height);
}

/* === TRANSITIONS === */

.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
  transform: translateY(-8px);
  opacity: 0;
}

.backdrop-enter-active,
.backdrop-leave-active {
  transition: opacity 0.25s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
  opacity: 0;
}

/* === NOTIF BELL (legacy) === */

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

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 900px) {
  .main-nav {
    margin-left: 20px;
    gap: 2px;
  }

  .nav-link {
    padding: 9px 10px;
    font-size: 13px;
  }
}

@media (max-width: 800px) {
  .burger {
    display: flex;
  }

  .main-nav {
    display: none;
  }

  .username {
    display: none;
  }

  .user-button {
    padding-right: 5px;
  }

  .header-inner {
    width: min(100% - 24px, 1200px);
  }
}

@media (max-width: 500px) {
  .btn-secondary {
    display: none;
  }

  .brand {
    font-size: 21px;
  }
}
</style>