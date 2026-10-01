<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '../stores/auth'
import { notificationsApi } from '@/services/notification.js'
import { chatApi } from '@/services/chat.js'

import UserName from '@/components/UserName.vue'
import VerifyEmailBanner from '@/components/VerifyEmailBanner.vue'
import CoinBadge from '@/components/CoinBadge.vue'
import TierTestBanner from '@/components/TierTestBanner.vue'
import NotificationToast from '@/components/NotificationToast.vue'

import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications'
import { useRealtimeMessages } from '@/composables/useRealtimeMessages'
import { useTitleBlink } from '@/composables/useTitleBlink'

const { startBlink, stopBlink } = useTitleBlink()

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const menuOpen = ref(false)
const mobileMenuOpen = ref(false)

const {
  unreadCount,
  latestNotification,
  newNotificationArrived,
} = useRealtimeNotifications()

const { latestMessage } = useRealtimeMessages()

const chatUnread = ref(0)

async function logout() {
  menuOpen.value = false
  mobileMenuOpen.value = false

  await auth.logout()
  router.push('/')
}

async function loadUnreadNotifications() {
  if (!auth.isAuthenticated) return

  try {
    const data = await notificationsApi.list()
    unreadCount.value = data.unread_count || 0
  } catch (e) {
    // ignore
  }
}

async function loadChatUnread() {
  if (!auth.isAuthenticated) return

  try {
    const data = await chatApi.unreadCount()
    chatUnread.value = data.unread_count || 0
  } catch (e) {
    // ignore
  }
}

watch(() => route.path, () => {
  mobileMenuOpen.value = false
  menuOpen.value = false
})

function onResize() {
  if (window.innerWidth > 800) {
    mobileMenuOpen.value = false
  }
}

function onClickOutside(e) {
  if (!e.target.closest('.user-menu')) {
    menuOpen.value = false
  }
}

watch(latestMessage, (message) => {
  if (!message) return

  loadChatUnread()

  if (!route.path.startsWith('/messages')) {
    startBlink()
  }
})

watch(() => route.path, (path) => {
  if (path.startsWith('/messages')) {
    stopBlink()
    setTimeout(loadChatUnread, 500)
  }
})

let notifInterval = null
let chatInterval = null

onMounted(() => {
  loadUnreadNotifications()
  loadChatUnread()

  notifInterval = setInterval(loadUnreadNotifications, 60000)
  chatInterval = setInterval(loadChatUnread, 60000)

  if (!auth.initialized) {
    auth.fetchMe()
  }

  window.addEventListener('resize', onResize)
  document.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
  if (notifInterval) clearInterval(notifInterval)
  if (chatInterval) clearInterval(chatInterval)

  window.removeEventListener('resize', onResize)
  document.removeEventListener('click', onClickOutside)

  document.body.style.overflow = ''
})

function toggleMobile() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

watch(mobileMenuOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})
</script>

