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

<style>
@import "./ClanWarCard.css";
/* Медиа-запросы карточки: правила скопированы из ClanWarsTab.css */
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

/* Правила действий и участников: элементы в этом компоненте,
   правила оставались у родителя и не применялись */

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

.action-button--accept {
  color: #fff;
  background: #16a34a;
  border: 1px solid rgba(74, 222, 128, 0.18);
}

.action-button--decline {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
}

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

.team-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.team-dot--mine {
  background: #8b5cf6;
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

.team-dot--enemy {
  background: #64748b;
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

.active-action--join {
  color: #fff;
  background: #16a34a;
  border: 1px solid rgba(74, 222, 128, 0.15);
}

.active-action--leave {
  color: #f87171;
  background: rgba(239, 68, 68, 0.045);
  border: 1px solid rgba(239, 68, 68, 0.16);
}

.active-action--result {
  color: #c4b5fd;
  background: rgba(124, 58, 237, 0.075);
  border: 1px solid rgba(124, 58, 237, 0.18);
}

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

</style>
