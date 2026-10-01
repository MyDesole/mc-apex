<script setup>
import { onMounted, ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { friendsApi } from '@/services/friends.js'
import GiftCoinsButton from '@/components/GiftCoinsButton.vue'
import UserName from "@/components/UserName.vue";
import { userLink } from '@/utils/links.js'

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
        <RouterLink :to="userLink(user)" class="friend-main">
          <div class="avatar" :class="`tier-bg-${user.tier}`">
            <img
                v-if="user.avatar_url"
                :src="user.avatar_url"
                :alt="user.username"
                class="avatar-img"
            />
            <template v-else>
              {{ (user.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </div>
          <div class="friend-info">
            <div class="name">
              <UserName :user="user" />
            </div>
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

          <GiftCoinsButton v-if="tab === 'friends'" :user="user" />

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
  min-width: 0;
  text-decoration: none;
  color: inherit;
}

/* === AVATAR === */

.avatar {
  position: relative;
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
  font-size: 16px;
  overflow: hidden;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
}

/* фоны по тиру, если нет аватарки */
.tier-bg-S { background: linear-gradient(135deg, #facc15, #d97706); }
.tier-bg-A { background: linear-gradient(135deg, #f97316, #c2410c); }
.tier-bg-B { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.tier-bg-C { background: linear-gradient(135deg, #06b6d4, #0e7490); }
.tier-bg-D { background: linear-gradient(135deg, #22c55e, #15803d); }
.tier-bg-E { background: linear-gradient(135deg, #6b7280, #374151); }

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

/* === INFO === */

.friend-info {
  min-width: 0;
  flex: 1;
}

.name {
  font-weight: 700;
  font-size: 14px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tier {
  color: var(--text-dim);
  font-size: 12px;
}

/* === ACTIONS === */

.actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
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

/* === MOBILE === */

@media (max-width: 640px) {
  .friends-page {
    width: calc(100% - 24px);
    margin: 20px auto;
  }

  h1 {
    font-size: 22px;
    margin-bottom: 18px;
  }

  .friend-row {
    padding: 10px 12px;
    gap: 10px;
  }

  .avatar {
    width: 40px;
    height: 40px;
    font-size: 15px;
    border-radius: 9px;
  }

  .btn-accept,
  .btn-remove {
    min-height: 32px;
    padding: 0 12px;
    font-size: 12.5px;
  }
}
</style>