<template>
  <div class="app">

    <VerifyEmailBanner v-if="auth.isAuthenticated" />

    <!-- =========================
         ШАПКА
    ========================== -->
    <header class="site-header">
      <div class="header-glow"></div>

      <div class="header-inner">

        <!-- Мобильная кнопка -->
        <button
            class="burger"
            :class="{ open: mobileMenuOpen }"
            @click="toggleMobile"
            aria-label="Меню"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>

        <!-- ЛОГО -->
        <RouterLink to="/" class="brand">


          <span class="brand-text">
            <span class="brand-main">APEX</span>
            <span class="brand-sub">TIERS</span>
          </span>
        </RouterLink>

        <!-- НАВИГАЦИЯ -->
        <nav class="main-nav">

          <RouterLink to="/" class="nav-link">
            <span class="nav-icon">⌂</span>
            <span>Главная</span>
          </RouterLink>

          <RouterLink to="/players" class="nav-link">
            <span class="nav-icon">♙</span>
            <span>Рейтинг</span>
          </RouterLink>

          <RouterLink to="/clans" class="nav-link">
            <span class="nav-icon">♜</span>
            <span>Кланы</span>
          </RouterLink>

          <RouterLink to="/tournaments" class="nav-link">
            <span class="nav-icon">♛</span>
            <span>Турниры</span>
          </RouterLink>

          <RouterLink to="/news" class="nav-link">
            <span class="nav-icon">▤</span>
            <span>Новости</span>
          </RouterLink>

          <RouterLink
              v-if="auth.user?.clan_member"
              to="/my-clan"
              class="nav-link nav-link-clan"
          >
            <span class="nav-icon">◆</span>
            <span>Мой клан</span>
          </RouterLink>

        </nav>

        <!-- ДЕЙСТВИЯ СПРАВА -->
        <div class="header-actions">

          <template v-if="!auth.isAuthenticated">

            <RouterLink to="/login" class="auth-link">
              Войти
            </RouterLink>

            <RouterLink to="/register" class="register-button">
              <span>Регистрация</span>
              <span class="button-arrow">→</span>
            </RouterLink>

          </template>

          <template v-else>

            <!-- APEXCOIN -->
            <CoinBadge />

            <!-- ЧАТ -->
            <RouterLink
                to="/messages"
                class="header-action"
                title="Сообщения"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path
                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                />
              </svg>

              <span
                  v-if="chatUnread > 0"
                  class="notification-count"
              >
                {{ chatUnread > 99 ? '99+' : chatUnread }}
              </span>
            </RouterLink>

            <!-- УВЕДОМЛЕНИЯ -->
            <RouterLink
                to="/notifications"
                class="header-action"
                title="Уведомления"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
              </svg>

              <span
                  v-if="unreadCount > 0"
                  class="notification-count"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </RouterLink>

            <!-- ПРОФИЛЬ -->
            <div class="user-menu">

              <button
                  class="user-button"
                  @click.stop="menuOpen = !menuOpen"
              >

                <span class="user-avatar">
                  <img
                      v-if="auth.user?.avatar_url"
                      :src="auth.user.avatar_url"
                      :alt="auth.user.username"
                      class="avatar-img"
                  />

                  <template v-else>
                    {{ (auth.user?.username || 'И').charAt(0).toUpperCase() }}
                  </template>

                  <span class="online-dot"></span>
                </span>

                <span class="user-info">
                  <span class="user-name">
                    <UserName :user="auth.user" compact />
                  </span>

                  <span class="user-role">
                    {{ auth.user?.role === 'admin'
                      ? 'Администратор'
                      : auth.user?.role === 'moderator'
                          ? 'Модератор'
                          : 'Игрок'
                    }}
                  </span>
                </span>

                <svg
                    class="chevron"
                    :class="{ open: menuOpen }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                  <path
                      d="m6 9 6 6 6-6"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  />
                </svg>

              </button>

              <!-- ВЫПАДАЮЩЕЕ МЕНЮ -->
              <Transition name="dropdown">
                <div v-if="menuOpen" class="dropdown">

                  <div class="dropdown-head">
                    <span class="dropdown-avatar">
                      <img
                          v-if="auth.user?.avatar_url"
                          :src="auth.user.avatar_url"
                          :alt="auth.user.username"
                      />
                      <template v-else>
                        {{ (auth.user?.username || 'И').charAt(0).toUpperCase() }}
                      </template>
                    </span>

                    <div>
                      <strong>
                        <UserName :user="auth.user" compact />
                      </strong>
                      <span>Личный кабинет</span>
                    </div>
                  </div>

                  <div class="dropdown-divider"></div>

                  <RouterLink
                      to="/profile"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">◉</span>
                    <span>Профиль</span>
                  </RouterLink>
                  <RouterLink
                      to="/inventory"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">◇</span>
                    <span>Инвентарь</span>
                  </RouterLink>
                  <RouterLink
                      to="/friends"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">♧</span>
                    <span>Друзья</span>
                  </RouterLink>

                  <RouterLink
                      to="/messages"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">◌</span>
                    <span>Сообщения</span>

                    <span
                        v-if="chatUnread > 0"
                        class="dropdown-count"
                    >
                      {{ chatUnread > 99 ? '99+' : chatUnread }}
                    </span>
                  </RouterLink>

                  <RouterLink
                      to="/notifications"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">♢</span>
                    <span>Уведомления</span>

                    <span
                        v-if="unreadCount > 0"
                        class="dropdown-count"
                    >
                      {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                  </RouterLink>

                  <RouterLink
                      v-if="auth.user?.clan_member"
                      to="/my-clan"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">◆</span>
                    <span>Мой клан</span>
                  </RouterLink>

                  <RouterLink
                      v-if="auth.user && ['tester', 'admin'].includes(auth.user.role)"
                      to="/tester"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">✓</span>
                    <span>Панель тестера</span>
                  </RouterLink>

                  <RouterLink
                      v-if="auth.user && ['moderator', 'admin'].includes(auth.user.role)"
                      to="/admin"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">⚙</span>
                    <span>Админка</span>
                  </RouterLink>

                  <div class="dropdown-divider"></div>

                  <button
                      class="dropdown-item danger"
                      @click="logout"
                  >
                    <span class="dropdown-icon">↪</span>
                    <span>Выйти</span>
                  </button>

                </div>
              </Transition>

            </div>

          </template>

        </div>
      </div>
    </header>

    <!-- =========================
         МОБИЛЬНОЕ МЕНЮ
    ========================== -->

    <Transition name="mobile-menu">
      <div
          v-if="mobileMenuOpen"
          class="mobile-menu"
      >

        <div class="mobile-menu-top">
          <span>Навигация</span>
          <span class="mobile-line"></span>
        </div>

        <nav class="mobile-nav">

          <RouterLink to="/" class="mobile-nav-link">
            <span class="mobile-icon">⌂</span>
            <span>Главная</span>
          </RouterLink>

          <RouterLink to="/players" class="mobile-nav-link">
            <span class="mobile-icon">♙</span>
            <span>Рейтинг игроков</span>
          </RouterLink>

          <RouterLink to="/clans" class="mobile-nav-link">
            <span class="mobile-icon">♜</span>
            <span>Кланы</span>
          </RouterLink>

          <RouterLink to="/tournaments" class="mobile-nav-link">
            <span class="mobile-icon">♛</span>
            <span>Турниры</span>
          </RouterLink>

          <RouterLink to="/news" class="mobile-nav-link">
            <span class="mobile-icon">▤</span>
            <span>Новости</span>
          </RouterLink>

          <RouterLink
              v-if="auth.user?.clan_member"
              to="/my-clan"
              class="mobile-nav-link"
          >
            <span class="mobile-icon">◆</span>
            <span>Мой клан</span>
          </RouterLink>

        </nav>

        <template v-if="auth.isAuthenticated">

          <div class="mobile-section">
            <span>Аккаунт</span>
            <span class="mobile-line"></span>
          </div>

          <nav class="mobile-nav">

            <RouterLink
                to="/profile"
                class="mobile-nav-link"
            >
              <span class="mobile-icon">◉</span>
              <span>Профиль</span>
            </RouterLink>

            <RouterLink
                to="/inventory"
                class="dropdown-item"
                @click="menuOpen = false"
            >
              <span class="dropdown-icon">◇</span>
              <span>Инвентарь</span>
            </RouterLink>

            <RouterLink
                to="/friends"
                class="mobile-nav-link"
            >
              <span class="mobile-icon">♧</span>
              <span>Друзья</span>
            </RouterLink>

            <RouterLink
                to="/messages"
                class="mobile-nav-link"
            >
              <span class="mobile-icon">◌</span>
              <span>Сообщения</span>

              <span
                  v-if="chatUnread > 0"
                  class="mobile-badge"
              >
                {{ chatUnread > 99 ? '99+' : chatUnread }}
              </span>
            </RouterLink>

            <RouterLink
                to="/notifications"
                class="mobile-nav-link"
            >
              <span class="mobile-icon">♢</span>
              <span>Уведомления</span>

              <span
                  v-if="unreadCount > 0"
                  class="mobile-badge"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </RouterLink>

            <RouterLink
                v-if="['tester', 'admin'].includes(auth.user?.role)"
                to="/tester"
                class="mobile-nav-link"
            >
              <span class="mobile-icon">✓</span>
              <span>Панель тестера</span>
            </RouterLink>

            <RouterLink
                v-if="['moderator', 'admin'].includes(auth.user?.role)"
                to="/admin"
                class="mobile-nav-link"
            >
              <span class="mobile-icon">⚙</span>
              <span>Админка</span>
            </RouterLink>

            <button
                class="mobile-nav-link mobile-nav-link--danger"
                @click="logout"
            >
              <span class="mobile-icon">↪</span>
              <span>Выйти</span>
            </button>

          </nav>

        </template>

        <div
            v-else
            class="mobile-auth"
        >
          <RouterLink
              to="/login"
              class="mobile-login"
          >
            Войти
          </RouterLink>

          <RouterLink
              to="/register"
              class="mobile-register"
          >
            Создать аккаунт
            <span>→</span>
          </RouterLink>
        </div>

      </div>
    </Transition>

    <Transition name="backdrop">
      <div
          v-if="mobileMenuOpen"
          class="mobile-backdrop"
          @click="mobileMenuOpen = false"
      />
    </Transition>

    <!-- =========================
         КОНТЕНТ
    ========================== -->

    <main class="page">
      <RouterView />
    </main>

  </div>

  <TierTestBanner />

  <NotificationToast
      :notification="latestNotification"
      :trigger="newNotificationArrived"
  />
</template>

<style scoped>
/* =========================================================
   ОСНОВА
========================================================= */

.app {
  min-height: 100vh;
  position: relative;
}

.site-header {
  position: sticky;
  top: 0;
  z-index: 1000;

  height: var(--header-height);

  background:
      linear-gradient(
          180deg,
          rgba(11, 10, 18, 0.97) 0%,
          rgba(10, 9, 16, 0.92) 100%
      );

  border-bottom: 1px solid rgba(139, 92, 246, 0.10);

  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);

  isolation: isolate;
}

