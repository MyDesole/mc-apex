<script setup>
import { onMounted, ref } from 'vue'
import { tierTestsApi } from '@/services/tierTests.js'
import TierTestForm from './TierTestForm.vue'

const loading = ref(true)
const myTests = ref([])
const asTester = ref([])

async function load() {
  loading.value = true
  try {
    const data = await tierTestsApi.list()
    myTests.value = data.my_tests || []
    asTester.value = data.as_tester || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const statusLabels = {
  pending: 'Ожидает',
  in_progress: 'В процессе',
  completed: 'Завершён',
  cancelled: 'Отменён',
}

onMounted(load)
</script>

<template>
  <div class="tier-tests">
    <div class="head">
      <h2>Тир-тесты</h2>
      <TierTestForm @created="load" />
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>

    <template v-else>
      <div v-if="!myTests.length" class="empty">
        Ты ещё не записывался на тир-тесты
      </div>

      <div v-else class="list">
        <div v-for="t in myTests" :key="t.id" class="test-row">
          <div class="test-info">
            <div class="test-mode">
              {{ t.mode === 'pvp' ? 'PvP' : 'BedWars' }}
            </div>
            <div class="test-date">
              {{ new Date(t.created_at).toLocaleDateString('ru-RU') }}
            </div>
          </div>

          <div class="test-status" :class="`status-${t.status}`">
            {{ statusLabels[t.status] }}
          </div>

          <div v-if="t.result_tier" class="test-result">
            <span class="tier">{{ t.result_tier }}</span>
            <span class="score">{{ t.result_score }}%</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tier-tests {
  margin-top: 32px;
  padding: 24px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.test-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.test-mode {
  font-weight: 700;
  font-size: 14px;
}

.test-date {
  color: var(--text-dim);
  font-size: 12px;
  margin-top: 2px;
}

.test-status {
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}

.status-pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.status-in_progress { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.status-completed { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.status-cancelled { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.test-result {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tier {
  font-size: 18px;
  font-weight: 900;
  color: var(--accent-light);
}

.score {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}

.empty {
  padding: 24px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
}
</style>