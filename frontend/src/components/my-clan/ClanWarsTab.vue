<script setup>
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  clan: { type: Object, required: true },
  permissions: { type: Object, default: () => ({}) },
})

const auth = useAuthStore()

const incoming = ref([])
const outgoing = ref([])
const loading = ref(true)
const processing = ref(null)
const error = ref('')

const showChallenge = ref(false)
const challengeForm = ref({
  opponent_id: null,
  scheduled_at: '',
  notes: '',
})

const availableClans = ref([])
const clansLoading = ref(false)

const showResult = ref(false)
const resultWar = ref(null)
const resultForm = ref({
  challenger_score: 0,
  opponent_score: 0,
  notes: '',
})

const expandedWars = ref({})

const statusLabels = {
  pending: 'Ожидает ответа',
  accepted: 'Активна',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

const statusShortLabels = {
  pending: 'Ожидает',
  accepted: 'Активна',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

const myClanId = computed(() => props.clan.id)

const allWars = computed(() => {
  const incomingWars = incoming.value.map(war => ({
    ...war,
    direction: 'incoming',
  }))

  const outgoingWars = outgoing.value.map(war => ({
    ...war,
    direction: 'outgoing',
  }))

  return [...incomingWars, ...outgoingWars].sort((a, b) => {
    const order = {
      accepted: 0,
      pending: 1,
      completed: 2,
      declined: 3,
      cancelled: 4,
    }

    const statusDiff = (order[a.status] ?? 9) - (order[b.status] ?? 9)

    if (statusDiff !== 0) return statusDiff

    return new Date(b.created_at || 0) - new Date(a.created_at || 0)
  })
})

const activeWars = computed(() =>
    allWars.value.filter(war => war.status === 'accepted')
)

const pendingWars = computed(() =>
    allWars.value.filter(war => war.status === 'pending')
)

const completedWars = computed(() =>
    allWars.value.filter(war => war.status === 'completed')
)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await clansApi.show(props.clan.id)

    incoming.value = data.incoming_wars || []
    outgoing.value = data.outgoing_wars || []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить войны.'
  } finally {
    loading.value = false
  }
}

async function loadAvailableClans() {
  clansLoading.value = true

  try {
    const data = await clansApi.list()

    availableClans.value = (data.data || []).filter(
        clan => clan.id !== props.clan.id && !clan.is_banned
    )
  } catch (e) {
    await alertDialog(e.message || 'Не удалось загрузить список кланов.')
  } finally {
    clansLoading.value = false
  }
}

function openChallenge() {
  showChallenge.value = true

  challengeForm.value = {
    opponent_id: null,
    scheduled_at: '',
    notes: '',
  }

  loadAvailableClans()
}

function closeChallenge() {
  if (processing.value === 'challenge') return
  showChallenge.value = false
}

async function submitChallenge() {
  if (!challengeForm.value.opponent_id) {
    await alertDialog('Выбери клан-соперник')
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
    await alertDialog(e.message || 'Не удалось отправить вызов.')
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
    await alertDialog(e.message || 'Не удалось принять вызов.')
  } finally {
    processing.value = null
  }
}