.header-glow {
  position: absolute;
  top: -100px;
  left: 50%;
  width: 100%;
  height: 180px;

  transform: translateX(-50%);

  background: radial-gradient(
      ellipse,
      rgba(124, 58, 237, 0.13),
      transparent 70%
  );

  pointer-events: none;
  z-index: -1;
}

.header-inner {
  position: relative;

  width: min(1280px, calc(100% - 40px));
  height: 100%;
  margin: 0 auto;

  display: flex;
  align-items: center;

  gap: 18px;
}

/* =========================================================
   БРЕНД
========================================================= */

.brand {
  display: flex;
  align-items: center;
  gap: 10px;

  flex-shrink: 0;

  color: #fff;
  text-decoration: none;

  transition:
      transform 0.2s ease,
      opacity 0.2s ease;
}

.brand:hover {
  transform: translateY(-1px);
}

.brand-mark {
  position: relative;

  width: 35px;
  height: 35px;

  display: flex;
  align-items: flex-end;
  justify-content: center;

  gap: 3px;

  padding: 7px;

  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, 0.25),
          rgba(124, 58, 237, 0.06)
      );

  border: 1px solid rgba(167, 139, 250, 0.25);
  border-radius: 10px;

  box-shadow:
      0 8px 25px rgba(124, 58, 237, 0.12),
      inset 0 1px rgba(255, 255, 255, 0.07);
}

