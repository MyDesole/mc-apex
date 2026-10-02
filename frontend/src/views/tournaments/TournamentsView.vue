<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { tournamentsApi } from '@/services/tournaments/tournaments.js'

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
@import "@/views/tournaments/TournamentsView.css";
</style>
