<script setup>
import { computed, onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  clan: { type: Object, required: true },
})

const auth = useAuthStore()

// === СОСТОЯНИЕ ===
const incoming = ref([])
const outgoing = ref([])
const loading = ref(true)
const processing = ref(null)
const error = ref('')

// модалка вызова
const showChallenge = ref(false)
const challengeForm = ref({
  opponent_id: null,
  scheduled_at: '',
  notes: '',
})
const availableClans = ref([])
const clansLoading = ref(false)

// модалка результата
const showResult = ref(false)
const resultWar = ref(null)
const resultForm = ref({
  challenger_score: 0,
  opponent_score: 0,
  notes: '',
})

const statusLabels = {
  pending: 'Ожидает',
  accepted: 'Принята',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

// === КОМПЬЮТЕД ===
const myClanId = computed(() => props.clan.id)

// === ЗАГРУЗКА ===
async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await clansApi.show(props.clan.id)
    incoming.value = data.incoming_wars || []
    outgoing.value = data.outgoing_wars || []
  } catch (e) {
    error.value = e.message || 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

async function loadAvailableClans() {
  clansLoading.value = true
  try {
    const data = await clansApi.list()
    availableClans.value = (data.data || []).filter(
        c => c.id !== props.clan.id && !c.is_banned
    )
  } finally {
    clansLoading.value = false
  }
}

// === ДЕЙСТВИЯ ===
function openChallenge() {
  showChallenge.value = true
  challengeForm.value = { opponent_id: null, scheduled_at: '', notes: '' }
  loadAvailableClans()
}

async function submitChallenge() {
  if (!challengeForm.value.opponent_id) {
    alert('Выбери клан-соперник')
    return
  }

  processing.value = 'challenge'
  try {
    await clansApi.challenge(challengeForm.value.opponent_id, {
      scheduled_at: challengeForm.value.scheduled_at || null,
      notes: challengeForm.value.notes || null,
    })
    showChallenge.value = false
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

async function accept(war) {
  processing.value = war.id
  try {
    await clansApi.acceptWar(war.id)
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

async function decline(war) {
  if (!confirm('Отклонить вызов?')) return
  processing.value = war.id
  try {
    await clansApi.declineWar(war.id)
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

function openResult(war) {
  resultWar.value = war
  resultForm.value = {
    challenger_score: war.challenger_score ?? 0,
    opponent_score: war.opponent_score ?? 0,
    notes: war.notes ?? '',
  }
  showResult.value = true
}

async function submitResult() {
  processing.value = 'result'
  try {
    await clansApi.completeWar(resultWar.value.id, resultForm.value)
    showResult.value = false
    resultWar.value = null
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

// === УЧАСТНИКИ ===
function isParticipant(war) {
  return (war.participants || []).some(p => p.user_id === auth.user?.id)
}

function myParticipants(war) {
  return (war.participants || []).filter(p => p.clan_id === props.clan.id)
}

function enemyParticipants(war) {
  return (war.participants || []).filter(p => p.clan_id !== props.clan.id)
}

async function joinWar(war) {
  processing.value = `join-${war.id}`
  try {
    await clansApi.joinWar(war.id)
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

async function leaveWar(war) {
  processing.value = `leave-${war.id}`
  try {
    await clansApi.leaveWar(war.id)
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

// === ХЕЛПЕРЫ ===
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('ru-RU', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <!-- HEAD -->
    <div class="head">
      <div>
        <h2>Клан-войны</h2>
        <p class="sub">Вызывай другие кланы и доказывай силу</p>
      </div>

      <button class="btn-create" @click="openChallenge">
        ⚔️ Вызвать клан
      </button>
    </div>

    <div v-if="error" class="error">{{ error }}</div>

    <!-- LOADING -->
    <div v-if="loading" class="empty">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <template v-else>
      <!-- ===== ВХОДЯЩИЕ ===== -->
      <section class="section">
        <div class="section-head">
          <h3>Входящие вызовы</h3>
          <span v-if="incoming.length" class="count">{{ incoming.length }}</span>
        </div>

        <div v-if="!incoming.length" class="empty-mini">
          Нет входящих вызовов
        </div>

        <div v-else class="list">
          <div
              v-for="w in incoming"
              :key="w.id"
              class="war war--incoming"
          >
            <!-- Соперник -->
            <div class="war__clan">
              <div
                  class="war__avatar"
                  :style="{ background: w.challenger?.banner_color || '#7c3aed' }"
              >
                {{ w.challenger?.tag?.charAt(0) || 'C' }}
              </div>

              <div class="war__info">
                <div class="war__name">
                  <span class="tag">[{{ w.challenger?.tag }}]</span>
                  {{ w.challenger?.name }}
                </div>
                <div class="war__meta">
                  <span>⚡ {{ w.challenger?.power }} силы</span>
                  <span v-if="w.scheduled_at" class="sep">·</span>
                  <span v-if="w.scheduled_at">📅 {{ formatDate(w.scheduled_at) }}</span>
                </div>
                <p v-if="w.notes" class="war__notes">"{{ w.notes }}"</p>
              </div>

              <div class="war__status" :class="`status-${w.status}`">
                {{ statusLabels[w.status] }}
              </div>
            </div>

            <!-- Действия pending -->
            <div v-if="w.status === 'pending'" class="war__actions">
              <button
                  class="btn-accept"
                  :disabled="processing === w.id"
                  @click="accept(w)"
              >
                ✅ Принять
              </button>
              <button
                  class="btn-decline"
                  :disabled="processing === w.id"
                  @click="decline(w)"
              >
                ❌ Отклонить
              </button>
            </div>

            <!-- Принята — участники + кнопки -->
            <div v-if="w.status === 'accepted'" class="war__participants">
              <div class="participants-head">
                <span>👥 Участники</span>
                <span class="count">{{ (w.participants || []).length }}</span>
              </div>

              <div class="participants-cols">
                <div class="participants-col">
                  <div class="col-label">🛡️ Мой клан</div>

                  <div v-if="!myParticipants(w).length" class="col-empty">
                    Никто не присоединился
                  </div>

                  <div
                      v-for="p in myParticipants(w)"
                      :key="p.id"
                      class="participant"
                  >
                    <div class="p-avatar">
                      <img v-if="p.user.avatar_url" :src="p.user.avatar_url" />
                      <template v-else>
                        {{ p.user.username.charAt(0).toUpperCase() }}
                      </template>
                    </div>
                    <span class="p-name">{{ p.user.username }}</span>
                  </div>
                </div>

                <div class="participants-col">
                  <div class="col-label">⚔️ Противник</div>

                  <div v-if="!enemyParticipants(w).length" class="col-empty">
                    Никто не присоединился
                  </div>

                  <div
                      v-for="p in enemyParticipants(w)"
                      :key="p.id"
                      class="participant"
                  >
                    <div class="p-avatar">
                      <img v-if="p.user.avatar_url" :src="p.user.avatar_url" />
                      <template v-else>
                        {{ p.user.username.charAt(0).toUpperCase() }}
                      </template>
                    </div>
                    <span class="p-name">{{ p.user.username }}</span>
                  </div>
                </div>
              </div>

              <div class="participants-actions">
                <button
                    v-if="!isParticipant(w)"
                    class="btn-join"
                    :disabled="processing === `join-${w.id}`"
                    @click="joinWar(w)"
                >
                  {{ processing === `join-${w.id}` ? '...' : '➕ Присоединиться' }}
                </button>

                <button
                    v-else
                    class="btn-leave"
                    :disabled="processing === `leave-${w.id}`"
                    @click="leaveWar(w)"
                >
                  🚪 Покинуть
                </button>

                <button
                    class="btn-result"
                    :disabled="processing === w.id"
                    @click="openResult(w)"
                >
                  📝 Внести результат
                </button>
              </div>
            </div>

            <!-- Завершена -->
            <div v-if="w.status === 'completed'" class="war__result">
                            <span class="score">
                                <b>{{ w.challenger_score }}</b> : <b>{{ w.opponent_score }}</b>
                            </span>
              <span
                  v-if="w.winner_clan_id === myClanId"
                  class="win"
              >
                                🏆 Победа
                            </span>
              <span v-else class="loss">
                                💀 Поражение
                            </span>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== ИСХОДЯЩИЕ ===== -->
      <section class="section">
        <div class="section-head">
          <h3>Исходящие вызовы</h3>
          <span v-if="outgoing.length" class="count">{{ outgoing.length }}</span>
        </div>

        <div v-if="!outgoing.length" class="empty-mini">
          Ты ещё не вызывал никого
        </div>

        <div v-else class="list">
          <div
              v-for="w in outgoing"
              :key="w.id"
              class="war war--outgoing"
          >
            <div class="war__clan">
              <div
                  class="war__avatar"
                  :style="{ background: w.opponent?.banner_color || '#7c3aed' }"
              >
                {{ w.opponent?.tag?.charAt(0) || 'C' }}
              </div>

              <div class="war__info">
                <div class="war__name">
                  <span class="tag">[{{ w.opponent?.tag }}]</span>
                  {{ w.opponent?.name }}
                </div>
                <div class="war__meta">
                  <span v-if="w.scheduled_at">📅 {{ formatDate(w.scheduled_at) }}</span>
                  <span v-if="w.notes" class="war__notes-inline">· "{{ w.notes }}"</span>
                </div>
              </div>

              <div class="war__status" :class="`status-${w.status}`">
                {{ statusLabels[w.status] }}
              </div>
            </div>

            <!-- Принята — участники -->
            <div v-if="w.status === 'accepted'" class="war__participants">
              <div class="participants-head">
                <span>👥 Участники</span>
                <span class="count">{{ (w.participants || []).length }}</span>
              </div>

              <div class="participants-cols">
                <div class="participants-col">
                  <div class="col-label">🛡️ Мой клан</div>
                  <div v-if="!myParticipants(w).length" class="col-empty">
                    Никто не присоединился
                  </div>
                  <div
                      v-for="p in myParticipants(w)"
                      :key="p.id"
                      class="participant"
                  >
                    <div class="p-avatar">
                      <img v-if="p.user.avatar_url" :src="p.user.avatar_url" />
                      <template v-else>
                        {{ p.user.username.charAt(0).toUpperCase() }}
                      </template>
                    </div>
                    <span class="p-name">{{ p.user.username }}</span>
                  </div>
                </div>

                <div class="participants-col">
                  <div class="col-label">⚔️ Противник</div>
                  <div v-if="!enemyParticipants(w).length" class="col-empty">
                    Никто не присоединился
                  </div>
                  <div
                      v-for="p in enemyParticipants(w)"
                      :key="p.id"
                      class="participant"
                  >
                    <div class="p-avatar">
                      <img v-if="p.user.avatar_url" :src="p.user.avatar_url" />
                      <template v-else>
                        {{ p.user.username.charAt(0).toUpperCase() }}
                      </template>
                    </div>
                    <span class="p-name">{{ p.user.username }}</span>
                  </div>
                </div>
              </div>

              <div class="participants-actions">
                <button
                    v-if="!isParticipant(w)"
                    class="btn-join"
                    :disabled="processing === `join-${w.id}`"
                    @click="joinWar(w)"
                >
                  {{ processing === `join-${w.id}` ? '...' : '➕ Присоединиться' }}
                </button>

                <button
                    v-else
                    class="btn-leave"
                    :disabled="processing === `leave-${w.id}`"
                    @click="leaveWar(w)"
                >
                  🚪 Покинуть
                </button>

                <button
                    class="btn-result"
                    :disabled="processing === w.id"
                    @click="openResult(w)"
                >
                  📝 Внести результат
                </button>
              </div>
            </div>

            <!-- Завершена -->
            <div v-if="w.status === 'completed'" class="war__result">
                            <span class="score">
                                <b>{{ w.challenger_score }}</b> : <b>{{ w.opponent_score }}</b>
                            </span>
              <span
                  v-if="w.winner_clan_id === myClanId"
                  class="win"
              >
                                🏆 Победа
                            </span>
              <span v-else class="loss">
                                💀 Поражение
                            </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Пусто -->
      <div v-if="!incoming.length && !outgoing.length" class="empty">
        <div class="empty__icon">⚔️</div>
        <div class="empty__title">Войн пока нет</div>
        <div class="empty__hint">Вызови первый клан на битву</div>
        <button class="btn-create" @click="openChallenge">⚔️ Вызвать клан</button>
      </div>
    </template>

    <!-- ===== МОДАЛКА ВЫЗОВА ===== -->
    <div v-if="showChallenge" class="modal-bg" @click.self="showChallenge = false">
      <div class="modal">
        <header class="modal-head">
          <h3>Вызвать клан на войну</h3>
          <button class="close" @click="showChallenge = false">✕</button>
        </header>

        <div class="modal-body">
          <div class="field">
            <label>Клан-соперник</label>

            <div v-if="clansLoading" class="loading-small">Загрузка кланов...</div>

            <div v-else class="clans-picker">
              <button
                  v-for="c in availableClans"
                  :key="c.id"
                  type="button"
                  class="clan-option"
                  :class="{ active: challengeForm.opponent_id === c.id }"
                  :style="{ '--clan-color': c.banner_color || '#7c3aed' }"
                  @click="challengeForm.opponent_id = c.id"
              >
                <div class="clan-option__avatar">
                  {{ c.tag?.charAt(0) || 'C' }}
                </div>
                <div class="clan-option__info">
                  <div class="clan-option__name">
                    [{{ c.tag }}] {{ c.name }}
                  </div>
                  <div class="clan-option__meta">
                    {{ c.power }} силы · {{ c.members_count }} участников
                  </div>
                </div>
              </button>
            </div>

            <div v-if="!clansLoading && !availableClans.length" class="empty-mini">
              Нет доступных кланов для вызова
            </div>
          </div>

          <div class="field">
            <label>Желаемое время (опционально)</label>
            <input
                v-model="challengeForm.scheduled_at"
                type="datetime-local"
            />
          </div>

          <div class="field">
            <label>Сообщение (опционально)</label>
            <textarea
                v-model="challengeForm.notes"
                rows="3"
                maxlength="500"
                placeholder="Например: BO3, BedWars, 5 на 5"
            />
          </div>
        </div>

        <footer class="modal-foot">
          <button class="btn-cancel" @click="showChallenge = false">Отмена</button>
          <button
              class="btn-save"
              :disabled="processing === 'challenge' || !challengeForm.opponent_id"
              @click="submitChallenge"
          >
            {{ processing === 'challenge' ? '...' : '⚔️ Отправить вызов' }}
          </button>
        </footer>
      </div>
    </div>

    <!-- ===== МОДАЛКА РЕЗУЛЬТАТА ===== -->
    <div v-if="showResult" class="modal-bg" @click.self="showResult = false">
      <div class="modal">
        <header class="modal-head">
          <h3>Результат войны</h3>
          <button class="close" @click="showResult = false">✕</button>
        </header>

        <div class="modal-body">
          <div class="result-teams">
            <div class="result-team">
              <div class="team-label">
                {{ resultWar?.challenger_clan_id === myClanId ? 'Ваш клан' : 'Соперник' }}
              </div>
              <div class="team-name">
                <span class="tag">[{{ resultWar?.challenger?.tag }}]</span>
                {{ resultWar?.challenger?.name }}
              </div>
              <input
                  v-model.number="resultForm.challenger_score"
                  type="number"
                  min="0"
                  max="100"
                  class="score-input"
              />
            </div>

            <div class="vs">VS</div>

            <div class="result-team">
              <div class="team-label">
                {{ resultWar?.opponent_clan_id === myClanId ? 'Ваш клан' : 'Соперник' }}
              </div>
              <div class="team-name">
                <span class="tag">[{{ resultWar?.opponent?.tag }}]</span>
                {{ resultWar?.opponent?.name }}
              </div>
              <input
                  v-model.number="resultForm.opponent_score"
                  type="number"
                  min="0"
                  max="100"
                  class="score-input"
              />
            </div>
          </div>

          <div class="field">
            <label>Заметки (опционально)</label>
            <textarea v-model="resultForm.notes" rows="3" placeholder="Комментарий к матчу..." />
          </div>
        </div>

        <footer class="modal-foot">
          <button class="btn-cancel" @click="showResult = false">Отмена</button>
          <button
              class="btn-save"
              :disabled="processing === 'result'"
              @click="submitResult"
          >
            {{ processing === 'result' ? '...' : '✓ Завершить войну' }}
          </button>
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 24px; }

.head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.head h2 { margin: 0 0 4px; font-size: 20px; font-weight: 800; }
.sub { margin: 0; color: var(--text-dim); font-size: 13px; }

.btn-create {
  padding: 10px 18px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
  white-space: nowrap;
  transition: all 0.15s;
}

.btn-create:hover { background: var(--accent-light); transform: translateY(-1px); }

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.section { display: flex; flex-direction: column; gap: 12px; }

.section-head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-head h3 {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.count {
  padding: 2px 8px;
  background: rgba(124, 58, 237, 0.15);
  color: var(--accent-light);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
}

.list { display: flex; flex-direction: column; gap: 10px; }

.war {
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: border-color 0.2s;
}

.war--incoming {
  border-color: rgba(96, 165, 250, 0.25);
  background: linear-gradient(90deg, rgba(96, 165, 250, 0.04), var(--bg-card) 40%);
}

.war--outgoing {
  border-color: rgba(124, 58, 237, 0.2);
}

.war__clan {
  display: flex;
  align-items: center;
  gap: 14px;
}

.war__avatar {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  color: #fff;
  font-size: 20px;
  font-weight: 900;
  flex-shrink: 0;
}

.war__info { flex: 1; min-width: 0; }

.war__name {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 3px;
}

.war__name .tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.war__meta {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--text-dim);
}

.war__meta .sep { opacity: 0.4; }

.war__notes {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--text-muted);
  font-style: italic;
  padding-left: 10px;
  border-left: 2px solid var(--accent);
}

.war__notes-inline {
  color: var(--text-muted);
  font-style: italic;
}

.war__status {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  flex-shrink: 0;
}

.status-pending { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.status-accepted { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.status-completed { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.status-declined { color: #6b7280; background: rgba(107, 114, 128, 0.1); }
.status-cancelled { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

/* Actions */
.war__actions {
  display: flex;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.btn-accept, .btn-decline, .btn-result, .btn-join, .btn-leave {
  padding: 10px 14px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.15s;
}

.btn-accept {
  flex: 1;
  color: #fff;
  background: #22c55e;
}
.btn-accept:hover:not(:disabled) { background: #16a34a; }

.btn-decline {
  flex: 1;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}
.btn-decline:hover:not(:disabled) { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

.btn-result {
  flex: 1;
  color: #fff;
  background: #3b82f6;
}
.btn-result:hover:not(:disabled) { background: #2563eb; }

.btn-join {
  flex: 1;
  color: #fff;
  background: #22c55e;
}
.btn-join:hover:not(:disabled) { background: #16a34a; }

.btn-leave {
  flex: 1;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}
.btn-leave:hover:not(:disabled) { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

.btn-accept:disabled, .btn-decline:disabled,
.btn-result:disabled, .btn-join:disabled, .btn-leave:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Participants */
.war__participants {
  padding-top: 12px;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.participants-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.participants-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.participants-col {
  padding: 10px 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.col-label {
  font-size: 10px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 4px;
}

.col-empty {
  font-size: 11px;
  color: var(--text-muted);
  font-style: italic;
  padding: 4px 0;
}

.participant {
  display: flex;
  align-items: center;
  gap: 8px;
}

.p-avatar {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 7px;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.p-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.p-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.participants-actions {
  display: flex;
  gap: 8px;
}

.participants-actions button {
  flex: 1;
}

/* Result */
.war__result {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.score {
  font-size: 18px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: 2px;
}

.score b { color: var(--accent-light); }

.win {
  color: #4ade80;
  font-weight: 800;
  font-size: 12px;
  text-transform: uppercase;
}

.loss {
  color: #f87171;
  font-weight: 800;
  font-size: 12px;
  text-transform: uppercase;
}

/* Empty */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 60px 20px;
  text-align: center;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
  color: var(--text-dim);
}

.empty__icon { font-size: 42px; opacity: 0.6; margin-bottom: 4px; }
.empty__title { font-size: 16px; font-weight: 800; color: var(--text); }
.empty__hint { font-size: 13px; margin-bottom: 12px; }

.empty-mini {
  padding: 20px;
  text-align: center;
  color: var(--text-muted);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 10px;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Modal */
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
  max-width: 560px;
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

.modal-head h3 { margin: 0; font-size: 17px; font-weight: 800; }

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}

.close:hover { background: rgba(255, 255, 255, 0.05); color: var(--text); }

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input,
.field textarea,
.field select {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.field input:focus,
.field textarea:focus { border-color: var(--accent); }

.loading-small {
  padding: 20px;
  text-align: center;
  color: var(--text-muted);
  font-size: 13px;
}

.clans-picker {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 280px;
  overflow-y: auto;
}

.clan-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  text-align: left;
}

.clan-option:hover { border-color: var(--border-hover); }

.clan-option.active {
  border-color: var(--clan-color);
  background: color-mix(in srgb, var(--clan-color) 8%, transparent);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--clan-color) 20%, transparent);
}

.clan-option__avatar {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  background: var(--clan-color);
  color: #fff;
  font-size: 15px;
  font-weight: 900;
  flex-shrink: 0;
}

.clan-option__info { flex: 1; min-width: 0; }
.clan-option__name { font-size: 13px; font-weight: 700; color: var(--text); }
.clan-option__meta { font-size: 11px; color: var(--text-dim); }

/* Result modal */
.result-teams {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 12px;
  align-items: center;
  padding: 16px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
}

.result-team {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.team-label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.4px;
}

.team-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--text);
  text-align: center;
}

.tag { color: var(--accent-light); }

.score-input {
  width: 80px !important;
  text-align: center;
  font-size: 22px !important;
  font-weight: 900 !important;
  padding: 8px 12px !important;
}

.vs {
  font-size: 16px;
  font-weight: 900;
  color: var(--text-muted);
}

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel, .btn-save {
  min-height: 40px;
  padding: 0 20px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.15s;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-save {
  color: #fff;
  background: var(--accent);
}

.btn-save:hover:not(:disabled) { background: var(--accent-light); }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 600px) {
  .head { flex-direction: column; }
  .btn-create { width: 100%; }
  .war__clan { flex-wrap: wrap; }
  .war__status { width: 100%; text-align: center; }
  .participants-cols { grid-template-columns: 1fr; }
  .result-teams { grid-template-columns: 1fr; }
  .vs { display: none; }
}
</style>