.brand-mark span {
  width: 4px;

  border-radius: 99px;

  background: linear-gradient(
      180deg,
      #c4b5fd,
      #7c3aed
  );

  box-shadow: 0 0 10px rgba(139, 92, 246, 0.5);
}

.brand-mark span:nth-child(1) {
  height: 35%;
}

.brand-mark span:nth-child(2) {
  height: 65%;
}

.brand-mark span:nth-child(3) {
  height: 100%;
}

.brand-text {
  display: flex;
  align-items: baseline;
  gap: 5px;
}

.brand-main {
  font-size: 22px;
  line-height: 1;

  font-weight: 950;
  letter-spacing: -1.3px;

  background: linear-gradient(
      135deg,
      #fff 10%,
      #ddd6fe 42%,
      #8b5cf6 100%
  );

  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.brand-sub {
  color: #7c6f98;

  font-size: 9px;
  font-weight: 900;

  letter-spacing: 1.8px;
}

/* =========================================================
   НАВИГАЦИЯ
========================================================= */

.main-nav {
  display: flex;
  align-items: center;
  gap: 3px;

  margin-left: 24px;
}

.nav-link {
  position: relative;

  display: flex;
  align-items: center;
  gap: 7px;

  padding: 9px 12px;

  color: #89869a;

  font-size: 13px;
  font-weight: 650;

  text-decoration: none;

  border: 1px solid transparent;
  border-radius: 9px;

  transition:
      color 0.2s ease,
      background 0.2s ease,
      border-color 0.2s ease,
      transform 0.2s ease;
}

.nav-icon {
  display: inline-flex;

  color: #625d72;

  font-size: 15px;
  line-height: 1;

  transition: color 0.2s ease;
}

.nav-link:hover {
  color: #f4f1ff;

  background: rgba(255, 255, 255, 0.035);
  border-color: rgba(255, 255, 255, 0.05);

  transform: translateY(-1px);
}

.nav-link:hover .nav-icon {
  color: #a78bfa;
}

.nav-link.router-link-exact-active {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          rgba(124, 58, 237, 0.16),
          rgba(124, 58, 237, 0.045)
      );

  border-color: rgba(139, 92, 246, 0.17);

  box-shadow:
      inset 0 1px rgba(255, 255, 255, 0.035);
}

