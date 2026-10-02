<script setup>
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({
  user: { type: Object, required: true },
})
const emit = defineEmits(['close', 'updated'])

const loading = ref(true)
const error = ref('')

const allAchievements = ref([])
const ownedIds = ref(new Set())
const processing = ref(null)

const totalCount = computed(() => allAchievements.value.length)
const ownedCount = computed(() => ownedIds.value.size)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [catalogRes, userRes] = await Promise.all([
      adminApi.achievements(),       // GET /api/admin/achievements
      adminApi.user(props.user.id),  // GET /api/admin/users/{id}
    ])

    // Laravel paginate → { data: [...] } либо голый массив
    const list =
        (Array.isArray(catalogRes?.data) && catalogRes.data) ||
        (Array.isArray(catalogRes) && catalogRes) ||
        catalogRes?.achievements ||
        []

    allAchievements.value = list

    const userList =
        userRes?.user?.achievements ??
        userRes?.achievements ??
        []

    ownedIds.value = new Set(userList.map((a) => a.id))
  } catch (e) {
    error.value = e?.message || 'Не удалось загрузить ачивки'
    console.error(e)
  } finally {
    loading.value = false
  }
}

function has(id) {
  return ownedIds.value.has(id)
}

async function toggle(a) {
  if (processing.value) return
  processing.value = a.id
  error.value = ''

  const isOwned = has(a.id)

  try {
    if (isOwned) {
      await adminApi.revokeAchievement(props.user.id, a.id)
      ownedIds.value.delete(a.id)
    } else {
      await adminApi.grantAchievement(props.user.id, a.id)
      ownedIds.value.add(a.id)
    }

    // реактивность Set
    ownedIds.value = new Set(ownedIds.value)
    emit('updated')
  } catch (e) {
    error.value = e?.message || 'Не удалось изменить ачивку'
  } finally {
    processing.value = null
  }
}

const rarityColors = {
  common: '#7c3aed',
  rare: '#06b6d4',
  epic: '#f97316',
  legendary: '#facc15',
}

function rarityColor(a) {
  return a.color || rarityColors[a.rarity] || '#7c3aed'
}

onMounted(load)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <!-- HEAD -->
      <header class="modal-head">
        <div class="modal-head__text">
          <h2>Ачивки игрока</h2>
          <p class="modal-head__sub">
            {{ user.username }}
            <span class="dot">·</span>
            есть
            <strong>{{ ownedCount }}</strong>
            из
            <strong>{{ totalCount }}</strong>
          </p>
        </div>
        <button class="close" @click="$emit('close')" aria-label="Закрыть">✕</button>
      </header>

      <!-- LEGEND -->
      <div class="legend">
        <span class="legend__item">
          <span class="legend__dot legend__dot--owned" />
          Есть у игрока
        </span>
        <span class="legend__item">
          <span class="legend__dot legend__dot--missing" />
          Не получена
        </span>
      </div>

      <!-- BODY -->
      <div class="body">
        <div v-if="loading" class="state">
          <div class="spinner" />
          <span>Загрузка...</span>
        </div>

        <div v-else-if="error" class="error">{{ error }}</div>

        <div v-else-if="!allAchievements.length" class="state">
          Каталог ачивок пуст
        </div>

        <div v-else class="grid">
          <button
              v-for="a in allAchievements"
              :key="a.id"
              type="button"
              class="ach"
              :class="{
                'ach--owned': has(a.id),
                'ach--processing': processing === a.id,
              }"
              :style="{ '--color': rarityColor(a) }"
              :disabled="processing === a.id"
              :title="a.description || a.name"
              @click="toggle(a)"
          >
            <span class="ach__icon">{{ a.icon }}</span>

            <span class="ach__text">
              <span class="ach__name">{{ a.name }}</span>
              <span class="ach__status">
                {{ has(a.id) ? 'Есть у игрока' : 'Не получена' }}
              </span>
            </span>

            <span class="ach__mark" aria-hidden="true">
              <svg v-if="has(a.id)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12l5 5L20 7" />
              </svg>
              <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14" />
              </svg>
            </span>
          </button>
        </div>
      </div>

      <!-- FOOT -->
      <footer class="modal-foot">
        <button class="btn-save" @click="$emit('close')">Готово</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminAchievementsModal.css";
</style>
