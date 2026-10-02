<script setup>
/**
 * Карточка клановой войны: соперники, счёт, участники и действия.
 *
 * Вынесена из ClanWarsTab: разметка одного места занимала больше
 * пятисот строк внутри вкладки.
 */
import { useClanWar, STATUS_SHORT_LABELS } from '@/composables/clan/useClanWar.js'

const props = defineProps({
  war: { type: Object, required: true },
  clan: { type: Object, required: true },
  // Права участника: определяют, какие действия показывать
  permissions: { type: Object, default: () => ({}) },
  // Перезагрузить список после действия
  onChanged: { type: Function, default: () => {} },
})

/*
 * Действия, которые меняют список войн, выполняет родитель: карточка
 * только сообщает о намерении. Так у неё нет своей копии состояния.
 */
const emit = defineEmits(['open-result', 'accept', 'decline'])

/* Короткие подписи статусов для карточки */
const statusShortLabels = STATUS_SHORT_LABELS

const {
  processing,
  isParticipant,
  myParticipants,
  enemyParticipants,
  getOpponent,
  getOpponentName,
  getOpponentTag,
  getOpponentColor,
  getMyScore,
  getEnemyScore,
  didWin,
  isDraw,
  toggleExpanded,
  isExpanded,
  joinWar,
  leaveWar,
} = useClanWar(() => props.clan.id, () => props.onChanged())

function openResult() {
  emit('open-result', props.war)
}

function accept() {
  emit('accept', props.war)
}

function decline() {
  emit('decline', props.war)
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

</script>

<template>
        <div
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
            @click="accept"
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
            @click="decline"
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
              @click="openResult"
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
</template>

<style scoped>
@import "./ClanWarCard.css";
</style>
