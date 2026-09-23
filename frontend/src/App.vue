<script setup>
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

const menuOpen = ref(false)

const username = computed(() => auth.user?.username || 'Игрок')

const avatarLetter = computed(() => {
  return username.value.charAt(0).toUpperCase()
})

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
        <!-- Logo -->
        <RouterLink to="/" class="brand">
          <span class="brand-main">APEX</span>
        </RouterLink>

        <!-- Navigation -->
        <nav class="main-nav">
          <RouterLink to="/" class="nav-link">
            Главная
          </RouterLink>

          <RouterLink to="/players" class="nav-link">
            Игроки
          </RouterLink>

          <RouterLink to="/clans" class="nav-link">
            Кланы
          </RouterLink>

          <RouterLink to="/tournaments" class="nav-link">
            Турниры
          </RouterLink>
        </nav>

        <!-- Right side -->
        <div class="header-actions">
          <!-- Guest -->
          <template v-if="!auth.isAuthenticated">
            <RouterLink
                to="/login"
                class="btn btn-secondary"
            >
              Войти
            </RouterLink>

            <RouterLink
                to="/register"
                class="btn btn-primary"
            >
              Регистрация
            </RouterLink>
          </template>

          <!-- Authenticated -->
          <div
              v-else
              class="user-menu"
          >
            <button
                class="user-button"
                type="button"
                @click="menuOpen = !menuOpen"
            >
              <span class="avatar">
                {{ avatarLetter }}
              </span>

              <span class="username">
                {{ username }}
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

            <div
                v-if="menuOpen"
                class="dropdown"
            >
              <RouterLink
                  to="/profile"
                  class="dropdown-item"
                  @click="menuOpen = false"
              >
                Профиль
              </RouterLink>

              <div class="dropdown-divider"></div>

              <button
                  type="button"
                  class="dropdown-item danger"
                  @click="logout"
              >
                Выйти
              </button>
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

<style>
/* ============================================
   APEX APP
   ============================================ */

:root {
  --bg: #0a0a0f;
  --bg-header: rgba(10, 10, 15, 0.82);
  --bg-card: #12121a;
  --bg-card-hover: #1a1a26;

  --border: #22222e;
  --border-hover: #343443;

  --accent: #7c3aed;
  --accent-light: #8b5cf6;
  --accent-glow: rgba(124, 58, 237, 0.3);

  --accent2: #06b6d4;

  --text: #e2e2e8;
  --text-dim: #8888a0;
  --text-muted: #5e5e70;

  --danger: #ef4444;

  --header-height: 72px;
}

/* Reset */

*,
*::before,
*::after {
  box-sizing: border-box;
}

html {
  background: var(--bg);
}

body {
  margin: 0;
  min-width: 320px;

  background: var(--bg);
  color: var(--text);

  font-family:
      Inter,
      ui-sans-serif,
      system-ui,
      -apple-system,
      BlinkMacSystemFont,
      "Segoe UI",
      sans-serif;

  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

button,
input {
  font: inherit;
}

button {
  border: 0;
}

a {
  color: inherit;
  text-decoration: none;
}

/* ============================================
   APP
   ============================================ */

.app {
  min-height: 100vh;
  background:
      radial-gradient(
          circle at 50% -20%,
          rgba(124, 58, 237, 0.08),
          transparent 40%
      ),
      var(--bg);
}

.page {
  min-height: calc(100vh - var(--header-height));
}

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
}

/* ============================================
   BRAND
   ============================================ */

.brand {
  display: inline-flex;
  align-items: center;

  flex-shrink: 0;

  font-size: 24px;
  font-weight: 900;
  letter-spacing: -1px;

  transition: opacity 0.2s ease;
}

.brand:hover {
  opacity: 0.85;
}

