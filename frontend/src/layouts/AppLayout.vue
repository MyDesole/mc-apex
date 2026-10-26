<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/core/auth.js'
import { notificationsApi } from '@/services/notifications/notification.js'
import { chatApi } from '@/services/chat/chat.js'
import { casesApi } from '@/services/shop/cases.js'

import UserName from '@/components/players/UserName.vue'
import VerifyEmailBanner from '@/components/notifications/VerifyEmailBanner.vue'
import CoinBadge from '@/components/shop/CoinBadge.vue'
import TierTestBanner from '@/components/tiers/TierTestBanner.vue'
import NotificationToast from '@/components/notifications/NotificationToast.vue'
import BrandCube from '@/components/core/BrandCube.vue'
import CommandPalette from '@/components/core/CommandPalette.vue'
import ReportDialog from '@/components/core/ReportDialog.vue'
import SiteFooter from '@/components/core/SiteFooter.vue'
import SiteAnnouncement from '@/components/core/SiteAnnouncement.vue'
import { useCommandPalette, paletteShortcut } from '@/composables/core/useCommandPalette.js'
import { startSitePresence, stopSitePresence } from '@/composables/chat/presence.js'

import { useRealtimeNotifications } from '@/composables/notifications/useRealtimeNotifications.js'
import { useRealtimeMessages } from '@/composables/chat/useRealtimeMessages.js'
import { useTitleBlink } from '@/composables/core/useTitleBlink.js'
import { useClanPulse, refreshClanPulse, clearClanPulse } from '@/composables/clan/useClanPulse.js'

const { startBlink, stopBlink } = useTitleBlink()

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const menuOpen = ref(false)
const mobileMenuOpen = ref(false)

const { open: openPalette } = useCommandPalette()

/*
 * Присутствие на всём сайте, а не только в чате: собеседник видит
 * зелёную точку, где бы игрок ни находился.
 */
watch(
    () => auth.user?.id,
    (id) => (id ? startSitePresence(id) : stopSitePresence({ silent: true })),
    { immediate: true },
)

const {
  unreadCount,
  latestNotification,
  newNotificationArrived,
} = useRealtimeNotifications()

const { latestMessage } = useRealtimeMessages()

const { pulse: clanPulse, attention: clanAttention } = useClanPulse()

// Сводку клана грузим, когда игрок в клане; вышел — убираем
watch(
    () => auth.user?.clan_member?.clan_id ?? auth.user?.clan_member?.id ?? null,
    (inClan) => (inClan ? refreshClanPulse() : clearClanPulse()),
    { immediate: true },
)

// Бесплатный сундук ждёт — подсвечиваем «Кейсы» в меню
const dailyCaseAvailable = ref(false)

async function loadCaseStatus() {
  if (!auth.isAuthenticated) return

  try {
    dailyCaseAvailable.value = (await casesApi.status()).daily_available
  } catch {
    dailyCaseAvailable.value = false
  }
}

watch(menuOpen, (open) => open && loadCaseStatus())

