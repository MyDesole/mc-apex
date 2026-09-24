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

const sum = computed(() => {
  const l = labels.value
  return Object.keys(l).reduce((acc, key) => acc + (Number(form.value[key]) || 0), 0)
})

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
          <div class="contact-block__title">📞 Контакт для связи</div>

          <div class="contact-row">
            <div class="contact-type">
              <span v-if="test.contact_type === 'discord'" class="type-badge discord">Discord</span>
              <span v-else class="type-badge telegram">Telegram</span>
            </div>

            <div class="contact-value">
              <code>{{ test.contact_value }}</code>
              <button class="btn-copy" @click="copyContact(test.contact_value)" title="Скопировать">📋</button>
            </div>
          </div>

          <div v-if="test.preferred_time" class="contact-row">
            <div class="contact-type">
              <span class="type-badge time">⏰ Удобное время</span>
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

        <!-- IN_PROGRESS, но у другого -->
        <template v-else-if="isInProgress && !isMine">
          <div class="hint hint--warn">
            Эту заявку уже взял {{ test.claimer?.username ?? test.tester?.username }}.
          </div>
        </template>

        <!-- COMPLETED -->
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
.player-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
}

.player-card__header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 20px;
  padding: 20px;
  background: #0d0d14;
  border-radius: 12px;
  overflow: hidden;
  min-height: 110px;
}

.player-card__left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  flex: 1;
}

.avatar-wrap { position: relative; flex-shrink: 0; }
.avatar-wrap--framed { padding: 3px; border-radius: 16px; }

.avatar-ring {
  position: absolute;
  inset: 0;
  border-radius: 16px;
  z-index: 0;
  pointer-events: none;
}

.avatar-wrap--framed .player-card__avatar {
  position: relative;
  z-index: 1;
  border: 2px solid var(--bg-card);
}

.player-card__avatar {
  position: relative;
  width: 72px;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  font-size: 28px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.4);
}

.player-card__avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.player-card__info { flex: 1; min-width: 0; }

.player-card__name {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.verified {
  display: inline-flex;
  flex-shrink: 0;
  filter: drop-shadow(0 0 6px rgba(29, 161, 242, 0.6));
}

.status {
  margin: 0;
  color: var(--accent-color);
  font-size: 13px;
  font-weight: 700;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.bio {
  margin: 0;
  color: #d1d1db;
  font-size: 13px;
  line-height: 1.5;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.quote {
  margin: 4px 0 0;
  color: #b8b8c7;
  font-size: 12px;
  font-style: italic;
  line-height: 1.4;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.modes { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }

.mode-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color) 35%, transparent);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.meta-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }

.meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  color: #d1d1db;
  backdrop-filter: blur(6px);
}

.meta-pill--discord {
  color: #8895f5;
  border-color: rgba(88, 101, 242, 0.3);
}

.player-card__right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  flex-shrink: 0;
}

.player-card__edit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  color: #fff;
  background: rgba(124, 58, 237, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  backdrop-filter: blur(8px);
  transition: all 0.2s ease;
}

.player-card__edit:hover {
  background: var(--accent);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
}

.player-card__tier {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid;
  border-radius: 12px;
  font-size: 24px;
  font-weight: 900;
  background: rgba(10, 10, 15, 0.85);
  backdrop-filter: blur(8px);
}

.player-card__header.effect-glow {
  box-shadow: inset 0 0 40px color-mix(in srgb, var(--accent-color) 20%, transparent);
}

.player-card__header.effect-pulse { animation: profilePulse 3s infinite; }

@keyframes profilePulse {
  0%, 100% { box-shadow: inset 0 0 40px color-mix(in srgb, var(--accent-color) 15%, transparent); }
  50% { box-shadow: inset 0 0 60px color-mix(in srgb, var(--accent-color) 35%, transparent); }
}

.player-card__header.effect-gradient::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg,
  color-mix(in srgb, var(--accent-color) 15%, transparent) 0%,
  transparent 40%,
  color-mix(in srgb, var(--accent-color) 15%, transparent) 100%);
  pointer-events: none;
}

.player-card__header.effect-fire::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(239, 68, 68, 0.3), transparent 50%);
  pointer-events: none;
  animation: fireFlicker 2s infinite;
}

@keyframes fireFlicker {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

.player-card__header.effect-ice::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 0% 100%, rgba(6, 182, 212, 0.3), transparent 50%);
  pointer-events: none;
}

.player-card__header.effect-legendary { border: 1px solid rgba(250, 204, 21, 0.4); }