async function decline(war) {
  if (!await confirmDialog('Отклонить вызов?')) return

  processing.value = war.id

  try {
    await clansApi.declineWar(war.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось отклонить вызов.')
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

function closeResult() {
  if (processing.value === 'result') return

  showResult.value = false
  resultWar.value = null
}

async function submitResult() {
  if (!resultWar.value) return

  processing.value = 'result'

  try {
    await clansApi.completeWar(resultWar.value.id, resultForm.value)

    showResult.value = false
    resultWar.value = null

    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось сохранить результат.')
  } finally {
    processing.value = null
  }
}

function isParticipant(war) {
  return (war.participants || []).some(
      participant => participant.user_id === auth.user?.id
  )
}

function myParticipants(war) {
  return (war.participants || []).filter(
      participant => participant.clan_id === props.clan.id
  )
}

function enemyParticipants(war) {
  return (war.participants || []).filter(
      participant => participant.clan_id !== props.clan.id
  )
}

async function joinWar(war) {
  processing.value = `join-${war.id}`

  try {
    await clansApi.joinWar(war.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось присоединиться к войне.')
  } finally {
    processing.value = null
  }
}

async function leaveWar(war) {
  if (!await confirmDialog('Покинуть эту войну?')) return

  processing.value = `leave-${war.id}`

  try {
    await clansApi.leaveWar(war.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось покинуть войну.')
  } finally {
    processing.value = null
  }
}

function toggleExpanded(war) {
  expandedWars.value[war.id] = !expandedWars.value[war.id]
}

function isExpanded(war) {
  return !!expandedWars.value[war.id]
}

function getOpponent(war) {
  return war.direction === 'incoming'
      ? war.challenger
      : war.opponent
}

function getOpponentId(war) {
  return war.direction === 'incoming'
      ? war.challenger_clan_id
      : war.opponent_clan_id
}

function getOpponentName(war) {
  return getOpponent(war)?.name || 'Неизвестный клан'
}

function getOpponentTag(war) {
  return getOpponent(war)?.tag || '???'
}

function getOpponentColor(war) {
  return getOpponent(war)?.banner_color || '#7c3aed'
}

function getMyScore(war) {
  if (war.challenger_clan_id === myClanId.value) {
    return war.challenger_score ?? 0
  }

  return war.opponent_score ?? 0
}

function getEnemyScore(war) {
  if (war.challenger_clan_id === myClanId.value) {
    return war.opponent_score ?? 0
  }

  return war.challenger_score ?? 0
}

function didWin(war) {
  return war.winner_clan_id === myClanId.value
}

function isDraw(war) {
  return (
      war.status === 'completed' &&
      war.challenger_score === war.opponent_score
  )
}

function formatDate(value) {
  if (!value) return '—'

  return new Date(value).toLocaleString('ru-RU', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatFullDate(value) {
  if (!value) return '—'

  return new Date(value).toLocaleString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <!-- =========================
         HERO
    ========================== -->

    <section class="wars-hero">
      <div class="hero-glow" />

      <div class="hero-main">
        <div class="hero-icon">
          <svg
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M14.5 17.5 3 6V3h3l11.5 11.5" />
            <path d="m13 19 6-6" />
            <path d="m16 16 4 4" />
            <path d="m19 21 2-2" />
            <path d="M14.5 6.5 18 3h3v3l-3.5 3.5" />
            <path d="m5 14 6 6" />
            <path d="m8 21-4-4" />
            <path d="m3 19 2 2" />
          </svg>
        </div>

        <div class="hero-copy">
          <div class="hero-eyebrow">CLAN WARFARE</div>
          <h2>Клановые войны</h2>
          <p>
            Вызывай соперников, собирай бойцов и фиксируй результаты матчей.
          </p>
        </div>
      </div>

      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat__value">{{ activeWars.length }}</span>
          <span class="hero-stat__label">активных</span>
        </div>

        <div class="hero-stat">
          <span class="hero-stat__value">{{ pendingWars.length }}</span>
          <span class="hero-stat__label">ожидают</span>
        </div>

        <div class="hero-stat">
          <span class="hero-stat__value">{{ completedWars.length }}</span>
          <span class="hero-stat__label">завершено</span>
        </div>
      </div>

      <button
          v-if="permissions.wars"
          class="hero-create"
          @click="openChallenge"
      >
        <svg
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
          <path d="M14.5 17.5 3 6V3h3l11.5 11.5" />
          <path d="m13 19 6-6" />
          <path d="m16 16 4 4" />
          <path d="m19 21 2-2" />
          <path d="M14.5 6.5 18 3h3v3l-3.5 3.5" />
          <path d="m5 14 6 6" />
          <path d="m8 21-4-4" />
          <path d="m3 19 2 2" />
        </svg>
        Вызвать клан
      </button>
    </section>

    <!-- =========================
         ERROR
    ========================== -->

    <div v-if="error" class="error">
      <svg
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
      >
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v4M12 16h.01" />
      </svg>

      <span>{{ error }}</span>
    </div>

    <!-- =========================
         LOADING
    ========================== -->

    <div v-if="loading" class="loading-state">
      <div class="spinner" />
      <span>Загрузка боевых данных...</span>
    </div>

    <template v-else>
      <!-- =========================
           EMPTY
      ========================== -->

      <div v-if="!allWars.length" class="empty-state">
        <div class="empty-state__icon">
          <svg
              width="36"
              height="36"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.4"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M14.5 17.5 3 6V3h3l11.5 11.5" />
            <path d="m13 19 6-6" />
            <path d="m16 16 4 4" />
            <path d="m19 21 2-2" />
            <path d="M14.5 6.5 18 3h3v3l-3.5 3.5" />
            <path d="m5 14 6 6" />
            <path d="m8 21-4-4" />
            <path d="m3 19 2 2" />
          </svg>
        </div>

        <h3>Арена пока пуста</h3>
        <p>У вашего клана ещё нет вызовов и войн.</p>

        <button
            v-if="permissions.wars"
            class="empty-state__button"
            @click="openChallenge"
        >
          Вызвать первого соперника
        </button>
      </div>

      <!-- =========================
           WAR LIST
      ========================== -->

      <div v-else class="wars-list">
        <div
            v-for="war in allWars"
            :key="`${war.direction}-${war.id}`"
            class="war-card"
            :class="[
            `war-card--${war.status}`,
            `war-card--${war.direction}`,
            { 'war-card--expanded': isExpanded(war) }
          ]"
        >
          <!-- Card top -->
          <div class="war-card__top">
            <div class="direction">
              <span
                  class="direction__dot"
                  :class="`direction__dot--${war.direction}`"
              />

              {{
                war.direction === 'incoming'
                    ? 'Входящий вызов'
                    : 'Ваш вызов'
              }}
            </div>

            <div
                class="status"
                :class="`status--${war.status}`"
            >
              <span class="status__dot" />
              {{ statusShortLabels[war.status] }}
            </div>
          </div>

          <!-- Match -->
          <div class="match">
            <!-- My clan -->
            <div class="team team--mine">
              <div class="team__identity">
                <div class="team__avatar team__avatar--mine">
                  {{ props.clan.tag?.charAt(0) || 'C' }}
                </div>

                <div class="team__copy">
                  <span class="team__label">Ваш клан</span>
                  <strong class="team__name">
                    <span class="team__tag">[{{ props.clan.tag }}]</span>
                    {{ props.clan.name }}
                  </strong>
                </div>
              </div>

              <div
                  v-if="war.status === 'completed'"
                  class="team__score"
                  :class="{
                  'team__score--win': didWin(war),
                  'team__score--draw': isDraw(war),
                }"
              >
                {{ getMyScore(war) }}
              </div>
            </div>

            <!-- VS -->
            <div class="match-vs">
              <div class="match-vs__line" />
              <span>VS</span>
              <div class="match-vs__line" />
            </div>

            <!-- Opponent -->
            <div class="team team--enemy">
              <div class="team__identity">
                <div
                    class="team__avatar"
                    :style="{
                    background: `linear-gradient(135deg, ${getOpponentColor(war)}, rgba(255,255,255,.12))`
                  }"
                >
                  {{ getOpponentTag(war).charAt(0) || 'C' }}
                </div>

                <div class="team__copy">
                  <span class="team__label">
                    {{
                      war.direction === 'incoming'
                          ? 'Вызывает'
                          : 'Соперник'
                    }}
                  </span>

                  <strong class="team__name">
                    <span class="team__tag">
                      [{{ getOpponentTag(war) }}]
                    </span>
                    {{ getOpponentName(war) }}
                  </strong>
                </div>
              </div>

              <div
                  v-if="war.status === 'completed'"
                  class="team__score"
                  :class="{
                  'team__score--win': !didWin(war) && !isDraw(war),
                  'team__score--draw': isDraw(war),
                }"
              >
                {{ getEnemyScore(war) }}
              </div>
            </div>
          </div>

          <!-- Meta -->
          <div class="war-meta">
            <span v-if="war.scheduled_at" class="war-meta__item">
              <svg
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <rect x="3" y="4" width="18" height="18" rx="2" />
                <path d="M16 2v4M8 2v4M3 10h18" />
              </svg>

              {{ formatDate(war.scheduled_at) }}
            </span>

            <span v-if="war.scheduled_at && getOpponent(war)?.power" class="war-meta__separator">
              ·
            </span>

            <span
                v-if="getOpponent(war)?.power"
                class="war-meta__item"
            >
              <svg
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="m12 2 2.5 6.5L21 11l-6.5 2.5L12 20l-2.5-6.5L3 11l6.5-2.5L12 2z" />
              </svg>

              {{ getOpponent(war).power }} силы
            </span>

            <span
                v-if="war.status === 'completed'"
                class="war-meta__result"
                :class="{
                'war-meta__result--win': didWin(war),
                'war-meta__result--draw': isDraw(war),
                'war-meta__result--loss': !didWin(war) && !isDraw(war),
              }"
            >
              {{
                isDraw(war)
                    ? 'Ничья'
                    : didWin(war)
                        ? 'Победа'
                        : 'Поражение'
              }}
            </span>
          </div>

          <!-- Notes -->
          <div v-if="war.notes" class="war-note">
            <svg
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
            </svg>

            <span>{{ war.notes }}</span>
          </div>

          <!-- Pending actions -->
          <div
              v-if="war.status === 'pending' && permissions.wars"
              class="war-actions"
          >
            <button
                class="action-button action-button--accept"
                :disabled="processing === war.id"
                @click="accept(war)"
            >
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M5 12l5 5L20 7" />
              </svg>

              Принять вызов
            </button>

            <button
                class="action-button action-button--decline"
                :disabled="processing === war.id"
                @click="decline(war)"
            >
              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>

              Отклонить
            </button>
          </div>

          <!-- Active war -->
          <div
              v-if="war.status === 'accepted'"
              class="active-war"
          >
            <button
                class="participants-toggle"
                @click="toggleExpanded(war)"
            >
              <span class="participants-toggle__left">
                <span class="participants-toggle__icon">
                  <svg
                      width="13"
                      height="13"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                  </svg>
                </span>

                <span>
                  Участники
                  <b>{{ (war.participants || []).length }}</b>
                </span>
              </span>

              <svg
                  class="participants-toggle__chevron"
                  :class="{ rotated: isExpanded(war) }"
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="m6 9 6 6 6-6" />
              </svg>
            </button>

            <div
                v-if="isExpanded(war)"
                class="participants"
            >
              <div class="participants__team">
                <div class="participants__head">
                  <span class="participants__team-name">
                    <i class="team-dot team-dot--mine" />
                    {{ props.clan.tag }}
                  </span>

                  <span class="participants__count">
                    {{ myParticipants(war).length }}
                  </span>
                </div>

                <div
                    v-if="!myParticipants(war).length"
                    class="participants__empty"
                >
                  Пока никто не присоединился
                </div>

                <div
                    v-for="participant in myParticipants(war)"
                    :key="participant.id"
                    class="participant"
                >
                  <div class="participant__avatar">
                    <img
                        v-if="participant.user.avatar_url"
                        :src="participant.user.avatar_url"
                    />

                    <template v-else>
                      {{ participant.user.username?.charAt(0).toUpperCase() }}
                    </template>
                  </div>

                  <span>{{ participant.user.username }}</span>

                  <span
                      v-if="participant.user_id === auth.user?.id"
                      class="participant__you"
                  >
                    вы
                  </span>
                </div>
              </div>

              <div class="participants__divider">
                <span>VS</span>
              </div>

              <div class="participants__team">
                <div class="participants__head">
                  <span class="participants__team-name">
                    <i class="team-dot team-dot--enemy" />
                    {{ getOpponentTag(war) }}
                  </span>

                  <span class="participants__count">
                    {{ enemyParticipants(war).length }}
                  </span>
                </div>

                <div
                    v-if="!enemyParticipants(war).length"
                    class="participants__empty"
                >
                  Пока никто не присоединился
                </div>

                <div
                    v-for="participant in enemyParticipants(war)"
                    :key="participant.id"
                    class="participant"
                >
                  <div
                      class="participant__avatar"
                      :style="{
                      background: `linear-gradient(135deg, ${getOpponentColor(war)}, #1a1a24)`
                    }"
                  >
                    <img
                        v-if="participant.user.avatar_url"
                        :src="participant.user.avatar_url"
                    />

                    <template v-else>
                      {{ participant.user.username?.charAt(0).toUpperCase() }}
                    </template>
                  </div>

                  <span>{{ participant.user.username }}</span>
                </div>
              </div>
            </div>

            <div class="active-actions">
              <button
                  v-if="!isParticipant(war)"
                  class="active-action active-action--join"
                  :disabled="processing === `join-${war.id}`"
                  @click="joinWar(war)"
              >
                <template v-if="processing === `join-${war.id}`">
                  ...
                </template>

                <template v-else>
                  <svg
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="M12 5v14M5 12h14" />
                  </svg>

                  В бой
                </template>
              </button>

              <button
                  v-else
                  class="active-action active-action--leave"
                  :disabled="processing === `leave-${war.id}`"
                  @click="leaveWar(war)"
              >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                  <path d="m16 17 5-5-5-5" />
                  <path d="M21 12H9" />
                </svg>

                Покинуть
              </button>

              <button
                  v-if="permissions.wars"
                  class="active-action active-action--result"
                  @click="openResult(war)"
              >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                  <path d="M12 20h9" />
                  <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                </svg>

                Результат
              </button>
            </div>
          </div>

          <!-- Completed -->
          <div
              v-if="war.status === 'completed'"
              class="completed"
              :class="{
              'completed--win': didWin(war),
              'completed--draw': isDraw(war),
              'completed--loss': !didWin(war) && !isDraw(war),
            }"
          >
            <div class="completed__icon">
              <svg
                  v-if="didWin(war)"
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M8 21h8M12 17v4" />
                <path d="M7 4h10v5a5 5 0 0 1-10 0V4z" />
                <path d="M17 5h3a2 2 0 0 1 0 4h-3" />
                <path d="M7 5H4a2 2 0 0 0 0 4h3" />
              </svg>

              <svg
                  v-else
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <circle cx="12" cy="12" r="9" />
                <path d="M8 8l8 8M16 8l-8 8" />
              </svg>
            </div>

            <span>
              {{
                isDraw(war)
                    ? 'Ничья'
                    : didWin(war)
                        ? 'Победа вашего клана'
                        : 'Поражение'
              }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <!-- =========================
         CHALLENGE MODAL
    ========================== -->

    <div
        v-if="showChallenge"
        class="modal-bg"
        @click.self="closeChallenge"
    >
      <div class="modal">
        <header class="modal-head">
          <div>
            <span class="modal-eyebrow">NEW MATCH</span>
            <h3>Вызвать клан</h3>
            <p>Выбери соперника и отправь ему вызов.</p>
          </div>

          <button
              class="close"
              @click="closeChallenge"
              aria-label="Закрыть"
          >
            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="modal-body">
          <div class="field">
            <div class="field-head">
              <label>Клан-соперник</label>
              <span v-if="availableClans.length">
                {{ availableClans.length }} доступно
              </span>
            </div>

            <div
                v-if="clansLoading"
                class="picker-loading"
            >
              <div class="spinner spinner--small" />
              <span>Загружаем кланы...</span>
            </div>

            <div
                v-else-if="availableClans.length"
                class="clans-picker"
            >
              <button
                  v-for="clan in availableClans"
                  :key="clan.id"
                  type="button"
                  class="clan-option"
                  :class="{
                  active: challengeForm.opponent_id === clan.id
                }"
                  :style="{
                  '--clan-color': clan.banner_color || '#7c3aed'
                }"
                  @click="challengeForm.opponent_id = clan.id"
              >
                <div class="clan-option__avatar">
                  {{ clan.tag?.charAt(0) || 'C' }}
                </div>

                <div class="clan-option__info">
                  <strong>
                    [{{ clan.tag }}] {{ clan.name }}
                  </strong>

                  <span>
                    {{ clan.power }} силы
                    <i>·</i>
                    {{ clan.members_count }} участников
                  </span>
                </div>

                <div class="clan-option__check">
                  <svg
                      width="13"
                      height="13"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  >
                    <path d="m5 12 5 5L20 7" />
                  </svg>
                </div>
              </button>
            </div>

            <div
                v-else
                class="picker-empty"
            >
              Нет доступных кланов для вызова.
            </div>
          </div>

          <div class="field">
            <label>Время матча</label>

            <input
                v-model="challengeForm.scheduled_at"
                type="datetime-local"
            />
          </div>

          <div class="field">
            <label>Сообщение</label>

            <textarea
                v-model="challengeForm.notes"
                rows="3"
                maxlength="500"
                placeholder="Например: BO3, BedWars, 5 на 5"
            />

            <span class="field-hint">
              До 500 символов
            </span>
          </div>
        </div>

        <footer class="modal-foot">
          <button
              class="btn-cancel"
              @click="closeChallenge"
          >
            Отмена
          </button>

          <button
              class="btn-save"
              :disabled="
              processing === 'challenge' ||
              !challengeForm.opponent_id
            "
              @click="submitChallenge"
          >
            <template v-if="processing === 'challenge'">
              ...
            </template>

            <template v-else>
              Отправить вызов

              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="m13 6 6 6-6 6" />
              </svg>
            </template>
          </button>
        </footer>
      </div>
    </div>

    <!-- =========================
         RESULT MODAL
    ========================== -->

    <div
        v-if="showResult"
        class="modal-bg"
        @click.self="closeResult"
    >
      <div class="modal modal--result">
        <header class="modal-head">
          <div>
            <span class="modal-eyebrow">MATCH RESULT</span>
            <h3>Результат войны</h3>
            <p>Зафиксируй итог матча.</p>
          </div>

          <button
              class="close"
              @click="closeResult"
              aria-label="Закрыть"
          >
            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>
        </header>

        <div class="modal-body">
          <div class="scoreboard">
            <div class="score-team">
              <span class="score-team__label">
                {{
                  resultWar?.challenger_clan_id === myClanId
                      ? 'Ваш клан'
                      : 'Соперник'
                }}
              </span>

              <strong class="score-team__name">
                [{{ resultWar?.challenger?.tag }}]
              </strong>

              <span class="score-team__full">
                {{ resultWar?.challenger?.name }}
              </span>

              <input
                  v-model.number="resultForm.challenger_score"
                  type="number"
                  min="0"
                  max="100"
                  class="score-input"
              />
            </div>

            <div class="scoreboard__vs">
              VS
            </div>

            <div class="score-team">
              <span class="score-team__label">
                {{
                  resultWar?.opponent_clan_id === myClanId
                      ? 'Ваш клан'
                      : 'Соперник'
                }}
              </span>

              <strong class="score-team__name">
                [{{ resultWar?.opponent?.tag }}]
              </strong>

              <span class="score-team__full">
                {{ resultWar?.opponent?.name }}
              </span>

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
            <label>Комментарий</label>

            <textarea
                v-model="resultForm.notes"
                rows="3"
                placeholder="Комментарий к матчу..."
            />
          </div>
        </div>

        <footer class="modal-foot">
          <button
              class="btn-cancel"
              @click="closeResult"
          >
            Отмена
          </button>

          <button
              class="btn-save"
              :disabled="processing === 'result'"
              @click="submitResult"
          >
            <template v-if="processing === 'result'">
              ...
            </template>

            <template v-else>
              Завершить войну

              <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M5 12l5 5L20 7" />
              </svg>
            </template>
          </button>
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* =========================================================
   HERO
