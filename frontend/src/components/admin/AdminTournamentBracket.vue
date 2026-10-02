<script setup>
import { computed } from 'vue'
import { avatarLetter } from '@/utils/playerStyling.js'

const props = defineProps({
  matches: { type: Array, default: () => [] },
  tournamentType: { type: String, default: 'solo' }, // solo / clan
})

const rounds = computed(() => {
  const grouped = {}
  props.matches.forEach(m => {
    if (!grouped[m.round]) grouped[m.round] = []
    grouped[m.round].push(m)
  })

  Object.keys(grouped).forEach(r => {
    grouped[r].sort((a, b) => a.position - b.position)
  })

  return grouped
})

const roundNumbers = computed(() =>
    Object.keys(rounds.value).map(Number).sort((a, b) => a - b)
)

const maxRound = computed(() =>
    roundNumbers.value[roundNumbers.value.length - 1] ?? 0
)

function roundName(round) {
  const left = maxRound.value - round
  if (left === 0) return 'Финал'
  if (left === 1) return 'Полуфинал'
  if (left === 2) return 'Четвертьфинал'
  if (left === 3) return '1/8 финала'
  return `Раунд ${round}`
}

function roundShort(round) {
  const left = maxRound.value - round
  if (left === 0) return 'F'
  if (left === 1) return 'SF'
  if (left === 2) return 'QF'
  if (left === 3) return 'R16'
  return `R${round}`
}

function displayName(participant) {
  if (!participant) return 'TBD'
  if (participant.user) return participant.user.username
  if (participant.clan) return `[${participant.clan.tag}] ${participant.clan.name}`
  return '—'
}

/**
 * Первая буква участника: у игрока — из ника, у клана — из тега.
 *
 * Имя игрока разбирает общий avatarLetter, чтобы поведение совпадало
 * с остальными карточками.
 */
function participantLetter(participant) {
  if (!participant) return '?'

  if (participant.user) return avatarLetter(participant.user.username)
  if (participant.clan) return avatarLetter(participant.clan.tag)

  return '?'
}

function avatarBg(participant) {
  if (participant?.clan?.banner_color) return participant.clan.banner_color
  return 'linear-gradient(135deg, #8b5cf6, #6d28d9)'
}

function isWinner(match, slot) {
  if (!match.winner_id) return false
  if (slot === 1) return match.winner_id === match.participant1_id
  return match.winner_id === match.participant2_id
}

function slotHasScore(match, slot) {
  return slot === 1 ? match.score1 !== null : match.score2 !== null
}

function slotScore(match, slot) {
  return slot === 1 ? match.score1 : match.score2
}
</script>

<template>
  <div v-if="!matches.length" class="bracket-empty">
    <div class="bracket-empty__icon">🏆</div>
    <div class="bracket-empty__title">Сетка ещё не сформирована</div>
    <div class="bracket-empty__hint">
      Ждём окончания регистрации и жеребьёвки
    </div>
  </div>

  <div v-else class="bracket-wrap">
    <div class="bracket">
      <div
          v-for="r in roundNumbers"
          :key="r"
          class="round"
      >
        <!-- Заголовок раунда -->
        <header class="round-head">
          <div class="round-head__title">{{ roundName(r) }}</div>
          <div class="round-head__badge">{{ roundShort(r) }}</div>
        </header>

        <!-- Матчи раунда -->
        <div class="round-matches">
          <article
              v-for="m in rounds[r]"
              :key="m.id"
              class="match"
              :class="[
                            `match--${m.status}`,
                            {
                                'match--ready': m.participant1 && m.participant2 && m.status !== 'completed',
                                'match--live': m.status === 'live',
                            }
                        ]"
          >
            <!-- Слот 1 -->
            <div
                class="slot"
                :class="{
                                'slot--empty': !m.participant1,
                                'slot--winner': isWinner(m, 1),
                            }"
            >
              <div
                  class="slot__avatar"
                  :style="{ background: avatarBg(m.participant1) }"
              >
                <img
                    v-if="m.participant1?.user?.avatar_url"
                    :src="m.participant1.user.avatar_url"
                    class="slot__avatar-img"
                />
                <template v-else>
                  {{ participantLetter(m.participant1) }}
                </template>
              </div>

              <div class="slot__name">
                {{ displayName(m.participant1) }}
              </div>

              <div
                  class="slot__score"
                  :class="{ 'slot__score--empty': !slotHasScore(m, 1) }"
              >
                {{ slotHasScore(m, 1) ? slotScore(m, 1) : '—' }}
              </div>
            </div>

            <div class="match__divider" />

            <!-- Слот 2 -->
            <div
                class="slot"
                :class="{
                                'slot--empty': !m.participant2,
                                'slot--winner': isWinner(m, 2),
                            }"
            >
              <div
                  class="slot__avatar"
                  :style="{ background: avatarBg(m.participant2) }"
              >
                <img
                    v-if="m.participant2?.user?.avatar_url"
                    :src="m.participant2.user.avatar_url"
                    class="slot__avatar-img"
                />
                <template v-else>
                  {{ participantLetter(m.participant2) }}
                </template>
              </div>

              <div class="slot__name">
                {{ displayName(m.participant2) }}
              </div>

              <div
                  class="slot__score"
                  :class="{ 'slot__score--empty': !slotHasScore(m, 2) }"
              >
                {{ slotHasScore(m, 2) ? slotScore(m, 2) : '—' }}
              </div>
            </div>

            <!-- Статус-индикатор для live -->
            <div v-if="m.status === 'live'" class="match__live">
              <span class="live-dot" />
              LIVE
            </div>

            <!-- Подпись статуса -->
            <div v-else-if="m.status === 'completed'" class="match__status">
              Завершён
            </div>

            <div v-else-if="m.status === 'cancelled'" class="match__status match__status--cancelled">
              Отменён
            </div>

            <div v-else-if="m.status === 'pending' && (!m.participant1 || !m.participant2)" class="match__status match__status--pending">
              Ожидание
            </div>
          </article>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminTournamentBracket.css";
</style>
