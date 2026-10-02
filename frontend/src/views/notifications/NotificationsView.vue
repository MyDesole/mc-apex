<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { notificationsApi } from '@/services/notifications/notification.js'
import { useRealtimeNotifications } from '@/composables/notifications/useRealtimeNotifications.js'
import AppIcon from '@/components/core/AppIcon.vue'

const router = useRouter()
const auth = useAuthStore()

// Общий счётчик с шапкой сайта: без этого бейдж в шапке не сбрасывался
const { setUnreadCount, decrementUnread } = useRealtimeNotifications()

const loading = ref(true)
const notifications = ref([])
const unread = ref(0)
const markingAll = ref(false)
const openingId = ref(null)

const hasUnread = computed(() => unread.value > 0)

const notificationCountLabel = computed(() => {
  if (!notifications.value.length) return 'Нет уведомлений'

  return `${notifications.value.length} ${
      notifications.value.length === 1 ? 'уведомление' : 'уведомлений'
  }`
})

function notificationIcon(notification) {
  const type = notification?.data?.type

  const icons = {
    friend_request: 'heart',
    friend_accepted: 'handshake',

    tier_test_request: 'target',
    tier_test_completed: 'trophy',

    clan_application: 'shield',
    clan_application_accepted: 'check',
    clan_application_declined: 'close',

    verified: 'check',
  }

  return icons[type] || 'sparkles'
}

function notificationType(notification) {
  const type = notification?.data?.type

  const labels = {
    friend_request: 'Друзья',
    friend_accepted: 'Друзья',

    tier_test_request: 'Тир-тест',
    tier_test_completed: 'Тир-тест',

    clan_application: 'Клан',
    clan_application_accepted: 'Клан',
    clan_application_declined: 'Клан',

    verified: 'Аккаунт',
  }

  return labels[type] || 'APEX'
}

function notificationClass(notification) {
  const type = notification?.data?.type

  if (
      type === 'clan_application_declined'
  ) {
    return 'notif--danger'
  }

  if (
      type === 'tier_test_completed' ||
      type === 'friend_accepted' ||
      type === 'clan_application_accepted' ||
      type === 'verified'
  ) {
    return 'notif--success'
  }

  if (
      type === 'tier_test_request' ||
      type === 'clan_application'
  ) {
    return 'notif--gold'
  }

  return 'notif--purple'
}

