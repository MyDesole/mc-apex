<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/admin.js'
import AdminUserActions from './AdminUserActions.vue'

const users = ref([])
const loading = ref(true)
const search = ref('')
const roleFilter = ref('')
const bannedFilter = ref(false)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

let timer = null

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (search.value) params.search = search.value
    if (roleFilter.value) params.role = roleFilter.value
    if (bannedFilter.value) params.banned = '1'

    const data = await adminApi.users(params)
    users.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; load() }, 300)
})

watch([roleFilter, bannedFilter], () => {
  page.value = 1
  load()
})

watch(page, load)

const roleLabels = {
  user: 'Пользователь',
  tester: 'Тестер',
  moderator: 'Модератор',
  admin: 'Администратор',
}

function onUpdated() {
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Фильтры -->
    <div class="filters">
      <input
          v-model="search"
          type="text"
          placeholder="Поиск по нику или email..."
          class="search"
      />

      <select v-model="roleFilter" class="select">
        <option value="">Все роли</option>
        <option value="user">Пользователи</option>
        <option value="tester">Тестеры</option>
        <option value="moderator">Модераторы</option>
        <option value="admin">Администраторы</option>
      </select>

      <label class="toggle">
        <input v-model="bannedFilter" type="checkbox" />
        <span>Только забаненные</span>
      </label>
    </div>

    <div class="stats">
      Найдено: <b>{{ total }}</b>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!users.length" class="empty">Не найдено</div>

    <!-- Список -->
    <div v-else class="users-list">
      <div
          v-for="user in users"
          :key="user.id"
          class="user-row"
          :class="{ 'user-row--banned': user.is_banned }"
      >
        <RouterLink
            :to="`/players/${user.id}`"
            class="user-main"
        >
          <div class="user-avatar">
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

          <div class="user-info">
            <div class="user-name">
              {{ user.username }}
              <span v-if="user.is_banned" class="banned-badge">
                                ЗАБАНЕН
                            </span>
            </div>
            <div class="user-email">{{ user.email }}</div>
          </div>

          <div class="user-meta">
                        <span class="role-pill" :class="`role-${user.role}`">
                            {{ roleLabels[user.role] }}
                        </span>
            <span class="tier-pill" :class="`tier-${user.tier}`">
                            {{ user.tier }}
                        </span>
            <span class="points-pill" :title="'Очки ачивок'">
                            🏆 {{ user.achievements_count ?? 0 }}
                        </span>
          </div>
        </RouterLink>

        <AdminUserActions :user="user" @updated="onUpdated" />
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="page <= 1" @click="page--">← Назад</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="page++">Вперёд →</button>
    </div>
  </div>
</template>

<style scoped>
.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.search {
  flex: 1;
  min-width: 200px;
  min-height: 42px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
}

.search:focus {
  border-color: var(--accent);
}

.select {
  min-height: 42px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
}

.toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 14px;
  min-height: 42px;
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}

.toggle input {
  accent-color: var(--danger);
  cursor: pointer;
}

.stats {
  margin-bottom: 12px;
  color: var(--text-dim);
  font-size: 13px;
}

.stats b {
  color: var(--text);
  font-weight: 900;
}

.users-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.user-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.user-row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.user-row--banned {
  border-color: rgba(239, 68, 68, 0.25);
  background: linear-gradient(90deg, rgba(239, 68, 68, 0.04), var(--bg-card) 40%);
  opacity: 0.85;
}

.user-main {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 0;
}

.user-avatar {
  position: relative;
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
}

.user-email {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.banned-badge {
  padding: 2px 6px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 4px;
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.5px;
}

.user-meta {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-shrink: 0;
}

.role-pill {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border: 1px solid;
}

.role-user {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.04);
  border-color: var(--border);
}

.role-tester {
  color: #06b6d4;
  background: rgba(6, 182, 212, 0.1);
  border-color: rgba(6, 182, 212, 0.3);
}

.role-moderator {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.1);
  border-color: rgba(96, 165, 250, 0.3);
}

.role-admin {
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border-color: rgba(250, 204, 21, 0.3);
}

.tier-pill {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 900;
}

.tier-S { color: #facc15; background: rgba(250, 204, 21, 0.1); }
.tier-A { color: #f97316; background: rgba(249, 115, 22, 0.1); }
.tier-B { color: #8b5cf6; background: rgba(139, 92, 246, 0.1); }
.tier-C { color: #06b6d4; background: rgba(6, 182, 212, 0.1); }
.tier-D { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.tier-E { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.points-pill {
  padding: 3px 10px;
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.25);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 24px;
}

.pagination button {
  min-height: 38px;
  padding: 0 16px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination span {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}

@media (max-width: 800px) {
  .user-meta {
    display: none;
  }
}
</style>