========================================================= */

.wars-hero {
  position: relative;
  display: flex;
  align-items: center;
  gap: 20px;

  min-height: 108px;
  padding: 20px;

  overflow: hidden;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(124, 58, 237, 0.14),
          transparent 36%
      ),
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.045),
          rgba(255, 255, 255, 0.015)
      );

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 17px;

  box-shadow:
      0 18px 50px rgba(0, 0, 0, 0.16),
      inset 0 1px 0 rgba(255, 255, 255, 0.035);
}

.hero-glow {
  position: absolute;
  width: 220px;
  height: 220px;
  left: -100px;
  top: -100px;

  background: rgba(124, 58, 237, 0.12);
  filter: blur(60px);
  pointer-events: none;
}

.hero-main {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  gap: 13px;

  flex: 1;
  min-width: 0;
}

.hero-icon {
  width: 48px;
  height: 48px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #c4b5fd;

  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, 0.2),
          rgba(124, 58, 237, 0.07)
      );

  border: 1px solid rgba(139, 92, 246, 0.22);
  border-radius: 13px;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.08),
      0 8px 25px rgba(124, 58, 237, 0.12);
}

.hero-copy {
  min-width: 0;
}

.hero-eyebrow,
.modal-eyebrow {
  color: #a78bfa;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.2px;
  line-height: 1.2;
}

