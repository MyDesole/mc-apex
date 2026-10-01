<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { notificationsApi } from '@/services/notification.js'
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications'
import AppIcon from '@/components/AppIcon.vue'

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
/* =========================================================
   APEX NOTIFICATIONS
========================================================= */

.notif-page {
  width: min(900px, calc(100% - 40px));
  margin: 0 auto;
  padding: 38px 0 70px;

  color: var(--text);
}

/* =========================================================
   HEADER
========================================================= */

.notif-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;

  margin-bottom: 24px;
}

.notif-head__content {
  min-width: 0;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  margin-bottom: 10px;

  color: var(--accent-light, #a78bfa);

  font-size: 10px;
  font-weight: 900;
  line-height: 1;

  letter-spacing: 1.8px;
  text-transform: uppercase;
}

.eyebrow::before {
  content: '';

  width: 18px;
  height: 1px;

  background: currentColor;

  opacity: 0.6;
}

.eyebrow--small {
  margin-bottom: 6px;

  font-size: 8px;
  letter-spacing: 1.5px;
}

.notif-head__title-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.notif-head__title {
  margin: 0;

  font-size: clamp(28px, 5vw, 40px);
  font-weight: 900;
  line-height: 1;

  letter-spacing: -1.7px;

  background:
      linear-gradient(
          135deg,
          var(--text) 25%,
          var(--text-dim)
      );

  -webkit-background-clip: text;
  background-clip: text;

  color: transparent;
}

.notif-head__subtitle {
  margin: 9px 0 0;

  color: var(--text-dim);

  font-size: 12px;
  line-height: 1.5;
}

/* =========================================================
   UNREAD COUNTER
========================================================= */

.unread-counter {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 6px 9px;

  color: #a78bfa;

  background: rgba(124, 58, 237, 0.08);

  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 7px;

  font-size: 9px;
  font-weight: 900;

  letter-spacing: 0.5px;
  text-transform: uppercase;

  white-space: nowrap;
}

.unread-counter__dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: #a78bfa;

  box-shadow: 0 0 9px #8b5cf6;
}

/* =========================================================
   MARK ALL
========================================================= */

.mark-all {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  min-height: 35px;

  padding: 0 11px;

  color: var(--text-dim);

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 8px;

  cursor: pointer;

  font-size: 10px;
  font-weight: 800;

  transition:
      color 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;

  white-space: nowrap;
}

.mark-all__icon {
  display: flex;

  color: var(--accent-light, #a78bfa);
}

.mark-all:hover:not(:disabled) {
  color: var(--text);

  border-color: var(--border-hover);

  background: rgba(124, 58, 237, 0.06);

  transform: translateY(-1px);
}

.mark-all:disabled {
  opacity: 0.55;
  cursor: wait;
}

/* =========================================================
   SUMMARY
========================================================= */

.summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;

  margin-bottom: 17px;
  padding: 11px 13px;

  background:
      linear-gradient(
          90deg,
          rgba(124, 58, 237, 0.045),
          transparent
      );

  border: 1px solid var(--border);
  border-radius: 9px;
}

.summary__left {
  display: flex;
  align-items: center;
  gap: 9px;
}

.summary__label {
  color: var(--text-muted);

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 1.2px;
}

.summary__value {
  color: var(--text-dim);

  font-size: 10px;
  font-weight: 700;
}

.summary__status {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: var(--text-muted);

  font-size: 8px;
  font-weight: 800;

  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.summary__status-dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: var(--text-muted);
}

.summary__status--active {
  color: #a78bfa;
}

.summary__status--active .summary__status-dot {
  background: #a78bfa;

  box-shadow:
      0 0 8px rgba(167, 139, 250, 0.8);
}

/* =========================================================
   LIST HEADER
========================================================= */

.notifications__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  margin-bottom: 9px;
}