.nav-link.router-link-exact-active .nav-icon {
  color: #a78bfa;
}

.nav-link.router-link-exact-active::after {
  content: '';

  position: absolute;

  left: 50%;
  bottom: -1px;

  width: 22px;
  height: 2px;

  transform: translateX(-50%);

  background: #8b5cf6;
  border-radius: 999px;

  box-shadow:
      0 0 10px rgba(139, 92, 246, 0.75),
      0 0 22px rgba(139, 92, 246, 0.35);
}

/* =========================================================
   ПРАВАЯ ЧАСТЬ
========================================================= */

.header-actions {
  margin-left: auto;

  display: flex;
  align-items: center;
  gap: 7px;
}

.auth-link {
  padding: 9px 12px;

  color: #aaa6b8;

  font-size: 13px;
  font-weight: 650;

  text-decoration: none;

  border-radius: 9px;

  transition:
      color 0.2s ease,
      background 0.2s ease;
}

.auth-link:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.04);
}

.register-button {
  min-height: 38px;

  display: inline-flex;
  align-items: center;
  gap: 10px;

  padding: 0 14px;

  color: #fff;

  font-size: 12px;
  font-weight: 800;

  text-decoration: none;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, 0.35);
  border-radius: 9px;

  box-shadow:
      0 8px 24px rgba(124, 58, 237, 0.18),
      inset 0 1px rgba(255, 255, 255, 0.15);

  transition:
      transform 0.2s ease,
      box-shadow 0.2s ease,
      filter 0.2s ease;
}

.register-button:hover {
  transform: translateY(-1px);

  filter: brightness(1.08);

  box-shadow:
      0 10px 30px rgba(124, 58, 237, 0.30),
      inset 0 1px rgba(255, 255, 255, 0.18);
}

.button-arrow {
  font-size: 16px;
  line-height: 1;
}

/* =========================================================
   ИКОНКИ ЧАТА / УВЕДОМЛЕНИЙ
========================================================= */