.hero-copy h2 {
  margin: 4px 0 3px;

  color: var(--text);
  font-size: 17px;
  font-weight: 850;
  letter-spacing: -0.25px;
}

.hero-copy p {
  margin: 0;

  color: var(--text-muted);
  font-size: 10px;
  line-height: 1.45;
}

.hero-stats {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: stretch;

  flex-shrink: 0;

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 11px;
  overflow: hidden;

  background: rgba(0, 0, 0, 0.12);
}

.hero-stat {
  min-width: 67px;
  padding: 8px 10px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  border-right: 1px solid rgba(255, 255, 255, 0.055);
}

.hero-stat:last-child {
  border-right: 0;
}

.hero-stat__value {
  color: var(--text);
  font-size: 15px;
  font-weight: 900;
  line-height: 1;
}

.hero-stat__label {
  margin-top: 5px;

  color: var(--text-muted);
  font-size: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.hero-create {
  position: relative;
  z-index: 1;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  min-height: 38px;
  padding: 0 15px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent, #7c3aed)
      );

  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;

  font-size: 11px;
  font-weight: 850;

  cursor: pointer;
  white-space: nowrap;

  box-shadow:
      0 7px 22px rgba(124, 58, 237, 0.23),
      inset 0 1px 0 rgba(255, 255, 255, 0.12);

  transition:
      transform 0.18s ease,
      filter 0.18s ease,
      box-shadow 0.18s ease;
}