function formatDate(value) {
  if (!value) return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return date.toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function load() {
  loading.value = true

  try {
    const data = await notificationsApi.list()

    notifications.value = data.notifications?.data || []
    unread.value = data.unread_count || 0
    setUnreadCount(unread.value)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function open(notification) {
  openingId.value = notification.id

  try {
    if (!notification.read_at) {
      const result = await notificationsApi.markAsRead(notification.id)

      notification.read_at = new Date().toISOString()
      unread.value = Math.max(0, unread.value - 1)
      setUnreadCount(result?.unread_count ?? unread.value)
    }

    const data = notification.data || {}
    const type = data.type

    if (
        type === 'friend_request' ||
        type === 'friend_accepted'
    ) {
      await router.push('/friends')
    }

    else if (type === 'tier_test_request') {
      if (['tester', 'admin'].includes(auth.user?.role)) {
        await router.push('/tester')
      } else {
        await router.push('/profile')
      }
    }

    else if (type === 'tier_test_completed') {
      await router.push('/profile')
    }

    else if (type === 'clan_application') {
      if (data.clan_id) {
        await router.push(`/clans/${data.clan_id}`)
      }
    }

    else if (
        type === 'clan_application_accepted' ||
        type === 'clan_application_declined'
    ) {
      if (data.clan_id) {
        await router.push(`/clans/${data.clan_id}`)
      }
    }

    else if (type === 'verified') {
      await router.push('/profile')
    }

    await load()
  } catch (e) {
    console.error(e)
  } finally {
    openingId.value = null
  }
}

async function markAll() {
  if (markingAll.value || !hasUnread.value) return

  markingAll.value = true

  try {
    const result = await notificationsApi.markAllAsRead()

    // Сразу обнуляем оба счётчика, не дожидаясь перезагрузки списка
    unread.value = 0
    setUnreadCount(result?.unread_count ?? 0)

    notifications.value = notifications.value.map((n) => ({
      ...n,
      read_at: n.read_at || new Date().toISOString(),
    }))

    await load()
  } catch (e) {
    console.error(e)
  } finally {
    markingAll.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="notif-page">

    <!-- ================= HEADER ================= -->

    <header class="notif-head">

      <div class="notif-head__content">
        <span class="eyebrow">
          APEX / NOTIFICATIONS
        </span>

        <div class="notif-head__title-row">
          <div>
            <h1 class="notif-head__title">
              Уведомления
            </h1>

            <p class="notif-head__subtitle">
              Все важные события вашего аккаунта в одном месте.
            </p>
          </div>

          <div
              v-if="hasUnread"
              class="unread-counter"
          >
            <span class="unread-counter__dot"></span>
            <span>{{ unread }} новых</span>
          </div>
        </div>
      </div>

      <button
          v-if="hasUnread"
          type="button"
          class="mark-all"
          :disabled="markingAll"
          @click="markAll"
      >
        <span class="mark-all__icon">
          <AppIcon icon="check" :size="14" />
        </span>

        <span>
          {{ markingAll ? 'Обработка...' : 'Прочитать все' }}
        </span>
      </button>

    </header>

    <!-- ================= SUMMARY ================= -->

    <section class="summary">

      <div class="summary__left">
        <span class="summary__label">
          INBOX
        </span>

        <span class="summary__value">
          {{ notificationCountLabel }}
        </span>
      </div>

      <div
          class="summary__status"
          :class="{ 'summary__status--active': hasUnread }"
      >
        <span class="summary__status-dot"></span>

        {{ hasUnread ? 'Есть новые события' : 'Всё прочитано' }}
      </div>

    </section>

    <!-- ================= LOADING ================= -->

    <section
        v-if="loading"
        class="state"
    >
      <div class="state__icon">
        <AppIcon icon="sparkles" :size="26" />
      </div>

      <div class="state__loader">
        <span></span>
        <span></span>
        <span></span>
      </div>

      <strong>
        Загружаем уведомления
      </strong>

      <p>
        Проверяем последние события аккаунта...
      </p>
    </section>

    <!-- ================= EMPTY ================= -->

    <section
        v-else-if="!notifications.length"
        class="state state--empty"
    >
      <div class="state__icon">
        <AppIcon icon="check" :size="27" />
      </div>

      <span class="eyebrow eyebrow--small">
        INBOX CLEAR
      </span>

      <strong>
        Уведомлений нет
      </strong>

      <p>
        Здесь появятся уведомления о друзьях, кланах,
        тир-тестах и других событиях.
      </p>
    </section>

    <!-- ================= LIST ================= -->

    <section
        v-else
        class="notifications"
    >

      <div class="notifications__head">
        <div>
          <span class="eyebrow eyebrow--small">
            ACTIVITY FEED
          </span>

          <h2>
            Последние события
          </h2>
        </div>

        <span class="notifications__count">
          {{ notifications.length }}
        </span>
      </div>

      <div class="list">

        <button
            v-for="notification in notifications"
            :key="notification.id"
            type="button"
            class="notif"
            :class="[
            notificationClass(notification),
            {
              'notif--unread': !notification.read_at,
              'notif--opening': openingId === notification.id,
            },
          ]"
            :disabled="openingId === notification.id"
            @click="open(notification)"
        >

          <!-- декоративный glow -->
          <div class="notif__glow"></div>

          <!-- unread line -->
          <div
              v-if="!notification.read_at"
              class="notif__unread-line"
          ></div>

          <!-- icon -->
          <div class="notif__icon-wrap">
            <div class="notif__icon">
              <AppIcon
                  :icon="notificationIcon(notification)"
                  :size="19"
              />
            </div>

            <span
                v-if="!notification.read_at"
                class="notif__dot"
            ></span>
          </div>

          <!-- content -->
          <div class="notif__content">

            <div class="notif__top">

              <span class="notif__type">
                {{ notificationType(notification) }}
              </span>

              <span class="notif__time">
                {{ formatDate(notification.created_at) }}
              </span>

            </div>

            <div class="notif__message">
              {{ notification.data?.message || 'Новое уведомление' }}
            </div>

            <div class="notif__bottom">

              <span
                  v-if="!notification.read_at"
                  class="notif__new"
              >
                <span></span>
                НОВОЕ
              </span>

              <span
                  v-else
                  class="notif__read"
              >
                Прочитано
              </span>

            </div>

          </div>

          <!-- arrow -->
          <div class="notif__arrow">
            <AppIcon
                icon="send"
                :size="14"
            />
          </div>

        </button>

      </div>

    </section>

  </main>
</template>

<style scoped>
@import "@/views/notifications/NotificationsView.css";
</style>
