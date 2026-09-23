<script setup>
import { onMounted, ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { friendsApi } from '@/services/friends.js'

const loading = ref(true)
const tab = ref('friends')
const friends = ref([])
const incoming = ref([])
const outgoing = ref([])

async function load() {
  loading.value = true
  try {
    const data = await friendsApi.list()
    friends.value = data.friends || []
    incoming.value = data.incoming_requests || []
    outgoing.value = data.outgoing_requests || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function accept(userId) {
  await friendsApi.accept(userId)
  await load()
}

async function remove(userId) {
  if (!confirm('Удалить из друзей?')) return
  await friendsApi.remove(userId)
  await load()
}

onMounted(load)

const currentList = computed(() => {
  if (tab.value === 'friends') return friends.value
  if (tab.value === 'incoming') return incoming.value.map(i => i.user)
  return outgoing.value.map(o => o.user)
})
</script>

<template>
  <div class="friends-page">
    <h1>Друзья</h1>

    <div class="tabs">
      <button :class="{ active: tab === 'friends' }" @click="tab = 'friends'">
        Друзья ({{ friends.length }})
      </button>
      <button :class="{ active: tab === 'incoming' }" @click="tab = 'incoming'">
        Входящие ({{ incoming.length }})
      </button>
      <button :class="{ active: tab === 'outgoing' }" @click="tab = 'outgoing'">
        Исходящие ({{ outgoing.length }})
      </button>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!currentList.length" class="empty">
      {{ tab === 'friends' ? 'Пока нет друзей' : 'Пусто' }}
    </div>

    <div v-else class="friends-list">
      <div v-for="user in currentList" :key="user.id" class="friend-row">
        <RouterLink :to="`/players/${user.id}`" class="friend-main">
          <div class="avatar">{{ (user.username || 'И').charAt(0).toUpperCase() }}</div>
          <div>
            <div class="name">{{ user.username }}</div>
            <div class="tier">Тир: {{ user.tier || '—' }}</div>
          </div>
        </RouterLink>

        <div class="actions">
          <button
              v-if="tab === 'incoming'"
              class="btn-accept"
              @click="accept(user.id)"
          >
            Принять
          </button>

          <button
              v-if="tab === 'friends' || tab === 'outgoing'"
              class="btn-remove"
              @click="remove(user.id)"
          >
            {{ tab === 'friends' ? 'Удалить' : 'Отменить' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.friends-page {
  width: min(900px, calc(100% - 40px));
  margin: 40px auto;
}

h1 {
  margin: 0 0 24px;
  font-size: 28px;
  font-weight: 800;
}

.tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
}

.tabs button {
  padding: 10px 16px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s;
}

.tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}

.friends-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.friend-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.friend-main {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.avatar {
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
}

.name {
  font-weight: 700;
  font-size: 14px;
}

.tier {
  color: var(--text-dim);
  font-size: 12px;
}

.actions {
  display: flex;
  gap: 8px;
}

.btn-accept,
.btn-remove {
  min-height: 34px;
  padding: 0 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid;
  transition: all 0.2s;
}

.btn-accept {
  color: #fff;
  background: #22c55e;
  border-color: #22c55e;
}

.btn-accept:hover {
  background: #16a34a;
}

.btn-remove {
  color: var(--text-dim);
  background: transparent;
  border-color: var(--border);
}

.btn-remove:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}
</style>