.hero-create:hover {
  transform: translateY(-1px);
  filter: brightness(1.08);

  box-shadow:
      0 10px 28px rgba(124, 58, 237, 0.31),
      inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

/* =========================================================
   ERROR / LOADING
========================================================= */

.error {
  display: flex;
  align-items: center;
  gap: 8px;

  padding: 11px 13px;

  color: #fca5a5;
  background: rgba(239, 68, 68, 0.07);

  border: 1px solid rgba(239, 68, 68, 0.18);
  border-radius: 10px;

  font-size: 11px;
}

.loading-state {
  min-height: 210px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;

  color: var(--text-muted);

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.06),
          transparent 55%
      ),
      rgba(255, 255, 255, 0.015);

  border: 1px dashed rgba(255, 255, 255, 0.07);
  border-radius: 15px;

  font-size: 11px;
}

.spinner {
  width: 28px;
  height: 28px;

  border: 2px solid rgba(124, 58, 237, 0.14);
  border-top-color: var(--accent);

  border-radius: 50%;

  animation: spin 0.75s linear infinite;
}

.spinner--small {
  width: 20px;
  height: 20px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================================
   EMPTY
========================================================= */

.empty-state {
  min-height: 280px;
  padding: 35px 20px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  text-align: center;

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.075),
          transparent 55%
      ),
      rgba(255, 255, 255, 0.015);

  border: 1px dashed rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

.empty-state__icon {
  width: 62px;
  height: 62px;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 12px;

  color: #8b5cf6;

  background: rgba(124, 58, 237, 0.07);
  border: 1px solid rgba(124, 58, 237, 0.13);
  border-radius: 16px;
}

.empty-state h3 {
  margin: 0 0 5px;

  color: var(--text);
  font-size: 15px;
  font-weight: 850;
}

.empty-state p {
  margin: 0 0 15px;

  color: var(--text-muted);
  font-size: 11px;
}

.empty-state__button {
  min-height: 36px;
  padding: 0 14px;

  color: #fff;
  background: var(--accent);

  border: 0;
  border-radius: 9px;

  font-size: 11px;
  font-weight: 800;

  cursor: pointer;
}

/* =========================================================
   WAR LIST
========================================================= */

.wars-list {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.war-card {
  position: relative;

  display: flex;
  flex-direction: column;
  gap: 0;

  overflow: hidden;

  background:
      linear-gradient(
          105deg,
          rgba(255, 255, 255, 0.038),
          rgba(255, 255, 255, 0.016)
      );

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 14px;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025);

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.war-card:hover {
  border-color: rgba(255, 255, 255, 0.105);

  background:
      linear-gradient(
          105deg,
          rgba(255, 255, 255, 0.05),
          rgba(255, 255, 255, 0.021)
      );

  box-shadow:
      0 12px 32px rgba(0, 0, 0, 0.16),
      inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.war-card--incoming {
  border-left: 2px solid rgba(96, 165, 250, 0.55);
}

.war-card--outgoing {
  border-left: 2px solid rgba(139, 92, 246, 0.55);
}

.war-card--accepted {
  border-color: rgba(96, 165, 250, 0.14);
}

.war-card--completed {
  opacity: 0.9;
}

/* =========================================================
   CARD TOP
========================================================= */

.war-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 10px 14px 0;
}

.direction {
  display: flex;
  align-items: center;
  gap: 6px;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.35px;
  text-transform: uppercase;
}

.direction__dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;
}

.direction__dot--incoming {
  background: #60a5fa;
  box-shadow: 0 0 9px rgba(96, 165, 250, 0.55);
}

.direction__dot--outgoing {
  background: #a78bfa;
  box-shadow: 0 0 9px rgba(167, 139, 250, 0.55);
}

.status {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 4px 7px;

  border-radius: 999px;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.25px;
  text-transform: uppercase;
}

.status__dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
}

.status--pending {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.075);
}

.status--pending .status__dot {
  background: #fbbf24;
}

.status--accepted {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.075);
}

.status--accepted .status__dot {
  background: #60a5fa;
  box-shadow: 0 0 7px rgba(96, 165, 250, 0.5);
}

.status--completed {
  color: #4ade80;
  background: rgba(34, 197, 94, 0.075);
}

.status--completed .status__dot {
  background: #4ade80;
}

.status--declined,
.status--cancelled {
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.035);
}

.status--declined .status__dot,
.status--cancelled .status__dot {
  background: var(--text-muted);
}

/* =========================================================
   MATCH
========================================================= */

.match {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 78px minmax(0, 1fr);
  align-items: center;

  padding: 17px 16px 14px;
}

