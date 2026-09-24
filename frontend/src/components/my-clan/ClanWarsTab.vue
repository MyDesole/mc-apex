<script setup>
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const incoming = ref([])
const outgoing = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const data = await clansApi.show(props.clan.id)
    incoming.value = data.incoming_wars || []
    outgoing.value = data.outgoing_wars || []
  } finally {
    loading.value = false
  }
}

const statusLabels = {
  pending: 'Ожидает',
  accepted: 'Принята',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <div v-if="loading" class="empty">Загрузка...</div>

    <template v-else>
      <section v-if="incoming.length" class="section">
        <h3>Входящие вызовы</h3>
        <div class="list">
          <div v-for="w in incoming" :key="w.id" class="war">
            <div class="war-info">
              <span class="tag">[{{ w.challenger?.tag }}]</span>
              {{ w.challenger?.name }}
            </div>
            <div class="status" :class="`status-${w.status}`">
              {{ statusLabels[w.status] }}
            </div>
          </div>
        </div>
      </section>

      <section v-if="outgoing.length" class="section">
        <h3>Исходящие вызовы</h3>
        <div class="list">
          <div v-for="w in outgoing" :key="w.id" class="war">
            <div class="war-info">
              <span class="tag">[{{ w.opponent?.tag }}]</span>
              {{ w.opponent?.name }}
            </div>
            <div class="status" :class="`status-${w.status}`">
              {{ statusLabels[w.status] }}
            </div>
          </div>
        </div>
      </section>

      <div v-if="!incoming.length && !outgoing.length" class="empty">
        Войн пока нет
      </div>
    </template>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 24px; }

.section { display: flex; flex-direction: column; gap: 12px; }

.section h3 {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.list { display: flex; flex-direction: column; gap: 6px; }

.war {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.war-info { font-weight: 700; font-size: 14px; }
.tag { color: var(--accent-light); margin-right: 4px; }

.status {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.status-pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.status-accepted { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.status-completed { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.status-declined { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}
</style>