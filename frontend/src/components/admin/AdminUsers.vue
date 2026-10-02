<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import AdminUserActions from '@/components/admin/AdminUserActions.vue'
import { userLink } from '@/utils/links.js'

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
  media: 'Медийка',
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
        <option value="media">Медийки</option>
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
            :to="userLink(user)"
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
@import "@/components/admin/AdminUsers.css";
</style>
