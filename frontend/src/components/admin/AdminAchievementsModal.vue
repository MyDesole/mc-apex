<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/admin.js'
import { achievementsApi } from '@/services/achievements.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const loading = ref(true)
const achievements = ref([])
const userAchievements = ref([])
const processing = ref(null)

async function load() {
  loading.value = true
  try {
    const [all, mine] = await Promise.all([
      adminApi.user(props.user.id), // чтобы взять все ачивки
      achievementsApi.list(),
    ])

    // все ачивки
    const allList = mine.achievements

    // ачивки юзера
    const userList = all.achievements ?? []

    achievements.value = allList
    userAchievements.value = userList.map(a => a.id)
  } finally {
    loading.value = false
  }
}

function has(id) {
  return userAchievements.value.includes(id)
}

async function toggle(a) {
  processing.value = a.id
  try {
    if (has(a.id)) {
      await adminApi.revokeAchievement(props.user.id, a.id)
      userAchievements.value = userAchievements.value.filter(id => id !== a.id)
    } else {
      await adminApi.grantAchievement(props.user.id, a.id)
      userAchievements.value.push(a.id)
    }
    emit('updated')
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

onMounted(load)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Ачивки {{ user.username }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="loading" class="empty">Загрузка...</div>

        <div v-else class="grid">
          <button
              v-for="a in achievements"
              :key="a.id"
              class="ach"
              :class="{
                            'ach--owned': has(a.id),
                            'ach--processing': processing === a.id,
                        }"
              :style="{ '--color': rarityColors[a.rarity] }"
              :disabled="processing === a.id"
              @click="toggle(a)"
          >
            <span class="ach__icon">{{ a.icon }}</span>
            <span class="ach__name">{{ a.name }}</span>
            <span class="ach__mark">
                            {{ has(a.id) ? '✓' : '+' }}
                        </span>
          </button>
        </div>
      </div>

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
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 640px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
}

.modal-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

.body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px;
}

.ach {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  text-align: left;
}

.ach:hover:not(:disabled) {
  border-color: var(--color);
  background: rgba(255, 255, 255, 0.03);
}

.ach--owned {
  border-color: var(--color);
  background: linear-gradient(90deg, color-mix(in srgb, var(--color) 10%, transparent), transparent);
}

.ach--processing {
  opacity: 0.5;
}

.ach__icon {
  font-size: 20px;
  flex-shrink: 0;
}

.ach__name {
  flex: 1;
  color: var(--text);
  font-size: 12px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ach__mark {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 900;
  color: var(--text-dim);
}

.ach--owned .ach__mark {
  background: var(--color);
  border-color: var(--color);
  color: #000;
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-save {
  min-height: 40px;
  padding: 0 24px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  color: #fff;
  background: var(--accent);
}
</style>