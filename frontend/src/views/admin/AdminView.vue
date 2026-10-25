<script setup>
/**
 * Админ-панель: боковое меню с разделами вместо дюжины вкладок в строку.
 *
 * Счётчики (жалобы, апелляции, очередь тестов) обновляются раз в минуту.
 * Досье игрока открывается выезжающей панелью из любого раздела —
 * через provide('openDossier').
 */
import { computed, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { adminApi } from '@/services/core/admin.js'

import AppIcon from '@/components/core/AppIcon.vue'
import TabTransition from '@/components/core/TabTransition.vue'
import AdminDashboard from '@/components/admin/AdminDashboard.vue'
import AdminReports from '@/components/admin/AdminReports.vue'
import AdminModerationLog from '@/components/admin/AdminModerationLog.vue'
import AdminDossier from '@/components/admin/AdminDossier.vue'
import AdminPlayerSearch from '@/components/admin/AdminPlayerSearch.vue'
import AdminUsers from '@/components/admin/AdminUsers.vue'
import AdminComments from '@/components/admin/AdminComments.vue'
import AdminEvents from '@/components/admin/AdminEvents.vue'
import AdminClans from '@/components/admin/AdminClans.vue'
import AdminTournaments from '@/components/admin/AdminTournaments.vue'
import AdminAchievements from '@/components/admin/AdminAchievements.vue'
import AdminHome from '@/components/admin/AdminHome.vue'
import AdminNews from '@/components/admin/AdminNews.vue'
import AdminShop from '@/components/admin/AdminShop.vue'
import AdminForum from '@/components/admin/AdminForum.vue'
import AdminVerification from '@/components/admin/AdminVerification.vue'
import AdminAppeals from '@/components/admin/AdminAppeals.vue'
import AdminReferrals from '@/components/admin/AdminReferrals.vue'
import AdminControl from '@/components/admin/AdminControl.vue'
import AdminSupport from '@/components/admin/AdminSupport.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const role = computed(() => auth.user?.role)
const isAdmin = computed(() => role.value === 'admin')

const ROLE_LABELS = {
  moderator: 'Модератор',
  admin: 'Администратор',
}

/*
 * Разделы меню. adminOnly — только для администратора,
 * badge — ключ из /admin/counters.
 */
const SECTIONS = [
  {
    title: 'Обзор',
    items: [
      { key: 'dashboard', label: 'Дашборд', icon: 'chart', component: AdminDashboard },
      { key: 'control', label: 'Пульт', icon: 'bolt', component: AdminControl, adminOnly: true },
    ],
  },
  {
    title: 'Модерация',
    items: [
      { key: 'support', label: 'Поддержка', icon: 'send', component: AdminSupport, badge: 'support' },
      { key: 'reports', label: 'Жалобы', icon: 'alert', component: AdminReports, badge: 'reports' },
      { key: 'appeals', label: 'Апелляции', icon: 'shield', component: AdminAppeals, badge: 'appeals' },
      { key: 'log', label: 'Журнал действий', icon: 'doc', component: AdminModerationLog },
    ],
  },
  {
    title: 'Игроки',
    items: [
      { key: 'users', label: 'Пользователи', icon: 'handshake', component: AdminUsers, adminOnly: true },
      { key: 'verification', label: 'Верификация', icon: 'check', component: AdminVerification, adminOnly: true },
      { key: 'referrals', label: 'Рефералы', icon: 'gift', component: AdminReferrals, adminOnly: true },
    ],
  },
  {
    title: 'Контент',
    items: [
      { key: 'forum', label: 'Форум', icon: 'doc', component: AdminForum },
      { key: 'comments', label: 'Комментарии', icon: 'send', component: AdminComments },
      { key: 'events', label: 'Клан-посты', icon: 'bolt', component: AdminEvents },
      { key: 'news', label: 'Новости', icon: 'rocket', component: AdminNews, adminOnly: true },
    ],
  },
  {
    title: 'Соревнования',
    items: [
      { key: 'tournaments', label: 'Турниры', icon: 'trophy', component: AdminTournaments },
      { key: 'clans', label: 'Кланы', icon: 'shield', component: AdminClans },
    ],
  },
  {
    title: 'Экономика',
    items: [
      { key: 'shop', label: 'Магазин', icon: 'coin', component: AdminShop },
      { key: 'achievements', label: 'Ачивки', icon: 'medal', component: AdminAchievements, adminOnly: true },
    ],
  },
  {
    title: 'Сайт',
    items: [
      { key: 'home', label: 'Главная и настройки', icon: 'globe', component: AdminHome, adminOnly: true },
    ],
  },
]

const sections = computed(() =>
    SECTIONS
        .map((section) => ({ ...section, items: section.items.filter((item) => isAdmin.value || !item.adminOnly) }))
        .filter((section) => section.items.length),
)

const items = computed(() => sections.value.flatMap((section) => section.items))

// Раздел живёт в адресе (?tab=reports): ссылкой можно поделиться, F5 его не сбрасывает
const tab = computed({
  get: () => (items.value.some((i) => i.key === route.query.tab) ? route.query.tab : 'dashboard'),
  set: (key) => router.replace({ query: { ...route.query, tab: key } }),
})

const current = computed(() => items.value.find((i) => i.key === tab.value))

/* ---------- Счётчики ---------- */

const counters = ref({ reports: 0, appeals: 0, tests: 0, support: 0 })
let countersTimer = null

async function loadCounters() {
  try {
    counters.value = await adminApi.counters()
  } catch {
    /* счётчики — подсказка, без них панель работает */
  }
}

/* ---------- Досье ---------- */

const dossierId = ref(null)

function openDossier(userId) {
  dossierId.value = userId
}

provide('openDossier', openDossier)
// Разделы зовут это после решения, чтобы бейджи сразу обновились
provide('refreshCounters', loadCounters)

/* ---------- Мобильное меню ---------- */

const menuOpen = ref(false)

watch(tab, () => {
  menuOpen.value = false
})

onMounted(() => {
  if (!['moderator', 'admin'].includes(role.value)) {
    router.push('/')
    return
  }

  loadCounters()
  countersTimer = setInterval(loadCounters, 60_000)
})

onUnmounted(() => clearInterval(countersTimer))
</script>

<template>
  <div class="admin">
    <!-- ============ БОКОВОЕ МЕНЮ ============ -->
    <aside class="admin-nav" :class="{ 'admin-nav--open': menuOpen }">
      <div class="admin-nav__head">
        <strong>Админ-панель</strong>
        <span class="admin-nav__role" :class="`admin-nav__role--${role}`">{{ ROLE_LABELS[role] }}</span>
      </div>

      <nav>
        <div v-for="section in sections" :key="section.title" class="admin-nav__section">
          <span class="admin-nav__title">{{ section.title }}</span>

          <button
              v-for="item in section.items"
              :key="item.key"
              type="button"
              class="admin-nav__item"
              :class="{ active: tab === item.key }"
              @click="tab = item.key"
          >
            <AppIcon :icon="item.icon" :size="16" />
            <span>{{ item.label }}</span>
            <b v-if="item.badge && counters[item.badge]" class="admin-nav__badge">
              {{ counters[item.badge] }}
            </b>
          </button>
        </div>
      </nav>
    </aside>

    <!-- ============ СОДЕРЖИМОЕ ============ -->
    <main class="admin-main">
      <header class="admin-main__head">
        <button type="button" class="admin-main__menu" @click="menuOpen = !menuOpen">
          <span /><span /><span />
          <span class="sr-only">Меню разделов</span>
        </button>

        <h1>{{ current?.label }}</h1>

        <AdminPlayerSearch class="admin-main__search" @select="openDossier" />
      </header>

      <TabTransition :active="tab">
        <component :is="current.component" v-if="current" :key="tab" />
      </TabTransition>
    </main>

    <AdminDossier
        v-if="dossierId"
        :user-id="dossierId"
        @close="dossierId = null"
        @changed="loadCounters"
        @open="openDossier"
    />
  </div>
</template>

<style scoped>
@import "@/views/admin/AdminView.css";
</style>
