<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const applications = ref([])
const loading = ref(true)
const processing = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await clansApi.applications(props.clan.id)
    applications.value = data.applications || []
  } finally {
    loading.value = false
  }
}

async function accept(app) {
  processing.value = app.id
  try {
    await clansApi.acceptApplication(props.clan.id, app.id)
    await load()
  } finally {
    processing.value = null
  }
}

async function decline(app) {
  processing.value = app.id
  try {
    await clansApi.declineApplication(props.clan.id, app.id)
    await load()
  } finally {
    processing.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!applications.length" class="empty">
      <div class="empty__icon">📋</div>
      <div>Новых заявок нет</div>
    </div>

    <div v-else class="list">
      <div v-for="app in applications" :key="app.id" class="app">
        <RouterLink :to="`/players/${app.user.id}`" class="app__main">
          <div class="avatar">
            <img v-if="app.user.avatar_url" :src="app.user.avatar_url" />
            <template v-else>{{ app.user.username?.charAt(0).toUpperCase() }}</template>
          </div>
          <div class="info">
            <div class="name">{{ app.user.username }}</div>
            <div class="meta">
              Тир: {{ app.user.tier }} · {{ app.user.tier_score }}%
            </div>
            <p v-if="app.message" class="message">"{{ app.message }}"</p>
          </div>
        </RouterLink>

        <div class="actions">
          <button class="btn-accept" :disabled="processing === app.id" @click="accept(app)">
            Принять
          </button>
          <button class="btn-decline" :disabled="processing === app.id" @click="decline(app)">
            Отклонить
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 12px; }
.list { display: flex; flex-direction: column; gap: 8px; }

.app {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.app__main { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; }

.avatar {
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
  font-size: 16px;
}

.avatar img { width: 100%; height: 100%; object-fit: cover; }

.info { flex: 1; min-width: 0; }
.name { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
.meta { font-size: 12px; color: var(--text-dim); }
.message {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--text-dim);
  font-style: italic;
  padding-left: 10px;
  border-left: 2px solid var(--accent);
}

.actions { display: flex; gap: 6px; flex-shrink: 0; }

.btn-accept, .btn-decline {
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-accept { color: #fff; background: #22c55e; }
.btn-accept:hover:not(:disabled) { background: #16a34a; }

.btn-decline {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}
.btn-decline:hover:not(:disabled) { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

.btn-accept:disabled, .btn-decline:disabled { opacity: 0.5; cursor: not-allowed; }

.empty {
  padding: 60px 20px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}

.empty__icon { font-size: 42px; opacity: 0.6; margin-bottom: 6px; }
</style>