.team {
  display: flex;
  align-items: center;
  gap: 10px;

  min-width: 0;
}

.team--enemy {
  justify-content: flex-end;
  text-align: right;
}

.team__identity {
  display: flex;
  align-items: center;
  gap: 10px;

  min-width: 0;
}

.team--enemy .team__identity {
  flex-direction: row-reverse;
}

.team__avatar {
  width: 42px;
  height: 42px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #252536,
          #15151f
      );

  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 11px;

  font-size: 14px;
  font-weight: 900;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.team__avatar--mine {
  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #5b21b6
      );

  border-color: rgba(167, 139, 250, 0.25);
}

.team__copy {
  min-width: 0;
}

.team__label {
  display: block;
  margin-bottom: 3px;

  color: var(--text-muted);

  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.team__name {
  display: block;

  max-width: 190px;

  color: var(--text);

  font-size: 12px;
  font-weight: 800;
  line-height: 1.3;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.team__tag {
  color: var(--accent-light, #a78bfa);
}

.team--enemy .team__tag {
  color: #94a3b8;
}

.team__score {
  flex-shrink: 0;

  color: var(--text);

  font-size: 25px;
  font-weight: 950;
  line-height: 1;
}

.team__score--win {
  color: #4ade80;
}

.team__score--draw {
  color: #fbbf24;
}

.match-vs {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.match-vs__line {
  width: 14px;
  height: 1px;
  background: rgba(255, 255, 255, 0.08);
}

.match-vs span {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.6px;
}

/* =========================================================
   META
========================================================= */

.war-meta {
  display: flex;
  align-items: center;
  gap: 7px;

  min-height: 30px;
  padding: 0 16px;

  border-top: 1px solid rgba(255, 255, 255, 0.045);
}

.war-meta__item {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  color: var(--text-muted);

  font-size: 9px;
}

.war-meta__separator {
  color: var(--text-muted);
  opacity: 0.35;
}

.war-meta__result {
  margin-left: auto;

  font-size: 9px;
  font-weight: 850;
  text-transform: uppercase;
}

.war-meta__result--win {
  color: #4ade80;
}

.war-meta__result--draw {
  color: #fbbf24;
}

.war-meta__result--loss {
  color: #f87171;
}

/* =========================================================
   NOTE
========================================================= */

.war-note {
  display: flex;
  align-items: flex-start;
  gap: 7px;

  margin: 0 14px 11px;
  padding: 8px 10px;

  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.022);

  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 8px;

  font-size: 10px;
  line-height: 1.45;
}

.war-note svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: var(--accent-light);
}

/* =========================================================
   PENDING ACTIONS
========================================================= */

.war-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px;

  padding: 10px 14px 13px;

  border-top: 1px solid rgba(255, 255, 255, 0.045);
}

.action-button {
  min-height: 35px;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  border-radius: 8px;

  font-size: 10px;
  font-weight: 800;

  cursor: pointer;

  transition:
      transform 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease,
      opacity 0.16s ease;
}

.action-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.action-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.action-button--accept {
  color: #fff;
  background: #16a34a;
  border: 1px solid rgba(74, 222, 128, 0.18);
}

.action-button--accept:hover:not(:disabled) {
  background: #22c55e;
}

.action-button--decline {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
}

.action-button--decline:hover:not(:disabled) {
  color: #f87171;
  background: rgba(239, 68, 68, 0.06);
  border-color: rgba(239, 68, 68, 0.22);
}

/* =========================================================
   ACTIVE WAR
========================================================= */

.active-war {
  border-top: 1px solid rgba(255, 255, 255, 0.045);
}

.participants-toggle {
  width: 100%;

  display: flex;
  align-items: center;
  justify-content: space-between;

  min-height: 38px;
  padding: 0 14px;

  color: var(--text-dim);
  background: transparent;

  border: 0;

  cursor: pointer;

  font-size: 10px;
  font-weight: 700;

  transition:
      color 0.16s ease,
      background 0.16s ease;
}

.participants-toggle:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.018);
}

.participants-toggle__left {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.participants-toggle__left b {
  margin-left: 4px;

  color: var(--accent-light);
  font-weight: 900;
}

.participants-toggle__icon {
  width: 24px;
  height: 24px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #a78bfa;
  background: rgba(124, 58, 237, 0.08);
  border-radius: 7px;
}

.participants-toggle__chevron {
  transition: transform 0.18s ease;
}

.participants-toggle__chevron.rotated {
  transform: rotate(180deg);
}

.participants {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 34px minmax(0, 1fr);
  gap: 8px;

  margin: 0 14px 10px;
  padding: 10px;

  background: rgba(5, 5, 10, 0.34);

  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 10px;
}

.participants__team {
  min-width: 0;

  padding: 9px;

  background: rgba(255, 255, 255, 0.018);
  border: 1px solid rgba(255, 255, 255, 0.045);
  border-radius: 9px;
}

.participants__head {
  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 8px;
}

.participants__team-name {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: var(--text-dim);

  font-size: 9px;
  font-weight: 900;
  text-transform: uppercase;
}

.team-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.team-dot--mine {
  background: #8b5cf6;
}

.team-dot--enemy {
  background: #64748b;
}

.participants__count {
  min-width: 17px;
  height: 17px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  padding: 0 5px;

  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.045);

  border-radius: 999px;

  font-size: 8px;
  font-weight: 900;
}

.participants__empty {
  color: var(--text-muted);

  font-size: 9px;
  font-style: italic;
  line-height: 1.4;
}

.participant {
  display: flex;
  align-items: center;
  gap: 7px;

  min-width: 0;

  padding: 4px 0;

  color: var(--text);

  font-size: 10px;
  font-weight: 700;
}

.participant__avatar {
  width: 25px;
  height: 25px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #fff;

  background: linear-gradient(135deg, #8b5cf6, #5b21b6);
  border-radius: 7px;

  overflow: hidden;

  font-size: 9px;
  font-weight: 900;
}

.participant__avatar img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

.participant > span:not(.participant__you) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.participant__you {
  flex-shrink: 0;

  padding: 2px 5px;

  color: #a78bfa;
  background: rgba(124, 58, 237, 0.08);

  border-radius: 999px;

  font-size: 7px;
  font-weight: 900;
  text-transform: uppercase;
}

.participants__divider {
  display: flex;
  align-items: center;
  justify-content: center;
}

.participants__divider span {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 900;
}

.active-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px;

  padding: 0 14px 13px;
}

