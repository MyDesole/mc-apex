<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { testerApi } from '@/services/tester.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  tierTest: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const auth = useAuthStore()

const test = ref(props.tierTest)
const loading = ref(false)
const error = ref('')
const processing = ref(false)

const isMine = computed(() => test.value.claimed_by === auth.user.id)
const isCompleted = computed(() => test.value.status === 'completed')
const isPending = computed(() => test.value.status === 'pending')
const isInProgress = computed(() => test.value.status === 'in_progress')

// форма оценок
const form = ref({
  block_placing: 0,
  rotka: 0,
  movement: 0,
  building: 0,
  ppl: 0,
  notes: '',
})

// если уже проведён — заполняем из aspects
if (isCompleted.value && test.value.aspects) {
  form.value = {
    block_placing: test.value.aspects.block_placing ?? 0,
    rotka: test.value.aspects.rotka ?? 0,
    movement: test.value.aspects.movement ?? 0,
    building: test.value.aspects.building ?? 0,
    ppl: test.value.aspects.ppl ?? 0,
    notes: test.value.notes ?? '',
  }
}

const sum = computed(() =>
    form.value.block_placing + form.value.rotka + form.value.movement
    + form.value.building + form.value.ppl
)

const percent = computed(() => sum.value * 2)

const tier = computed(() => {
  const p = percent.value
  if (p >= 90) return 'S'
  if (p >= 80) return 'A'
  if (p >= 70) return 'B'
  if (p >= 60) return 'C'
  if (p >= 50) return 'D'
  return 'E'
})

