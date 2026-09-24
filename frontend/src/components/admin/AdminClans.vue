<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/admin.js'
import AdminClanActions from './AdminClanActions.vue'

const clans = ref([])
const loading = ref(true)
const search = ref('')
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
    if (bannedFilter.value) params.banned = '1'

    const data = await adminApi.clans(params)
    clans.value = data.data
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

watch(bannedFilter, () => {
  page.value = 1
  load()
})

watch(page, load)

function onUpdated() {
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="filters">
      <input
          v-model="search"
          type="text"
          placeholder="Поиск по названию или тегу..."
          class="search"
      />

      <label class="toggle">
        <input v-model="bannedFilter" type="checkbox" />
        <span>Только забаненные</span>
      </label>
    </div>

    <div class="stats">
      Найдено: <b>{{ total }}</b>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!clans.length" class="empty">Не найдено</div>

    <div v-else class="clans-list">
      <div
          v-for="clan in clans"
          :key="clan.id"
          class="clan-row"
          :class="{ 'clan-row--banned': clan.is_banned }"
      >
        <RouterLink
            :to="`/clans/${clan.id}`"
            class="clan-main"
        >
          <div
              class="clan-avatar"
              :style="{ background: clan.banner_color }"
          >
            <img
                v-if="clan.avatar_url"
                :src="clan.avatar_url"
                :alt="clan.name"
                class="avatar-img"
            />
            <template v-else>
              {{ (clan.tag || 'C').charAt(0) }}
            </template>
          </div>

          <div class="clan-info">
            <div class="clan-name">
              <span class="tag">[{{ clan.tag }}]</span>
              {{ clan.name }}
              <span v-if="clan.is_banned" class="banned-badge">
                                ЗАБАНЕН
                            </span>
            </div>
            <div class="clan-meta">
              {{ clan.members_count }} участников
              <span class="sep">·</span>
              Лидер: {{ clan.leader?.username ?? '—' }}
            </div>
          </div>

          <div class="clan-stats">
            <div class="stat">
              <span class="stat-value power">{{ clan.power }}</span>
              <span class="stat-label">сила</span>
            </div>
            <div class="stat">
              <span class="stat-value win">{{ clan.wins }}</span>
              <span class="stat-label">побед</span>
            </div>
            <div class="stat">
              <span class="stat-value loss">{{ clan.losses }}</span>
              <span class="stat-label">поражений</span>
            </div>
          </div>
        </RouterLink>

        <AdminClanActions :clan="clan" @updated="onUpdated" />
      </div>
    </div>

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

.clans-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.clan-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.clan-row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.clan-row--banned {
  border-color: rgba(239, 68, 68, 0.25);
  background: linear-gradient(90deg, rgba(239, 68, 68, 0.04), var(--bg-card) 40%);
  opacity: 0.85;
}

.clan-main {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 0;
}

.clan-avatar {
  position: relative;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  color: #fff;
  font-size: 18px;
  font-weight: 900;
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

.clan-info {
  flex: 1;
  min-width: 0;
}

.clan-name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tag {
  color: var(--accent-light);
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

.clan-meta {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-meta .sep {
  margin: 0 6px;
  opacity: 0.4;
}

.clan-stats {
  display: flex;
  gap: 20px;
  padding-left: 16px;
  border-left: 1px solid var(--border);
  flex-shrink: 0;
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 40px;
}

.stat-value {
  font-size: 15px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.5px;
}

.stat-value.power { color: #a78bfa; }
.stat-value.win { color: #4ade80; }
.stat-value.loss { color: #f87171; }

.stat-label {
  font-size: 9px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 800;
  margin-top: 2px;
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
  .clan-stats {
    display: none;
  }
}
</style>