.notifications__head h2 {
  margin: 0;

  font-size: 16px;
  font-weight: 850;

  letter-spacing: -0.3px;
}

.notifications__count {
  min-width: 24px;
  height: 24px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--text-dim);

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 6px;

  font-size: 9px;
  font-weight: 900;
}

/* =========================================================
   LIST
========================================================= */

.list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

/* =========================================================
   NOTIFICATION
========================================================= */

.notif {
  position: relative;

  width: 100%;

  display: flex;
  align-items: center;

  gap: 13px;

  min-height: 76px;

  padding: 11px 13px;

  overflow: hidden;

  color: var(--text);
  text-align: left;

  background: var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 11px;

  cursor: pointer;

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease,
      box-shadow 0.2s ease,
      opacity 0.2s ease;
}

.notif:hover:not(:disabled) {
  transform: translateY(-2px);

  border-color: var(--border-hover);

  background:
      linear-gradient(
          90deg,
          var(--bg-card),
          rgba(124, 58, 237, 0.025)
      );

  box-shadow:
      0 10px 25px rgba(0, 0, 0, 0.14);
}

.notif:disabled {
  cursor: wait;
}

.notif--opening {
  opacity: 0.6;
  transform: scale(0.995);
}

/* =========================================================
   NOTIFICATION GLOW
========================================================= */