/** Служебные разделы: показываем только ролям с доступом. */
const STAFF_LINKS = [
  { to: '/tester', label: 'Панель тестера', roles: ['tester', 'admin'], icon: 'M20 6 9 17l-5-5' },
  { to: '/bridge-review', label: 'Проверка бриджа', roles: ['bridge_tester', 'admin'], icon: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11' },
  { to: '/bridge-curator', label: 'Виды бриджа', roles: ['bridge_curator', 'admin'], icon: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z' },
  { to: '/admin', label: 'Админка', roles: ['moderator', 'admin'], icon: 'M12 2 3 6v6c0 5 3.8 9.3 9 10 5.2-.7 9-5 9-10V6l-9-4Z' },
]

const staffLinks = computed(() => STAFF_LINKS.filter((link) => link.roles.includes(auth.user?.role)))

const chatUnread = ref(0)

async function logout() {
  menuOpen.value = false
  mobileMenuOpen.value = false

  // Пока сессия жива: после выхода сервер не примет отметку
  stopSitePresence()

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
          <BrandCube class="brand-cube" />

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


        </nav>

        <!-- ДЕЙСТВИЯ СПРАВА -->
        <div class="header-actions">

          <!-- ПОИСК (Ctrl+K) -->
          <button
              type="button"
              class="search-trigger"
              aria-label="Поиск по сайту"
              @click="openPalette"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="7" />
              <path d="m20 20-3.5-3.5" stroke-linecap="round" />
            </svg>
            <span class="search-trigger__label">Поиск</span>
            <kbd class="search-trigger__kbd">{{ paletteShortcut }}</kbd>
          </button>

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

            <!-- МОЙ КЛАН: тег на виду, точка — есть что сделать -->
            <RouterLink
                v-if="clanPulse"
                to="/my-clan"
                class="header-clan"
                :style="{ '--clan-color': clanPulse.clan.banner_color || '#8b5cf6' }"
                :title="clanPulse.checked_in ? 'Мой клан' : 'Мой клан — отметьтесь сегодня'"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                <path d="M12 2 3 6v6c0 5 3.8 9.3 9 10 5.2-.7 9-5 9-10V6l-9-4Z" />
              </svg>
              <span>{{ clanPulse.clan.tag }}</span>
              <i v-if="clanAttention > 0" class="header-clan__dot" />
            </RouterLink>

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

                <!-- На телефоне кнопки клана нет — «есть дела в клане» точкой здесь -->
                <i v-if="clanAttention > 0" class="user-button__dot"></i>

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

                  <RouterLink to="/profile" class="dropdown-head" @click="menuOpen = false">
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
                      <span>Открыть профиль →</span>
                    </div>
                  </RouterLink>

                  <!-- Главное — плитками -->
                  <div class="dropdown-tiles">
                    <RouterLink to="/inventory" class="dropdown-tile" @click="menuOpen = false">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 8 12 3 3 8v8l9 5 9-5V8Z" />
                        <path d="m3 8 9 5 9-5M12 13v8" />
                      </svg>
                      <span>Инвентарь</span>
                    </RouterLink>

                    <RouterLink to="/messages" class="dropdown-tile" @click="menuOpen = false">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                      </svg>
                      <span>Сообщения</span>
                      <b v-if="chatUnread > 0" class="dropdown-tile__count">{{ chatUnread > 99 ? '99+' : chatUnread }}</b>
                    </RouterLink>

                    <RouterLink to="/notifications" class="dropdown-tile" @click="menuOpen = false">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                      </svg>
                      <span>Уведомления</span>
                      <b v-if="unreadCount > 0" class="dropdown-tile__count">{{ unreadCount > 99 ? '99+' : unreadCount }}</b>
                    </RouterLink>

                    <RouterLink to="/friends" class="dropdown-tile" @click="menuOpen = false">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                      </svg>
                      <span>Друзья</span>
                    </RouterLink>
                  </div>

                  <!-- Клан: заметная карточка, чтобы о «Моём клане» знали -->
                  <RouterLink
                      v-if="auth.user?.clan_member && clanPulse"
                      to="/my-clan"
                      class="dropdown-clan"
                      :style="{ '--clan-color': clanPulse.clan.banner_color || '#8b5cf6' }"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-clan__avatar">
                      <img v-if="clanPulse.clan.avatar_url" :src="clanPulse.clan.avatar_url" :alt="clanPulse.clan.name" />
                      <template v-else>{{ clanPulse.clan.tag?.charAt(0) }}</template>
                    </span>

                    <span class="dropdown-clan__info">
                      <strong>[{{ clanPulse.clan.tag }}] {{ clanPulse.clan.name }}</strong>
                      <span>Мой клан · {{ clanPulse.level }} ур.</span>
                    </span>

                    <span v-if="!clanPulse.checked_in" class="dropdown-clan__chip dropdown-clan__chip--pulse">
                      Отметься
                    </span>
                    <span v-else-if="clanPulse.wars_active" class="dropdown-clan__chip">
                      КВ: {{ clanPulse.wars_active }}
                    </span>
                    <span v-else class="dropdown-clan__arrow">→</span>
                  </RouterLink>

                  <RouterLink
                      v-else-if="auth.user?.clan_member"
                      to="/my-clan"
                      class="dropdown-clan"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-clan__avatar">◆</span>
                    <span class="dropdown-clan__info">
                      <strong>Мой клан</strong>
                      <span>Отметки, войны, казна</span>
                    </span>
                    <span class="dropdown-clan__arrow">→</span>
                  </RouterLink>

                  <RouterLink
                      v-else
                      to="/clans"
                      class="dropdown-clan dropdown-clan--join"
                      @click="menuOpen = false"
                  >
                    <span class="dropdown-clan__avatar">＋</span>
                    <span class="dropdown-clan__info">
                      <strong>Найти клан</strong>
                      <span>Клановые войны, казна и уровень</span>
                    </span>
                    <span class="dropdown-clan__arrow">→</span>
                  </RouterLink>

                  <RouterLink to="/cases" class="dropdown-item" @click="menuOpen = false">
                    <svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="8" width="18" height="13" rx="2" />
                      <path d="M3 12h18M12 8v13M7.5 8a2.5 2.5 0 1 1 4.5-1.5A2.5 2.5 0 1 1 16.5 8" />
                    </svg>
                    <span>Кейсы</span>
                    <span v-if="dailyCaseAvailable" class="dropdown-free">Бесплатно</span>
                  </RouterLink>

                  <RouterLink to="/support" class="dropdown-item" @click="menuOpen = false">
                    <svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="9" />
                      <path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4M12 17h.01" />
                    </svg>
                    <span>Техподдержка</span>
                  </RouterLink>

                  <!-- Служебное: только для ролей с доступом -->
                  <template v-if="staffLinks.length">
                    <div class="dropdown-label">Служебное</div>

                    <RouterLink
                        v-for="link in staffLinks"
                        :key="link.to"
                        :to="link.to"
                        class="dropdown-item"
                        @click="menuOpen = false"
                    >
                      <svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="link.icon" />
                      </svg>
                      <span>{{ link.label }}</span>
                    </RouterLink>
                  </template>

                  <div class="dropdown-divider"></div>

                  <button
                      class="dropdown-item danger"
                      @click="logout"
                  >
                    <svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
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

          <RouterLink to="/cases" class="mobile-nav-link">
            <span class="mobile-icon">▣</span>
            <span>Кейсы</span>
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
                to="/support"
                class="dropdown-item"
                @click="menuOpen = false"
            >
              <span class="dropdown-icon">?</span>
              <span>Техподдержка</span>
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

    <!-- Объявление и событие xN с «Пульта» админки -->
    <SiteAnnouncement />

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

    <!-- В сообщениях окно чата занимает всю высоту — подвал там лишний -->
    <SiteFooter v-if="!route.path.startsWith('/messages')" />

  </div>

  <TierTestBanner />

  <CommandPalette />
  <!-- Окно жалобы: открывается из профиля, чата и форума через openReport() -->
  <ReportDialog v-if="auth.isAuthenticated" />

  <NotificationToast
      :notification="latestNotification"
      :trigger="newNotificationArrived"
  />
</template>

<style scoped>
@import "@/layouts/AppLayout.css";
</style>