.header-action {
  position: relative;

  width: 38px;
  height: 38px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #858092;

  text-decoration: none;

  border: 1px solid transparent;
  border-radius: 10px;

  transition:
      color 0.2s ease,
      background 0.2s ease,
      border-color 0.2s ease,
      transform 0.2s ease;
}

.header-action svg {
  width: 18px;
  height: 18px;
}

.header-action:hover {
  color: #fff;

  background: rgba(255, 255, 255, 0.045);
  border-color: rgba(255, 255, 255, 0.06);

  transform: translateY(-1px);
}

.notification-count {
  position: absolute;

  top: 1px;
  right: 0;

  min-width: 16px;
  height: 16px;

  padding: 0 4px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;

  background: #ef4444;

  border: 2px solid #0c0b12;
  border-radius: 999px;

  font-size: 8px;
  font-weight: 900;

  box-shadow:
      0 0 10px rgba(239, 68, 68, 0.35);
}

/* =========================================================
   ПРОФИЛЬ
========================================================= */

.user-menu {
  position: relative;
  margin-left: 3px;
}

.user-button {
  display: flex;
  align-items: center;
  gap: 9px;

  padding: 4px 8px 4px 4px;

  color: #fff;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 11px;

  cursor: pointer;

  transition:
      background 0.2s ease,
      border-color 0.2s ease,
      transform 0.2s ease;
}

.user-button:hover,
.user-button:focus-visible {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(139, 92, 246, 0.18);

  transform: translateY(-1px);
}

.user-avatar {
  position: relative;

  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  overflow: hidden;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #4c1d95
      );

  border: 1px solid rgba(196, 181, 253, 0.2);
  border-radius: 8px;

  font-size: 12px;
  font-weight: 900;

  box-shadow:
      0 4px 16px rgba(124, 58, 237, 0.22);
}