.notif__glow {
  position: absolute;

  right: -70px;
  top: -80px;

  width: 170px;
  height: 170px;

  border-radius: 50%;

  background: var(--notif-color, #8b5cf6);

  opacity: 0;

  filter: blur(40px);

  pointer-events: none;

  transition: opacity 0.2s ease;
}

.notif:hover .notif__glow {
  opacity: 0.045;
}

/* =========================================================
   UNREAD
========================================================= */

.notif--unread {
  background:
      linear-gradient(
          90deg,
          rgba(124, 58, 237, 0.055),
          var(--bg-card) 55%
      );

  border-color: rgba(124, 58, 237, 0.2);
}

.notif--unread:hover {
  border-color: rgba(124, 58, 237, 0.38);
}

.notif__unread-line {
  position: absolute;

  left: 0;
  top: 9px;
  bottom: 9px;

  width: 2px;

  background:
      linear-gradient(
          to bottom,
          transparent,
          var(--accent),
          transparent
      );

  border-radius: 0 2px 2px 0;

  box-shadow:
      0 0 10px var(--accent);
}

/* =========================================================
   ICON
========================================================= */

.notif__icon-wrap {
  position: relative;

  flex-shrink: 0;
}

.notif__icon {
  width: 43px;
  height: 43px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--notif-color, #a78bfa);

  background:
      linear-gradient(
          145deg,
          color-mix(
              in srgb,
              var(--notif-color, #a78bfa) 11%,
              transparent
          ),
          rgba(255, 255, 255, 0.01)
      );

  border: 1px solid
  color-mix(
      in srgb,
      var(--notif-color, #a78bfa) 20%,
      var(--border)
  );

  border-radius: 10px;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025);
}

.notif__dot {
  position: absolute;

  right: -2px;
  top: -2px;

  width: 8px;
  height: 8px;

  background: var(--accent);

  border: 2px solid var(--bg-card);

  border-radius: 50%;

  box-shadow:
      0 0 8px var(--accent);
}

/* =========================================================
   TYPES
========================================================= */

.notif--purple {
  --notif-color: #a78bfa;
}

.notif--gold {
  --notif-color: #facc15;
}

.notif--success {
  --notif-color: #4ade80;
}

.notif--danger {
  --notif-color: #f87171;
}

/* =========================================================
   CONTENT
========================================================= */

.notif__content {
  position: relative;
  z-index: 1;

  flex: 1;
  min-width: 0;
}

.notif__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  margin-bottom: 4px;
}

.notif__type {
  color: var(--notif-color, #a78bfa);

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 1px;
  text-transform: uppercase;
}

.notif__time {
  flex-shrink: 0;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 600;
}

.notif__message {
  color: var(--text);

  font-size: 12px;
  font-weight: 600;

  line-height: 1.45;
}

.notif__bottom {
  display: flex;

  margin-top: 5px;
}

.notif__new {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  color: var(--accent-light, #a78bfa);

  font-size: 7px;
  font-weight: 1000;

  letter-spacing: 1px;
}

.notif__new span {
  width: 4px;
  height: 4px;

  border-radius: 50%;

  background: currentColor;

  box-shadow:
      0 0 6px currentColor;
}

.notif__read {
  color: var(--text-muted);

  font-size: 7px;
  font-weight: 700;

  letter-spacing: 0.7px;
  text-transform: uppercase;
}

/* =========================================================
   ARROW
========================================================= */

.notif__arrow {
  position: relative;
  z-index: 1;

  width: 29px;
  height: 29px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: var(--text-muted);

  background: rgba(255, 255, 255, 0.02);

  border: 1px solid var(--border);
  border-radius: 7px;

  transition:
      color 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.notif:hover .notif__arrow {
  color: var(--notif-color, var(--accent-light));

  border-color: color-mix(
      in srgb,
      var(--notif-color, #a78bfa) 25%,
      var(--border)
  );

  background: color-mix(
      in srgb,
      var(--notif-color, #a78bfa) 7%,
      transparent
  );

  transform: translateX(2px);
}

/* =========================================================
   STATE
========================================================= */

.state {
  min-height: 270px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  padding: 40px 20px;

  text-align: center;

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.055),
          transparent 50%
      ),
      var(--bg-card);

  border: 1px dashed var(--border);
  border-radius: 13px;
}

.state__icon {
  width: 58px;
  height: 58px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--accent-light, #a78bfa);

  background: rgba(124, 58, 237, 0.07);

  border: 1px solid rgba(124, 58, 237, 0.18);
  border-radius: 15px;

  box-shadow:
      0 0 30px rgba(124, 58, 237, 0.08);
}

.state__loader {
  display: flex;
  gap: 5px;

  margin-top: 15px;
}

.state__loader span {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: var(--accent-light, #a78bfa);

  animation: notification-loader 1s infinite ease-in-out;
}

.state__loader span:nth-child(2) {
  animation-delay: 0.12s;
}

.state__loader span:nth-child(3) {
  animation-delay: 0.24s;
}

.state strong {
  margin-top: 14px;

  color: var(--text);

  font-size: 14px;
  font-weight: 800;
}

.state p {
  max-width: 390px;

  margin: 7px 0 0;

  color: var(--text-dim);

  font-size: 11px;
  line-height: 1.55;
}

.state--empty .eyebrow {
  margin-top: 15px;
}

@keyframes notification-loader {
  0%,
  60%,
  100% {
    opacity: 0.3;
    transform: translateY(0);
  }

  30% {
    opacity: 1;
    transform: translateY(-4px);
  }
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {
  .notif-page {
    width: calc(100% - 24px);
    padding-top: 25px;
  }

  .notif-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .notif-head__title-row {
    align-items: flex-start;
    flex-direction: column;
    gap: 10px;
  }

  .mark-all {
    width: 100%;
    justify-content: center;
  }

  .summary {
    align-items: flex-start;
    flex-direction: column;
  }

  .summary__status {
    align-self: flex-start;
  }
}

@media (max-width: 500px) {
  .notif-page {
    width: calc(100% - 18px);
  }

  .notif {
    align-items: flex-start;

    padding: 11px;
  }

  .notif__icon {
    width: 39px;
    height: 39px;
  }

  .notif__top {
    align-items: flex-start;
    flex-direction: column;
    gap: 3px;
  }

  .notif__time {
    font-size: 8px;
  }

  .notif__message {
    font-size: 11px;
  }

  .notif__arrow {
    display: none;
  }
}
</style>