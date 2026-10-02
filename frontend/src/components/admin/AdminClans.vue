<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import AdminClanActions from '@/components/admin/AdminClanActions.vue'
import { clanLink } from '@/utils/links.js'

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
            :to="clanLink(clan)"
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
@import "@/components/admin/AdminClans.css";
</style>