.brand-main {
  background:
      linear-gradient(
          135deg,
          #c4b5fd 0%,
          #8b5cf6 45%,
          #7c3aed 100%
      );

  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

/* ============================================
   NAVIGATION
   ============================================ */

.main-nav {
  display: flex;
  align-items: center;

  gap: 4px;

  margin-left: 48px;
}

.nav-link {
  position: relative;

  padding: 9px 13px;

  color: var(--text-dim);

  font-size: 14px;
  font-weight: 500;

  border-radius: 8px;

  transition:
      color 0.2s ease,
      background 0.2s ease;
}

.nav-link:hover {
  color: var(--text);

  background: rgba(255, 255, 255, 0.035);
}

.nav-link.router-link-exact-active {
  color: #fff;
}

.nav-link.router-link-exact-active::after {
  content: "";

  position: absolute;
  left: 13px;
  right: 13px;
  bottom: 2px;

  height: 2px;

  background: var(--accent);

  border-radius: 999px;

  box-shadow:
      0 0 10px var(--accent-glow);
}

/* ============================================
   HEADER ACTIONS
   ============================================ */

.header-actions {
  display: flex;
  align-items: center;

  gap: 9px;

  margin-left: auto;
}

/* ============================================
   BUTTONS
   ============================================ */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-height: 38px;

  padding: 0 15px;

  border-radius: 8px;

  font-size: 13px;
  font-weight: 700;

  transition:
      transform 0.2s ease,
      background 0.2s ease,
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.btn:hover {
  transform: translateY(-1px);
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

  box-shadow:
      0 5px 20px rgba(124, 58, 237, 0.2);
}

.btn-primary:hover {
  background: var(--accent-light);

  box-shadow:
      0 7px 25px rgba(124, 58, 237, 0.3);
}

/* ============================================
   USER MENU
   ============================================ */

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

  transition:
      background 0.2s ease,
      border-color 0.2s ease;
}

.user-button:hover,
.user-button:focus-visible {
  background: var(--bg-card);

  border-color: var(--border);
}

.avatar {
  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border-radius: 8px;

  font-size: 13px;
  font-weight: 800;

  box-shadow:
      0 3px 12px rgba(124, 58, 237, 0.25);
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

  transition: transform 0.2s ease;
}

.chevron.open {
  transform: rotate(180deg);
}

/* ============================================
   DROPDOWN
   ============================================ */

.dropdown {
  position: absolute;

  top: calc(100% + 8px);
  right: 0;

  width: 190px;

  padding: 6px;

  background:
      rgba(18, 18, 26, 0.97);

  border: 1px solid var(--border);
  border-radius: 11px;

  box-shadow:
      0 18px 50px rgba(0, 0, 0, 0.45);

  backdrop-filter: blur(16px);
}

.dropdown-item {
  display: flex;
  align-items: center;

  width: 100%;

  padding: 10px 11px;

  color: var(--text-dim);

  background: transparent;

  border-radius: 7px;

  font-size: 13px;
  font-weight: 500;

  text-align: left;

  cursor: pointer;

  transition:
      color 0.2s ease,
      background 0.2s ease;
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

.dropdown-divider {
  height: 1px;

  margin: 5px 4px;

  background: var(--border);
}

/* ============================================
   MOBILE
   ============================================ */

@media (max-width: 800px) {
  .header-inner {
    width: min(100% - 24px, 1200px);
  }

  .main-nav {
    margin-left: 20px;
  }

  .nav-link {
    padding-left: 9px;
    padding-right: 9px;
  }

  .btn-secondary {
    display: none;
  }
}

@media (max-width: 650px) {
  :root {
    --header-height: 64px;
  }

  .header-inner {
    width: calc(100% - 20px);
  }

  .brand {
    font-size: 21px;
  }

  .main-nav {
    display: none;
  }

  .btn {
    min-height: 36px;

    padding: 0 12px;

    font-size: 12px;
  }

  .username {
    display: none;
  }

  .user-button {
    padding-right: 5px;
  }
}
</style>