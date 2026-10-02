/**
 * Логика клановых войн для карточки и вкладки.
 *
 * Функции разбора войны (свой счёт, соперник, победа, участие) были
 * внутри ClanWarsTab, но нужны и карточке войны, которая теперь
 * отдельный компонент. Держим их в одном месте, чтобы не расходились.
 */
import { computed, ref } from 'vue'

import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'

/** Подписи статусов войны. */
export const STATUS_LABELS = {
  pending: 'Ожидает ответа',
  accepted: 'Активна',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

/** Короткие подписи для карточки. */
export const STATUS_SHORT_LABELS = {
  pending: 'Ожидает',
  accepted: 'Активна',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

/**
 * @param {import('vue').Ref<number>|Function} clanId id своего клана
 * @param {Function} onChanged вызывается после успешного действия
 */
export function useClanWar(clanId, onChanged = () => {}) {
  const auth = useAuthStore()

  const processing = ref(null)
  const expandedWars = ref({})

  /** id клана и как его читать: ref, computed или функция. */
  const clanIdOf = () =>
      typeof clanId === 'function' ? clanId() : clanId?.value

  /* ===== Разбор войны ===== */

  function isParticipant(war) {
    return (war?.participants || []).some(
        participant => participant.user_id === auth.user?.id
    )
  }

  /** Кто с нашей стороны: используется в списке участников. */
  function myParticipants(war) {
    return (war?.participants || []).filter(
        participant => participant.clan_id === clanIdOf()
    )
  }

  function enemyParticipants(war) {
    return (war?.participants || []).filter(
        participant => participant.clan_id !== clanIdOf()
    )
  }

  /** Клан соперника: зависит от направления вызова. */
  function getOpponent(war) {
    return war?.direction === 'incoming' ? war?.challenger : war?.opponent
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
    return war?.challenger_clan_id === clanIdOf()
        ? war?.challenger_score ?? 0
        : war?.opponent_score ?? 0
  }

  function getEnemyScore(war) {
    return war?.challenger_clan_id === clanIdOf()
        ? war?.opponent_score ?? 0
        : war?.challenger_score ?? 0
  }

  function didWin(war) {
    return war?.winner_clan_id === clanIdOf()
  }

  function isDraw(war) {
    return (
        war?.status === 'completed' &&
        war?.challenger_score === war?.opponent_score
    )
  }

  /* ===== Раскрытие подробностей ===== */

  function toggleExpanded(war) {
    expandedWars.value[war.id] = !expandedWars.value[war.id]
  }

  function isExpanded(war) {
    return !!expandedWars.value[war.id]
  }

  /* ===== Действия ===== */

  async function joinWar(war) {
    processing.value = `join-${war.id}`

    try {
      await clansApi.joinWar(war.id)
      await onChanged()
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
      await onChanged()
    } catch (e) {
      await alertDialog(e.message || 'Не удалось покинуть войну.')
    } finally {
      processing.value = null
    }
  }

  return {
    processing,
    expandedWars,
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
  }
}

export default useClanWar