const tierColors = {
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

const labels = {
  block_placing: 'БП',
  rotka: 'Ротка',
  movement: 'Мувмент',
  building: 'Строительство',
  ppl: 'Аим',
}

async function claim() {
  processing.value = true
  error.value = ''
  try {
    await testerApi.claim(test.value.id)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function unclaim() {
  if (!confirm('Отказаться от заявки?')) return
  processing.value = true
  try {
    await testerApi.unclaim(test.value.id)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function complete() {
  if (!confirm(`Провести тест? Итог: тир ${tier.value} (${percent.value}%)`)) return

  processing.value = true
  error.value = ''

  try {
    await testerApi.complete(test.value.id, form.value)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function cancel() {
  const reason = prompt('Причина отмены (опционально):')
  if (reason === null) return

  processing.value = true
  try {
    await testerApi.cancel(test.value.id, reason)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

function avatarLetter(username) {
  return (username || 'И').charAt(0).toUpperCase()
}
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <div>
          <h2>Тир-тест · {{ test.mode === 'pvp' ? 'PvP' : 'BedWars' }}</h2>
          <span class="sub">
                        {{ new Date(test.created_at).toLocaleString('ru-RU') }}
                    </span>
        </div>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Игрок -->
        <RouterLink
            :to="`/players/${test.user.id}`"
            class="player-card"
        >
          <div class="avatar">
            <img
                v-if="test.user.avatar_url"
                :src="test.user.avatar_url"
                class="avatar-img"
            />
            <template v-else>
              {{ avatarLetter(test.user.username) }}
            </template>
          </div>
          <div class="player-info">
            <div class="player-name">
                            <span v-if="test.user.clan_member?.clan" class="clan-tag">
                                [{{ test.user.clan_member.clan.tag }}]
                            </span>
              {{ test.user.username }}
            </div>
            <div class="player-meta">
              Текущий тир: <b>{{ test.user.tier }}</b>
              · {{ test.user.tier_score }}%
            </div>
          </div>
        </RouterLink>

        <!-- Заметка игрока -->
        <div v-if="test.notes && !isInProgress && !isCompleted" class="note">
          <span class="note__label">Заметка игрока:</span>
          <span class="note__text">{{ test.notes }}</span>
        </div>

        <!-- === PENDING: свободная заявка === -->
        <template v-if="isPending">
          <div class="hint">
            Эта заявка свободна. Нажми «Взять в работу», чтобы начать тест.
          </div>
          <button class="btn-primary" :disabled="processing" @click="claim">
            {{ processing ? '...' : 'Взять в работу' }}
          </button>
        </template>

        <!-- === IN_PROGRESS + MINE: форма теста === -->
        <template v-else-if="isInProgress && isMine">
          <div class="form">
            <div
                v-for="(label, key) in labels"
                :key="key"
                class="aspect-row"
            >
              <span class="aspect-label">{{ label }}</span>
              <input
                  v-model.number="form[key]"
                  type="range"
                  min="0"
                  max="10"
                  class="slider"
              />
              <input
                  v-model.number="form[key]"
                  type="number"
                  min="0"
                  max="10"
                  class="value"
              />
            </div>
          </div>

          <textarea
              v-model="form.notes"
              rows="3"
              placeholder="Заметки тестера (опционально)"
              class="notes"
          />

          <!-- Итог -->
          <div class="result" :style="{ '--tier-color': tierColors[tier] }">
            <div class="result__block">
              <span class="result__label">Балл</span>
              <span class="result__value">{{ sum }} / 50</span>
            </div>
            <div class="result__block">
              <span class="result__label">Процент</span>
              <span class="result__value accent">{{ percent }}%</span>
            </div>
            <div class="result__block">
              <span class="result__label">Итоговый тир</span>
              <span class="result__value tier">{{ tier }}</span>
            </div>
          </div>

          <div class="actions">
            <button class="btn-cancel" :disabled="processing" @click="unclaim">
              Вернуть в очередь
            </button>
            <button class="btn-danger" :disabled="processing" @click="cancel">
              Отменить
            </button>
            <button class="btn-primary flex-1" :disabled="processing" @click="complete">
              {{ processing ? '...' : 'Завершить тест' }}
            </button>
          </div>
        </template>

        <!-- === IN_PROGRESS, но у другого === -->
        <template v-else-if="isInProgress && !isMine">
          <div class="hint hint--warn">
            Эту заявку уже взял {{ test.claimer?.username ?? test.tester?.username }}.
          </div>
        </template>

        <!-- === COMPLETED: результат === -->
        <template v-else-if="isCompleted">
          <div class="result result--final" :style="{ '--tier-color': tierColors[test.result_tier] }">
            <div class="result__block">
              <span class="result__label">Балл</span>
              <span class="result__value">{{ sum }} / 50</span>
            </div>
            <div class="result__block">
              <span class="result__label">Процент</span>
              <span class="result__value accent">{{ test.result_score }}%</span>
            </div>
            <div class="result__block">
              <span class="result__label">Тир</span>
              <span class="result__value tier">{{ test.result_tier }}</span>
            </div>
          </div>

          <div class="notes-view" v-if="test.notes">
            <span class="notes-view__label">Заметки:</span>
            <p>{{ test.notes }}</p>
          </div>
        </template>

        <!-- === CANCELLED === -->
        <template v-else-if="test.status === 'cancelled'">
          <div class="hint hint--warn">
            Заявка отменена.
            <span v-if="test.notes">Причина: {{ test.notes }}</span>
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
  max-width: 600px;
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
  align-items: flex-start;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}

.modal-head h2 {
  margin: 0 0 2px;
  font-size: 17px;
  font-weight: 800;
}

.sub {
  font-size: 12px;
  color: var(--text-dim);
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  flex-shrink: 0;
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
  gap: 16px;
}

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

/* Player card */
.player-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s;
}

.player-card:hover {
  border-color: var(--border-hover);
}

.avatar {
  position: relative;
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 12px;
  color: #fff;
  font-size: 20px;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.player-name {
  font-size: 15px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 2px;
}

.clan-tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.player-meta {
  font-size: 12px;
  color: var(--text-dim);
}

.player-meta b {
  color: var(--text);
  font-weight: 800;
}

/* Note */
.note {
  padding: 10px 14px;
  background: rgba(124, 58, 237, 0.05);
  border-left: 3px solid var(--accent);
  border-radius: 6px;
  font-size: 13px;
}

.note__label {
  color: var(--text-muted);
  font-weight: 700;
  margin-right: 6px;
}

.note__text {
  color: var(--text-dim);
}

/* Form */
.form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.aspect-row {
  display: grid;
  grid-template-columns: 100px 1fr 50px;
  align-items: center;
  gap: 12px;
}

.aspect-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-dim);
}

.slider {
  width: 100%;
  accent-color: var(--accent);
  cursor: pointer;
}

.value {
  width: 100%;
  padding: 6px 8px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 8px;
  text-align: center;
  font-weight: 900;
  outline: none;
}

.value:focus {
  border-color: var(--accent);
}

.notes {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  font-size: 13px;
  resize: vertical;
  outline: none;
}

.notes:focus {
  border-color: var(--accent);
}

/* Result */
.result {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 10px;
  padding: 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
}

.result--final {
  border-color: var(--tier-color);
  box-shadow: 0 0 30px color-mix(in srgb, var(--tier-color) 15%, transparent);
}

.result__block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.result__label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.4px;
}

.result__value {
  font-size: 20px;
  font-weight: 900;
  color: var(--text);
}

.result__value.accent {
  color: var(--accent-light);
}

.result__value.tier {
  font-size: 26px;
  color: var(--tier-color);
  filter: drop-shadow(0 0 10px var(--tier-color));
}

/* Actions */
.actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.flex-1 {
  flex: 1;
}

.btn-primary,
.btn-cancel,
.btn-danger {
  min-height: 42px;
  padding: 0 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.2s;
}

.btn-primary {
  color: #fff;
  background: var(--accent);
}

.btn-primary:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-cancel:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--border-hover);
}

.btn-danger {
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.btn-danger:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.08);
}

.btn-primary:disabled,
.btn-cancel:disabled,
.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Hints */
.hint {
  padding: 12px 14px;
  color: var(--text-dim);
  background: rgba(124, 58, 237, 0.05);
  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 10px;
  font-size: 13px;
  line-height: 1.5;
}

.hint--warn {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.05);
  border-color: rgba(251, 191, 36, 0.2);
}

.notes-view {
  padding: 12px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.notes-view__label {
  display: block;
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.4px;
  margin-bottom: 6px;
}

.notes-view p {
  margin: 0;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
  white-space: pre-wrap;
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}
</style>