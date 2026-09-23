<script setup>
import { ref } from 'vue'
import { clansApi } from '@/services/clans.js'

const props = defineProps({
  clan: Object,
  incoming: Array,
  outgoing: Array,
  isMember: Boolean,
  isLeader: Boolean,
})

const emit = defineEmits(['refresh'])

const challenging = ref(false)
const form = ref({ scheduled_at: '', notes: '' })

async function challenge() {
  await clansApi.challenge(props.clan.id, {
    ...form.value,
    scheduled_at: form.value.scheduled_at || null,
  })
  challenging.value = false
  form.value = { scheduled_at: '', notes: '' }
  emit('refresh')
}

async function accept(war) {
  await clansApi.acceptWar(war.id)
  emit('refresh')
}

async function decline(war) {
  await clansApi.declineWar(war.id)
  emit('refresh')
}

async function complete(war) {
  const c = prompt('Счёт нашего клана?')
  const o = prompt('Счёт противника?')
  if (!c || !o) return

  await clansApi.completeWar(war.id, {
    challenger_score: Number(c),
    opponent_score: Number(o),
  })
  emit('refresh')
}

const statusLabels = {
  pending: 'Ожидает',
  accepted: 'Принята',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}
</script>

<template>
  <div class="wars">
    <div class="actions">
      <button v-if="isMember" class="btn-challenge" @click="challenging = !challenging">
        + Вызвать клан
      </button>
    </div>

    <div v-if="challenging" class="form">
      <input v-model="form.scheduled_at" type="datetime-local" />
      <textarea v-model="form.notes" rows="2" placeholder="Условия..." />
      <div class="form-actions">
        <button class="btn-cancel" @click="challenging = false">Отмена</button>
        <button class="btn-save" @click="challenge">Отправить вызов</button>
      </div>
    </div>

    <!-- Входящие -->
    <section v-if="incoming?.length">
      <h3>Входящие вызовы</h3>
      <div class="war-list">
        <div v-for="w in incoming" :key="w.id" class="war-row">
          <div class="war-info">
            <span class="tag">[{{ w.challenger.tag }}]</span>
            <span>{{ w.challenger.name }}</span>
          </div>
          <div v-if="w.scheduled_at" class="war-date">
            {{ new Date(w.scheduled_at).toLocaleString('ru-RU') }}
          </div>
          <div class="war-actions" v-if="isLeader">
            <button class="btn-accept" @click="accept(w)">Принять</button>
            <button class="btn-decline" @click="decline(w)">Отклонить</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Исходящие -->
    <section v-if="outgoing?.length">
      <h3>Исходящие вызовы</h3>
      <div class="war-list">
        <div v-for="w in outgoing" :key="w.id" class="war-row">
          <div class="war-info">
            <span class="tag">[{{ w.opponent.tag }}]</span>
            <span>{{ w.opponent.name }}</span>
          </div>
          <div class="status" :class="`status-${w.status}`">
            {{ statusLabels[w.status] }}
          </div>
          <div v-if="w.status === 'accepted' && isLeader" class="war-actions">
            <button class="btn-accept" @click="complete(w)">Внести результат</button>
          </div>
        </div>
      </div>
    </section>

    <div v-if="!incoming?.length && !outgoing?.length" class="empty">
      Войн пока нет
    </div>
  </div>
</template>

<style scoped>
.wars { display: flex; flex-direction: column; gap: 20px; }

.actions { display: flex; justify-content: flex-end; }

.btn-challenge {
  padding: 9px 16px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.form input, .form textarea {
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 8px;
  font: inherit;
}

.form-actions { display: flex; gap: 8px; justify-content: flex-end; }

.btn-cancel, .btn-save, .btn-accept, .btn-decline {
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 12px;
  cursor: pointer;
  border: 0;
}

.btn-cancel { color: var(--text-dim); background: transparent; border: 1px solid var(--border); }
.btn-save { color: #fff; background: var(--accent); }
.btn-accept { color: #fff; background: #22c55e; }
.btn-decline { color: var(--text-dim); background: transparent; border: 1px solid var(--border); }

h3 {
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 800;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.war-list { display: flex; flex-direction: column; gap: 8px; }

.war-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.war-info { flex: 1; font-weight: 700; font-size: 14px; }
.tag { color: var(--accent-light); margin-right: 4px; }
.war-date { color: var(--text-dim); font-size: 12px; }

.war-actions { display: flex; gap: 8px; }

.status {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.status-pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.status-accepted { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.status-completed { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.status-declined { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.empty { padding: 40px; text-align: center; color: var(--text-dim); }
</style>