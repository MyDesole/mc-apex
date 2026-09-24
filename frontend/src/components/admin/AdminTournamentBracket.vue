<script setup>
import { onMounted, ref, computed } from 'vue'
import { adminApi } from '@/services/admin.js'

const props = defineProps({
  tournament: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const loading = ref(true)
const matches = ref([])
const generating = ref(false)
const error = ref('')
const editingMatch = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await adminApi.tournamentMatches(props.tournament.id)
    matches.value = data.matches
  } finally {
    loading.value = false
  }
}

async function generate() {
  if (!confirm('Сгенерировать сетку? Текущие матчи будут удалены.')) return

  generating.value = true
  error.value = ''

  try {
    await adminApi.generateBracket(props.tournament.id)
    await load()
  } catch (e) {
    error.value = e.message || 'Ошибка генерации'
  } finally {
    generating.value = false
  }
}

const rounds = computed(() => {
  const grouped = {}
  matches.value.forEach(m => {
    if (!grouped[m.round]) grouped[m.round] = []
    grouped[m.round].push(m)
  })
  Object.keys(grouped).forEach(r => {
    grouped[r].sort((a, b) => a.position - b.position)
  })
  return grouped
})

const roundNumbers = computed(() => Object.keys(rounds.value).map(Number).sort((a, b) => a - b))
const maxRound = computed(() => roundNumbers.value[roundNumbers.value.length - 1] ?? 0)

function roundName(round) {
  const left = maxRound.value - round
  if (left === 0) return 'Финал'
  if (left === 1) return 'Полуфинал'
  if (left === 2) return 'Четвертьфинал'
  return `Раунд ${round}`
}

function displayName(participant) {
  if (!participant) return '—'
  if (participant.user) return participant.user.username
  if (participant.clan) return `[${participant.clan.tag}] ${participant.clan.name}`
  return '—'
}

function openMatch(m) {
  editingMatch.value = {
    ...m,
    score1: m.score1 ?? '',
    score2: m.score2 ?? '',
  }
}

function closeMatch() {
  editingMatch.value = null
}

async function saveMatch() {
  const m = editingMatch.value
  if (!m) return

  const payload = {
    score1: m.score1 === '' ? null : Number(m.score1),
    score2: m.score2 === '' ? null : Number(m.score2),
  }

  // автоматически определяем победителя по счёту, если введены оба
  if (payload.score1 !== null && payload.score2 !== null) {
    if (payload.score1 > payload.score2) {
      payload.winner_id = m.participant1_id
    } else if (payload.score2 > payload.score1) {
      payload.winner_id = m.participant2_id
    }
  }

  // если вручную выбран победитель
  if (m.winner_id && !payload.winner_id) {
    payload.winner_id = m.winner_id
  }

  try {
    await adminApi.updateMatch(props.tournament.id, m.id, payload)
    closeMatch()
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  }
}

async function cancelMatch(m) {
  if (!confirm('Отменить матч?')) return
  await adminApi.updateMatch(props.tournament.id, m.id, { status: 'cancelled' })
  await load()
}

onMounted(load)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Сетка · {{ tournament.name }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <div v-if="loading" class="empty">Загрузка...</div>

        <div v-else-if="!matches.length" class="empty">
          <p>Сетка ещё не сгенерирована.</p>
          <button class="btn-generate" :disabled="generating" @click="generate">
            {{ generating ? 'Генерация...' : '🎲 Сгенерировать сетку' }}
          </button>
          <p class="hint">
            Перед генерацией одобри участников и расставь сиды.
          </p>
        </div>

        <template v-else>
          <div class="toolbar">
                        <span class="info">
                            Раундов: <b>{{ maxRound }}</b> · Матчей: <b>{{ matches.length }}</b>
                        </span>
            <button class="btn-regen" :disabled="generating" @click="generate">
              🔄 Перегенерировать
            </button>
          </div>

          <div class="bracket">
            <div
                v-for="r in roundNumbers"
                :key="r"
                class="round"
            >
              <div class="round-title">
                {{ roundName(r) }}
              </div>

              <div class="round-matches">
                <button
                    v-for="m in rounds[r]"
                    :key="m.id"
                    class="match"
                    :class="[`match--${m.status}`, { 'match--ready': m.participant1 && m.participant2 }]"
                    @click="openMatch(m)"
                >
                  <div class="match-slot" :class="{ 'match-slot--winner': m.winner_id === m.participant1_id }">
                                        <span class="slot-name">
                                            {{ displayName(m.participant1) }}
                                        </span>
                    <span class="slot-score">
                                            {{ m.score1 ?? '—' }}
                                        </span>
                  </div>

                  <div class="match-divider" />

                  <div class="match-slot" :class="{ 'match-slot--winner': m.winner_id === m.participant2_id }">
                                        <span class="slot-name">
                                            {{ displayName(m.participant2) }}
                                        </span>
                    <span class="slot-score">
                                            {{ m.score2 ?? '—' }}
                                        </span>
                  </div>

                  <div class="match-status">
                    {{ m.status }}
                  </div>
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Закрыть</button>
      </footer>
    </div>

    <!-- Модалка матча -->
    <div
        v-if="editingMatch"
        class="match-modal-bg"
        @click.self="closeMatch"
    >
      <div class="match-modal">
        <header class="match-modal__head">
          <h3>
            Матч · {{ roundName(editingMatch.round) }} #{{ editingMatch.position + 1 }}
          </h3>
          <button class="close" @click="closeMatch">✕</button>
        </header>

        <div class="match-modal__body">
          <div class="participant-row">
            <span class="p-name">{{ displayName(editingMatch.participant1) }}</span>
            <input
                v-model="editingMatch.score1"
                type="number"
                min="0"
                placeholder="0"
                class="p-score"
            />
          </div>

          <div class="participant-row">
            <span class="p-name">{{ displayName(editingMatch.participant2) }}</span>
            <input
                v-model="editingMatch.score2"
                type="number"
                min="0"
                placeholder="0"
                class="p-score"
            />
          </div>

          <div class="or">— или выбери победителя вручную —</div>

          <div class="winner-select">
            <button
                class="winner-btn"
                :class="{ active: editingMatch.winner_id === editingMatch.participant1_id }"
                :disabled="!editingMatch.participant1_id"
                @click="editingMatch.winner_id = editingMatch.participant1_id"
            >
              {{ displayName(editingMatch.participant1) }}
            </button>

            <button
                class="winner-btn"
                :class="{ active: editingMatch.winner_id === editingMatch.participant2_id }"
                :disabled="!editingMatch.participant2_id"
                @click="editingMatch.winner_id = editingMatch.participant2_id"
            >
              {{ displayName(editingMatch.participant2) }}
            </button>
          </div>

          <div class="match-modal-actions">
            <button class="btn-cancel" @click="cancelMatch(editingMatch)">
              Отменить матч
            </button>
            <button class="btn-save" @click="saveMatch">
              Сохранить
            </button>
          </div>
        </div>
      </div>
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
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 1200px;
  max-height: 92vh;
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
  overflow: auto;
  padding: 20px 22px;
}

