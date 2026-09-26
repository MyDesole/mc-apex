<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { testerApi } from '@/services/tester.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  tierTest: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const auth = useAuthStore()

const test = ref(props.tierTest)
const error = ref('')
const processing = ref(false)

const isMine = computed(() => test.value.claimed_by === auth.user.id)
const isCompleted = computed(() => test.value.status === 'completed')
const isPending = computed(() => test.value.status === 'pending')
const isInProgress = computed(() => test.value.status === 'in_progress')

const ASPECT_LABELS = {
  pvp: {
    block_placing: 'БП',
    rotka: 'Ротка',
    movement: 'Мувмент',
    aim: 'Аим',
    game_sense: 'Понимание боя',
  },
  bedwars: {
    pvp: 'PvP',
    game_sense: 'Понимание игры',
    bed_play: 'Игра на кровати',
    teamplay: 'Командная игра',
    building: 'Строительство',
  },
}

const labels = computed(() => ASPECT_LABELS[test.value.mode] ?? ASPECT_LABELS.pvp)

// Пустая форма
function emptyForm() {
  return {
    block_placing: 0,
    rotka: 0,
    movement: 0,
    aim: 0,
    game_sense: 0,
    pvp: 0,
    bed_play: 0,
    teamplay: 0,
    building: 0,
    notes: '',
  }
}

const form = ref(emptyForm())

// Если уже проведён — заполняем из aspects
if (isCompleted.value && test.value.aspects) {
  const a = test.value.aspects
  form.value = {
    ...emptyForm(),
    ...a,
    notes: test.value.notes ?? '',
  }
}

// При смене режима — сбрасываем форму
watch(() => test.value.mode, () => {
  form.value = emptyForm()
})

// Сумма баллов по активным полям (макс. 100)
const sum = computed(() => {
  const l = labels.value
  return Object.keys(l).reduce((acc, key) => acc + (Number(form.value[key]) || 0), 0)
})

// Процент = сумма (шкала 0–100 без умножения)
const percent = computed(() => sum.value)

// Тир по новой сетке: A — 71+, S здесь НЕ выдаётся (только за турниры)
const tier = computed(() => {
  const p = percent.value
  if (p >= 71) return 'A'
  if (p >= 56) return 'B'
  if (p >= 41) return 'C'
  if (p >= 21) return 'D'
  return 'E'
})

const tierColors = {
  'S+': '#fbbf24',
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
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
    // отправляем только нужные поля под текущий режим
    const payload = { notes: form.value.notes }
    for (const key of Object.keys(labels.value)) {
      payload[key] = form.value[key]
    }

    await testerApi.complete(test.value.id, payload)
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

function copyContact(value) {
  navigator.clipboard.writeText(value)
  alert('Скопировано: ' + value)
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
          <span class="sub">{{ new Date(test.created_at).toLocaleString('ru-RU') }}</span>
        </div>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Игрок -->
        <RouterLink :to="`/players/${test.user.id}`" class="player-card">
          <div class="avatar">
            <img v-if="test.user.avatar_url" :src="test.user.avatar_url" class="avatar-img" />
            <template v-else>{{ avatarLetter(test.user.username) }}</template>
          </div>
          <div class="player-info">
            <div class="player-name">
              <span v-if="test.user.clan_member?.clan" class="clan-tag">
                [{{ test.user.clan_member.clan.tag }}]
              </span>
              {{ test.user.username }}
            </div>
            <div class="player-meta">
              Текущий тир: <b>{{ test.user.tier }}</b> · {{ test.user.tier_score }}%
            </div>
          </div>
        </RouterLink>

        <!-- Заметка игрока -->
        <div v-if="test.notes && !isInProgress && !isCompleted" class="note">
          <span class="note__label">Заметка игрока:</span>
          <span class="note__text">{{ test.notes }}</span>
        </div>

        <!-- Контакт -->
        <div v-if="test.contact_value" class="contact-block">
          <div class="contact-block__title">Контакт для связи</div>

          <div class="contact-row">
            <div class="contact-type">
              <span v-if="test.contact_type === 'discord'" class="type-badge discord">Discord</span>
              <span v-else class="type-badge telegram">Telegram</span>
            </div>

            <div class="contact-value">
              <code>{{ test.contact_value }}</code>
              <button class="btn-copy" @click="copyContact(test.contact_value)" title="Скопировать">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="9" y="9" width="13" height="13" rx="2" />
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
              </button>
            </div>
          </div>

          <div v-if="test.preferred_time" class="contact-row">
            <div class="contact-type">
              <span class="type-badge time">Удобное время</span>
            </div>
            <div class="contact-value">{{ test.preferred_time }}</div>
          </div>
        </div>

        <!-- PENDING -->
        <template v-if="isPending">
          <div class="hint">
            Эта заявка свободна. Нажми «Взять в работу», чтобы начать тест.
          </div>
          <button class="btn-primary" :disabled="processing" @click="claim">
            {{ processing ? '...' : 'Взять в работу' }}
          </button>
        </template>

        <!-- IN_PROGRESS + MINE -->
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
                  max="20"
                  class="slider"
              />
              <input
                  v-model.number="form[key]"
                  type="number"
                  min="0"
                  max="20"
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

          <div class="result" :style="{ '--tier-color': tierColors[tier] }">
            <div class="result__block">
              <span class="result__label">Балл</span>
              <span class="result__value">{{ sum }} / 100</span>
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

        <!-- IN_PROGRESS, но у другого -->
        <template v-else-if="isInProgress && !isMine">
          <div class="hint hint--warn">
            Эту заявку уже взял {{ test.claimer?.username ?? test.tester?.username }}.
          </div>
        </template>

        <!-- COMPLETED -->
        <template v-else-if="isCompleted">
          <div class="result result--final" :style="{ '--tier-color': tierColors[test.result_tier] || '#6b7280' }">
            <div class="result__block">
              <span class="result__label">Балл</span>
              <span class="result__value">{{ sum }} / 100</span>
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

          <div v-if="test.notes" class="notes-view">
            <span class="notes-view__label">Заметки:</span>
            <p>{{ test.notes }}</p>
          </div>
        </template>

        <!-- CANCELLED -->
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
/* ============================================
   MODAL BG / MODAL
   ============================================ */

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.75);
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
  color: var(--text);
}

