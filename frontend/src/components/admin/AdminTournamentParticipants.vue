<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

const props = defineProps({
  tournament: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const loading = ref(true)
const participants = ref([])
const processing = ref(null)
const seedsDraft = ref({})

async function load() {
  loading.value = true
  try {
    const data = await adminApi.tournamentParticipants(props.tournament.id)
    participants.value = data.participants

    // инициализируем seeds
    seedsDraft.value = {}
    data.participants.forEach(p => {
      seedsDraft.value[p.id] = p.seed ?? ''
    })
  } finally {
    loading.value = false
  }
}

async function approve(p) {
  processing.value = p.id
  try {
    await adminApi.approveParticipant(props.tournament.id, p.id)
    await load()
  } finally {
    processing.value = null
  }
}

async function reject(p) {
  processing.value = p.id
  try {
    await adminApi.rejectParticipant(props.tournament.id, p.id)
    await load()
  } finally {
    processing.value = null
  }
}

async function saveSeeds() {
  const seeds = participants.value
      .filter(p => seedsDraft.value[p.id] !== '')
      .map(p => ({ id: p.id, seed: Number(seedsDraft.value[p.id]) }))

  await adminApi.setSeeds(props.tournament.id, seeds)
  await load()
}

const statusLabels = {
  pending: 'На рассмотрении',
  approved: 'Одобрен',
  rejected: 'Отклонён',
  withdrawn: 'Отозван',
}

function displayName(p) {
  if (p.user) return p.user.username
  if (p.clan) return `[${p.clan.tag}] ${p.clan.name}`
  return '—'
}

function displayAvatar(p) {
  if (p.user) return p.user.username.charAt(0).toUpperCase()
  if (p.clan) return p.clan.tag.charAt(0)
  return '?'
}

onMounted(load)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Участники · {{ tournament.name }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="loading" class="empty">Загрузка...</div>
        <div v-else-if="!participants.length" class="empty">
          Заявок ещё нет
        </div>

        <template v-else>
          <div class="hint">
            Seed — порядковый номер для расстановки в сетке. Меньше = сильнее.
          </div>

          <div class="list">
            <div
                v-for="p in participants"
                :key="p.id"
                class="row"
                :class="`row--${p.status}`"
            >
              <div class="avatar">
                {{ displayAvatar(p) }}
              </div>

              <div class="info">
                <div class="name">{{ displayName(p) }}</div>
                <div class="meta">
                                    <span class="status" :class="`status-${p.status}`">
                                        {{ statusLabels[p.status] }}
                                    </span>
                  <span v-if="p.user?.tier" class="tier">
                                        Тир: {{ p.user.tier }}
                                    </span>
                </div>
              </div>

              <div class="seed">
                <input
                    v-model="seedsDraft[p.id]"
                    type="number"
                    min="1"
                    placeholder="—"
                    class="seed-input"
                />
              </div>

              <div class="actions">
                <button
                    v-if="p.status === 'pending'"
                    class="btn-approve"
                    :disabled="processing === p.id"
                    @click="approve(p)"
                >
                  Принять
                </button>
                <button
                    v-if="p.status === 'pending'"
                    class="btn-reject"
                    :disabled="processing === p.id"
                    @click="reject(p)"
                >
                  Отклонить
                </button>

                <span v-else class="no-actions">—</span>
              </div>
            </div>
          </div>

          <div class="save-seeds">
            <button class="btn-save" @click="saveSeeds">
              Сохранить сиды
            </button>
          </div>
        </template>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Закрыть</button>
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
  max-width: 720px;
  max-height: 90vh;
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
  font-size: 16px;
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
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.hint {
  padding: 10px 12px;
  color: var(--text-dim);
  background: rgba(124, 58, 237, 0.05);
  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 8px;
  font-size: 12px;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.row {
  display: grid;
  grid-template-columns: 40px 1fr 70px auto;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.row--pending {
  border-color: rgba(251, 191, 36, 0.2);
}

.row--approved {
  border-color: rgba(34, 197, 94, 0.2);
}

.row--rejected {
  opacity: 0.6;
}

.avatar {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 9px;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
}

.info {
  min-width: 0;
}

.name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta {
  display: flex;
  gap: 10px;
  margin-top: 2px;
  font-size: 11px;
}

.status {
  font-weight: 700;
}

.status-pending { color: #fbbf24; }
.status-approved { color: #4ade80; }
.status-rejected { color: #f87171; }
.status-withdrawn { color: var(--text-muted); }

.tier {
  color: var(--text-muted);
}

.seed-input {
  width: 60px;
  padding: 6px 8px;
  color: var(--text);
  background: #0a0a0f;
  border: 1px solid var(--border);
  border-radius: 7px;
  text-align: center;
  font-weight: 800;
  outline: none;
}

.seed-input:focus {
  border-color: var(--accent);
}

.actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.btn-approve,
.btn-reject {
  padding: 6px 10px;
  border-radius: 7px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  white-space: nowrap;
}

.btn-approve {
  color: #fff;
  background: #22c55e;
}

.btn-approve:hover:not(:disabled) {
  background: #16a34a;
}

.btn-reject {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-reject:hover:not(:disabled) {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
}

.btn-approve:disabled,
.btn-reject:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.no-actions {
  color: var(--text-muted);
  font-size: 12px;
}

.save-seeds {
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  padding: 10px 20px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-save:hover {
  background: var(--accent-light);
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

.btn-cancel {
  min-height: 40px;
  padding: 0 22px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}
</style>