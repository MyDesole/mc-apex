<script setup>
import { computed } from 'vue'

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

function avatarLetter(participant) {
  if (!participant) return '?'
  if (participant.user) return participant.user.username.charAt(0).toUpperCase()
  if (participant.clan) return participant.clan.tag.charAt(0)
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
                  {{ avatarLetter(m.participant1) }}
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
                  {{ avatarLetter(m.participant2) }}
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
/* === EMPTY === */

.bracket-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 60px 20px;
  background: #0d0d14;
  border: 1px dashed var(--border);
  border-radius: 14px;
  text-align: center;
}

.bracket-empty__icon {
  font-size: 42px;
  margin-bottom: 8px;
  filter: grayscale(0.5);
  opacity: 0.5;
}

.bracket-empty__title {
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
}

.bracket-empty__hint {
  font-size: 13px;
  color: var(--text-dim);
}

/* === BRACKET === */

.bracket-wrap {
  position: relative;
  padding: 8px 0;
}

.bracket {
  display: flex;
  gap: 28px;
  overflow-x: auto;
  padding: 12px 4px 20px;
  scrollbar-width: thin;
}

.bracket::-webkit-scrollbar {
  height: 8px;
}

.bracket::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.02);
  border-radius: 999px;
}

.bracket::-webkit-scrollbar-thumb {
  background: rgba(124, 58, 237, 0.35);
  border-radius: 999px;
}

.bracket::-webkit-scrollbar-thumb:hover {
  background: rgba(124, 58, 237, 0.55);
}

/* === ROUND === */

.round {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 260px;
  flex-shrink: 0;
}

.round-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: rgba(124, 58, 237, 0.08);
  border: 1px solid rgba(124, 58, 237, 0.2);
  border-radius: 10px;
}

.round-head__title {
  font-size: 12px;
  font-weight: 800;
  color: var(--accent-light);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.round-head__badge {
  padding: 2px 8px;
  font-size: 10px;
  font-weight: 900;
  color: var(--text-muted);
  background: rgba(0, 0, 0, 0.3);
  border-radius: 999px;
  letter-spacing: 0.5px;
}

.round-matches {
  display: flex;
  flex-direction: column;
  gap: 14px;
  justify-content: space-around;
  flex: 1;
}

/* === MATCH === */

.match {
  position: relative;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.2s ease;
}

.match:hover {
  border-color: var(--border-hover);
  transform: translateY(-1px);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.3);
}

.match--ready {
  border-color: rgba(124, 58, 237, 0.35);
  box-shadow: 0 0 0 1px rgba(124, 58, 237, 0.1);
}

.match--ready:hover {
  border-color: rgba(124, 58, 237, 0.6);
}

.match--live {
  border-color: rgba(239, 68, 68, 0.5);
  animation: livePulse 2s infinite;
}

.match--completed {
  border-color: rgba(34, 197, 94, 0.25);
}

.match--cancelled {
  opacity: 0.4;
}

/* === SLOT === */

.slot {
  display: grid;
  grid-template-columns: 32px 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  transition: background 0.2s;
}

.slot--empty {
  opacity: 0.5;
}

.slot--winner {
  background: linear-gradient(90deg, rgba(34, 197, 94, 0.1), transparent 70%);
}

.slot--winner .slot__name {
  color: #4ade80;
  font-weight: 800;
}

.slot__avatar {
  position: relative;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.slot__avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.slot__name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.slot__score {
  font-size: 15px;
  font-weight: 900;
  color: var(--text);
  min-width: 24px;
  text-align: right;
  letter-spacing: -0.5px;
}

.slot__score--empty {
  color: var(--text-muted);
  font-weight: 600;
}

.match--completed .slot--winner .slot__score {
  color: #4ade80;
}

/* Divider */
.match__divider {
  height: 1px;
  background: var(--border);
  margin: 0 12px;
}

/* === LIVE === */

.match__live {
  position: absolute;
  top: 8px;
  right: 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.4);
  border-radius: 999px;
  font-size: 9px;
  font-weight: 900;
  color: #fca5a5;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.live-dot {
  width: 6px;
  height: 6px;
  background: #ef4444;
  border-radius: 50%;
  box-shadow: 0 0 8px #ef4444;
  animation: pulse 1.5s infinite;
}

/* === STATUS === */

.match__status {
  padding: 4px 10px;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  text-align: center;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.02);
  border-top: 1px solid var(--border);
}

.match__status--cancelled {
  color: #f87171;
  background: rgba(239, 68, 68, 0.05);
}

.match__status--pending {
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.05);
}

/* === ANIMATIONS === */

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

@keyframes livePulse {
  0%, 100% { box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.1); }
  50% { box-shadow: 0 0 20px rgba(239, 68, 68, 0.2); }
}

/* === MOBILE === */

@media (max-width: 700px) {
  .bracket {
    gap: 20px;
  }

  .round {
    min-width: 220px;
  }

  .slot {
    padding: 8px 10px;
    gap: 8px;
  }

  .slot__avatar {
    width: 28px;
    height: 28px;
    font-size: 11px;
  }

  .slot__name {
    font-size: 12px;
  }

  .slot__score {
    font-size: 14px;
  }
}
</style>