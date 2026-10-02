<script setup>
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'

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
@import "@/components/clan/tabs/ClanWarsTab.css";
</style>