.avatar-img {
  position: absolute;
  inset: 0;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

.online-dot {
  position: absolute;

  right: -1px;
  bottom: -1px;

  width: 8px;
  height: 8px;

  background: #22c55e;

  border: 2px solid #0d0b14;
  border-radius: 50%;

  box-shadow: 0 0 8px rgba(34, 197, 94, 0.55);
}

.user-info {
  min-width: 0;

  display: flex;
  flex-direction: column;

  align-items: flex-start;
  gap: 1px;
}

.user-name {
  max-width: 115px;

  overflow: hidden;

  color: #eee;

  font-size: 12px;
  font-weight: 750;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role {
  color: #666274;

  font-size: 9px;
  font-weight: 600;
}

.chevron {
  width: 14px;
  height: 14px;

  color: #686375;

  transition: transform 0.2s ease;
}

.chevron.open {
  transform: rotate(180deg);
}

/* =========================================================
   ВЫПАДАЮЩЕЕ МЕНЮ
========================================================= */

.dropdown {
  position: absolute;

  top: calc(100% + 10px);
  right: 0;

  width: 245px;

  padding: 7px;

  background:
      linear-gradient(
          145deg,
          rgba(25, 23, 35, 0.98),
          rgba(13, 12, 19, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;

  box-shadow:
      0 25px 70px rgba(0, 0, 0, 0.55),
      0 0 0 1px rgba(124, 58, 237, 0.03);

  backdrop-filter: blur(22px);
}

.dropdown::before {
  content: '';

  position: absolute;

  top: -5px;
  right: 19px;

  width: 9px;
  height: 9px;

  background: #1b1925;

  border-left: 1px solid rgba(255, 255, 255, 0.08);
  border-top: 1px solid rgba(255, 255, 255, 0.08);

  transform: rotate(45deg);
}

.dropdown-head {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 10px 9px;
}

.dropdown-avatar {
  width: 35px;
  height: 35px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  overflow: hidden;

  color: #fff;

  background: linear-gradient(
      135deg,
      #8b5cf6,
      #4c1d95
  );

  border-radius: 9px;

  font-size: 12px;
  font-weight: 900;
}

.dropdown-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dropdown-head > div {
  min-width: 0;

  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropdown-head strong {
  overflow: hidden;

  color: #fff;

  font-size: 12px;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-head span {
  color: #666274;
  font-size: 10px;
}

.dropdown-divider {
  height: 1px;

  margin: 5px 4px;

  background: rgba(255, 255, 255, 0.055);
}

.dropdown-item {
  width: 100%;

  display: flex;
  align-items: center;
  gap: 10px;

  padding: 10px;

  color: #9d99aa;

  background: transparent;

  border: 0;
  border-radius: 8px;

  font-size: 12px;
  font-weight: 600;

  text-align: left;
  text-decoration: none;

  cursor: pointer;

  transition:
      color 0.15s ease,
      background 0.15s ease;
}

.dropdown-item:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.045);
}

.dropdown-icon {
  width: 19px;

  color: #706b80;

  text-align: center;
  font-size: 14px;
}

.dropdown-item:hover .dropdown-icon {
  color: #a78bfa;
}

.dropdown-count {
  min-width: 19px;
  height: 19px;

  margin-left: auto;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 0 5px;

  color: #fff;

  background: #ef4444;

  border-radius: 999px;

  font-size: 9px;
  font-weight: 900;
}

.dropdown-item.danger {
  color: #f87171;
}

.dropdown-item.danger:hover {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
}

.dropdown-item.danger .dropdown-icon {
  color: #ef4444;
}

/* =========================================================
   АНИМАЦИЯ DROPDOWN
========================================================= */

.dropdown-enter-active,
.dropdown-leave-active {
  transition:
      opacity 0.16s ease,
      transform 0.16s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.98);
}

/* =========================================================
   БУРГЕР
========================================================= */

.burger {
  display: none;

  width: 40px;
  height: 40px;

  padding: 0;

  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;

  color: #fff;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;

  cursor: pointer;

  transition:
      background 0.2s ease,
      border-color 0.2s ease;
}

.burger:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(139, 92, 246, 0.25);
}

.burger span {
  width: 17px;
  height: 2px;

  display: block;

  background: #aaa5b7;

  border-radius: 99px;

  transition:
      transform 0.25s ease,
      opacity 0.2s ease;
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

/* =========================================================
   МОБИЛЬНОЕ МЕНЮ
========================================================= */

.mobile-menu {
  position: fixed;

  top: var(--header-height);
  left: 0;
  right: 0;

  z-index: 999;

  max-height: calc(100vh - var(--header-height));

  overflow-y: auto;

  padding: 18px;

  background:
      radial-gradient(
          circle at 90% 0%,
          rgba(124, 58, 237, 0.13),
          transparent 35%
      ),
      rgba(10, 9, 15, 0.985);

  border-bottom: 1px solid rgba(139, 92, 246, 0.12);

  box-shadow:
      0 25px 60px rgba(0, 0, 0, 0.45);

  backdrop-filter: blur(24px);
}

.mobile-menu-top,
.mobile-section {
  display: flex;
  align-items: center;
  gap: 10px;

  margin: 3px 3px 9px;

  color: #5e596c;

  font-size: 9px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: 1.5px;
}

.mobile-line {
  height: 1px;
  flex: 1;

  background: rgba(255, 255, 255, 0.06);
}

.mobile-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mobile-nav-link {
  width: 100%;

  display: flex;
  align-items: center;
  gap: 12px;

  padding: 13px 14px;

  color: #918c9e;

  background: transparent;

  border: 1px solid transparent;
  border-radius: 10px;

  font-size: 13px;
  font-weight: 650;

  text-align: left;
  text-decoration: none;

  cursor: pointer;

  transition:
      color 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease;
}

.mobile-icon {
  width: 22px;

  color: #686274;

  font-size: 16px;
  text-align: center;
}

.mobile-nav-link:hover,
.mobile-nav-link.router-link-exact-active {
  color: #fff;

  background: rgba(124, 58, 237, 0.09);
  border-color: rgba(139, 92, 246, 0.12);
}

.mobile-nav-link.router-link-exact-active {
  color: #c4b5fd;
}

.mobile-nav-link.router-link-exact-active .mobile-icon {
  color: #a78bfa;
}

.mobile-badge {
  min-width: 20px;
  height: 20px;

  margin-left: auto;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 0 6px;

  color: #fff;

  background: #ef4444;

  border-radius: 999px;

  font-size: 9px;
  font-weight: 900;
}

.mobile-section {
  margin-top: 20px;
}

.mobile-nav-link--danger {
  color: #f87171;
}

.mobile-nav-link--danger:hover {
  color: #fca5a5;

  background: rgba(239, 68, 68, 0.07);
  border-color: rgba(239, 68, 68, 0.08);
}

.mobile-auth {
  display: flex;
  flex-direction: column;
  gap: 8px;

  margin-top: 12px;
}

.mobile-login,
.mobile-register {
  min-height: 47px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 10px;

  font-size: 13px;
  font-weight: 750;

  text-decoration: none;
}

.mobile-login {
  color: #ddd8e8;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.07);
}

.mobile-register {
  gap: 10px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, 0.25);

  box-shadow:
      0 8px 25px rgba(124, 58, 237, 0.18);
}

/* =========================================================
   ФОН МОБИЛЬНОГО МЕНЮ
========================================================= */

.mobile-backdrop {
  position: fixed;

  inset: 0;

  z-index: 998;

  top: var(--header-height);

  background: rgba(0, 0, 0, 0.62);

  backdrop-filter: blur(4px);
}

/* =========================================================
   АНИМАЦИИ МОБИЛЬНОГО МЕНЮ
========================================================= */

.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition:
      opacity 0.22s ease,
      transform 0.22s ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.backdrop-enter-active,
.backdrop-leave-active {
  transition: opacity 0.22s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
  opacity: 0;
}

/* =========================================================
   АДАПТИВ
========================================================= */

@media (max-width: 1100px) {
  .main-nav {
    margin-left: 10px;
  }

  .nav-link {
    padding: 9px 9px;
  }

  .nav-link span:last-child {
    font-size: 12px;
  }

  .nav-icon {
    display: none;
  }

  .user-role {
    display: none;
  }
}

@media (max-width: 900px) {
  .header-inner {
    width: min(100% - 28px, 1280px);
  }

  .main-nav {
    gap: 1px;
  }

  .nav-link {
    padding: 8px 8px;
  }

  .brand-sub {
    display: none;
  }

  .user-info {
    display: none;
  }
}

@media (max-width: 800px) {
  .header-inner {
    width: calc(100% - 24px);
  }

  .burger {
    display: flex;
  }

  .main-nav {
    display: none;
  }

  .header-actions {
    gap: 4px;
  }

  .user-button {
    padding-right: 5px;
  }

  .user-avatar {
    width: 31px;
    height: 31px;
  }
}

@media (max-width: 500px) {
  .site-header {
    height: 60px;
  }

  .brand-main {
    font-size: 20px;
  }

  .brand-mark {
    width: 33px;
    height: 33px;
  }

  .auth-link {
    display: none;
  }

  .register-button {
    min-height: 36px;
    padding: 0 11px;

    font-size: 11px;
  }

  .header-action {
    width: 35px;
    height: 35px;
  }

  .mobile-menu {
    top: 60px;
    max-height: calc(100vh - 60px);
  }

  .mobile-backdrop {
    top: 60px;
  }
}

@media (max-width: 380px) {
  .header-inner {
    width: calc(100% - 16px);
    gap: 8px;
  }

  .brand {
    gap: 7px;
  }

  .brand-main {
    font-size: 18px;
  }

  .brand-mark {
    width: 30px;
    height: 30px;
    padding: 6px;
  }

  .register-button {
    display: none;
  }

  .header-actions {
    margin-left: auto;
  }
}
</style>