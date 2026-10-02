<script setup>
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref, watch } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AdminAchievementForm from '@/components/admin/AdminAchievementForm.vue'

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
  if (!await confirmDialog(`Удалить ачивку «${a.name}»?`)) return
  processing.value = a.id
  try {
    await adminApi.destroyAchievement(a.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Ошибка')
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
@import "@/components/admin/AdminAchievements.css";
</style>
