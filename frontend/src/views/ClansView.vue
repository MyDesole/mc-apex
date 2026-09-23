<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'

const clans = ref([])
const loading = ref(true)
const search = ref('')

let timer = null

async function load() {
  loading.value = true
  try {
    const data = await clansApi.list(search.value ? { search: search.value } : {})
    clans.value = data.data
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})

onMounted(load)
</script>

<template>
  <div class="clans-page">
    <div class="head">
      <h1>Кланы</h1>
      <RouterLink to="/clans/create" class="btn-create">+ Создать клан</RouterLink>
    </div>

    <input
        v-model="search"
        class="search"
        placeholder="Поиск по названию или тегу..."
    />

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!clans.length" class="empty">Кланов не найдено</div>

    <div v-else class="list">
      <RouterLink
          v-for="clan in clans"
          :key="clan.id"
          :to="`/clans/${clan.id}`"
          class="clan-card"
      >
        <div class="avatar" :style="{ background: clan.banner_color }">
          {{ clan.tag?.charAt(0) || 'C' }}
        </div>

        <div class="info">
          <div class="name">
            <span class="tag">[{{ clan.tag }}]</span>
            {{ clan.name }}
          </div>
          <div class="meta">
            {{ clan.members_count }} участников · Лидер: {{ clan.leader?.username }}
          </div>
        </div>

        <div class="power">
          <div class="value">{{ clan.power }}</div>
          <div class="label">сила</div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.clans-page {
  width: min(900px, calc(100% - 40px));
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

.btn-create {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.search {
  width: 100%;
  min-height: 44px;
  padding: 0 16px;
  margin-bottom: 20px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
}

.search:focus { border-color: var(--accent); }

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.clan-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.clan-card:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
  transform: translateX(4px);
}

.avatar {
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  color: #fff;
  font-size: 22px;
  font-weight: 900;
  flex-shrink: 0;
}

.info { flex: 1; }

.name {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 4px;
}

.tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.meta {
  color: var(--text-dim);
  font-size: 12px;
}

.power {
  text-align: right;
}

.power .value {
  font-size: 22px;
  font-weight: 900;
  color: var(--accent-light);
}

.power .label {
  font-size: 10px;
  color: var(--text-dim);
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.empty {
  padding: 60px;
  text-align: center;
  color: var(--text-dim);
}
</style>