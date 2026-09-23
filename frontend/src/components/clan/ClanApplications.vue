<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'

const props = defineProps({
  clan: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const loading = ref(true)
const applications = ref([])
const processing = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await clansApi.applications(props.clan.id)
    applications.value = data.applications || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function accept(app) {
  processing.value = app.id
  try {
    await clansApi.acceptApplication(props.clan.id, app.id)
    await load()
    emit('refresh')
  } finally {
    processing.value = null
  }
}

async function decline(app) {
  processing.value = app.id
  try {
    await clansApi.declineApplication(props.clan.id, app.id)
    await load()
    emit('refresh')
  } finally {
    processing.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="applications">
    <div v-if="loading" class="empty">Загрузка...</div>

    <div v-else-if="!applications.length" class="empty">
      Новых заявок нет
    </div>

    <div v-else class="apps-list">
      <div
          v-for="app in applications"
          :key="app.id"
          class="app-row"
      >
        <RouterLink
            :to="`/players/${app.user.id}`"
            class="app-user"
        >
          <div class="avatar">
            {{ (app.user.username || 'И').charAt(0).toUpperCase() }}
          </div>

          <div class="app-info">
            <div class="app-name">{{ app.user.username }}</div>
            <div class="app-meta">
              Тир: {{ app.user.tier || '—' }}
              <span class="sep">·</span>
              {{ app.user.tier_score }}%
            </div>
          </div>
        </RouterLink>

        <div v-if="app.message" class="app-message">
          {{ app.message }}
        </div>

        <div v-if="canManage" class="app-actions">
          <button
              class="btn-accept"
              :disabled="processing === app.id"
              @click="accept(app)"
          >
            Принять
          </button>
          <button
              class="btn-decline"
              :disabled="processing === app.id"
              @click="decline(app)"
          >
            Отклонить
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.applications { display: flex; flex-direction: column; gap: 8px; }

.apps-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.app-row {
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.app-row:hover {
  border-color: var(--border-hover);
}

.app-user {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 10px;
}

.avatar {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 11px;
  color: #fff;
  font-weight: 800;
  font-size: 17px;
}

.app-name {
  font-weight: 700;
  font-size: 15px;
  margin-bottom: 2px;
}

.app-meta {
  font-size: 12px;
  color: var(--text-dim);
}

.app-meta .sep { margin: 0 6px; opacity: 0.4; }

.app-message {
  padding: 10px 14px;
  margin-bottom: 12px;
  background: rgba(124, 58, 237, 0.05);
  border-left: 3px solid var(--accent);
  border-radius: 6px;
  color: var(--text-dim);
  font-size: 13px;
  font-style: italic;
}

.app-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.btn-accept,
.btn-decline {
  min-height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.2s;
}

.btn-accept {
  color: #fff;
  background: #22c55e;
}

.btn-accept:hover:not(:disabled) {
  background: #16a34a;
  transform: translateY(-1px);
}

.btn-decline {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-decline:hover:not(:disabled) {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

.btn-accept:disabled,
.btn-decline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
  font-size: 13px;
}
</style>