.active-action {
  min-height: 34px;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  border-radius: 8px;

  font-size: 10px;
  font-weight: 800;

  cursor: pointer;

  transition:
      transform 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease,
      color 0.16s ease;
}

.active-action:hover:not(:disabled) {
  transform: translateY(-1px);
}

.active-action--join {
  color: #fff;
  background: #16a34a;
  border: 1px solid rgba(74, 222, 128, 0.15);
}

.active-action--join:hover:not(:disabled) {
  background: #22c55e;
}

.active-action--leave {
  color: #f87171;
  background: rgba(239, 68, 68, 0.045);
  border: 1px solid rgba(239, 68, 68, 0.16);
}

.active-action--leave:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.26);
}

.active-action--result {
  color: #c4b5fd;
  background: rgba(124, 58, 237, 0.075);
  border: 1px solid rgba(124, 58, 237, 0.18);
}

.active-action--result:hover:not(:disabled) {
  background: rgba(124, 58, 237, 0.12);
  border-color: rgba(139, 92, 246, 0.3);
}

.active-action:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* =========================================================
   COMPLETED
========================================================= */

.completed {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  min-height: 38px;
  margin-top: 0;
  padding: 0 14px;

  border-top: 1px solid rgba(255, 255, 255, 0.045);

  font-size: 9px;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.completed__icon {
  width: 23px;
  height: 23px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 7px;
}

.completed--win {
  color: #4ade80;
}

.completed--win .completed__icon {
  background: rgba(34, 197, 94, 0.08);
}

.completed--draw {
  color: #fbbf24;
}

.completed--draw .completed__icon {
  background: rgba(251, 191, 36, 0.08);
}

.completed--loss {
  color: #f87171;
}

.completed--loss .completed__icon {
  background: rgba(239, 68, 68, 0.07);
}

/* =========================================================
   MODAL
========================================================= */

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 3000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 20px;

  background: rgba(3, 3, 7, 0.84);
  backdrop-filter: blur(14px);

  animation: modalFade 0.18s ease;
}

@keyframes modalFade {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

.modal {
  position: relative;

  width: 100%;
  max-width: 520px;
  max-height: calc(100vh - 40px);

  display: flex;
  flex-direction: column;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(24, 24, 34, 0.98),
          rgba(12, 12, 19, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 17px;

  box-shadow:
      0 40px 100px rgba(0, 0, 0, 0.72),
      0 0 0 1px rgba(124, 58, 237, 0.035);

  animation: modalIn 0.2s ease;
}

.modal::before {
  content: '';

  position: absolute;
  top: 0;
  left: 10%;
  right: 10%;

  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(139, 92, 246, 0.65),
          transparent
      );
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.985);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 15px;

  padding: 18px 20px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.modal-head h3 {
  margin: 4px 0 3px;

  color: var(--text);
  font-size: 16px;
  font-weight: 850;
}

.modal-head p {
  margin: 0;

  color: var(--text-muted);
  font-size: 10px;
}

.close {
  width: 32px;
  height: 32px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 9px;

  cursor: pointer;

  transition:
      color 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease,
      transform 0.16s ease;
}

.close:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.07);
  border-color: rgba(255, 255, 255, 0.14);
  transform: rotate(3deg);
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 17px;

  padding: 20px;

  overflow-y: auto;
}

.field {
  display: flex;
  flex-direction: column;
}

.field-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.field-head > span,
.field-hint {
  color: var(--text-muted);
  font-size: 9px;
}

.field label {
  display: block;

  margin-bottom: 7px;

  color: var(--text-dim);
  font-size: 10px;
  font-weight: 850;
  letter-spacing: 0.45px;
  text-transform: uppercase;
}

