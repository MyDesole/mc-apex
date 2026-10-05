<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/core/auth.js'
import { notificationsApi } from '@/services/notifications/notification.js'
import { chatApi } from '@/services/chat/chat.js'

import UserName from '@/components/players/UserName.vue'
import VerifyEmailBanner from '@/components/notifications/VerifyEmailBanner.vue'
import CoinBadge from '@/components/shop/CoinBadge.vue'
import TierTestBanner from '@/components/tiers/TierTestBanner.vue'
import NotificationToast from '@/components/notifications/NotificationToast.vue'

import { useRealtimeNotifications } from '@/composables/notifications/useRealtimeNotifications.js'
import { useRealtimeMessages } from '@/composables/chat/useRealtimeMessages.js'
import { useTitleBlink } from '@/composables/core/useTitleBlink.js'

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

          <RouterLink to="/forum" class="nav-link">
            <span class="nav-icon">✉</span>
            <span>Форум</span>
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
                      v-if="auth.user && ['bridge_tester', 'admin'].includes(auth.user.role)"
                      to="/bridge-review"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">⌁</span>
                    <span>Проверка бриджа</span>
                  </RouterLink>

                  <RouterLink
                      v-if="auth.user && ['bridge_curator', 'admin'].includes(auth.user.role)"
                      to="/bridge-curator"
                      class="dropdown-item"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-icon">▦</span>
                    <span>Виды бриджа</span>
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

          <RouterLink to="/forum" class="mobile-nav-link">
            <span class="mobile-icon">✉</span>
            <span>Форум</span>
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
      <!--
        Смена страницы с проявлением и лёгким подъёмом.
        mode="out-in" обязателен: без него уходящая и приходящая
        страницы накладываются друг на друга.
      -->
      <!--
        Ключ — имя маршрута, а не полный путь: смена параметров внутри
        одной страницы (например, диалога в сообщениях) не должна
        перезапускать переход всей страницы.
      -->
      <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="route.name" />
        </Transition>
      </RouterView>
    </main>

  </div>

  <TierTestBanner />

  <NotificationToast
      :notification="latestNotification"
      :trigger="newNotificationArrived"
  />
</template>

<style scoped>
@import "@/layouts/AppLayout.css";
</style>
