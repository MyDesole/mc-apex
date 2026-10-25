<script setup>
/**
 * Проведение турнира в админке: сетка и ввод результатов.
 *
 * Клик по матчу с двумя участниками → счёт и победитель. Победитель
 * уходит в следующий матч, победа в финале завершает турнир (сервер).
 * Раньше кнопка «Сетка» открывала просмотр без данных и без ввода —
 * провести турнир с сайта было нельзя.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AdminTournamentBracket from '@/components/admin/AdminTournamentBracket.vue'

const props = defineProps({
  tournament: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const STATUS_LABELS = {
  registration: 'Регистрация',
  ongoing: 'Идёт',
  completed: 'Завершён',
  cancelled: 'Отменён',
  draft: 'Черновик',
}

const matches = ref([])
const status = ref(props.tournament.status)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const selected = ref(null)
const form = reactive({ score1: null, score2: null, winner_id: null })

const final = computed(() => matches.value.find((m) => !m.next_match_id))
const champion = computed(() => {
  const f = final.value
  if (!f?.winner_id) return null
  const p = f.winner_id === f.participant1_id ? f.participant1 : f.participant2
  return name(p)
})

const progress = computed(() => {
  const real = matches.value.filter((m) => !m.is_bye)
  return { done: real.filter((m) => m.status === 'completed').length, total: real.length }
})

function name(p) {
  if (!p) return '—'
  if (p.user) return p.user.username
  if (p.clan) return `[${p.clan.tag}] ${p.clan.name}`
  return '—'
}

async function load() {
  loading.value = true
  try {
    matches.value = (await adminApi.tournamentMatches(props.tournament.id)).matches ?? []

    // Статус турнира меняет сервер (сетка → «идёт», финал → «завершён»)
    const list = await adminApi.tournaments({ page: 1 })
    status.value = list.data?.find((t) => t.id === props.tournament.id)?.status ?? status.value
  } finally {
    loading.value = false
  }
}

function pick(match) {
  selected.value = match
  error.value = ''
  Object.assign(form, {
    score1: match.score1,
    score2: match.score2,
    winner_id: match.winner_id,
  })
}

// Счёт подсказывает победителя: у кого больше — тот и выиграл
function autoWinner() {
  const s1 = Number(form.score1)
  const s2 = Number(form.score2)

  if (Number.isFinite(s1) && Number.isFinite(s2) && s1 !== s2 && form.score1 !== null && form.score2 !== null) {
    form.winner_id = s1 > s2 ? selected.value.participant1_id : selected.value.participant2_id
  }
}

async function save(extra = {}) {
  saving.value = true
  error.value = ''

  try {
    await adminApi.updateMatch(props.tournament.id, selected.value.id, {
      score1: form.score1 === '' ? null : form.score1,
      score2: form.score2 === '' ? null : form.score2,
      winner_id: form.winner_id || null,
      ...extra,
    })

    selected.value = null
    await load()
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить результат'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <Teleport to="body">
    <div class="tb-backdrop" @click.self="emit('close')">
      <div class="tb" role="dialog" :aria-label="`Сетка: ${tournament.name}`">
        <header class="tb__head">
          <div>
            <h2>{{ tournament.name }}</h2>
            <p class="tb__meta">
              <span class="tb__status" :class="`tb__status--${status}`">{{ STATUS_LABELS[status] ?? status }}</span>
              <template v-if="progress.total">· сыграно {{ progress.done }} из {{ progress.total }} матчей</template>
            </p>
          </div>
          <button type="button" class="tb__close" aria-label="Закрыть" @click="emit('close')">×</button>
        </header>

        <div v-if="champion" class="tb__champion">🏆 Чемпион: <b>{{ champion }}</b></div>

        <div v-if="loading" class="tb__empty">Загрузка сетки…</div>

        <div v-else-if="!matches.length" class="tb__empty">
          Сетки ещё нет. Одобрите участников и нажмите «Сформировать сетку» в окне «Участники».
        </div>

        <template v-else>
          <p class="tb__hint">Нажмите на матч, чтобы ввести счёт и победителя.</p>

          <AdminTournamentBracket
              :matches="matches"
              :tournament-type="tournament.type"
              editable
              :selected-id="selected?.id ?? null"
              @pick="pick"
          />

          <!-- Ввод результата -->
          <section v-if="selected" class="tb__result">
            <h3>Результат матча</h3>

            <div class="tb__players">
              <label class="tb__player" :class="{ win: form.winner_id === selected.participant1_id }">
                <input v-model="form.winner_id" type="radio" :value="selected.participant1_id" />
                <span>{{ name(selected.participant1) }}</span>
                <input v-model.number="form.score1" type="number" min="0" class="tb__score" placeholder="0" @input="autoWinner" />
              </label>

              <label class="tb__player" :class="{ win: form.winner_id === selected.participant2_id }">
                <input v-model="form.winner_id" type="radio" :value="selected.participant2_id" />
                <span>{{ name(selected.participant2) }}</span>
                <input v-model.number="form.score2" type="number" min="0" class="tb__score" placeholder="0" @input="autoWinner" />
              </label>
            </div>

            <p v-if="!selected.next_match_id" class="tb__note">Это финал — победитель станет чемпионом, турнир завершится.</p>
            <p v-if="error" class="tb__error">{{ error }}</p>

            <div class="tb__actions">
              <button type="button" class="tb__btn" @click="selected = null">Отмена</button>
              <button
                  v-if="selected.status !== 'live' && selected.status !== 'completed'"
                  type="button"
                  class="tb__btn"
                  :disabled="saving"
                  @click="save({ status: 'live', winner_id: null })"
              >
                Идёт сейчас
              </button>
              <button type="button" class="tb__btn tb__btn--primary" :disabled="saving || !form.winner_id" @click="save()">
                {{ saving ? 'Сохраняем…' : 'Сохранить результат' }}
              </button>
            </div>
          </section>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.tb-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;

  display: flex;
  align-items: flex-start;
  justify-content: center;

  padding: 32px 16px;
  overflow-y: auto;

  background: rgba(0, 0, 0, 0.6);
}

.tb {
  display: flex;
  flex-direction: column;
  gap: 14px;

  width: min(1200px, 100%);
  padding: 20px;

  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.tb__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.tb__head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 22px;
}

.tb__meta {
  margin: 6px 0 0;
  color: var(--text-muted);
  font-size: 13px;
}

.tb__status {
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.06);
}

.tb__status--ongoing { color: #fcd34d; background: rgba(250, 204, 21, 0.12); }
.tb__status--completed { color: #86efac; background: rgba(34, 197, 94, 0.12); }
.tb__status--registration { color: #93c5fd; background: rgba(59, 130, 246, 0.12); }

.tb__close {
  width: 32px;
  height: 32px;
  color: var(--text-muted);
  background: none;
  border: 0;
  font-size: 26px;
  cursor: pointer;
}

.tb__champion {
  padding: 12px 14px;
  color: #fde68a;
  background: rgba(250, 204, 21, 0.1);
  border: 1px solid rgba(250, 204, 21, 0.3);
  border-radius: 6px;
}

.tb__empty,
.tb__hint {
  margin: 0;
  color: var(--text-muted);
}

.tb__empty {
  padding: 30px;
  text-align: center;
}

.tb__result {
  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 16px;

  background: var(--bg-card);
  border: 1px solid var(--accent);
  border-radius: 6px;
}

.tb__result h3 {
  margin: 0;
  font-size: 16px;
}

.tb__players {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tb__player {
  display: grid;
  grid-template-columns: auto 1fr 90px;
  gap: 10px;
  align-items: center;

  padding: 10px 12px;

  border: 1px solid var(--border);
  border-radius: 6px;

  cursor: pointer;
}

.tb__player.win {
  border-color: #22c55e;
  background: rgba(34, 197, 94, 0.08);
}

.tb__score {
  width: 100%;
  padding: 7px 10px;

  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  text-align: center;
}

.tb__note {
  margin: 0;
  color: #fcd34d;
  font-size: 13px;
}

.tb__error {
  margin: 0;
  color: #fca5a5;
  font-size: 13px;
}

.tb__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.tb__btn {
  padding: 8px 14px;

  color: var(--text);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  font-size: 14px;
  font-weight: 600;

  cursor: pointer;
}

.tb__btn--primary {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}

.tb__btn:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