.error {
  padding: 10px 12px;
  margin-bottom: 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.empty {
  padding: 60px 20px;
  text-align: center;
  color: var(--text-dim);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.btn-generate {
  padding: 12px 24px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.btn-generate:hover:not(:disabled) {
  background: var(--accent-light);
}

.btn-generate:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.hint {
  margin: 0;
  color: var(--text-muted);
  font-size: 12px;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  gap: 16px;
}

.info {
  color: var(--text-dim);
  font-size: 13px;
}

.info b {
  color: var(--text);
}

.btn-regen {
  padding: 8px 14px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-regen:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--accent);
}

/* === BRACKET === */

.bracket {
  display: flex;
  gap: 32px;
  overflow-x: auto;
  padding: 20px 0;
  scrollbar-width: thin;
}

.round {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 240px;
  flex-shrink: 0;
}

.round-title {
  font-size: 11px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  text-align: center;
  margin-bottom: 4px;
}

.round-matches {
  display: flex;
  flex-direction: column;
  gap: 12px;
  justify-content: space-around;
  flex: 1;
}

.match {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
  overflow: hidden;
}

.match:hover {
  border-color: var(--accent);
  transform: scale(1.02);
}

.match--ready {
  border-color: rgba(124, 58, 237, 0.4);
}

.match--completed {
  border-color: rgba(34, 197, 94, 0.3);
}

.match--cancelled {
  opacity: 0.4;
}

.match-slot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  font-size: 13px;
}

.match-slot--winner {
  background: rgba(34, 197, 94, 0.08);
}

.match-slot--winner .slot-name {
  color: #4ade80;
  font-weight: 800;
}

.slot-name {
  color: var(--text);
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 160px;
}

.slot-score {
  color: var(--text-dim);
  font-weight: 900;
  font-size: 14px;
  min-width: 24px;
  text-align: right;
}

.match-divider {
  height: 1px;
  background: var(--border);
}

.match-status {
  padding: 3px 8px;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.02);
  text-align: center;
  border-top: 1px solid var(--border);
}

/* === MATCH MODAL === */

.match-modal-bg {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
}

.match-modal {
  width: 100%;
  max-width: 440px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}

.match-modal__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}

.match-modal__head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
}

.match-modal__body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.participant-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
}

.p-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.p-score {
  width: 60px;
  padding: 6px 8px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 7px;
  text-align: center;
  font-size: 14px;
  font-weight: 800;
  outline: none;
}

.p-score:focus {
  border-color: var(--accent);
}

.or {
  text-align: center;
  color: var(--text-muted);
  font-size: 11px;
  margin: 4px 0;
}

.winner-select {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.winner-btn {
  padding: 10px 14px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.winner-btn:hover:not(:disabled) {
  border-color: var(--accent);
  color: var(--text);
}

.winner-btn.active {
  color: #fff;
  background: #22c55e;
  border-color: #22c55e;
}

.winner-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.match-modal-actions {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-top: 8px;
}

.btn-cancel,
.btn-save {
  min-height: 40px;
  padding: 0 18px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-cancel {
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.btn-cancel:hover {
  background: rgba(239, 68, 68, 0.08);
}

.btn-save {
  color: #fff;
  background: var(--accent);
  flex: 1;
}

.btn-save:hover {
  background: var(--accent-light);
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}
</style>