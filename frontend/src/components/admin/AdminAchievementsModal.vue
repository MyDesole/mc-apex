<script setup>
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

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
.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(6, 6, 10, 0.72);
  backdrop-filter: blur(8px);
}

.modal {
  width: 100%;
  max-width: 680px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card, #12121a);
  border: 1px solid var(--border, #23232e);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 24px 60px -20px rgba(0, 0, 0, 0.6);
}

/* HEAD */

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 22px 14px;
  border-bottom: 1px solid var(--border, #23232e);
}

.modal-head__text h2 {
  margin: 0 0 3px;
  font-size: 17px;
  font-weight: 800;
}

.modal-head__sub {
  margin: 0;
  color: var(--text-dim, #8a8a97);
  font-size: 12.5px;
}

.modal-head__sub strong {
  color: var(--text, #fff);
  font-weight: 800;
}

.dot {
  margin: 0 4px;
  color: var(--text-muted, #6c6c78);
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim, #8a8a97);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  flex-shrink: 0;
  transition: all 0.15s;
}

.close:hover {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text, #fff);
}

/* LEGEND */

.legend {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 22px;
  border-bottom: 1px solid var(--border, #23232e);
  background: rgba(255, 255, 255, 0.01);
  font-size: 11.5px;
  color: var(--text-dim, #8a8a97);
}

.legend__item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legend__dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend__dot--owned {
  background: #22c55e;
  box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
}

.legend__dot--missing {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid var(--border, #23232e);
}

/* BODY */

.body {
  flex: 1;
  overflow-y: auto;
  padding: 18px 22px;
}

.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 50px 20px;
  color: var(--text-dim, #8a8a97);
  font-size: 13px;
}

.spinner {
  width: 24px;
  height: 24px;
  border: 2.5px solid color-mix(in srgb, var(--accent, #7c3aed) 25%, transparent);
  border-top-color: var(--accent, #7c3aed);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error {
  padding: 11px 14px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.22);
  border-radius: 10px;
  font-size: 13px;
}

/* GRID */

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 8px;
}

.ach {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  background: #0d0d14;
  border: 1px solid var(--border, #23232e);
  border-radius: 10px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
  position: relative;
}

.ach:hover:not(:disabled) {
  border-color: var(--color);
  transform: translateY(-1px);
}

.ach--owned {
  border-color: color-mix(in srgb, var(--color) 55%, transparent);
  background:
      linear-gradient(90deg,
      color-mix(in srgb, var(--color) 12%, transparent),
      transparent 60%),
      #0d0d14;
}

.ach--processing {
  opacity: 0.5;
  cursor: wait;
}

.ach__icon {
  font-size: 22px;
  line-height: 1;
  flex-shrink: 0;
  filter: grayscale(1) opacity(0.5);
  transition: filter 0.15s;
}

.ach--owned .ach__icon {
  filter: none;
}

.ach__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ach__name {
  color: var(--text, #fff);
  font-size: 12.5px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ach__status {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: var(--text-muted, #6c6c78);
}

.ach--owned .ach__status {
  color: #4ade80;
}

.ach__mark {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--border, #23232e);
  border-radius: 6px;
  color: var(--text-muted, #6c6c78);
  transition: all 0.15s;
}

.ach--owned .ach__mark {
  background: #22c55e;
  border-color: #22c55e;
  color: #04140a;
}

/* FOOT */

.modal-foot {
  display: flex;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border, #23232e);
  background: linear-gradient(0deg, rgba(255, 255, 255, 0.02), transparent);
}

.btn-save {
  min-height: 40px;
  padding: 0 24px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  color: #fff;
  background: var(--accent, #7c3aed);
  box-shadow: 0 6px 20px -6px color-mix(in srgb, var(--accent, #7c3aed) 60%, transparent);
  transition: all 0.15s;
}

.btn-save:hover {
  transform: translateY(-1px);
}

/* MOBILE */

@media (max-width: 600px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .modal-head,
  .legend,
  .body,
  .modal-foot {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>