.player-card__header.effect-legendary::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(250, 204, 21, 0.15), transparent 40%, rgba(249, 115, 22, 0.15));
  pointer-events: none;
  animation: legendaryShift 4s infinite;
  background-size: 200% 200%;
}

@keyframes legendaryShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}

.player-card__socials {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.social-link {
  display: inline-flex;
  align-items: center;
  padding: 7px 12px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover { transform: translateY(-1px); color: #fff; }
.social-link.discord:hover { background: #5865f2; border-color: #5865f2; }
.social-link.telegram:hover { background: #229ed9; border-color: #229ed9; }
.social-link.youtube:hover { background: #ff0000; border-color: #ff0000; }
.social-link.vk:hover { background: #0077ff; border-color: #0077ff; }
.social-link.website:hover { background: var(--accent); border-color: var(--accent); }

.featured {
  margin-bottom: 20px;
  padding: 16px 18px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
}

.featured__title {
  margin-bottom: 12px;
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.featured__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 8px;
}

.featured__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid color-mix(in srgb, var(--color) 30%, transparent);
  border-radius: 10px;
  transition: all 0.2s;
}

.featured__item:hover {
  border-color: var(--color);
  transform: translateY(-1px);
  box-shadow: 0 4px 20px color-mix(in srgb, var(--color) 25%, transparent);
}

.featured__icon { font-size: 20px; flex-shrink: 0; }

.featured__name {
  font-size: 12px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chart-section { margin-bottom: 20px; }

.chart-section__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 12px;
  padding: 0 2px;
}

.chart-section__head h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.chart-section__count {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.aspects { margin-top: 4px; }

.aspects__head {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 14px;
  padding: 0 2px;
}

.aspects__title { margin: 0; font-size: 14px; font-weight: 800; color: var(--text); }
.aspects__sub { font-size: 11px; color: var(--text-muted); font-weight: 600; }

.aspects__list { display: flex; flex-direction: column; gap: 12px; }

.aspect-card {
  position: relative;
  padding: 16px 18px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s ease;
}

.aspect-card:hover { border-color: var(--border-hover); }
.aspect-card--empty { opacity: 0.75; }

.aspect-card--pvp {
  background: linear-gradient(180deg, rgba(124, 58, 237, 0.04), transparent 40%), #0d0d14;
}

.aspect-card--bedwars {
  background: linear-gradient(180deg, rgba(6, 182, 212, 0.04), transparent 40%), #0d0d14;
}

.aspect-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  gap: 12px;
}

.aspect-card__head-left {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
  flex-wrap: wrap;
}

.aspect-card__mode { font-size: 14px; font-weight: 800; color: var(--text); }
.aspect-card__sub { font-size: 11px; color: var(--text-muted); font-weight: 600; }

.badge-empty {
  display: inline-block;
  padding: 2px 8px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.aspect-card__head-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.total { display: flex; align-items: baseline; gap: 2px; font-weight: 900; }
.total__value { font-size: 16px; color: var(--text); }
.total__max { font-size: 11px; color: var(--text-muted); font-weight: 700; }

.percent-pill {
  padding: 4px 10px;
  border: 1px solid;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
}

.aspect-card__grid { display: grid; gap: 10px; }
.aspect { display: flex; flex-direction: column; gap: 5px; }

.aspect__top {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.aspect__label { color: var(--text-dim); font-size: 12px; font-weight: 600; }
.aspect__value { color: var(--text); font-size: 12px; font-weight: 800; }
.aspect__value--zero { color: var(--text-muted); }

.aspect__bar {
  height: 6px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 999px;
  overflow: hidden;
}

.aspect__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (max-width: 600px) {
  .player-card { padding: 16px; }

  .player-card__header {
    padding: 16px;
    gap: 12px;
    min-height: 90px;
  }

  .player-card__left { gap: 12px; }

  .player-card__avatar {
    width: 56px;
    height: 56px;
    font-size: 22px;
    border-radius: 12px;
  }

  .player-card__name { font-size: 16px; }
  .bio, .status { font-size: 11px; }

  .player-card__edit { padding: 6px 10px; font-size: 11px; }
  .player-card__edit svg { display: none; }

  .player-card__tier {
    width: 44px;
    height: 44px;
    font-size: 19px;
    border-radius: 10px;
  }

  .aspect-card { padding: 14px; }
  .aspect-card__head { flex-wrap: wrap; }
  .aspect-card__head-right { width: 100%; justify-content: space-between; }
}
</style>