<script setup>
import { onMounted, ref, watch } from 'vue'
import { adminApi } from '@/services/admin.js'
import AdminAchievementForm from './AdminAchievementForm.vue'

const achievements = ref([])
const loading = ref(true)
const search = ref('')
const rarityFilter = ref('')
const filter = ref('all')        // all | system | custom

const showForm = ref(false)
const editing = ref(null)
const processing = ref(null)

let timer = null

async function load() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.search = search.value
    if (rarityFilter.value) params.rarity = rarityFilter.value
    if (filter.value === 'system') params.system = '1'
    if (filter.value === 'custom') params.custom = '1'

    const data = await adminApi.achievements(params)
    achievements.value = data.achievements
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})

watch([rarityFilter, filter], load)

function openCreate() {
  editing.value = null
  showForm.value = true
}

function openEdit(a) {
  editing.value = a
  showForm.value = true
}

async function destroy(a) {
  if (!confirm(`Удалить ачивку «${a.name}»?`)) return
  processing.value = a.id
  try {
    await adminApi.destroyAchievement(a.id)
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

async function toggleActive(a) {
  processing.value = a.id
  try {
    await adminApi.updateAchievement(a.id, { is_active: !a.is_active })
    await load()
  } finally {
    processing.value = null
  }
}

function onUpdated() {
  showForm.value = false
  load()
}

const rarityLabels = {
  common: 'Обычная',
  rare: 'Редкая',
  epic: 'Эпическая',
  legendary: 'Легендарная',
}

const rarityColors = {
  common: '#7c3aed',
  rare: '#06b6d4',
  epic: '#f97316',
  legendary: '#facc15',
}

onMounted(load)
</script>

<template>
  <div>
    <div class="head">
      <div class="filters">
        <input
            v-model="search"
            class="search"
            placeholder="Поиск по названию или коду..."
        />

        <select v-model="rarityFilter" class="select">
          <option value="">Все редкости</option>
          <option value="common">Обычные</option>
          <option value="rare">Редкие</option>
          <option value="epic">Эпические</option>
          <option value="legendary">Легендарные</option>
        </select>

        <select v-model="filter" class="select">
          <option value="all">Все</option>
          <option value="system">Системные</option>
          <option value="custom">Кастомные</option>
        </select>
      </div>

      <button class="btn-create" @click="openCreate">
        + Создать ачивку
      </button>
    </div>

    <div class="stats">
      Найдено: <b>{{ achievements.length }}</b>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!achievements.length" class="empty">Ачивок нет</div>

    <div v-else class="grid">
      <div
          v-for="a in achievements"
          :key="a.id"
          class="ach"
          :class="{
                    'ach--system': a.is_system,
                    'ach--inactive': !a.is_active,
                }"
          :style="{ '--color': a.color }"
      >
        <div class="ach__icon">{{ a.icon }}</div>

        <div class="ach__info">
          <div class="ach__head">
            <span class="ach__name">{{ a.name }}</span>
            <span
                class="ach__rarity"
                :style="{ color: rarityColors[a.rarity] }"
            >
                            {{ rarityLabels[a.rarity] }}
                        </span>
          </div>

          <div class="ach__desc">{{ a.description }}</div>

          <div class="ach__meta">
            <span class="ach__code">{{ a.code }}</span>
            <span class="dot">·</span>
            <span>+{{ a.points }}</span>
            <span class="dot">·</span>
            <span>выдано: {{ a.granted_count ?? 0 }}</span>
          </div>
        </div>

        <div class="ach__badges">
                    <span v-if="a.is_system" class="badge badge--system">
                        SYSTEM
                    </span>
          <span v-if="!a.is_active" class="badge badge--inactive">
                        OFF
                    </span>
        </div>

        <div class="ach__actions">
          <button
              class="btn-action"
              :disabled="processing === a.id"
              @click="toggleActive(a)"
          >
            {{ a.is_active ? '🚫' : '✅' }}
          </button>
          <button
              class="btn-action"
              @click="openEdit(a)"
          >
            ⚙️
          </button>
          <button
              v-if="!a.is_system"
              class="btn-action danger"
              :disabled="processing === a.id"
              @click="destroy(a)"
          >
            🗑
          </button>
        </div>
      </div>
    </div>

    <AdminAchievementForm
        v-if="showForm"
        :achievement="editing"
        @close="showForm = false"
        @updated="onUpdated"
    />
  </div>
</template>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.filters {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  flex: 1;
}

.search {
  flex: 1;
  min-width: 180px;
  min-height: 40px;
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
  min-height: 40px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
}

.btn-create {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
  white-space: nowrap;
}

.btn-create:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.stats {
  color: var(--text-dim);
  font-size: 13px;
  margin-bottom: 12px;
}

.stats b {
  color: var(--text);
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 10px;
}

.ach {
  position: relative;
  display: grid;
  grid-template-columns: 48px 1fr auto;
  gap: 12px;
  padding: 14px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
  overflow: hidden;
}

.ach::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 0% 50%, color-mix(in srgb, var(--color) 8%, transparent), transparent 60%);
  pointer-events: none;
}

.ach:hover {
  border-color: var(--border-hover);
  transform: translateY(-1px);
}

.ach--inactive {
  opacity: 0.5;
}

.ach--system {
  border-color: rgba(124, 58, 237, 0.25);
}

.ach__icon {
  position: relative;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--color);
  border-radius: 12px;
  font-size: 24px;
  flex-shrink: 0;
}

.ach__info {
  position: relative;
  min-width: 0;
}

.ach__head {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.ach__name {
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.ach__rarity {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.ach__desc {
  font-size: 12px;
  color: var(--text-dim);
  line-height: 1.4;
  margin-bottom: 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.ach__meta {
  display: flex;
  gap: 6px;
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
  flex-wrap: wrap;
}

.ach__code {
  font-family: monospace;
  color: var(--text-dim);
}

.dot {
  opacity: 0.5;
}

.ach__badges {
  position: absolute;
  top: 10px;
  right: 10px;
  display: flex;
  gap: 4px;
}

.badge {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.4px;
}

.badge--system {
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.15);
  border: 1px solid rgba(124, 58, 237, 0.3);
}

.badge--inactive {
  color: #f87171;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.ach__actions {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-self: center;
}

.btn-action {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-action:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--border-hover);
  background: rgba(255, 255, 255, 0.03);
}

.btn-action.danger:hover:not(:disabled) {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

.btn-action:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

@media (max-width: 600px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>