.field input,
.field textarea {
  width: 100%;
  box-sizing: border-box;

  padding: 11px 12px;

  color: var(--text);
  background: rgba(7, 7, 13, 0.72);

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 10px;

  font: inherit;
  font-size: 12px;

  outline: none;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.field textarea {
  resize: vertical;
  min-height: 82px;
}

.field input:hover,
.field textarea:hover {
  border-color: rgba(255, 255, 255, 0.12);
}

.field input:focus,
.field textarea:focus {
  background: rgba(7, 7, 13, 0.9);
  border-color: var(--accent);

  box-shadow:
      0 0 0 3px rgba(124, 58, 237, 0.1),
      0 8px 25px rgba(0, 0, 0, 0.12);
}

.field input::placeholder,
.field textarea::placeholder {
  color: var(--text-muted);
}

.field-hint {
  margin-top: 5px;
  align-self: flex-end;
}

/* =========================================================
   CLAN PICKER
========================================================= */

.clans-picker {
  display: flex;
  flex-direction: column;
  gap: 6px;

  max-height: 285px;
  overflow-y: auto;
}

.clan-option {
  position: relative;

  width: 100%;

  display: flex;
  align-items: center;
  gap: 11px;

  padding: 10px;

  text-align: left;

  color: var(--text);

  background: rgba(255, 255, 255, 0.022);

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 10px;

  cursor: pointer;

  transition:
      background 0.16s ease,
      border-color 0.16s ease,
      transform 0.16s ease;
}

.clan-option:hover {
  background: rgba(255, 255, 255, 0.045);
  border-color: rgba(255, 255, 255, 0.11);
}

.clan-option.active {
  background: color-mix(
      in srgb,
      var(--clan-color) 8%,
      transparent
  );

  border-color: color-mix(
      in srgb,
      var(--clan-color) 45%,
      transparent
  );

  box-shadow:
      0 0 0 2px color-mix(
          in srgb,
          var(--clan-color) 8%,
          transparent
      );
}

.clan-option__avatar {
  width: 38px;
  height: 38px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #fff;

  background: var(--clan-color);
  border-radius: 10px;

  font-size: 13px;
  font-weight: 900;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.clan-option__info {
  flex: 1;
  min-width: 0;
}

.clan-option__info strong {
  display: block;

  margin-bottom: 3px;

  color: var(--text);

  font-size: 11px;
  font-weight: 800;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-option__info span {
  display: flex;
  align-items: center;
  gap: 5px;

  color: var(--text-muted);

  font-size: 9px;
}

.clan-option__info i {
  opacity: 0.4;
}

.clan-option__check {
  width: 24px;
  height: 24px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #fff;
  background: var(--clan-color);

  border-radius: 7px;

  opacity: 0;
  transform: scale(0.8);

  transition:
      opacity 0.16s ease,
      transform 0.16s ease;
}

.clan-option.active .clan-option__check {
  opacity: 1;
  transform: scale(1);
}

.picker-loading {
  min-height: 120px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;

  color: var(--text-muted);

  background: rgba(255, 255, 255, 0.018);

  border: 1px dashed rgba(255, 255, 255, 0.07);
  border-radius: 10px;

  font-size: 10px;
}

.picker-empty {
  padding: 18px;

  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.018);

  border: 1px dashed rgba(255, 255, 255, 0.07);
  border-radius: 10px;

  text-align: center;
  font-size: 10px;
}

/* =========================================================
   SCOREBOARD
========================================================= */

.scoreboard {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 42px minmax(0, 1fr);
  align-items: center;
  gap: 10px;

  padding: 17px;

  background:
      radial-gradient(
          circle at 50% 50%,
          rgba(124, 58, 237, 0.055),
          transparent 55%
      ),
      rgba(7, 7, 13, 0.45);

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 13px;
}

.score-team {
  display: flex;
  flex-direction: column;
  align-items: center;

  min-width: 0;

  text-align: center;
}

.score-team__label {
  margin-bottom: 5px;

  color: var(--text-muted);

  font-size: 8px;
  font-weight: 850;
  letter-spacing: 0.45px;
  text-transform: uppercase;
}

.score-team__name {
  color: var(--accent-light);
  font-size: 12px;
  font-weight: 900;
}

.score-team__full {
  max-width: 150px;

  margin-top: 3px;

  color: var(--text-dim);

  font-size: 9px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.score-input {
  width: 72px !important;
  min-height: 52px !important;

  margin-top: 10px;

  padding: 0 !important;

  color: var(--text) !important;

  background: rgba(255, 255, 255, 0.035) !important;

  border-color: rgba(255, 255, 255, 0.09) !important;
  border-radius: 11px !important;

  text-align: center;

  font-size: 22px !important;
  font-weight: 950 !important;
}

.score-input:focus {
  border-color: var(--accent) !important;
  box-shadow:
      0 0 0 3px rgba(124, 58, 237, 0.1),
      0 10px 25px rgba(0, 0, 0, 0.15) !important;
}

.scoreboard__vs {
  color: var(--text-muted);

  font-size: 9px;
  font-weight: 950;
  letter-spacing: 0.7px;

  text-align: center;
}

/* =========================================================
   MODAL FOOTER
========================================================= */

.modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;

  padding: 13px 20px;

  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.btn-cancel,
.btn-save {
  min-height: 38px;
  padding: 0 16px;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  border-radius: 9px;

  font-size: 11px;
  font-weight: 850;

  cursor: pointer;

  transition:
      transform 0.16s ease,
      filter 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease,
      opacity 0.16s ease;
}

.btn-cancel {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.075);
}

.btn-cancel:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.055);
  border-color: rgba(255, 255, 255, 0.13);
}

.btn-save {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent, #7c3aed)
      );

  border: 1px solid rgba(255, 255, 255, 0.1);

  box-shadow:
      0 7px 20px rgba(124, 58, 237, 0.2),
      inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.btn-save:hover:not(:disabled) {
  transform: translateY(-1px);
  filter: brightness(1.08);
}

.btn-save:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {
  .wars-hero {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .hero-main {
    flex-basis: calc(100% - 20px);
  }

  .hero-stats {
    order: 3;
  }

  .hero-create {
    margin-left: auto;
  }

  .match {
    grid-template-columns: minmax(0, 1fr) 50px minmax(0, 1fr);
  }

  .team__name {
    max-width: 140px;
  }
}

@media (max-width: 620px) {
  .tab {
    gap: 12px;
  }

  .wars-hero {
    padding: 15px;
    gap: 13px;
  }

  .hero-main {
    flex-basis: 100%;
  }

  .hero-icon {
    width: 42px;
    height: 42px;
  }

  .hero-copy h2 {
    font-size: 15px;
  }

  .hero-copy p {
    font-size: 9px;
  }

  .hero-stats {
    flex: 1;
  }

  .hero-stat {
    flex: 1;
    min-width: 0;
  }

  .hero-create {
    flex: 1;
    margin-left: 0;
  }

  .match {
    grid-template-columns: 1fr;
    gap: 11px;

    padding: 15px 13px 13px;
  }

  .team,
  .team--enemy {
    justify-content: flex-start;
    text-align: left;
  }

  .team--enemy .team__identity {
    flex-direction: row;
  }

  .team__score {
    margin-left: auto;
  }

  .match-vs {
    display: none;
  }

  .team__name {
    max-width: none;
  }

  .participants {
    grid-template-columns: 1fr;
  }

  .participants__divider {
    display: none;
  }

  .active-actions {
    grid-template-columns: 1fr;
  }

  .scoreboard {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .scoreboard__vs {
    padding: 2px 0;
  }

  .modal-bg {
    padding: 10px;
  }

  .modal {
    max-height: calc(100vh - 20px);
    border-radius: 14px;
  }
}

@media (max-width: 440px) {
  .hero-stats {
    width: 100%;
  }

  .hero-create {
    width: 100%;
    flex: none;
  }

  .war-card__top {
    padding-left: 11px;
    padding-right: 11px;
  }

  .war-meta {
    flex-wrap: wrap;
    padding: 7px 12px;
  }

  .war-meta__result {
    width: 100%;
    margin-left: 0;
    margin-top: 1px;
  }

  .war-actions {
    grid-template-columns: 1fr;
  }

  .team__avatar {
    width: 38px;
    height: 38px;
  }

  .team__name {
    font-size: 11px;
  }

  .team__score {
    font-size: 21px;
  }

  .modal-head {
    padding: 15px 16px;
  }

  .modal-body {
    padding: 16px;
  }

  .modal-foot {
    padding: 12px 16px;
  }

  .btn-cancel,
  .btn-save {
    flex: 1;
  }
}
</style>
