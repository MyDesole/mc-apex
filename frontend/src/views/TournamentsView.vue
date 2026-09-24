<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { tournamentsApi } from '@/services/tournaments.js'

const tournaments = ref([])
const loading = ref(true)
const search = ref('')
const typeFilter = ref('')
const statusFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

let timer = null

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (search.value) params.search = search.value
    if (typeFilter.value) params.type = typeFilter.value
    if (statusFilter.value) params.status = statusFilter.value

    const data = await tournamentsApi.list(params)
    tournaments.value = data.data
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

watch([typeFilter, statusFilter], () => { page.value = 1; load() })
watch(page, load)

const statusLabels = {
  draft: 'Черновик',
  registration: 'Регистрация',
  ongoing: 'Идёт',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

onMounted(load)
</script>

<template>
  <div class="tournaments-page">
    <header class="page-head">
      <div>
        <h1>Турниры</h1>
        <p class="subtitle">Найдено: {{ total }}</p>
      </div>
    </header>

    <div class="filters">
      <input v-model="search" class="search" placeholder="Поиск турнира..." />

      <select v-model="typeFilter" class="select">
        <option value="">Все типы</option>
        <option value="solo">1 vs 1</option>
        <option value="clan">Клан на клан</option>
      </select>

      <select v-model="statusFilter" class="select">
        <option value="">Все статусы</option>
        <option value="registration">Регистрация</option>
        <option value="ongoing">Идут</option>
        <option value="completed">Завершены</option>
      </select>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!tournaments.length" class="empty">Турниров нет</div>

    <div v-else class="grid">
      <RouterLink
          v-for="t in tournaments"
          :key="t.id"
          :to="`/tournaments/${t.id}`"
          class="card"
          :class="`card--${t.status}`"
      >
        <div class="card__banner" :style="t.banner_url ? { backgroundImage: `url(${t.banner_url})` } : {}">
                    <span v-if="!t.banner_url" class="card__banner-placeholder">
                        {{ t.name.charAt(0).toUpperCase() }}
                    </span>

          <div class="card__badges">
                        <span class="badge badge--type">
                            {{ t.type === 'solo' ? '1 vs 1' : 'Клан vs Клан' }}
                        </span>
            <span class="badge" :class="`badge--${t.status}`">
                            {{ statusLabels[t.status] }}
                        </span>
          </div>
        </div>

        <div class="card__body">
          <h3 class="card__title">{{ t.name }}</h3>

          <p v-if="t.description" class="card__desc">
            {{ t.description }}
          </p>

          <div class="card__stats">
            <div class="stat">
              <span class="stat__value">{{ t.participants_count }}</span>
              <span class="stat__label">участников</span>
            </div>
            <div v-if="Number(t.prize_pool) > 0" class="stat">
                            <span class="stat__value accent">
                                {{ Number(t.prize_pool).toLocaleString('ru-RU') }} {{ t.prize_currency }}
                            </span>
              <span class="stat__label">призовой фонд</span>
            </div>
            <div v-if="t.min_tier || t.max_tier" class="stat">
                            <span class="stat__value">
                                {{ t.min_tier || 'E' }}–{{ t.max_tier || 'S' }}
                            </span>
              <span class="stat__label">тир</span>
            </div>
          </div>
        </div>
      </RouterLink>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="page <= 1" @click="page--">← Назад</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="page++">Вперёд →</button>
    </div>
  </div>
</template>

<style scoped>
.tournaments-page {
  width: min(1100px, calc(100% - 40px));
  margin: 40px auto;
}

.page-head {
  margin-bottom: 24px;
}

.page-head h1 {
  margin: 0 0 4px;
  font-size: 28px;
  font-weight: 800;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.search {
  flex: 1;
  min-width: 200px;
  min-height: 44px;
  padding: 0 16px;
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
  min-height: 44px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.25s;
}

.card:hover {
  border-color: var(--border-hover);
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.card__banner {
  position: relative;
  height: 140px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
}

.card__banner-placeholder {
  font-size: 48px;
  font-weight: 900;
  color: #fff;
  opacity: 0.35;
}

.card__badges {
  position: absolute;
  top: 10px;
  left: 10px;
  right: 10px;
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  backdrop-filter: blur(8px);
}

.badge--type {
  color: #fff;
  background: rgba(0, 0, 0, 0.5);
}

.badge--draft { color: var(--text-dim); background: rgba(0, 0, 0, 0.5); }
.badge--registration { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
.badge--ongoing { color: #fbbf24; background: rgba(251, 191, 36, 0.15); }
.badge--completed { color: #60a5fa; background: rgba(96, 165, 250, 0.15); }
.badge--cancelled { color: #f87171; background: rgba(239, 68, 68, 0.15); }

.card__body {
  padding: 16px 18px;
}

.card__title {
  margin: 0 0 8px;
  font-size: 17px;
  font-weight: 800;
  color: var(--text);
}

.card__desc {
  margin: 0 0 14px;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card__stats {
  display: flex;
  gap: 18px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.stat {
  display: flex;
  flex-direction: column;
}

.stat__value {
  font-size: 15px;
  font-weight: 900;
  color: var(--text);
}

.stat__value.accent {
  color: var(--accent-light);
}

.stat__label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  font-weight: 700;
}

.empty {
  padding: 60px;
  text-align: center;
  color: var(--text-dim);
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 32px;
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
</style>