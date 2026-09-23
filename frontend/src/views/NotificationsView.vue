<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {notificationsApi} from "@/services/notification.js";

const router = useRouter()
const loading = ref(true)
const notifications = ref([])
const unread = ref(0)

async function load() {
  loading.value = true
  try {
    const data = await notificationsApi.list()
    notifications.value = data.notifications.data || []
    unread.value = data.unread_count || 0
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function open(n) {
  if (!n.read_at) {
    await notificationsApi.markAsRead(n.id)
  }

  const d = n.data

  if (d.type === 'friend_request' || d.type === 'friend_accepted') {
    router.push('/friends')
  } else if (d.type === 'tier_test_request' || d.type === 'tier_test_completed') {
    router.push('/profile')
  } else if (d.type === 'clan_application') {
    // лидер → сразу на заявки клана
    router.push(`/clans/${d.clan_id}`)
  } else if (d.type === 'clan_application_accepted' || d.type === 'clan_application_declined') {
    router.push(`/clans/${d.clan_id}`)
  }

  await load()
}

async function markAll() {
  await notificationsApi.markAllAsRead()
  await load()
}

onMounted(load)
</script>

<template>
  <div class="notif-page">
    <div class="head">
      <h1>Уведомления</h1>
      <button v-if="unread" class="mark-all" @click="markAll">
        Прочитать все
      </button>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!notifications.length" class="empty">Уведомлений нет</div>

    <div v-else class="list">
      <button
          v-for="n in notifications"
          :key="n.id"
          class="notif"
          :class="{ unread: !n.read_at }"
          @click="open(n)"
      >
        <div class="dot" v-if="!n.read_at" />
        <div class="content">
          <div class="msg">{{ n.data.message }}</div>
          <div class="time">
            {{ new Date(n.created_at).toLocaleString('ru-RU') }}
          </div>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.notif-page {
  width: min(800px, calc(100% - 40px));
  margin: 40px auto;
}

.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
}

.mark-all {
  padding: 8px 14px;
  color: var(--accent-light);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}

.mark-all:hover {
  border-color: var(--accent);
}

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notif {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  text-align: left;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
}

.notif.unread {
  border-color: rgba(124, 58, 237, 0.4);
  background: rgba(124, 58, 237, 0.04);
}

.notif:hover {
  border-color: var(--border-hover);
  transform: translateY(-1px);
}

.dot {
  width: 8px;
  height: 8px;
  margin-top: 7px;
  background: var(--accent);
  border-radius: 50%;
  flex-shrink: 0;
  box-shadow: 0 0 8px var(--accent-glow);
}

.msg {
  color: var(--text);
  font-size: 14px;
  font-weight: 500;
}

.time {
  margin-top: 4px;
  color: var(--text-dim);
  font-size: 12px;
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}
</style>