.sub {
  font-size: 12px;
  color: var(--text-dim);
}

.close {
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  flex-shrink: 0;
  transition: all 0.15s;
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

/* ============================================
   PLAYER CARD
   ============================================ */

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

.player-info {
  flex: 1;
  min-width: 0;
}

.player-name {
  font-size: 15px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

/* ============================================
   NOTE
   ============================================ */

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

/* ============================================
   CONTACT
   ============================================ */

.contact-block {
  padding: 16px 18px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.contact-block__title {
  font-size: 12px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.contact-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.contact-type {
  flex-shrink: 0;
}

.type-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.type-badge.discord {
  color: #8895f5;
  background: rgba(88, 101, 242, 0.1);
  border: 1px solid rgba(88, 101, 242, 0.3);
}

.type-badge.telegram {
  color: #5eb5e0;
  background: rgba(34, 158, 217, 0.1);
  border: 1px solid rgba(34, 158, 217, 0.3);
}

.type-badge.time {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.1);
  border: 1px solid rgba(251, 191, 36, 0.3);
}

.contact-value {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.contact-value code {
  flex: 1;
  padding: 6px 10px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--accent-light);
  font-family: 'Inter', monospace;
  font-size: 13px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-copy {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
  flex-shrink: 0;
}

.btn-copy:hover {
  border-color: var(--accent);
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.05);
}

/* ============================================
   FORM
   ============================================ */

.form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.aspect-row {
  display: grid;
  grid-template-columns: 140px 1fr 60px;
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
  min-height: 90px;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  font-size: 13px;
  line-height: 1.5;
  resize: vertical;
  outline: none;
  box-sizing: border-box;
}

.notes:focus {
  border-color: var(--accent);
}

/* ============================================
   RESULT
   ============================================ */

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

/* ============================================
   ACTIONS
   ============================================ */

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
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
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

/* ============================================
   HINTS
   ============================================ */

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

/* ============================================
   NOTES VIEW
   ============================================ */

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

/* ============================================
   FOOTER
   ============================================ */

.modal-foot {
  display: flex;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 600px) {
  .modal {
    max-height: 96vh;
  }

  .modal-head,
  .body,
  .modal-foot {
    padding-left: 16px;
    padding-right: 16px;
  }

  .aspect-row {
    grid-template-columns: 110px 1fr 50px;
    gap: 8px;
  }

  .result {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .result__block {
    flex-direction: row;
    justify-content: space-between;
  }

  .actions {
    flex-direction: column;
  }

  .btn-primary,
  .btn-cancel,
  .btn-danger {
    width: 100%;
  }

  .contact-value {
    width: 100%;
  }

  .contact-value code {
    width: 100%;
  }
}
</style>